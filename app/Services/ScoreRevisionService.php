<?php

namespace App\Services;

use App\Models\UserTest;
use Illuminate\Support\Facades\DB;

class ScoreRevisionService
{
    public function __construct(
        private SatScoringPipeline $pipeline,
    ) {}

    public function preview(UserTest $attempt): ?array
    {
        if ($attempt->status !== 'completed' || $attempt->score_conversion_set_id !== null
            || $attempt->score_conversion_version !== 'generic_ds_v1') {
            return null;
        }

        return $this->pipeline->rescoreLegacy($attempt);
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
