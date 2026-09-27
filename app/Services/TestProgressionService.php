<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestModuleSubmission;
use Illuminate\Support\Facades\DB;

class TestProgressionService
{
    public function __construct(
        private TestStructureService $structures,
        private SatScoringPipeline $pipeline,
    ) {}

    public function submit(UserTest $attempt, Module $submitted): array
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
     * Would submitting this module finish the attempt?
     *
     * Terminal submissions run finalize() — two IRT estimates, score conversion and
     * a possible auto-merge — and are the only ones expensive enough to justify the
     * queue. Everything else is a single theta estimate and can be scored inline.
     *
     * NOTE the criterion is "terminal", not "module 1". On an adaptive full-length
     * (RW M1, RW M2, Math M1, Math M2) only Math M2 is terminal; treating
     * module_number === 1 as the test would leave two of four submissions queued
     * for no reason.
     *
     * Mirrors the branching in submit() above. Callers must treat a thrown
     * exception as "assume terminal" and fall back to the queue.
     */
    public function isTerminalSubmission(UserTest $attempt, Module $submitted): bool
    {
        $test = Test::with(['sections.modules'])->findOrFail($attempt->test_id);
        $shape = $this->structures->validate($test);
        $section = $shape['sections']->first(fn ($candidate) => $candidate->modules->contains('id', $submitted->id));

        if (! $section) {
            throw new \RuntimeException('Submitted module is outside the validated test structure.');
        }

        $flow = $shape['flows']->get($section->id);

        // Adaptive module 1 always routes to a module 2.
        if ($flow === TestStructureService::FLOW_ADAPTIVE && (int) $submitted->module_number === 1) {
            return false;
        }

        // Linear flow with another module left in this section.
        if ($flow === TestStructureService::FLOW_LINEAR) {
            $modules = $this->structures->orderedModules($section);
            $index = $modules->search(fn ($module) => (int) $module->id === (int) $submitted->id);
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
    }

    private function routeAdaptive(UserTest $attempt, Test $test, Section $section, Module $module): array
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

    private function advanceSectionOrComplete(UserTest $attempt, Test $test, Section $section, $sections): array
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

            $breakMinutes = (int) ($test->break_duration_minutes ?? 10);

            return $this->nextModuleResult(
                $next,
                'Section completed. Moving to the next section.',
                isSectionBreak: true,
                breakDurationSeconds: $breakMinutes * 60
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
     * Guests have no authenticated app to land in — /home and the full score
     * report both render the icon-rail/profile/logout shell (see
     * ExamSessionService::provisionGuest for why a guest is a real `User` row
     * that would otherwise sail straight through those pages). Route them to
     * the minimal, ownership-gated result view instead.
     */
    private function completionDestination(UserTest $attempt): array
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

    private function nextModuleResult(Module $module, string $message, bool $isSectionBreak = false, int $breakDurationSeconds = 600): array
    {
        $result = ['status' => 'success', 'next_module_id' => $module->ulid, 'message' => $message];
        if ($isSectionBreak) {
            $result['is_section_break'] = true;
            $result['break_duration_seconds'] = $breakDurationSeconds;
        }

        return $result;
    }

    private function autoMergeIfEligible(UserTest $attempt, Test $test): UserTest
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

            // This runs on the FINAL module of a section attempt — exactly when a
            // whole cohort converges at once. The previous version issued one
            // exists() per answer (~108 queries) and one INSERT per row, all inside
            // this transaction while it holds two lockForUpdate rows. Same rows,
            // same skip rule, same timestamps — three statements instead of ~220.
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
                    // Raw value: the model casts this to array, and insert() bypasses
                    // casting, so the encoded JSON has to go in as stored.
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

    private function finalize(UserTest $attempt, Test $test): void
    {
        $scores = $this->pipeline->scoreAttempt($test, $attempt);
        $attempt->update($this->completionFields() + ($scores ?? []));
    }

    private function completionFields(): array
    {
        return ['status' => 'completed', 'completed_at' => now(), 'current_module_id' => null];
    }
}
