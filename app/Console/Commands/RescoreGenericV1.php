<?php

namespace App\Console\Commands;

use App\Models\UserTest;
use App\Services\SatScoringPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RescoreGenericV1 extends Command
{
    protected $signature = 'sat:rescore-generic-v1 {--apply : Persist revisions after previewing them}';

    protected $description = 'Preview or apply audited replacement scores for generic_ds_v1 attempts';

    public function handle(SatScoringPipeline $pipeline): int
    {
        $runId = (string) Str::uuid();
        $rows = [];
        UserTest::where('score_conversion_version', 'generic_ds_v1')->orderBy('id')->each(function ($attempt) use ($pipeline, $runId, &$rows) {
            try {
                $revised = $pipeline->rescoreLegacy($attempt);
            } catch (\Throwable $exception) {
                $rows[] = [$attempt->id, $attempt->total_score, 'Skipped', 'preview_failed: '.$exception->getMessage()];

                return;
            }
            if (($revised['status'] ?? null) !== 'ready') {
                $rows[] = [$attempt->id, $attempt->total_score, 'Skipped', $revised['reason'] ?? 'not_eligible'];

                return;
            }
            $rows[] = [$attempt->id, $attempt->total_score, $revised['total_score'], $revised['score_conversion_version']];
            if ($this->option('apply')) {
                $applied = $pipeline->applyScoreRevision($attempt, $runId);
                if (($applied['status'] ?? null) !== 'applied') {
                    $rows[array_key_last($rows)] = [$attempt->id, $attempt->total_score, 'Skipped', $applied['reason'] ?? 'attempt_changed_before_apply'];
                }
            }
        });

        $this->table(['Attempt', 'Previous', 'Revised', 'Version/status'], $rows);
        $this->info($this->option('apply') ? 'Score revisions applied with run '.$runId : 'Dry run only. Re-run with --apply to persist.');

        return self::SUCCESS;
    }
}
