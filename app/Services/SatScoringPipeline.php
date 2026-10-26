<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SatScoringPipeline
{
    public function __construct(
        private SatScoringService $irt,
        private ScoreConversionService $conversions,
        private AdaptiveScoreConversionService $curves,
        private TestStructureService $structure,
    ) {}

    /**
     * Score a completed attempt. Returns score fields to persist on UserTest,
     * or null for admin preview attempts.
     *
     * @return array{
     *     score_reading_writing: ?int,
     *     score_reading_writing_lower: ?int,
     *     score_reading_writing_upper: ?int,
     *     score_math: ?int,
     *     score_math_lower: ?int,
     *     score_math_upper: ?int,
     *     rw_theta: ?float,
     *     math_theta: ?float,
     *     rw_theta_se: ?float,
     *     math_theta_se: ?float,
     *     scoring_method: ?string,
     *     total_score: ?int,
     *     total_score_lower: ?int,
     *     total_score_upper: ?int,
     *     score_conversion_set_id: ?int,
     *     score_conversion_version: ?string,
     *     score_estimate_kind: ?string,
     * }|null
     */
    public function scoreAttempt(Test $test, UserTest $attempt): ?array
    {
        $attempt->loadMissing('user');
        if ($attempt->user?->role === 'admin') {
            return null;
        }

        $test->loadMissing('sections.modules.questions');

        return match ($test->test_type) {
            Test::TYPE_ADAPTIVE_FULL => $this->scoreAdaptiveFull($test, $attempt),
            Test::TYPE_FULL => $this->scoreNormalFull($test, $attempt),
            default => $this->scoreAbilityOnly($test, $attempt),
        };
    }

    /**
     * Preview an eligible generic score; unsafe history is returned as an explicit skip.
     *
     * @return array<string, mixed>
     */
    public function rescoreLegacy(UserTest $attempt): array
    {
        if ($attempt->status !== 'completed' || $attempt->score_conversion_set_id !== null
            || $attempt->score_conversion_version !== 'generic_ds_v1') {
            return ['status' => 'skipped', 'reason' => 'not_completed_or_eligible_generic_score'];
        }

        $validated = $this->validatedResponses($attempt);
        if (isset($validated['reason'])) {
            return ['status' => 'skipped', 'reason' => $validated['reason']];
        }

        return ['status' => 'ready'] + $this->scoreHistoricalResponses($validated);
    }

    /**
     * Read-only EAP-vs-MLE comparison for complete attempts with snapshotted items.
     *
     * @return array<string, mixed>
     */
    public function candidateComparison(UserTest $attempt): array
    {
        $validated = $this->validatedResponses($attempt);
        if (isset($validated['reason'])) {
            return ['status' => 'skipped', 'reason' => $validated['reason']];
        }

        $sections = [];
        foreach ([Section::TYPE_RW, Section::TYPE_MATH] as $sectionType) {
            $responses = $validated['scored'][$sectionType];
            $path = $validated['paths'][$sectionType];
            $eap = $this->irt->estimateAbility($responses);
            $mle = $this->irt->estimateAbilityMle($responses);
            $raw = $responses->where('is_correct', true)->count();
            $count = $responses->count();
            $eapScore = $this->curves->convert($eap['theta'], $eap['se'], $sectionType, $path, $raw, $count);
            $mleScore = $this->curves->convert($mle['theta'], $mle['se'], $sectionType, $path, $raw, $count);
            $sections[$sectionType] = ['eap' => $eapScore, 'mle' => $mleScore];
        }

        $eapTotal = $this->curves->totalRange($sections[Section::TYPE_RW]['eap'], $sections[Section::TYPE_MATH]['eap'])['score'];
        $mleTotal = $this->curves->totalRange($sections[Section::TYPE_RW]['mle'], $sections[Section::TYPE_MATH]['mle'])['score'];

        return [
            'status' => 'compared',
            'current_total' => $attempt->total_score,
            'eap_curve_v2_total' => $eapTotal,
            'mle_candidate_v2_total' => $mleTotal,
            'mle_delta' => $attempt->total_score === null ? null : $mleTotal - (int) $attempt->total_score,
            'rw_eap' => $sections[Section::TYPE_RW]['eap']['scaled_score'],
            'rw_mle' => $sections[Section::TYPE_RW]['mle']['scaled_score'],
            'math_eap' => $sections[Section::TYPE_MATH]['eap']['scaled_score'],
            'math_mle' => $sections[Section::TYPE_MATH]['mle']['scaled_score'],
        ];
    }

    /**
     * Revalidate and recompute while holding the attempt row lock before writing a revision.
     *
     * @return array<string, mixed>|null
     */
    public function applyScoreRevision(UserTest $attempt, string $runId): ?array
    {
        return DB::transaction(function () use ($attempt, $runId) {
            $locked = UserTest::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $preview = $this->rescoreLegacy($locked);
            if (($preview['status'] ?? null) !== 'ready') {
                return $preview;
            }

            $revised = collect($preview)->except('status')->all();
            $previous = $locked->only(array_keys($revised));
            $locked->scoreRevisions()->create([
                'run_id' => $runId,
                'reason' => 'Replace route-weighted generic_ds_v1 after full-test type split.',
                'previous_score' => $previous,
                'revised_score' => $revised,
                'created_at' => now(),
            ]);
            $locked->update($revised);

            return ['status' => 'applied'] + $revised;
        });
    }

    /**
     * @return array{reason?:string, test?:Test, answers?:Collection<int, UserTestAnswer>, scored?:array<string, Collection<int, UserTestAnswer>>, paths?:array<string, string>}
     */
    private function validatedResponses(UserTest $attempt): array
    {
        if ($attempt->status !== 'completed') {
            return ['reason' => 'attempt_not_completed'];
        }
        if ($attempt->score_conversion_set_id !== null) {
            return ['reason' => 'approved_conversion_override'];
        }

        $attempt->loadMissing('test');
        $test = $attempt->test;
        if (! $test || ! in_array($test->test_type, [Test::TYPE_FULL, Test::TYPE_ADAPTIVE_FULL], true)) {
            return ['reason' => 'unsupported_test_type'];
        }

        try {
            $shape = $this->structure->validate($test, true);
        } catch (ValidationException) {
            return ['reason' => 'invalid_or_incomplete_form_structure'];
        }

        $paths = [Section::TYPE_RW => Module::DIFFICULTY_STANDARD, Section::TYPE_MATH => Module::DIFFICULTY_STANDARD];
        $presentedModules = collect();
        foreach ($shape['sections'] as $section) {
            $modules = $section->modules;
            if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
                $path = $section->type === Section::TYPE_RW ? $attempt->rw_m2_path : $attempt->math_m2_path;
                if (! in_array($path, [Module::DIFFICULTY_EASY, Module::DIFFICULTY_HARD], true)) {
                    return ['reason' => 'missing_or_invalid_adaptive_route'];
                }
                $paths[$section->type] = $path;
                $modules = $modules->filter(fn ($module) => (int) $module->module_number === 1
                    || ((int) $module->module_number === 2 && $module->difficulty_level === $path));
                if ($modules->where('module_number', 1)->count() !== 1 || $modules->where('module_number', 2)->count() !== 1) {
                    return ['reason' => 'missing_presented_adaptive_module'];
                }
            }
            $presentedModules = $presentedModules->concat($modules->map(fn ($module) => [$section, $module]));
        }

        $attempt->loadMissing('userAnswers');
        $answers = $attempt->userAnswers;
        $expected = [];
        $moduleSections = [];
        foreach ($presentedModules as [$section, $module]) {
            $questionIds = $module->questions->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($questionIds === []) {
                return ['reason' => 'form_contains_unavailable_questions'];
            }
            $expected[$module->id] = $questionIds;
            $moduleSections[$module->id] = $section->type;
        }

        $actual = [];
        foreach ($answers as $answer) {
            $moduleId = (int) $answer->module_id;
            $questionId = (int) $answer->question_id;
            if (! isset($expected[$moduleId])) {
                return ['reason' => 'response_for_unpresented_or_wrong_branch_module'];
            }
            $actual[$moduleId][] = $questionId;
        }
        foreach ($expected as $moduleId => $questionIds) {
            $responseIds = $actual[$moduleId] ?? [];
            if (count($responseIds) !== count(array_unique($responseIds))) {
                return ['reason' => 'duplicate_response'];
            }
            sort($responseIds);
            sort($questionIds);
            if ($responseIds !== $questionIds) {
                return ['reason' => 'incomplete_or_unexpected_module_responses'];
            }
        }

        $scored = [Section::TYPE_RW => collect(), Section::TYPE_MATH => collect()];
        foreach ($answers as $answer) {
            $snapshot = $answer->question_snapshot;
            if (! is_array($snapshot) || $snapshot === []) {
                return ['reason' => 'historical_question_snapshot_missing_or_malformed'];
            }
            $sectionType = $moduleSections[(int) $answer->module_id];
            if (($snapshot['section_type'] ?? null) !== $sectionType || ! array_key_exists('is_pretest', $snapshot)
                || ! is_bool($snapshot['is_pretest']) || empty($snapshot['question_type']) || ! isset($snapshot['stem'])) {
                return ['reason' => 'historical_question_snapshot_incomplete'];
            }
            if (! $snapshot['is_pretest']) {
                foreach (['irt_a', 'irt_b', 'irt_c'] as $parameter) {
                    if (! isset($snapshot[$parameter]) || ! is_numeric($snapshot[$parameter]) || ! is_finite((float) $snapshot[$parameter])) {
                        return ['reason' => 'historical_irt_parameters_missing_or_non_finite'];
                    }
                }
                $a = (float) $snapshot['irt_a'];
                $b = (float) $snapshot['irt_b'];
                $c = (float) $snapshot['irt_c'];
                if ($a <= 0 || $a > 4 || $b < -6 || $b > 6 || $c < 0 || $c >= 1) {
                    return ['reason' => 'historical_irt_parameters_unsupported'];
                }
                $scored[$sectionType]->push($answer);
            }
        }

        foreach ($scored as $responses) {
            if ($responses->isEmpty()) {
                return ['reason' => 'missing_scored_section_responses'];
            }
        }

        return compact('test', 'answers', 'scored', 'paths');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function scoreHistoricalResponses(array $validated): array
    {
        $test = $validated['test'];
        $rw = $validated['scored'][Section::TYPE_RW];
        $math = $validated['scored'][Section::TYPE_MATH];

        if ($test->test_type === Test::TYPE_FULL) {
            $rwScore = $this->conversions->convertDefault(Section::TYPE_RW, $rw->where('is_correct', true)->count(), $rw->count());
            $mathScore = $this->conversions->convertDefault(Section::TYPE_MATH, $math->where('is_correct', true)->count(), $math->count());

            return [
                'score_reading_writing' => $rwScore['scaled_score'], 'score_reading_writing_lower' => null, 'score_reading_writing_upper' => null,
                'score_math' => $mathScore['scaled_score'], 'score_math_lower' => null, 'score_math_upper' => null,
                'total_score' => $rwScore['scaled_score'] + $mathScore['scaled_score'], 'total_score_lower' => null, 'total_score_upper' => null,
                'score_conversion_version' => $rwScore['conversion_version'], 'score_estimate_kind' => $rwScore['estimate_kind'],
                'score_conversion_set_id' => null, 'scoring_method' => 'raw_table_v1', 'rw_theta' => null, 'math_theta' => null,
                'rw_theta_se' => null, 'math_theta_se' => null,
            ];
        }

        $rwAbility = $this->irt->estimateAbility($rw);
        $mathAbility = $this->irt->estimateAbility($math);
        $rwScore = $this->curves->convert($rwAbility['theta'], $rwAbility['se'], Section::TYPE_RW, $validated['paths'][Section::TYPE_RW], $rw->where('is_correct', true)->count(), $rw->count());
        $mathScore = $this->curves->convert($mathAbility['theta'], $mathAbility['se'], Section::TYPE_MATH, $validated['paths'][Section::TYPE_MATH], $math->where('is_correct', true)->count(), $math->count());
        $total = $this->curves->totalRange($rwScore, $mathScore);

        return [
            'score_reading_writing' => $rwScore['scaled_score'], 'score_reading_writing_lower' => $rwScore['lower'], 'score_reading_writing_upper' => $rwScore['upper'],
            'score_math' => $mathScore['scaled_score'], 'score_math_lower' => $mathScore['lower'], 'score_math_upper' => $mathScore['upper'],
            'total_score' => $total['score'], 'total_score_lower' => $total['lower'], 'total_score_upper' => $total['upper'],
            'score_conversion_version' => $rwScore['conversion_version'], 'score_estimate_kind' => $rwScore['estimate_kind'],
            'score_conversion_set_id' => null, 'scoring_method' => $rwAbility['method'], 'rw_theta' => round($rwAbility['theta'], 3), 'math_theta' => round($mathAbility['theta'], 3),
            'rw_theta_se' => round($rwAbility['se'], 3), 'math_theta_se' => round($mathAbility['se'], 3),
        ];
    }

    /**
     * Route an adaptive section's Module 2 based on Module 1 theta.
     */
    public function routeAdaptiveModule(UserTest $attempt, Section $section, Module $module): string
    {
        $responses = $this->completeResponsesForModule($attempt, $module);
        $theta = $this->irt->estimateTheta($responses);

        return $this->irt->routeModule2($theta, $section->type);
    }

    /**
     * Compute total uncertainty band bounds for combined section attempts.
     *
     * @return array{lower: ?int, upper: ?int}
     */
    public function combinedTotalRange(UserTest $rwAttempt, UserTest $mathAttempt, ?int $totalScore): array
    {
        $rwScore = $rwAttempt->score_reading_writing;
        $rwLower = $rwAttempt->score_reading_writing_lower;
        $rwUpper = $rwAttempt->score_reading_writing_upper;
        $mathScore = $mathAttempt->score_math;
        $mathLower = $mathAttempt->score_math_lower;
        $mathUpper = $mathAttempt->score_math_upper;

        if ($totalScore === null || $rwScore === null || $rwLower === null || $rwUpper === null
            || $mathScore === null || $mathLower === null || $mathUpper === null) {
            return ['lower' => null, 'upper' => null];
        }

        $lowerMargin = (int) round(sqrt((($rwScore - $rwLower) ** 2) + (($mathScore - $mathLower) ** 2)) / 10) * 10;
        $upperMargin = (int) round(sqrt((($rwUpper - $rwScore) ** 2) + (($mathUpper - $mathScore) ** 2)) / 10) * 10;

        return [
            'lower' => max(400, $totalScore - $lowerMargin),
            'upper' => min(1600, $totalScore + $upperMargin),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreAdaptiveFull(Test $test, UserTest $attempt): array
    {
        $rwSection = $this->resolveSection($test, $attempt, Section::TYPE_RW);
        $mathSection = $this->resolveSection($test, $attempt, Section::TYPE_MATH);

        $rwScore = $this->tryScoreAdaptiveSection($attempt, $rwSection, $attempt->rw_m2_path);
        $mathScore = $this->tryScoreAdaptiveSection($attempt, $mathSection, $attempt->math_m2_path);

        $rwConversion = $this->resolveConversion($test, $rwScore, Section::TYPE_RW, $attempt->rw_m2_path);
        $mathConversion = $this->resolveConversion($test, $mathScore, Section::TYPE_MATH, $attempt->math_m2_path);

        return $this->buildScoreFields($rwScore, $mathScore, $rwConversion, $mathConversion);
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreNormalFull(Test $test, UserTest $attempt): array
    {
        $rwSection = $this->resolveSection($test, $attempt, Section::TYPE_RW);
        $mathSection = $this->resolveSection($test, $attempt, Section::TYPE_MATH);

        $rwScore = $this->tryScoreLinearSection($attempt, $rwSection);
        $mathScore = $this->tryScoreLinearSection($attempt, $mathSection);

        $rwConversion = $this->resolveConversion($test, $rwScore, Section::TYPE_RW, Module::DIFFICULTY_STANDARD);
        $mathConversion = $this->resolveConversion($test, $mathScore, Section::TYPE_MATH, Module::DIFFICULTY_STANDARD);

        return $this->buildScoreFields($rwScore, $mathScore, $rwConversion, $mathConversion);
    }

    private function resolveSection(Test $test, UserTest $attempt, string $type): ?Section
    {
        return $this->attemptIncludesSection($attempt, $type)
            ? $test->sections->firstWhere('type', $type)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreAbilityOnly(Test $test, UserTest $attempt): array
    {
        $rwResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_RW));
        $mathResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_MATH));
        $rwAbility = $this->tryEstimateAbility($attempt, $rwResponses);
        $mathAbility = $this->tryEstimateAbility($attempt, $mathResponses);

        return [
            'score_reading_writing' => null,
            'score_reading_writing_lower' => null,
            'score_reading_writing_upper' => null,
            'score_math' => null,
            'score_math_lower' => null,
            'score_math_upper' => null,
            'rw_theta' => $rwAbility ? round($rwAbility['theta'], 3) : null,
            'math_theta' => $mathAbility ? round($mathAbility['theta'], 3) : null,
            'rw_theta_se' => $rwAbility ? round($rwAbility['se'], 3) : null,
            'math_theta_se' => $mathAbility ? round($mathAbility['se'], 3) : null,
            'scoring_method' => $rwAbility['method'] ?? $mathAbility['method'] ?? null,
            'total_score' => null,
            'total_score_lower' => null,
            'total_score_upper' => null,
            'score_conversion_set_id' => null,
            'score_conversion_version' => null,
            'score_estimate_kind' => null,
        ];
    }

    private function attemptIncludesSection(UserTest $attempt, string $sectionType): bool
    {
        return $attempt->attempt_type !== 'section' || $attempt->section_type === $sectionType;
    }

    /**
     * Resolve one section's scaled score. Prefers an approved override table;
     * falls back to the path-aware IRT curve.
     *
     * @param  array<string, mixed>|null  $score
     * @return array<string, mixed>|null
     */
    private function resolveConversion(Test $test, ?array $score, string $sectionType, ?string $path): ?array
    {
        if (! $score) {
            return null;
        }

        $override = $this->conversions->tryApprovedConversion(
            $test, $sectionType, $path ?? Module::DIFFICULTY_HARD, (int) $score['raw_score']
        );
        if ($override) {
            $scaledScore = $this->curves->applyObservedResponseBoundary(
                (int) $override['scaled_score'], $score['raw_score'], $score['scored_questions'], $path
            );

            return [
                'scaled_score' => $scaledScore,
                'lower' => null,
                'upper' => null,
                'conversion_set_id' => $override['conversion_set_id'],
                'conversion_version' => $override['conversion_version'],
                'estimate_kind' => $override['estimate_kind'],
            ];
        }

        $curve = $this->curves->convert(
            $score['theta'], $score['theta_se'], $sectionType, $path, $score['raw_score'], $score['scored_questions']
        );

        return [
            'scaled_score' => $curve['scaled_score'],
            'lower' => $curve['lower'],
            'upper' => $curve['upper'],
            'conversion_set_id' => null,
            'conversion_version' => $curve['conversion_version'],
            'estimate_kind' => $curve['estimate_kind'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rwScore
     * @param  array<string, mixed>|null  $mathScore
     * @param  array<string, mixed>|null  $rwConversion
     * @param  array<string, mixed>|null  $mathConversion
     * @return array<string, mixed>
     */
    private function buildScoreFields(?array $rwScore, ?array $mathScore, ?array $rwConversion, ?array $mathConversion): array
    {
        $total = ($rwConversion && $mathConversion
            && $rwConversion['lower'] !== null && $rwConversion['upper'] !== null
            && $mathConversion['lower'] !== null && $mathConversion['upper'] !== null)
            ? $this->curves->totalRange($rwConversion, $mathConversion)
            : null;
        $totalScore = ($rwConversion && $mathConversion)
            ? $rwConversion['scaled_score'] + $mathConversion['scaled_score']
            : null;

        return [
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
     * @return array<string, mixed>|null
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
     * @return array<string, mixed>
     */
    private function scoreAdaptiveSection(UserTest $attempt, Section $section, ?string $path): array
    {
        $m1 = $section->modules->first(fn ($module) => (int) $module->module_number === 1);
        $m2 = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === $path);

        if (! $m1 || ! $m2) {
            throw new \RuntimeException('Adaptive section is missing the submitted route.');
        }

        return $this->irt->scoreSection(
            $this->completeResponsesForModule($attempt, $m1),
            $this->completeResponsesForModule($attempt, $m2),
            $path
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    private function tryScoreLinearSection(UserTest $attempt, ?Section $section): ?array
    {
        if (! $section) {
            return null;
        }

        $responses = $this->responsesForSection($attempt, $section);
        $expectedScoredQuestions = $section->modules->sum(
            fn ($module) => $module->questions->where('is_pretest', false)->count()
        );
        if ($responses->isEmpty() || $responses->count() !== $expectedScoredQuestions) {
            Log::warning('Normal section response count did not match its presented scored questions.', [
                'user_test_id' => $attempt->id,
                'section_id' => $section->id,
                'responses' => $responses->count(),
                'expected' => $expectedScoredQuestions,
            ]);

            return null;
        }

        try {
            $ability = $this->irt->estimateAbility($responses);
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
            'scored_questions' => $responses->count(),
            'method' => $ability['method'],
        ];
    }

    /**
     * @param  Collection<int, UserTestAnswer>  $responses
     * @return array<string, mixed>|null
     */
    private function tryEstimateAbility(UserTest $attempt, Collection $responses): ?array
    {
        if ($responses->isEmpty()) {
            return null;
        }

        try {
            return $this->irt->estimateAbility($responses);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Log::warning('Section ability could not be estimated at finalize; completing without a theta for this section.', [
                'user_test_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return Collection<int, UserTestAnswer>
     */
    private function responsesForSection(UserTest $attempt, ?Section $section): Collection
    {
        if (! $section) {
            return collect();
        }

        return $section->modules->flatMap(fn ($module) => $this->responsesForModule($attempt, $module))
            ->filter(fn ($response) => ! (bool) $response->question?->is_pretest)->values();
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
     *
     * @return Collection<int, UserTestAnswer>
     */
    private function responsesForModule(UserTest $attempt, Module $module): Collection
    {
        return UserTestAnswer::where('user_test_id', $attempt->id)
            ->where('module_id', $module->id)
            ->with('question:id,irt_a,irt_b,irt_c,is_pretest')
            ->get();
    }

    /**
     * @return Collection<int, UserTestAnswer>
     */
    private function completeResponsesForModule(UserTest $attempt, Module $module): Collection
    {
        $responses = $this->responsesForModule($attempt, $module);
        if ($responses->count() !== $module->questions->count()) {
            throw new \RuntimeException('Submitted module does not contain a response record for every presented question.');
        }

        return $responses;
    }
}
