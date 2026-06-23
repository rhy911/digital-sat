<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use Illuminate\Support\Facades\DB;

class ScoreRevisionService
{
    public function __construct(
        private DefaultScoreConversionService $normal,
        private SatScoringService $irt,
        private AdaptiveScoreConversionService $adaptive,
    ) {}

    public function preview(UserTest $attempt): ?array
    {
        if ($attempt->status !== 'completed' || $attempt->score_conversion_set_id !== null
            || $attempt->score_conversion_version !== 'generic_ds_v1') {
            return null;
        }
        $attempt->loadMissing(['test', 'userAnswers.question']);
        $rw = $attempt->userAnswers->filter(fn ($answer) => $answer->question?->section_type === Section::TYPE_RW)->values();
        $math = $attempt->userAnswers->filter(fn ($answer) => $answer->question?->section_type === Section::TYPE_MATH)->values();

        if ($attempt->test->test_type === Test::TYPE_FULL) {
            $rwScore = $this->normal->convert(Section::TYPE_RW, $rw->where('is_correct', true)->count(), $rw->count());
            $mathScore = $this->normal->convert(Section::TYPE_MATH, $math->where('is_correct', true)->count(), $math->count());

            return [
                'score_reading_writing' => $rwScore['scaled_score'], 'score_reading_writing_lower' => null, 'score_reading_writing_upper' => null,
                'score_math' => $mathScore['scaled_score'], 'score_math_lower' => null, 'score_math_upper' => null,
                'total_score' => $rwScore['scaled_score'] + $mathScore['scaled_score'], 'total_score_lower' => null, 'total_score_upper' => null,
                'score_conversion_version' => $rwScore['conversion_version'], 'score_estimate_kind' => $rwScore['estimate_kind'],
                'scoring_method' => 'raw_table_v1', 'rw_theta' => null, 'math_theta' => null,
                'rw_theta_se' => null, 'math_theta_se' => null,
            ];
        }

        if ($attempt->test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            return null;
        }
        $rwAbility = $this->irt->estimateAbility($rw->filter(fn ($answer) => ! $answer->question?->is_pretest)->values());
        $mathAbility = $this->irt->estimateAbility($math->filter(fn ($answer) => ! $answer->question?->is_pretest)->values());
        $rwScore = $this->adaptive->convert($rwAbility['theta'], $rwAbility['se']);
        $mathScore = $this->adaptive->convert($mathAbility['theta'], $mathAbility['se']);
        $total = $this->adaptive->totalRange($rwScore, $mathScore);

        return [
            'score_reading_writing' => $rwScore['scaled_score'], 'score_reading_writing_lower' => $rwScore['lower'], 'score_reading_writing_upper' => $rwScore['upper'],
            'score_math' => $mathScore['scaled_score'], 'score_math_lower' => $mathScore['lower'], 'score_math_upper' => $mathScore['upper'],
            'total_score' => $total['score'], 'total_score_lower' => $total['lower'], 'total_score_upper' => $total['upper'],
            'score_conversion_version' => $rwScore['conversion_version'], 'score_estimate_kind' => $rwScore['estimate_kind'],
            'scoring_method' => $rwAbility['method'], 'rw_theta' => round($rwAbility['theta'], 3), 'math_theta' => round($mathAbility['theta'], 3),
            'rw_theta_se' => round($rwAbility['se'], 3), 'math_theta_se' => round($mathAbility['se'], 3),
        ];
    }

    public function apply(UserTest $attempt, array $revised, string $runId): void
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
}
