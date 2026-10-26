<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestModuleSubmission;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Deep domain service encapsulating attempt timing, progression, answer
 * verification, submission coordination, and cascade sweep timeouts.
 */
class AttemptEngine
{
    public const MAX_CASCADE_STEPS = 12;

    public function __construct(
        private TestStructureService $structures,
        private SatScoringPipeline $pipeline,
    ) {}

    public function isTerminalSubmission(UserTest $attempt, Module $module): bool
    {
        try {
            $test = Test::with(['sections.modules'])->findOrFail($attempt->test_id);
            $shape = $this->structures->validate($test);
            $section = $shape['sections']->first(fn ($candidate) => $candidate->modules->contains('id', $module->id));

            if (! $section) {
                throw new \RuntimeException('Submitted module is outside the validated test structure.');
            }

            $flow = $shape['flows']->get($section->id);

            // Adaptive module 1 always routes to a module 2.
            if ($flow === TestStructureService::FLOW_ADAPTIVE && (int) $module->module_number === 1) {
                return false;
            }

            // Linear flow with another module left in this section.
            if ($flow === TestStructureService::FLOW_LINEAR) {
                $modules = $this->structures->orderedModules($section);
                $index = $modules->search(fn ($m) => (int) $m->id === (int) $module->id);
                if ($index !== false && $modules->has($index + 1)) {
                    return false;
                }
            }

            // Section-scoped attempts finalize as soon as their one section ends.
            if ($attempt->attempt_type === 'section') {
                return true;
            }

            // Otherwise: terminal only when no later section remains.
            return ! $shape['sections']->contains(
                fn ($candidate) => (int) $candidate->order > (int) $section->order
            );
        } catch (\Throwable) {
            return false;
        }
    }

    // ==========================================
    // Timing & Clock
    // ==========================================

    public function durationSeconds(Module $module): int
    {
        if ($module->relationLoaded('section') && $module->section?->relationLoaded('test') && $module->section->test->title === 'Test Preview') {
            return 0;
        }

        $minutes = $module->duration_minutes
            ?? ($module->relationLoaded('section') && $module->section?->type === Section::TYPE_MATH ? Module::MATH_DURATION : Module::RW_DURATION);

        return max(0, (int) $minutes * 60);
    }

    /**
     * @return array{duration_seconds: int, elapsed_seconds: int, remaining_seconds: int, expired: bool}
     */
    public function timing(UserTest $attempt, Module $module, ?Carbon $now = null): array
    {
        $durationSeconds = $this->durationSeconds($module);
        $startedAt = $attempt->current_module_started_at;
        $now ??= now();

        if (! $startedAt) {
            $elapsedSeconds = min($durationSeconds, (int) ($attempt->current_module_elapsed_seconds ?? 0));
        } else {
            $sessionElapsed = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
            $elapsedSeconds = ($attempt->runsOnChainedClock() ? 0 : (int) ($attempt->current_module_elapsed_seconds ?? 0)) + $sessionElapsed;
        }

        $elapsedSeconds = min($durationSeconds, max(0, $elapsedSeconds));
        $remainingSeconds = max(0, $durationSeconds - $elapsedSeconds);

        return [
            'duration_seconds' => $durationSeconds,
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startOrResumeModule(UserTest $attempt, Module $module, bool $isPreview = false): array
    {
        if (! $attempt->current_module_started_at || (! $isPreview && ! $attempt->runsOnChainedClock())) {
            $attempt->current_module_started_at = now();
            $attempt->current_module_elapsed_seconds ??= 0;
            if ($attempt->exists) {
                $attempt->save();
            }
        }

        return $this->timing($attempt, $module);
    }

    /**
     * @return array<string, mixed>
     */
    public function timingSnapshot(UserTest $attempt, Module $module): array
    {
        return ['module_id' => (int) $module->id] + $this->timing($attempt, $module);
    }

    public function deadline(UserTest $attempt, Module $module): ?CarbonInterface
    {
        if (! $attempt->current_module_started_at) {
            return null;
        }

        $durationSeconds = $this->durationSeconds($module);
        $seconds = $attempt->runsOnChainedClock()
            ? $durationSeconds
            : max(0, $durationSeconds - (int) ($attempt->current_module_elapsed_seconds ?? 0));

        return $attempt->current_module_started_at->copy()->addSeconds($seconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncElapsed(UserTest $attempt, Module $module): array
    {
        $timing = $this->timing($attempt, $module);

        if ($attempt->current_module_started_at) {
            $attempt->current_module_elapsed_seconds = $timing['elapsed_seconds'];
            if (! $attempt->runsOnChainedClock()) {
                $attempt->current_module_started_at = now();
            }
            if ($attempt->exists) {
                $attempt->save();
            }
        }

        return $timing;
    }

    public function isPastGraceDeadline(UserTest $attempt, Module $module, int $graceSeconds = 300): bool
    {
        if (! $attempt->current_module_started_at) {
            return false;
        }

        if ($this->durationSeconds($module) <= 0) {
            return false;
        }

        $deadline = $this->deadline($attempt, $module);
        if (! $deadline) {
            return false;
        }

        return now()->greaterThan($deadline->copy()->addSeconds($graceSeconds));
    }

    /**
     * @return array{duration_seconds: int, remaining_seconds: int, break_ends_at: string}|null
     */
    public function pendingBreak(UserTest $attempt, Module $module): ?array
    {
        if ($attempt->status !== 'in_progress' || (int) $attempt->current_module_id !== (int) $module->id) {
            return null;
        }

        $receipt = UserTestModuleSubmission::where('user_test_id', $attempt->id)
            ->where('issued_next_module_id', $module->id)
            ->latest('id')
            ->first();

        if (! $receipt || empty($receipt->result['is_section_break'])) {
            return null;
        }

        $result = $receipt->result;
        $durationSeconds = (int) ($result['break_duration_seconds'] ?? 600);
        if ($durationSeconds <= 0) {
            return null;
        }

        $breakEndsAtIso = $result['break_ends_at'] ?? null;
        $breakEndsAt = $breakEndsAtIso ? Carbon::parse($breakEndsAtIso) : null;
        $now = now();

        if ($attempt->runsOnChainedClock()) {
            if ($attempt->current_module_started_at && $attempt->current_module_started_at->isFuture()) {
                $remainingSeconds = (int) ceil(max(0, $attempt->current_module_started_at->getTimestamp() - $now->getTimestamp()));

                return [
                    'duration_seconds' => $durationSeconds,
                    'remaining_seconds' => $remainingSeconds,
                    'break_ends_at' => $attempt->current_module_started_at->toIso8601String(),
                ];
            }

            return null;
        }

        if ($attempt->current_module_started_at === null && $breakEndsAt && $breakEndsAt->isFuture()) {
            $remainingSeconds = (int) ceil(max(0, $breakEndsAt->getTimestamp() - $now->getTimestamp()));

            return [
                'duration_seconds' => $durationSeconds,
                'remaining_seconds' => $remainingSeconds,
                'break_ends_at' => $breakEndsAt->toIso8601String(),
            ];
        }

        return null;
    }

    public function isPendingBreak(UserTest $attempt, Module $module): bool
    {
        return $this->pendingBreak($attempt, $module) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resumeBreak(int $userTestId, int $moduleId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($userTestId, $moduleId, $userId) {
            $query = UserTest::where('id', $userTestId);
            if ($userId !== null) {
                $query->where('user_id', $userId);
            }
            $attempt = $query->lockForUpdate()->first();

            if (! $attempt || $attempt->status !== 'in_progress') {
                throw new AuthorizationException('This test attempt is no longer available.');
            }

            $module = Module::findOrFail($moduleId);
            $belongsToAttemptTest = $module->sections()->where('sections.test_id', $attempt->test_id)->exists()
                || ($module->section_id && Section::whereKey($module->section_id)
                    ->where('test_id', $attempt->test_id)->exists());
            if (! $belongsToAttemptTest) {
                throw new AuthorizationException('This module does not belong to the active test attempt.');
            }

            if ((int) $attempt->current_module_id !== (int) $module->id) {
                throw new ConflictHttpException('This module is not active for the test attempt.');
            }

            $pending = $this->pendingBreak($attempt, $module);
            if ($pending !== null) {
                $attempt->forceFill([
                    'current_module_started_at' => now(),
                    'current_module_elapsed_seconds' => 0,
                ])->save();
            } elseif ($attempt->current_module_started_at === null) {
                $attempt->forceFill([
                    'current_module_started_at' => now(),
                    'current_module_elapsed_seconds' => 0,
                ])->save();
            }

            return $this->timingSnapshot($attempt, $module) + ['status' => 'active'];
        });
    }

    // ==========================================
    // Module Progression
    // ==========================================

    public function firstModule(Test $test, ?UserTest $attempt = null): ?Module
    {
        if ($attempt && $attempt->attempt_type === 'section' && $attempt->section_type) {
            $sectionType = $attempt->section_type === 'reading_writing' ? Section::TYPE_RW : Section::TYPE_MATH;
            $section = $test->relationLoaded('sections')
                ? $test->sections->firstWhere('type', $sectionType)
                : $test->sections()->where('type', $sectionType)->first();
        } else {
            $section = $test->relationLoaded('sections')
                ? $test->sections->sortBy('order')->first()
                : $test->sections()->orderBy('order')->first();
        }

        if (! $section) {
            return null;
        }

        if ($section->relationLoaded('modules')) {
            return $section->modules->sortBy('order')->first();
        }

        return $section->modules()
            ->reorder()
            ->orderBy('modules.order')
            ->orderBy('modules.id')
            ->first();
    }

    public function issueInitialModule(UserTest $attempt, Test $test): Module
    {
        if ($attempt->current_module_id) {
            return $attempt->currentModule()->firstOrFail();
        }

        $module = $this->firstModule($test, $attempt);
        if (! $module) {
            throw new UnprocessableEntityHttpException('Test has no module.');
        }

        $this->bindInitialModule($attempt, $module);

        return $module;
    }

    public function assertIssued(UserTest $attempt, Module $module): void
    {
        if (! $attempt->current_module_id) {
            $this->bindInitialModule($attempt, $module);

            return;
        }

        if ((int) $attempt->current_module_id !== (int) $module->id) {
            throw new ConflictHttpException('This module is not active for the test attempt.');
        }
    }

    private function bindInitialModule(UserTest $attempt, Module $module): void
    {
        $attempt->forceFill([
            'current_module_id' => $module->id,
            'current_module_started_at' => $attempt->runsOnChainedClock() ? now() : null,
            'current_module_elapsed_seconds' => 0,
        ])->save();
    }


    /**
     * @param  array<string, mixed>  $result
     */
    public function advance(UserTest $attempt, array $result, ?CarbonInterface $startNextAt = null): ?Module
    {
        if (! empty($result['test_completed'])) {
            $attempt->forceFill([
                'current_module_id' => null,
                'current_module_started_at' => null,
                'current_module_elapsed_seconds' => 0,
                'status' => 'completed',
            ])->save();

            return null;
        }

        $nextModuleUlid = $result['next_module_id'] ?? null;
        if (! $nextModuleUlid) {
            throw new \RuntimeException('Successful scoring result did not issue a next module.');
        }

        $nextModule = Module::query()
            ->where('ulid', $nextModuleUlid)
            ->whereHas('sections', fn ($sections) => $sections->where('test_id', $attempt->test_id))
            ->firstOrFail();

        $attempt->forceFill([
            'current_module_id' => $nextModule->id,
            'current_module_started_at' => $attempt->runsOnChainedClock()
                ? ($startNextAt?->copy() ?? now())
                : null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $nextModule;
    }

    // ==========================================
    // Answer Grading & Persistence
    // ==========================================

    protected function checkAnswer(Question $question, mixed $userAnswer): bool
    {
        $type = (string) $question->question_type;
        if ($type === 'multiple_choice') {
            $correctChoice = $question->answerChoices->where('is_correct', true)->first();

            return $correctChoice && trim($correctChoice->label) === trim((string) $userAnswer);
        }

        if ($type !== 'student_produced_response' && $type !== 'spr') {
            throw new \InvalidArgumentException("Unsupported question type: {$type}");
        }

        $submitted = trim((string) $userAnswer);
        $submittedNumber = $this->parseNumericAnswer($submitted);

        foreach ($question->sprCorrectAnswers as $accepted) {
            $acceptedText = trim((string) $accepted->answer);
            $acceptedNumber = $this->parseNumericAnswer($acceptedText);

            if ($submittedNumber !== null && $acceptedNumber !== null) {
                $tolerance = max(0.0, (float) ($accepted->tolerance ?? 0.0001));

                if (abs($submittedNumber - $acceptedNumber) <= $tolerance) {
                    return true;
                }

                continue;
            }

            if ($acceptedText === $submitted) {
                return true;
            }
        }

        return false;
    }

    protected function parseNumericAnswer(string $value): ?float
    {
        $value = trim(str_replace(["\u{2212}", "\u{2013}"], '-', $value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([+-]?\d+)\s+(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $value, $matches)) {
            $whole = (float) $matches[1];
            $numerator = (float) $matches[2];
            $denominator = (float) $matches[3];
            if ($denominator == 0.0) {
                return null;
            }

            $fraction = $numerator / $denominator;

            return $whole < 0 ? $whole - $fraction : $whole + $fraction;
        }

        if (preg_match('/^([+-]?(?:\d+(?:\.\d*)?|\.\d+))\s*\/\s*([+-]?(?:\d+(?:\.\d*)?|\.\d+))$/', $value, $matches)) {
            $denominator = (float) $matches[2];
            if ($denominator == 0.0) {
                return null;
            }

            return (float) $matches[1] / $denominator;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    /**
     * @param  array<string|int, mixed>  $answers
     */
    private function validateAnswers(Module $module, array $answers): void
    {
        foreach (array_keys($answers) as $id) {
            if (! ctype_digit((string) $id)) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers contain invalid question ids.',
                ]);
            }
        }

        $numericQuestionIds = array_values(array_unique(array_map('intval', array_keys($answers))));
        if (! empty($numericQuestionIds)) {
            $validCount = $module->questions()
                ->whereIn('questions.id', $numericQuestionIds)
                ->count();

            if ($validCount !== count($numericQuestionIds)) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers include questions outside the current module.',
                ]);
            }
        }
    }

    /**
     * @return array{0: UserTest, 1: Module}
     */
    public function resolveActiveModule(int $userTestId, int $moduleId, ?int $userId = null, bool $allowReceipt = false): array
    {
        $query = UserTest::where('id', $userTestId);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $attempt = $query->first();

        if (! $attempt || ($attempt->status !== 'in_progress' && ! $allowReceipt)) {
            throw new AuthorizationException('This test attempt is no longer available.');
        }

        $module = Module::with(['section.test', 'questions'])->findOrFail($moduleId);
        $section = $module->section;

        if (! $section || (int) $section->test_id !== (int) $attempt->test_id) {
            throw new AuthorizationException('This module does not belong to the active test attempt.');
        }

        if ((int) $attempt->current_module_id !== (int) $module->id) {
            if ($allowReceipt) {
                $receipt = UserTestModuleSubmission::where('user_test_id', $attempt->id)
                    ->where('module_id', $module->id)
                    ->first();

                if ($receipt && (
                    ($attempt->status === 'in_progress' && (int) $receipt->issued_next_module_id === (int) $attempt->current_module_id)
                    || ($attempt->status === 'completed' && ! empty($receipt->result['test_completed']))
                )) {
                    return [$attempt, $module];
                }
            }

            Log::channel('queue')->info('conflict.module_mismatch', [
                'user_test_id' => $attempt->id,
                'requested_module_id' => (int) $module->id,
                'current_module_id' => (int) $attempt->current_module_id,
                'attempt_status' => $attempt->status,
            ]);

            throw new ConflictHttpException('This module is not active for the test attempt.');
        }

        return [$attempt, $module];
    }

    /**
     * @param  array<string|int, mixed>  $submittedAnswers
     * @param  array<string|int, mixed>  $submittedTimes
     */
    private function saveModuleAnswers(
        UserTest $attempt,
        Module $module,
        array $submittedAnswers,
        array $submittedTimes = [],
        bool $onlyMissing = false
    ): int {
        $questions = $module->relationLoaded('questions')
            ? $module->questions
            : $module->questions()
                ->with(['answerChoices', 'sprCorrectAnswers', 'passage', 'explanation'])
                ->get();
        $questions->loadMissing(['answerChoices', 'sprCorrectAnswers', 'passage', 'explanation']);
        $existingQuestionIds = $onlyMissing
            ? UserTestAnswer::where('user_test_id', $attempt->id)
                ->where('module_id', $module->id)
                ->pluck('question_id')
                ->all()
            : [];

        $savedCount = 0;
        $upsertData = [];

        foreach ($questions as $question) {
            if ($onlyMissing && in_array((int) $question->id, $existingQuestionIds, true)) {
                continue;
            }

            $questionId = (int) $question->id;
            $answer = $submittedAnswers[$questionId] ?? null;

            $normalizedAnswer = $answer === null ? null : trim((string) $answer);
            if ($normalizedAnswer === '') {
                $normalizedAnswer = null;
            }
            $isCorrect = $normalizedAnswer !== null
                ? $this->checkAnswer($question, $normalizedAnswer)
                : false;

            $snapshot = [
                'stem' => $question->stem,
                'question_type' => $question->question_type,
                'difficulty' => $question->difficulty,
                'is_pretest' => (bool) $question->is_pretest,
                'skill_domain' => $question->skill_domain,
                'skill_subdomain' => $question->skill_subdomain,
                'spr_hint' => $question->spr_hint,
                'module_position' => $question->pivot?->position,
                'calculator_allowed' => (bool) $question->calculator_allowed,
                'expected_time' => $question->expected_time,
                'irt_a' => (float) $question->irt_a,
                'irt_b' => (float) $question->irt_b,
                'irt_c' => (float) $question->irt_c,
                'irt_calibration_status' => $question->irt_calibration_status ?? 'provisional',
                'irt_calibration_version' => $question->irt_calibration_version,
                'section_type' => $question->section_type,
                'passage' => $question->passage ? [
                    'content' => $question->passage->content,
                ] : null,
                'choices' => $question->answerChoices->map(fn ($choice) => [
                    'label' => $choice->label,
                    'content' => $choice->content,
                    'is_correct' => (bool) $choice->is_correct,
                    'order' => $choice->order,
                ])->toArray(),
                'spr_answers' => $question->sprCorrectAnswers->map(fn ($ans) => [
                    'answer' => $ans->answer,
                    'answer_type' => $ans->answer_type,
                    'tolerance' => $ans->tolerance,
                ])->toArray(),
                'explanation' => $question->explanation ? [
                    'explanation' => $question->explanation->explanation,
                    'rationale_a' => $question->explanation->rationale_a,
                    'rationale_b' => $question->explanation->rationale_b,
                    'rationale_c' => $question->explanation->rationale_c,
                    'rationale_d' => $question->explanation->rationale_d,
                ] : null,
            ];

            $row = [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'question_id' => (int) $questionId,
                'selected_answer' => $normalizedAnswer,
                'is_correct' => $isCorrect,
                'time_spent' => (int) ($submittedTimes[$questionId] ?? 0),
                'question_snapshot' => json_encode($snapshot),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $upsertData[] = $row;
            $savedCount++;
        }

        if (! empty($upsertData)) {
            $uniqueBy = ['user_test_id', 'module_id', 'question_id'];

            UserTestAnswer::upsert(
                $upsertData,
                $uniqueBy,
                ['selected_answer', 'is_correct', 'time_spent', 'updated_at', 'question_snapshot']
            );
        }

        return $savedCount;
    }

    // ==========================================
    // Autosave & Submit Operations
    // ==========================================

    /**
     * Cache key marking "a submission for this attempt+module is being processed".
     */
    public static function submitMarkerKey(int $userTestId, int $moduleId): string
    {
        return "module_submit_active_{$userTestId}_{$moduleId}";
    }

    /**
     * @param  array<string|int, mixed>  $answers
     * @param  array<string|int, mixed>  $questionTimes
     * @return array<string, mixed>
     */
    public function autosave(
        UserTest $attempt,
        Module $module,
        array $answers = [],
        array $questionTimes = [],
        ?int $clientElapsedSeconds = null
    ): array {
        return DB::transaction(function () use ($attempt, $module, $answers, $questionTimes, $clientElapsedSeconds) {
            $lockedAttempt = $attempt;
            if ($attempt->exists) {
                $lockedAttempt = UserTest::where('id', $attempt->id)->lockForUpdate()->first();
                if (! $lockedAttempt || $lockedAttempt->status !== 'in_progress') {
                    throw new AuthorizationException('This test attempt is no longer available.');
                }

                if ((int) $lockedAttempt->current_module_id !== (int) $module->id) {
                    throw new ConflictHttpException('This module is not active for the test attempt.');
                }

                if ($this->isPendingBreak($lockedAttempt, $module)) {
                    throw new ConflictHttpException('break_pending');
                }
            }

            if (Cache::has(self::submitMarkerKey((int) $lockedAttempt->id, (int) $module->id))) {
                return ['status' => 'submitting', 'expired' => false, 'saved_count' => 0];
            }

            if ($lockedAttempt->runsOnChainedClock()) {
                $timing = $this->syncElapsed($lockedAttempt, $module);
                if ($timing['expired']) {
                    return ['status' => 'expired', 'expired' => true, 'saved_count' => 0];
                }
            } elseif ($clientElapsedSeconds !== null) {
                $lockedAttempt->current_module_elapsed_seconds = max(0, $clientElapsedSeconds);
                $lockedAttempt->current_module_started_at = now();
                if ($lockedAttempt->exists) {
                    $lockedAttempt->save();
                }
            }

            $savedCount = 0;
            if (! empty($answers) || ! empty($questionTimes)) {
                $this->validateAnswers($module, $answers);
                $savedCount = $this->saveModuleAnswers($lockedAttempt, $module, $answers, $questionTimes);
            }

            return [
                'status' => 'success',
                'expired' => false,
                'saved_count' => $savedCount,
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readyResultFor(int $attemptId, int $wantedModuleId): ?array
    {
        $result = Cache::get("scoring_result_{$attemptId}");

        if (is_array($result) && (! isset($result['scored_module_id']) || (int) $result['scored_module_id'] === $wantedModuleId || $wantedModuleId <= 0)) {
            return $result;
        }

        if ($wantedModuleId > 0) {
            $receiptResult = UserTestModuleSubmission::where('user_test_id', $attemptId)
                ->where('module_id', $wantedModuleId)
                ->value('result');

            if (is_array($receiptResult)) {
                return $receiptResult;
            }
        }

        return null;
    }

    /**
     * @param  array<string|int, mixed>  $answers
     * @param  array<string|int, mixed>  $questionTimes
     * @return array<string, mixed>
     */
    public function submitModule(
        UserTest $attempt,
        Module $module,
        array $answers = [],
        array $questionTimes = [],
        bool $clientTimedOut = false,
    ): array {
        $attemptId = (int) $attempt->id;
        $moduleId = (int) $module->id;

        $lockKey = "module_submit_lock_{$attemptId}_{$moduleId}";
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            $ready = $this->readyResultFor($attemptId, $moduleId);
            if ($ready !== null) {
                return ['status' => 'ready', 'result' => $ready];
            }

            Log::channel('queue')->info('conflict.lock_held', [
                'user_test_id' => $attemptId,
                'requested_module_id' => $moduleId,
            ]);

            return ['status' => 'in_flight'];
        }

        $markerKey = self::submitMarkerKey($attemptId, $moduleId);
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        $jobDispatched = false;

        try {
            Log::info('submitModule called via AttemptEngine', [
                'user_test_id' => $attemptId,
                'module_id' => $moduleId,
            ]);

            $outcome = DB::transaction(function () use ($attempt, $module, $answers, $questionTimes, $clientTimedOut) {
                $lockedAttempt = $attempt;
                if ($attempt->exists) {
                    $lockedAttempt = UserTest::where('id', $attempt->id)->lockForUpdate()->first();
                    if (! $lockedAttempt) {
                        throw new AuthorizationException('This test attempt is no longer available.');
                    }
                }

                $receipt = UserTestModuleSubmission::where('user_test_id', $lockedAttempt->id)
                    ->where('module_id', $module->id)
                    ->first();

                if ($receipt) {
                    if (! empty($receipt->result['test_completed']) && $lockedAttempt->status === 'completed') {
                        return ['status' => 'completed', 'result' => $receipt->result];
                    }

                    if ($receipt->issued_next_module_id && (int) $receipt->issued_next_module_id === (int) $lockedAttempt->current_module_id) {
                        return ['status' => 'ready', 'result' => $receipt->result];
                    }

                    Log::channel('queue')->info('conflict.stale_receipt', [
                        'user_test_id' => $lockedAttempt->id,
                        'requested_module_id' => $module->id,
                        'current_module_id' => (int) $lockedAttempt->current_module_id,
                        'issued_next_module_id' => (int) $receipt->issued_next_module_id,
                        'attempt_status' => $lockedAttempt->status,
                    ]);

                    throw new ConflictHttpException('This submission is stale because the attempt has already progressed.');
                }

                if ($lockedAttempt->status !== 'in_progress') {
                    throw new AuthorizationException('This test attempt is no longer available.');
                }

                if ($this->isPendingBreak($lockedAttempt, $module)) {
                    throw new ConflictHttpException('break_pending');
                }

                $this->validateAnswers($module, $answers);

                $section = $module->section;
                $timedOut = false;
                $nextStartsAt = null;

                if ($lockedAttempt->runsOnChainedClock()) {
                    $timedOut = $this->syncElapsed($lockedAttempt, $module)['expired'];
                    if ($clientTimedOut && ! $timedOut) {
                        return ['status' => 'not_expired', 'timing' => $this->timingSnapshot($lockedAttempt, $module)];
                    }
                    if ($timedOut) {
                        $nextStartsAt = $this->deadline($lockedAttempt, $module);
                    }
                } elseif ($lockedAttempt->current_module_started_at) {
                    if ($this->isPastGraceDeadline($lockedAttempt, $module)) {
                        throw new AuthorizationException('Module submission time has expired.');
                    }
                }

                $this->saveModuleAnswers(
                    $lockedAttempt,
                    $module,
                    $timedOut ? [] : $answers,
                    $timedOut ? [] : $questionTimes,
                    onlyMissing: $timedOut
                );

                return [
                    'status' => 'proceed',
                    'user_test_id' => $lockedAttempt->id,
                    'module_id' => $module->id,
                    'section_id' => $section?->id,
                    'timed_out' => $timedOut,
                    'next_starts_at' => $nextStartsAt,
                ];
            });

            if ($outcome['status'] === 'not_expired') {
                return $outcome;
            }

            if (in_array($outcome['status'], ['completed', 'ready'], true)) {
                return ['status' => $outcome['status'], 'result' => $outcome['result']];
            }

            $cacheKey = "scoring_result_{$outcome['user_test_id']}";
            Cache::forget($cacheKey);

            if (config('scoring.inline_non_final')) {
                try {
                    if (! $this->isTerminalSubmission($attempt, $module)) {
                        $result = $this->scoreAndAdvance(
                            $outcome['user_test_id'],
                            $outcome['module_id'],
                            $outcome['timed_out'],
                            $outcome['next_starts_at'],
                        );

                        return ['status' => 'ready', 'result' => $result];
                    }
                } catch (\Throwable $e) {
                    Log::channel('queue')->warning('inline_scoring_fell_back_to_queue', [
                        'user_test_id' => $outcome['user_test_id'],
                        'module_id' => $outcome['module_id'],
                        'reason' => $e->getMessage(),
                    ]);
                }
            }

            \App\Jobs\ScoreModuleJob::dispatch(
                $outcome['user_test_id'],
                $outcome['module_id'],
                $outcome['section_id'],
                $outcome['timed_out'],
                $lockKey,
                $lock->owner(),
                startNextAtIso: $outcome['next_starts_at']?->toIso8601String(),
            );
            $jobDispatched = true;

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return ['status' => 'ready', 'result' => $cached];
            }

            return ['status' => 'scoring', 'message' => 'Scoring in progress...'];
        } finally {
            if (! $jobDispatched) {
                Cache::forget($markerKey);
                $lock->release();
            }
        }
    }



    /**
     * Score a submitted module, advance the attempt, and record the outcome.
     *
     * Shared between the inline path in submit() and ScoreModuleJob so both
     * write identical receipts and cached outcomes.
     *
     * @return array<string, mixed>
     */
    public function scoreAndAdvance(int $userTestId, int $moduleId, bool $timedOut, ?CarbonInterface $startNextAt = null): array
    {
        $cacheKey = "scoring_result_{$userTestId}";
        $resultTtl = (int) config('scoring.result_ttl', 900);

        $userTest = UserTest::findOrFail($userTestId);
        $module = Module::findOrFail($moduleId);

        $result = $this->submitProgression($userTest, $module);
        $result['timed_out'] = $timedOut;
        $result['scored_module_id'] = $moduleId;

        if (! empty($result['is_section_break'])) {
            $breakDurationSeconds = (int) ($result['break_duration_seconds'] ?? 600);
            $breakAnchor = ($timedOut && $startNextAt) ? $startNextAt->copy() : now();
            $breakEndsAt = $breakAnchor->copy()->addSeconds($breakDurationSeconds);
            $result['break_ends_at'] = $breakEndsAt->toIso8601String();
            if ($userTest->runsOnChainedClock()) {
                $startNextAt = $breakEndsAt;
            }
        }

        if (! isset($result['error'])) {
            $result = $this->advanceAndRecord($userTestId, $moduleId, $result, $startNextAt);
        }

        Cache::put($cacheKey, $result, $resultTtl);
        Cache::forget(self::submitMarkerKey($userTestId, $moduleId));

        return $result;
    }

    /**
     * Persist the routing decision and a submission receipt. If a duplicate
     * request raced this one and already recorded a receipt for this module, fall
     * back to that receipt's result instead of erroring.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function advanceAndRecord(int $userTestId, int $moduleId, array $result, ?CarbonInterface $startNextAt = null): array
    {
        return DB::transaction(function () use ($userTestId, $moduleId, $result, $startNextAt) {
            $existing = UserTestModuleSubmission::where('user_test_id', $userTestId)
                ->where('module_id', $moduleId)
                ->first();

            if ($existing) {
                return $existing->result;
            }

            $userTest = UserTest::where('id', $userTestId)->lockForUpdate()->first();

            if (! $userTest) {
                return $result;
            }

            $nextModule = $this->advance($userTest, $result, $startNextAt);

            try {
                UserTestModuleSubmission::create([
                    'user_test_id' => $userTestId,
                    'module_id' => $moduleId,
                    'issued_next_module_id' => $nextModule?->id,
                    'result' => $result,
                    'submitted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return UserTestModuleSubmission::where('user_test_id', $userTestId)
                    ->where('module_id', $moduleId)
                    ->first()
                    ->result;
            }

            return $result;
        });
    }

    /**
     * Cache the generic failure payload. Shared so the inline path, the job's
     * catch block and its failed() hook all report the same shape.
     */
    /**
     * @return array<string, mixed>
     */
    public function recordFailure(int $userTestId, int $moduleId, \Throwable $e, string $context): array
    {
        Log::error($context, [
            'user_test_id' => $userTestId,
            'module_id' => $moduleId,
            'exception' => $e,
        ]);

        $payload = [
            'status' => 'error',
            'error' => 'Server error during submission.',
            'message' => 'An unexpected server error occurred.',
            'scored_module_id' => $moduleId,
        ];

        Cache::put("scoring_result_{$userTestId}", $payload, (int) config('scoring.result_ttl', 900));
        Cache::forget(self::submitMarkerKey($userTestId, $moduleId));

        return $payload;
    }

    // ==========================================
    // Cascade & Expired Submissions
    // ==========================================

    private function submitOmittedModule(UserTest $attempt, Module $module, ?CarbonInterface $deadline = null): bool
    {
        $lockKey = "module_submit_lock_{$attempt->id}_{$module->id}";
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            return false;
        }

        $markerKey = self::submitMarkerKey($attempt->id, $module->id);
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        try {
            if (UserTestModuleSubmission::where('user_test_id', $attempt->id)->where('module_id', $module->id)->exists()) {
                Log::channel('queue')->warning('omitted_submit.stuck_receipt', [
                    'user_test_id' => $attempt->id,
                    'module_id' => $module->id,
                ]);

                return false;
            }

            DB::transaction(function () use ($attempt, $module) {
                $this->syncElapsed($attempt, $module);
                $this->saveModuleAnswers($attempt, $module, [], [], onlyMissing: true);
            });

            Cache::forget("scoring_result_{$attempt->id}");

            $result = $this->scoreAndAdvance(
                $attempt->id,
                $module->id,
                timedOut: true,
                startNextAt: $deadline,
            );

            if (isset($result['error'])) {
                Log::channel('queue')->error('omitted_submit.scoring_failed', [
                    'user_test_id' => $attempt->id,
                    'module_id' => $module->id,
                ]);

                return false;
            }

            Log::channel('queue')->info('omitted_submit.module_submitted', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'deadline' => $deadline?->toIso8601String(),
                'test_completed' => (bool) ($result['test_completed'] ?? false),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('queue')->error('omitted_submit.exception', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'exception' => $e,
            ]);

            return false;
        } finally {
            Cache::forget($markerKey);
            $lock->release();
        }
    }

    public function cascadeExpire(
        UserTest $attempt,
        ?CarbonInterface $deadline = null,
        bool $force = false
    ): int {
        if (! $force && (! $attempt->runsOnChainedClock() || $attempt->status !== 'in_progress')) {
            return 0;
        }

        $overallDeadlineExpired = $deadline !== null && $deadline->isPast();
        $submitted = 0;

        for ($step = 0; $step < self::MAX_CASCADE_STEPS; $step++) {
            $attempt->refresh();

            if ($attempt->status !== 'in_progress' || ! $attempt->current_module_id) {
                break;
            }

            $module = Module::find($attempt->current_module_id);
            if (! $module) {
                break;
            }

            $modDeadline = $this->deadline($attempt, $module);
            $isExpired = $force
                || $overallDeadlineExpired
                || ($modDeadline && ! $modDeadline->isFuture());

            if (! $isExpired) {
                break;
            }

            if (! $this->submitOmittedModule($attempt, $module, $modDeadline ?? now())) {
                break;
            }

            $submitted++;
        }

        return $submitted;
    }

    /**
     * Candidate attempts for a cascade sweep: running attempts with chained clocks
     * that have an active module with a started clock.
     *
     * @return \Illuminate\Database\Eloquent\Builder<UserTest>
     */
    public function candidateCascadeAttemptsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return UserTest::query()
            ->where(fn ($q) => $q->whereNotNull('assignment_id')->orWhereNotNull('exam_session_id'))
            ->where('status', 'in_progress')
            ->whereNotNull('current_module_id')
            ->whereNotNull('current_module_started_at');
    }

    // ==========================================
    // Progression & Adaptive Routing
    // ==========================================

    /**
     * @return array<string, mixed>
     */
    public function submitProgression(UserTest $attempt, Module $submitted): array
    {
        // Only counts and IRT parameters are ever read off this relation:
        // TestStructureService::validate() counts questions, and
        // validateAdaptiveMeasurement() reads is_pretest + irt_a/b/c.
        // Selecting whole rows pulled every question `stem` in the test (~162
        // LONGTEXT rows on an adaptive full-length) on EVERY module submit.
        // TestStructureService uses loadMissing(), so it will not refetch — these
        // columns must stay present or that validation silently misbehaves.
        $test = Test::with(['sections.modules.questions' => fn ($query) => $query->select([
            'questions.id',
            'questions.is_pretest',
            'questions.irt_a',
            'questions.irt_b',
            'questions.irt_c',
        ])])->findOrFail($attempt->test_id);
        $shape = $this->structures->validate($test);
        $section = $shape['sections']->first(fn ($candidate) => $candidate->modules->contains('id', $submitted->id));
        if (! $section) {
            throw new \RuntimeException('Submitted module is outside the validated test structure.');
        }

        $flow = $shape['flows']->get($section->id);
        if ($flow === TestStructureService::FLOW_ADAPTIVE && (int) $submitted->module_number === 1) {
            return $this->routeAdaptive($attempt, $test, $section, $submitted);
        }

        if ($flow === TestStructureService::FLOW_LINEAR) {
            $modules = $this->structures->orderedModules($section);
            $index = $modules->search(fn ($module) => (int) $module->id === (int) $submitted->id);
            if ($index !== false && $modules->has($index + 1)) {
                return $this->nextModuleResult($modules->get($index + 1), 'Moving to the next module.');
            }
        }

        return $this->advanceSectionOrComplete($attempt, $test, $section, $shape['sections']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function routeAdaptive(UserTest $attempt, Test $test, Section $section, Module $module): array
    {
        $requestedPath = $this->pipeline->routeAdaptiveModule($attempt, $section, $module);
        $next = $section->modules->first(fn ($candidate) => (int) $candidate->module_number === 2 && $candidate->difficulty_level === $requestedPath);
        $usedFallback = false;
        if (! $next && $test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            $next = $section->modules->first(fn ($candidate) => (int) $candidate->module_number === 2 && $candidate->difficulty_level !== $requestedPath);
            $usedFallback = $next !== null;
        }
        if (! $next) {
            throw new \RuntimeException('Adaptive test is missing the routed Module 2 path. Convert the draft to Normal Full or restore both branches.');
        }

        $actualPath = $next->difficulty_level;
        $attempt->forceFill($section->type === Section::TYPE_RW ? ['rw_m2_path' => $actualPath] : ['math_m2_path' => $actualPath])->save();

        $result = $this->nextModuleResult($next, "Module 1 submitted. Routed to {$actualPath} Module 2.") + [
            'path' => $requestedPath,
            'actual_path' => $actualPath,
        ];
        if ($usedFallback) {
            $result['fallback_module_id'] = $next->ulid;
        }

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Section>  $sections
     * @return array<string, mixed>
     */
    protected function advanceSectionOrComplete(UserTest $attempt, Test $test, Section $section, Collection $sections): array
    {
        if ($attempt->attempt_type === 'section') {
            $this->finalize($attempt, $test);
            $finalAttempt = $this->autoMergeIfEligible($attempt, $test);

            return [
                'status' => 'success',
                'test_completed' => true,
                'message' => 'Section completed.',
            ] + $this->completionDestination($finalAttempt);
        }

        $nextSection = $sections->first(fn ($candidate) => (int) $candidate->order > (int) $section->order);
        if ($nextSection) {
            $next = $this->structures->orderedModules($nextSection)->first();

            $breakMinutes = (int) $test->break_duration_minutes;
            $breakDurationSeconds = max(0, $breakMinutes * 60);

            return $this->nextModuleResult(
                $next,
                'Section completed. Moving to the next section.',
                isSectionBreak: true,
                breakDurationSeconds: $breakDurationSeconds
            );
        }

        $this->finalize($attempt, $test);

        return [
            'status' => 'success',
            'test_completed' => true,
            'message' => 'Test completed.',
        ] + $this->completionDestination($attempt);
    }

    /**
     * @return array{redirect_url: string, results_url: string}
     */
    protected function completionDestination(UserTest $attempt): array
    {
        if (optional($attempt->user)->is_guest) {
            $url = route('exam-join.result', $attempt);

            return ['redirect_url' => $url, 'results_url' => $url];
        }

        return [
            'redirect_url' => route('home'),
            'results_url' => route('student.scores.show', $attempt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function nextModuleResult(Module $module, string $message, bool $isSectionBreak = false, int $breakDurationSeconds = 600): array
    {
        $result = [
            'status' => 'success',
            'next_module_id' => $module->ulid,
            'next_module_numeric_id' => (int) $module->id,
            'message' => $message,
        ];
        if ($isSectionBreak) {
            $result['is_section_break'] = true;
            $result['break_duration_seconds'] = $breakDurationSeconds;
        }

        return $result;
    }

    protected function autoMergeIfEligible(UserTest $attempt, Test $test): UserTest
    {
        if ($attempt->attempt_type !== 'section' || ! $attempt->section_type) {
            return $attempt;
        }

        $oppositeSectionType = $attempt->section_type === 'reading_writing' ? 'math' : 'reading_writing';

        $oppositeAttempt = UserTest::where('user_id', $attempt->user_id)
            ->where('test_id', $attempt->test_id)
            ->where('attempt_type', 'section')
            ->where('section_type', $oppositeSectionType)
            ->where('status', 'completed')
            ->where('id', '!=', $attempt->id)
            ->latest('completed_at')
            ->first();

        if (! $oppositeAttempt) {
            return $attempt;
        }

        $firstAttempt = ($attempt->completed_at && $oppositeAttempt->completed_at && $attempt->completed_at->lt($oppositeAttempt->completed_at))
            ? $attempt
            : $oppositeAttempt;
        $secondAttempt = $firstAttempt->id === $attempt->id ? $oppositeAttempt : $attempt;

        return DB::transaction(function () use ($firstAttempt, $secondAttempt, $test) {
            $first = UserTest::where('id', $firstAttempt->id)->lockForUpdate()->first();
            $second = UserTest::where('id', $secondAttempt->id)->lockForUpdate()->first();

            if (! $first || ! $second) {
                return $firstAttempt;
            }

            $rwAttempt = $first->section_type === 'reading_writing' ? $first : $second;
            $mathAttempt = $first->section_type === 'math' ? $first : $second;

            $rwScore = $rwAttempt->score_reading_writing;
            $mathScore = $mathAttempt->score_math;

            $totalScore = ($rwScore !== null && $mathScore !== null) ? ($rwScore + $mathScore) : null;
            $totalLower = null;
            $totalUpper = null;

            if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
                $range = $this->pipeline->combinedTotalRange($rwAttempt, $mathAttempt, $totalScore);
                $totalLower = $range['lower'];
                $totalUpper = $range['upper'];
            }

            $existingAnswerKeys = UserTestAnswer::where('user_test_id', $first->id)
                ->get(['module_id', 'question_id'])
                ->map(fn ($answer) => $answer->module_id.':'.$answer->question_id)
                ->flip();

            $answerRows = [];
            foreach (UserTestAnswer::where('user_test_id', $second->id)->cursor() as $ans) {
                if ($existingAnswerKeys->has($ans->module_id.':'.$ans->question_id)) {
                    continue;
                }

                $answerRows[] = [
                    'user_test_id' => $first->id,
                    'module_id' => $ans->module_id,
                    'question_id' => $ans->question_id,
                    'selected_answer' => $ans->selected_answer,
                    'is_correct' => $ans->is_correct,
                    'time_spent' => $ans->time_spent,
                    'question_snapshot' => $ans->getRawOriginal('question_snapshot'),
                    'created_at' => $ans->created_at,
                    'updated_at' => $ans->updated_at,
                ];
            }

            foreach (array_chunk($answerRows, 200) as $chunk) {
                UserTestAnswer::insert($chunk);
            }

            $existingSubmissionModuleIds = UserTestModuleSubmission::where('user_test_id', $first->id)
                ->pluck('module_id')
                ->flip();

            $submissionRows = [];
            foreach (UserTestModuleSubmission::where('user_test_id', $second->id)->cursor() as $sub) {
                if ($existingSubmissionModuleIds->has($sub->module_id)) {
                    continue;
                }

                $submissionRows[] = [
                    'user_test_id' => $first->id,
                    'module_id' => $sub->module_id,
                    'issued_next_module_id' => $sub->issued_next_module_id,
                    'result' => $sub->getRawOriginal('result'),
                    'submitted_at' => $sub->submitted_at,
                    'created_at' => $sub->created_at,
                    'updated_at' => $sub->updated_at,
                ];
            }

            foreach (array_chunk($submissionRows, 200) as $chunk) {
                UserTestModuleSubmission::insert($chunk);
            }

            $first->forceFill([
                'attempt_type' => 'full',
                'section_type' => null,
                'score_reading_writing' => $rwAttempt->score_reading_writing,
                'score_reading_writing_lower' => $rwAttempt->score_reading_writing_lower,
                'score_reading_writing_upper' => $rwAttempt->score_reading_writing_upper,
                'score_math' => $mathAttempt->score_math,
                'score_math_lower' => $mathAttempt->score_math_lower,
                'score_math_upper' => $mathAttempt->score_math_upper,
                'total_score' => $totalScore,
                'total_score_lower' => $totalLower,
                'total_score_upper' => $totalUpper,
                'rw_theta' => $rwAttempt->rw_theta,
                'math_theta' => $mathAttempt->math_theta,
                'rw_theta_se' => $rwAttempt->rw_theta_se,
                'math_theta_se' => $mathAttempt->math_theta_se,
                'rw_m2_path' => $rwAttempt->rw_m2_path,
                'math_m2_path' => $mathAttempt->math_m2_path,
                'scoring_method' => $rwAttempt->scoring_method ?? $mathAttempt->scoring_method,
                'score_conversion_set_id' => $rwAttempt->score_conversion_set_id ?? $mathAttempt->score_conversion_set_id,
                'score_conversion_version' => $rwAttempt->score_conversion_version ?? $mathAttempt->score_conversion_version,
                'score_estimate_kind' => $rwAttempt->score_estimate_kind ?? $mathAttempt->score_estimate_kind,
                'completed_at' => now(),
            ])->save();

            if (! $second->assignment_id) {
                $second->delete();
            }

            return $first;
        });
    }

    protected function finalize(UserTest $attempt, Test $test): void
    {
        $scores = $this->pipeline->scoreAttempt($test, $attempt);
        $attempt->update($this->completionFields() + ($scores ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    protected function completionFields(): array
    {
        return ['status' => 'completed', 'completed_at' => now(), 'current_module_id' => null];
    }
}
