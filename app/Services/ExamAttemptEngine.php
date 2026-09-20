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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Deep module governing the entire lifecycle of a test attempt:
 * timing, module progression, answer grading/persistence, and cascade timeouts.
 */
class ExamAttemptEngine
{
    public const MAX_CASCADE_STEPS = 12;

    public function __construct(
        private ?ModuleScoringService $moduleScoring = null,
    ) {}

    private function getModuleScoring(): ModuleScoringService
    {
        return $this->moduleScoring ??= app(ModuleScoringService::class);
    }

    // ==========================================
    // Timing & Clock
    // ==========================================

    public function durationSeconds(Module $module): int
    {
        $minutes = $module->duration_minutes
            ?? ($module->section?->type === Section::TYPE_MATH ? 35 : 32);

        return max(0, (int) $minutes * 60);
    }

    public function timing(UserTest $userTest, Module $module, ?Carbon $now = null): array
    {
        $durationSeconds = $this->durationSeconds($module);
        $startedAt = $userTest->current_module_started_at;

        if (! $startedAt) {
            return [
                'duration_seconds' => $durationSeconds,
                'elapsed_seconds' => 0,
                'remaining_seconds' => $durationSeconds,
                'expired' => false,
            ];
        }

        $now ??= now();
        $elapsedSeconds = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
        $elapsedSeconds = min($durationSeconds, $elapsedSeconds);
        $remainingSeconds = max(0, $durationSeconds - $elapsedSeconds);

        return [
            'duration_seconds' => $durationSeconds,
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds === 0,
        ];
    }

    public function deadline(UserTest $userTest, Module $module): ?CarbonInterface
    {
        if (! $userTest->current_module_started_at) {
            return null;
        }

        return $userTest->current_module_started_at->copy()
            ->addSeconds($this->durationSeconds($module));
    }

    public function syncElapsed(UserTest $userTest, Module $module): array
    {
        $timing = $this->timing($userTest, $module);
        $userTest->current_module_elapsed_seconds = $timing['elapsed_seconds'];
        $userTest->save();

        return $timing;
    }

    // ==========================================
    // Module Progression
    // ==========================================

    public function firstModule(Test $test, ?UserTest $attempt = null): ?Module
    {
        if ($attempt && $attempt->attempt_type === 'section' && $attempt->section_type) {
            $sectionType = $attempt->section_type === 'reading_writing' ? Section::TYPE_RW : Section::TYPE_MATH;
            $section = $test->sections()->where('type', $sectionType)->first();
        } else {
            $section = $test->sections()->orderBy('order')->first();
        }

        return $section?->modules()
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
        abort_unless($module, 422, 'Test has no module.');

        $attempt->forceFill([
            'current_module_id' => $module->id,
            'current_module_started_at' => null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $module;
    }

    public function assertIssued(UserTest $attempt, Module $module): void
    {
        if ((int) $attempt->current_module_id !== (int) $module->id) {
            throw new ConflictHttpException('This module is not active for the test attempt.');
        }
    }

    public function advance(UserTest $attempt, array $result, ?CarbonInterface $startNextAt = null): ?Module
    {
        if (! empty($result['test_completed'])) {
            $attempt->forceFill([
                'current_module_id' => null,
                'current_module_started_at' => null,
                'current_module_elapsed_seconds' => 0,
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
                ? ($startNextAt ? $startNextAt->copy() : now())
                : null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $nextModule;
    }

    // ==========================================
    // Answer Grading & Persistence
    // ==========================================

    public function checkAnswer(Question $question, $userAnswer): bool
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices->where('is_correct', true)->first();

            return $correctChoice && trim($correctChoice->label) === trim((string) $userAnswer);
        }

        if ($question->question_type === 'student_produced_response' || $question->question_type === 'spr') {
            $submitted = trim((string) $userAnswer);
            $submittedNumber = $this->parseNumericAnswer($submitted);

            foreach ($question->sprCorrectAnswers as $accepted) {
                $acceptedText = trim((string) $accepted->answer);
                $acceptedNumber = $this->parseNumericAnswer($acceptedText);

                if ($submittedNumber !== null && $acceptedNumber !== null) {
                    $tolerance = $accepted->tolerance !== null
                        ? max(0.0, (float) $accepted->tolerance)
                        : 0.0001;

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

        throw new \InvalidArgumentException("Unsupported question type '{$question->question_type}' for question ID {$question->id}");
    }

    public function parseNumericAnswer(string $value): ?float
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

    public function resolveSubmissionContext(array $validated, ?UserTest $lockedAttempt = null): array
    {
        $userTest = $lockedAttempt ?? UserTest::where('id', $validated['user_test_id'])
            ->where('user_id', Auth::id())
            ->lockForUpdate()
            ->first();

        if (! $userTest || $userTest->status !== 'in_progress') {
            throw new AuthorizationException('This test attempt is no longer available.');
        }

        if ($userTest->exam_session_id) {
            $userTest->loadMissing('examSession');
            if ($userTest->examSession && $userTest->examSession->status === 'paused') {
                throw new ConflictHttpException('session_paused');
            }
        }

        $module = Module::with(['section.test', 'questions'])->findOrFail($validated['module_id']);
        $section = $module->section;

        if (! $section || (int) $section->test_id !== (int) $userTest->test_id) {
            throw new AuthorizationException('This module does not belong to the active test attempt.');
        }

        if ((int) $userTest->current_module_id !== (int) $module->id) {
            Log::channel('queue')->info('conflict.module_mismatch', [
                'user_test_id' => $userTest->id,
                'requested_module_id' => (int) $module->id,
                'current_module_id' => (int) $userTest->current_module_id,
                'attempt_status' => $userTest->status,
            ]);

            throw new ConflictHttpException('This module is not active for the test attempt.');
        }

        $questionIds = collect(array_keys($validated['answers']))
            ->map(fn ($id) => (string) $id);

        if ($questionIds->contains(fn ($id) => ! ctype_digit($id))) {
            throw ValidationException::withMessages([
                'answers' => 'Submitted answers contain invalid question ids.',
            ]);
        }

        $questionIds = $questionIds->map(fn ($id) => (int) $id)->unique()->values();
        if ($questionIds->isNotEmpty()) {
            $validQuestionIds = $module->questions()
                ->whereIn('questions.id', $questionIds->all())
                ->pluck('questions.id')
                ->map(fn ($id) => (int) $id);

            if ($validQuestionIds->count() !== $questionIds->count()) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers include questions outside the current module.',
                ]);
            }
        }

        return [$userTest, $module];
    }

    public function saveModuleAnswers(
        UserTest $userTest,
        Module $module,
        array $submittedAnswers,
        array $submittedTimes = [],
        bool $onlyMissing = false
    ): int {
        $questions = $module->questions()
            ->with(['answerChoices', 'sprCorrectAnswers', 'passage', 'explanation'])
            ->get();
        $existingQuestionIds = $onlyMissing
            ? UserTestAnswer::where('user_test_id', $userTest->id)
                ->where('module_id', $module->id)
                ->pluck('question_id')
                ->map(fn ($id) => (int) $id)
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
                'user_test_id' => $userTest->id,
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
    // Cascade & Expired Submissions
    // ==========================================

    public function submitExpiredModule(UserTest $attempt, Module $module, CarbonInterface $deadline): bool
    {
        $lockKey = "module_submit_lock_{$attempt->id}_{$module->id}";
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            return false;
        }

        $markerKey = ModuleScoringService::submitMarkerKey($attempt->id, $module->id);
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        try {
            $receipt = UserTestModuleSubmission::where('user_test_id', $attempt->id)
                ->where('module_id', $module->id)
                ->exists();
            if ($receipt) {
                Log::channel('queue')->warning('timeout_sweep.stuck_receipt', [
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

            $result = $this->getModuleScoring()->scoreAndAdvance(
                $attempt->id,
                $module->id,
                timedOut: true,
                startNextAt: $deadline,
            );

            if (isset($result['error'])) {
                Log::channel('queue')->error('timeout_sweep.scoring_failed', [
                    'user_test_id' => $attempt->id,
                    'module_id' => $module->id,
                ]);

                return false;
            }

            Log::channel('queue')->info('timeout_sweep.module_submitted', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'deadline' => $deadline->toIso8601String(),
                'late_by_seconds' => max(0, now()->getTimestamp() - $deadline->getTimestamp()),
                'test_completed' => (bool) ($result['test_completed'] ?? false),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('queue')->error('timeout_sweep.exception', [
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

    public function cascadeExpire(UserTest $attempt): int
    {
        if (! $attempt->runsOnChainedClock() || $attempt->status !== 'in_progress') {
            return 0;
        }

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

            $deadline = $this->deadline($attempt, $module);
            if (! $deadline || $deadline->isFuture()) {
                break;
            }

            if (! $this->submitExpiredModule($attempt, $module, $deadline)) {
                break;
            }

            $submitted++;
        }

        return $submitted;
    }

    /**
     * Teacher-triggered forced module submit for live exam sessions.
     */
    public function forceSubmitModule(UserTest $attempt, Module $module): bool
    {
        $lockKey = "module_submit_lock_{$attempt->id}_{$module->id}";
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            return false;
        }

        $markerKey = ModuleScoringService::submitMarkerKey($attempt->id, $module->id);
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        try {
            $alreadySubmitted = UserTestModuleSubmission::where('user_test_id', $attempt->id)
                ->where('module_id', $module->id)
                ->exists();

            if ($alreadySubmitted) {
                return false;
            }

            DB::transaction(function () use ($attempt, $module) {
                $this->saveModuleAnswers($attempt, $module, [], [], onlyMissing: true);
            });

            $result = $this->getModuleScoring()->scoreAndAdvance($attempt->id, $module->id, true);

            return ! isset($result['error']);
        } finally {
            $lock->release();
            Cache::forget($markerKey);
        }
    }

    public function forceSubmitCascade(UserTest $attempt): void
    {
        abort_unless((bool) $attempt->exam_session_id, 422, 'Not an exam-session attempt.');

        for ($step = 0; $step < self::MAX_CASCADE_STEPS; $step++) {
            $attempt->refresh();

            if ($attempt->status !== 'in_progress' || ! $attempt->current_module_id) {
                break;
            }

            $module = Module::find($attempt->current_module_id);
            if (! $module || ! $this->forceSubmitModule($attempt, $module)) {
                break;
            }
        }
    }
}
