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

class SatScoringPipeline
{
    public function __construct(
        private SatScoringService $irt,
        private ScoreConversionService $conversions,
        private AdaptiveScoreConversionService $curves,
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
     * Rescore a legacy generic_ds_v1 attempt:
     * - Normal full length attempts receive the audited consensus table score (normal_consensus_v1).
     * - Adaptive full length attempts receive the canonical IRT curve score.
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
    public function rescoreLegacy(UserTest $attempt): ?array
    {
        if ($attempt->status !== 'completed' || $attempt->score_conversion_set_id !== null
            || $attempt->score_conversion_version !== 'generic_ds_v1') {
            return null;
        }

        $attempt->loadMissing(['test', 'userAnswers.question']);
        $test = $attempt->test;
        if (! $test) {
            return null;
        }

        $rw = $attempt->userAnswers->filter(fn ($a) => $a->question?->section_type === Section::TYPE_RW)->values();
        $math = $attempt->userAnswers->filter(fn ($a) => $a->question?->section_type === Section::TYPE_MATH)->values();

        if ($test->test_type === Test::TYPE_FULL) {
            $rwScore = $this->conversions->convertDefault(Section::TYPE_RW, $rw->where('is_correct', true)->count(), $rw->count());
            $mathScore = $this->conversions->convertDefault(Section::TYPE_MATH, $math->where('is_correct', true)->count(), $math->count());

            return [
                'score_reading_writing' => $rwScore['scaled_score'],
                'score_reading_writing_lower' => null,
                'score_reading_writing_upper' => null,
                'score_math' => $mathScore['scaled_score'],
                'score_math_lower' => null,
                'score_math_upper' => null,
                'rw_theta' => null,
                'math_theta' => null,
                'rw_theta_se' => null,
                'math_theta_se' => null,
                'scoring_method' => 'raw_table_v1',
                'total_score' => $rwScore['scaled_score'] + $mathScore['scaled_score'],
                'total_score_lower' => null,
                'total_score_upper' => null,
                'score_conversion_set_id' => null,
                'score_conversion_version' => $rwScore['conversion_version'],
                'score_estimate_kind' => $rwScore['estimate_kind'],
            ];
        }

        if ($test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            return null;
        }

        $rwScored = $rw->filter(fn ($a) => ! $a->question?->is_pretest)->values();
        $mathScored = $math->filter(fn ($a) => ! $a->question?->is_pretest)->values();

        $rwAbility = $this->irt->estimateAbility($rwScored);
        $mathAbility = $this->irt->estimateAbility($mathScored);

        $rwScore = $this->curves->convert($rwAbility['theta'], $rwAbility['se'], Section::TYPE_RW, $attempt->rw_m2_path);
        $mathScore = $this->curves->convert($mathAbility['theta'], $mathAbility['se'], Section::TYPE_MATH, $attempt->math_m2_path);
        $total = $this->curves->totalRange($rwScore, $mathScore);

        return [
            'score_reading_writing' => $rwScore['scaled_score'],
            'score_reading_writing_lower' => $rwScore['lower'],
            'score_reading_writing_upper' => $rwScore['upper'],
            'score_math' => $mathScore['scaled_score'],
            'score_math_lower' => $mathScore['lower'],
            'score_math_upper' => $mathScore['upper'],
            'rw_theta' => round($rwAbility['theta'], 3),
            'math_theta' => round($mathAbility['theta'], 3),
            'rw_theta_se' => round($rwAbility['se'], 3),
            'math_theta_se' => round($mathAbility['se'], 3),
            'scoring_method' => $rwAbility['method'],
            'total_score' => $total['score'],
            'total_score_lower' => $total['lower'],
            'total_score_upper' => $total['upper'],
            'score_conversion_set_id' => null,
            'score_conversion_version' => $rwScore['conversion_version'],
            'score_estimate_kind' => $rwScore['estimate_kind'],
        ];
    }

    public function applyScoreRevision(UserTest $attempt, array $revised, string $runId): void
    {
        $previous = $attempt->only(array_keys($revised));
        DB::transaction(function () use ($attempt, $previous, $revised, $runId) {
            $attempt->scoreRevisions()->create([
                'run_id' => $runId,
                'reason' => 'Replace route-weighted generic_ds_v1 after full-test type split.',
                'previous_score' => $previous,
                'revised_score' => $revised,
                'created_at' => now(),
            ]);
            $attempt->update($revised);
        });
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
        if ($rwAttempt->rw_theta === null || $mathAttempt->math_theta === null) {
            return ['lower' => null, 'upper' => null];
        }

        $rwConv = $this->curves->convert((float) $rwAttempt->rw_theta, (float) $rwAttempt->rw_theta_se, Section::TYPE_RW, $rwAttempt->rw_m2_path);
        $mathConv = $this->curves->convert((float) $mathAttempt->math_theta, (float) $mathAttempt->math_theta_se, Section::TYPE_MATH, $mathAttempt->math_m2_path);
        $total = $this->curves->totalRange($rwConv, $mathConv);

        $lowerMargin = $total['score'] - $total['lower'];
        $upperMargin = $total['upper'] - $total['score'];

        return [
            'lower' => $totalScore !== null ? max(400, $totalScore - $lowerMargin) : null,
            'upper' => $totalScore !== null ? min(1600, $totalScore + $upperMargin) : null,
        ];
    }

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

        $curve = $this->curves->convert($score['theta'], $score['theta_se'], $sectionType, $path);

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

    private function buildScoreFields(?array $rwScore, ?array $mathScore, ?array $rwConversion, ?array $mathConversion): array
    {
        $total = ($rwConversion && $mathConversion
            && $rwConversion['scaled_se'] !== null && $mathConversion['scaled_se'] !== null)
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
            'method' => $ability['method'],
        ];
    }

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
     */
    private function responsesForModule(UserTest $attempt, Module $module): Collection
    {
        return UserTestAnswer::where('user_test_id', $attempt->id)
            ->where('module_id', $module->id)
            ->with('question:id,irt_a,irt_b,irt_c,is_pretest')
            ->get();
    }

    private function completeResponsesForModule(UserTest $attempt, Module $module): Collection
    {
        $responses = $this->responsesForModule($attempt, $module);
        if ($responses->count() !== $module->questions->count()) {
            throw new \RuntimeException('Submitted module does not contain a response record for every presented question.');
        }

        return $responses;
    }
}
