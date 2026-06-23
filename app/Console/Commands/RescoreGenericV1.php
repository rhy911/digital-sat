<?php

namespace App\Console\Commands;

use App\Models\UserTest;
use App\Services\ScoreRevisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RescoreGenericV1 extends Command
{
    protected $signature = 'sat:rescore-generic-v1 {--apply : Persist revisions after previewing them}';

    protected $description = 'Preview or apply audited replacement scores for generic_ds_v1 attempts';

    public function handle(ScoreRevisionService $revisions): int
    {
        $runId = (string) Str::uuid();
        $rows = [];
        UserTest::where('score_conversion_version', 'generic_ds_v1')->orderBy('id')->each(function ($attempt) use ($revisions, $runId, &$rows) {
            try {
                $revised = $revisions->preview($attempt);
            } catch (\RuntimeException $exception) {
                $rows[] = [$attempt->id, $attempt->total_score, 'Skipped', $exception->getMessage()];

                return;
            }
            if (! $revised) {
                return;
            }
            $rows[] = [$attempt->id, $attempt->total_score, $revised['total_score'], $revised['score_conversion_version']];
            if ($this->option('apply')) {
                $revisions->apply($attempt, $revised, $runId);
            }
        });

        $this->table(['Attempt', 'Previous', 'Revised', 'Version/status'], $rows);
        $this->info($this->option('apply') ? 'Score revisions applied with run '.$runId : 'Dry run only. Re-run with --apply to persist.');

        return self::SUCCESS;
    }
}
