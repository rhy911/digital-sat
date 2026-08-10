<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TestProgressionService
{
    public function __construct(
        private TestStructureService $structures,
        private SatScoringService $scoring,
        private ScoreConversionService $conversions,
        private AdaptiveScoreConversionService $adaptiveConversions,
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
        $responses = $this->completeResponsesForModule($attempt, $module);
        $theta = $this->scoring->estimateTheta($responses);
        $requestedPath = $this->scoring->routeModule2($theta, $section->type);
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
                'redirect_url' => route('home'),
                'results_url' => route('student.scores.show', $finalAttempt),
                'message' => 'Section completed.',
            ];
        }

        $nextSection = $sections->first(fn ($candidate) => (int) $candidate->order > (int) $section->order);
        if ($nextSection) {
            $next = $this->structures->orderedModules($nextSection)->first();

            return $this->nextModuleResult($next, 'Section completed. Moving to the next section.');
        }

        $this->finalize($attempt, $test);

        return [
            'status' => 'success',
            'test_completed' => true,
            'redirect_url' => route('home'),
            'results_url' => route('student.scores.show', $attempt),
            'message' => 'Test completed.',
        ];
    }

    private function nextModuleResult(Module $module, string $message): array
    {
        return ['status' => 'success', 'next_module_id' => $module->ulid, 'message' => $message];
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($firstAttempt, $secondAttempt, $test) {
            $first  = UserTest::where('id', $firstAttempt->id)->lockForUpdate()->first();
            $second = UserTest::where('id', $secondAttempt->id)->lockForUpdate()->first();

            if (! $first || ! $second) {
                return $firstAttempt;
            }

            $rwAttempt   = $first->section_type === 'reading_writing' ? $first : $second;
            $mathAttempt = $first->section_type === 'math' ? $first : $second;

            $rwScore   = $rwAttempt->score_reading_writing;
            $mathScore = $mathAttempt->score_math;

            $totalScore = ($rwScore !== null && $mathScore !== null) ? ($rwScore + $mathScore) : null;
            $totalLower = null;
            $totalUpper = null;

            if ($test->test_type === Test::TYPE_ADAPTIVE_FULL && $rwAttempt->rw_theta !== null && $mathAttempt->math_theta !== null) {
                $rwConv   = $this->adaptiveConversions->convert((float) $rwAttempt->rw_theta, (float) $rwAttempt->rw_theta_se, Section::TYPE_RW, $rwAttempt->rw_m2_path);
                $mathConv = $this->adaptiveConversions->convert((float) $mathAttempt->math_theta, (float) $mathAttempt->math_theta_se, Section::TYPE_MATH, $mathAttempt->math_m2_path);
                if ($rwConv && $mathConv) {
                    // Keep the stored section-score sum (which respects an approved
                    // override table); use the IRT curve only for the +/- margin.
                    $total = $this->adaptiveConversions->totalRange($rwConv, $mathConv);
                    $lowerMargin = $total['score'] - $total['lower'];
                    $upperMargin = $total['upper'] - $total['score'];
                    $totalLower = $totalScore !== null ? max(400, $totalScore - $lowerMargin) : null;
                    $totalUpper = $totalScore !== null ? min(1600, $totalScore + $upperMargin) : null;
                }
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
                    'user_test_id'      => $first->id,
                    'module_id'         => $ans->module_id,
                    'question_id'       => $ans->question_id,
                    'selected_answer'   => $ans->selected_answer,
                    'is_correct'        => $ans->is_correct,
                    'time_spent'        => $ans->time_spent,
                    // Raw value: the model casts this to array, and insert() bypasses
                    // casting, so the encoded JSON has to go in as stored.
                    'question_snapshot' => $ans->getRawOriginal('question_snapshot'),
                    'created_at'        => $ans->created_at,
                    'updated_at'        => $ans->updated_at,
                ];
            }

            foreach (array_chunk($answerRows, 200) as $chunk) {
                UserTestAnswer::insert($chunk);
            }

            $existingSubmissionModuleIds = \App\Models\UserTestModuleSubmission::where('user_test_id', $first->id)
                ->pluck('module_id')
                ->flip();

            $submissionRows = [];
            foreach (\App\Models\UserTestModuleSubmission::where('user_test_id', $second->id)->cursor() as $sub) {
                if ($existingSubmissionModuleIds->has($sub->module_id)) {
                    continue;
                }

                $submissionRows[] = [
                    'user_test_id'          => $first->id,
                    'module_id'             => $sub->module_id,
                    'issued_next_module_id' => $sub->issued_next_module_id,
                    'result'                => $sub->getRawOriginal('result'),
                    'submitted_at'          => $sub->submitted_at,
                    'created_at'            => $sub->created_at,
                    'updated_at'            => $sub->updated_at,
                ];
            }

            foreach (array_chunk($submissionRows, 200) as $chunk) {
                \App\Models\UserTestModuleSubmission::insert($chunk);
            }

            $first->forceFill([
                'attempt_type'                => 'full',
                'section_type'               => null,
                'score_reading_writing'       => $rwAttempt->score_reading_writing,
                'score_reading_writing_lower' => $rwAttempt->score_reading_writing_lower,
                'score_reading_writing_upper' => $rwAttempt->score_reading_writing_upper,
                'score_math'                  => $mathAttempt->score_math,
                'score_math_lower'            => $mathAttempt->score_math_lower,
                'score_math_upper'            => $mathAttempt->score_math_upper,
                'total_score'                 => $totalScore,
                'total_score_lower'           => $totalLower,
                'total_score_upper'           => $totalUpper,
                'rw_theta'                    => $rwAttempt->rw_theta,
                'math_theta'                  => $mathAttempt->math_theta,
                'rw_theta_se'                 => $rwAttempt->rw_theta_se,
                'math_theta_se'               => $mathAttempt->math_theta_se,
                'rw_m2_path'                  => $rwAttempt->rw_m2_path,
                'math_m2_path'                => $mathAttempt->math_m2_path,
                'scoring_method'              => $rwAttempt->scoring_method ?? $mathAttempt->scoring_method,
                'score_conversion_set_id'     => $rwAttempt->score_conversion_set_id ?? $mathAttempt->score_conversion_set_id,
                'score_conversion_version'    => $rwAttempt->score_conversion_version ?? $mathAttempt->score_conversion_version,
                'score_estimate_kind'         => $rwAttempt->score_estimate_kind ?? $mathAttempt->score_estimate_kind,
                'completed_at'                => now(),
            ])->save();

            if (! $second->assignment_id) {
                $second->delete();
            }

            return $first;
        });
    }

    private function finalize(UserTest $attempt, Test $test): void
    {
        if ($attempt->user?->role === 'admin') {
            $attempt->update($this->completionFields());

            return;
        }

        if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
            $this->finalizeAdaptive($attempt, $test);

            return;
        }

        if ($test->test_type === Test::TYPE_FULL) {
            $this->finalizeNormal($attempt, $test);

            return;
        }

        $rwResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_RW));
        $mathResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_MATH));
        $rwAbility = $this->tryEstimateAbility($attempt, $rwResponses);
        $mathAbility = $this->tryEstimateAbility($attempt, $mathResponses);
        $attempt->update($this->completionFields() + [
            'score_reading_writing' => null,
            'score_math' => null,
            'total_score' => null,
            'rw_theta' => $rwAbility ? round($rwAbility['theta'], 3) : null,
            'math_theta' => $mathAbility ? round($mathAbility['theta'], 3) : null,
            'rw_theta_se' => $rwAbility ? round($rwAbility['se'], 3) : null,
            'math_theta_se' => $mathAbility ? round($mathAbility['se'], 3) : null,
            'scoring_method' => $rwAbility['method'] ?? $mathAbility['method'] ?? null,
            'score_conversion_set_id' => null,
            'score_conversion_version' => null,
            'score_estimate_kind' => null,
        ]);
    }

    private function finalizeAdaptive(UserTest $attempt, Test $test): void
    {
        $rw = $test->sections->firstWhere('type', Section::TYPE_RW);
        $math = $test->sections->firstWhere('type', Section::TYPE_MATH);

        $isRw = $attempt->attempt_type !== 'section' || $attempt->section_type === 'reading_writing';
        $isMath = $attempt->attempt_type !== 'section' || $attempt->section_type === 'math';

        $rwScore = $isRw ? $this->tryScoreAdaptiveSection($attempt, $rw, $attempt->rw_m2_path) : null;
        $mathScore = $isMath ? $this->tryScoreAdaptiveSection($attempt, $math, $attempt->math_m2_path) : null;

        $rwConversion = $this->resolveAdaptiveConversion($test, $rwScore, Section::TYPE_RW, $attempt->rw_m2_path);
        $mathConversion = $this->resolveAdaptiveConversion($test, $mathScore, Section::TYPE_MATH, $attempt->math_m2_path);

        $attempt->update($this->completeScoreFields($rwScore, $mathScore, $rwConversion, $mathConversion));
    }

    /**
     * Build the persisted score fields shared by the adaptive and normal IRT paths.
     * A total confidence band is only meaningful when both sections came from the IRT
     * curve (which carries a scaled SE); an approved override table has no band.
     *
     * @param  array{theta:float,theta_se:float,raw_score:int,method:string}|null  $rwScore
     * @param  array{theta:float,theta_se:float,raw_score:int,method:string}|null  $mathScore
     * @param  array{scaled_score:int,lower:?int,upper:?int,scaled_se:?int,conversion_set_id:?int,conversion_version:string,estimate_kind:string}|null  $rwConversion
     * @param  array{scaled_score:int,lower:?int,upper:?int,scaled_se:?int,conversion_set_id:?int,conversion_version:string,estimate_kind:string}|null  $mathConversion
     */
    private function completeScoreFields(?array $rwScore, ?array $mathScore, ?array $rwConversion, ?array $mathConversion): array
    {
        $total = ($rwConversion && $mathConversion
            && $rwConversion['scaled_se'] !== null && $mathConversion['scaled_se'] !== null)
            ? $this->adaptiveConversions->totalRange($rwConversion, $mathConversion)
            : null;
        $totalScore = ($rwConversion && $mathConversion)
            ? $rwConversion['scaled_score'] + $mathConversion['scaled_score']
            : null;

        return $this->completionFields() + [
            'score_reading_writing' => $rwConversion['scaled_score'] ?? null,
            'score_reading_writing_lower' => $rwConversion['lower'] ?? null,
            'score_reading_writing_upper' => $rwConversion['upper'] ?? null,
            'score_math' => $mathConversion['scaled_score'] ?? null,
            'score_math_lower' => $mathConversion['lower'] ?? null,
            'score_math_upper' => $mathConversion['upper'] ?? null,
            'rw_theta' => $rwScore['theta'] ?? null,
            'math_theta' => $mathScore['theta'] ?? null,
            'rw_theta_se' => $rwScore['theta_se'] ?? null,
            'math_theta_se' => $mathScore['theta_se'] ?? null,
            'scoring_method' => $rwScore['method'] ?? $mathScore['method'] ?? null,
            'total_score' => $totalScore,
            'total_score_lower' => $total['lower'] ?? null,
            'total_score_upper' => $total['upper'] ?? null,
            'score_conversion_set_id' => $rwConversion['conversion_set_id'] ?? $mathConversion['conversion_set_id'] ?? null,
            'score_conversion_version' => $rwConversion['conversion_version'] ?? $mathConversion['conversion_version'] ?? null,
            'score_estimate_kind' => $rwConversion['estimate_kind'] ?? $mathConversion['estimate_kind'] ?? null,
        ];
    }

    /**
     * Resolve one adaptive section's scaled score. Prefers the test's approved,
     * checksum-valid conversion table (routed by the taken Module 2 path) as an
     * override; falls back to the path-aware IRT curve when no such table applies.
     * Returns a shape with a null scaled_se when the override table is used (no band).
     *
     * @param  array{theta:float,theta_se:float,raw_score:int}|null  $score
     * @return array{scaled_score:int,lower:?int,upper:?int,scaled_se:?int,conversion_set_id:?int,conversion_version:string,estimate_kind:string}|null
     */
    private function resolveAdaptiveConversion(Test $test, ?array $score, string $sectionType, ?string $path): ?array
    {
        if (! $score) {
            return null;
        }

        $override = $this->conversions->tryApprovedConversion(
            $test, $sectionType, $path ?? Module::DIFFICULTY_HARD, (int) $score['raw_score']
        );
        if ($override) {
            return [
                'scaled_score' => $override['scaled_score'],
                'lower' => null,
                'upper' => null,
                'scaled_se' => null,
                'conversion_set_id' => $override['conversion_set_id'],
                'conversion_version' => $override['conversion_version'],
                'estimate_kind' => $override['estimate_kind'],
            ];
        }

        $curve = $this->adaptiveConversions->convert($score['theta'], $score['theta_se'], $sectionType, $path);

        return [
            'scaled_score' => $curve['scaled_score'],
            'lower' => $curve['lower'],
            'upper' => $curve['upper'],
            'scaled_se' => $curve['scaled_se'],
            'conversion_set_id' => null,
            'conversion_version' => $curve['conversion_version'],
            'estimate_kind' => $curve['estimate_kind'],
        ];
    }

    private function finalizeNormal(UserTest $attempt, Test $test): void
    {
        $isRw = $attempt->attempt_type !== 'section' || $attempt->section_type === 'reading_writing';
        $isMath = $attempt->attempt_type !== 'section' || $attempt->section_type === 'math';

        $rwSection = $isRw ? $test->sections->firstWhere('type', Section::TYPE_RW) : null;
        $mathSection = $isMath ? $test->sections->firstWhere('type', Section::TYPE_MATH) : null;

        $rwScore = $this->tryScoreLinearSection($attempt, $rwSection);
        $mathScore = $this->tryScoreLinearSection($attempt, $mathSection);

        // Normal (non-adaptive) has no routing, so it never applies a path cap: always
        // the full-range curve. Passing 'standard' keeps override lookups on the
        // 'standard' rows while AdaptiveScoreConversionService maps through the full
        // 200-800 curve (it treats any non-easy path as full range).
        $rwConversion = $this->resolveAdaptiveConversion($test, $rwScore, Section::TYPE_RW, Module::DIFFICULTY_STANDARD);
        $mathConversion = $this->resolveAdaptiveConversion($test, $mathScore, Section::TYPE_MATH, Module::DIFFICULTY_STANDARD);

        $attempt->update($this->completeScoreFields($rwScore, $mathScore, $rwConversion, $mathConversion));
    }

    /**
     * Estimate one non-adaptive section's ability over all its scored (non-pretest)
     * responses. Mirrors tryScoreAdaptiveSection's defensive completion: on invalid or
     * empty IRT data it logs and returns null so the attempt still completes with that
     * section's score left null instead of crashing the job.
     *
     * @return array{theta:float,theta_se:float,raw_score:int,method:string}|null
     */
    private function tryScoreLinearSection(UserTest $attempt, ?Section $section): ?array
    {
        if (! $section) {
            return null;
        }

        $responses = $this->responsesForSection($attempt, $section);
        if ($responses->isEmpty()) {
            return null;
        }

        try {
            $ability = $this->scoring->estimateAbility($responses);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Log::warning('Normal section could not be IRT-scored at finalize; completing without a score for this section.', [
                'user_test_id' => $attempt->id,
                'section_id' => $section->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return [
            'theta' => round($ability['theta'], 3),
            'theta_se' => round($ability['se'], 3),
            'raw_score' => $responses->where('is_correct', true)->count(),
            'method' => $ability['method'],
        ];
    }

    private function completionFields(): array
    {
        return ['status' => 'completed', 'completed_at' => now(), 'current_module_id' => null];
    }

    /**
     * Score one adaptive section for finalize, completing the attempt with a null
     * score for this section instead of throwing when a routed module 2 branch or a
     * response record is missing (mirrors finalizeNormal's defensive completion).
     */
    private function tryScoreAdaptiveSection(UserTest $attempt, ?Section $section, ?string $path): ?array
    {
        if (! $section) {
            return null;
        }

        try {
            return $this->scoreAdaptiveSection($attempt, $section, $path);
        } catch (\RuntimeException $e) {
            Log::warning('Adaptive section could not be scored at finalize; completing without a score for this section.', [
                'user_test_id' => $attempt->id,
                'section_id' => $section->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Estimate a section's ability for the non-full adaptive finalize path, completing
     * with a null ability (rather than throwing) when a question carries invalid IRT
     * params. Mirrors tryScoreAdaptiveSection's defensive completion so one bad item
     * cannot crash the scoring job and leave the polling client spinning.
     */
    private function tryEstimateAbility(UserTest $attempt, $responses): ?array
    {
        if ($responses->isEmpty()) {
            return null;
        }

        try {
            return $this->scoring->estimateAbility($responses);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Log::warning('Section ability could not be estimated at finalize; completing without a theta for this section.', [
                'user_test_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function scoreAdaptiveSection(UserTest $attempt, Section $section, ?string $path): array
    {
        $m1 = $section->modules->first(fn ($module) => (int) $module->module_number === 1);
        $m2 = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === $path);

        if (! $m1 || ! $m2) {
            throw new \RuntimeException('Adaptive section is missing the submitted route.');
        }

        return $this->scoring->scoreSection(
            $this->completeResponsesForModule($attempt, $m1),
            $this->completeResponsesForModule($attempt, $m2),
            $path
        );
    }

    private function responsesForSection(UserTest $attempt, ?Section $section)
    {
        if (! $section) {
            return collect();
        }

        return $section->modules->flatMap(fn ($module) => $this->responsesForModule($attempt, $module))
            ->filter(fn ($response) => ! $response->question?->is_pretest)->values();
    }

    /**
     * Do NOT narrow this select to drop `question_snapshot`, however tempting the
     * blob size makes it. UserTestAnswer::getQuestionAttribute() rebuilds the
     * Question from that snapshot and SHADOWS the eager-loaded relation, so the
     * snapshot — not the live `questions` row — supplies the irt_a/b/c and
     * is_pretest that SatScoringService scores with. Dropping it would silently
     * change which parameters grade a student.
     *
     * The eager load below is the fallback for legacy rows that have no snapshot.
     */
    private function responsesForModule(UserTest $attempt, Module $module)
    {
        return UserTestAnswer::where('user_test_id', $attempt->id)
            ->where('module_id', $module->id)
            ->with('question:id,irt_a,irt_b,irt_c,is_pretest')
            ->get();
    }

    private function completeResponsesForModule(UserTest $attempt, Module $module)
    {
        $responses = $this->responsesForModule($attempt, $module);
        if ($responses->count() !== $module->questions->count()) {
            throw new \RuntimeException('Submitted module does not contain a response record for every presented question.');
        }

        return $responses;
    }
}
