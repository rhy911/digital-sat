<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Console\Command;

/**
 * Close out chained-clock attempts (assignments and exam sessions) whose time expired with nobody watching.
 *
 * This is what makes the exam clock server-authoritative: without it, an
 * attempt only ever advances while the student's browser is open, so
 * closing the tab leaves it `in_progress` forever.
 *
 * Scheduled every minute in bootstrap/app.php. Requires `php artisan schedule:run`
 * in cron — the same lazy catch-up runs on student re-entry and on the teacher
 * report, so a dead cron degrades to stale statuses rather than lost attempts.
 */
class FinalizeExpiredAssignmentAttempts extends Command
{
    protected $signature = 'assignments:finalize-expired
                            {--dry-run : List what would be submitted without touching anything}';

    protected $description = 'Auto-submit and advance chained-clock attempts (assignments and exam sessions) whose module time has run out';

    public function handle(AttemptEngine $engine): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $attemptsTouched = 0;
        $modulesSubmitted = 0;

        $engine->candidateCascadeAttemptsQuery()
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use ($engine, $dryRun, &$attemptsTouched, &$modulesSubmitted) {
                foreach ($attempts as $attempt) {
                    if ($dryRun) {
                        if ($this->isExpired($attempt, $engine)) {
                            $attemptsTouched++;
                            $this->line("attempt {$attempt->id} (module {$attempt->current_module_id}) is past its deadline");
                        }

                        continue;
                    }

                    $submitted = $engine->cascadeExpire($attempt);

                    if ($submitted > 0) {
                        $attemptsTouched++;
                        $modulesSubmitted += $submitted;
                    }
                }
            });

        $this->info($dryRun
            ? "{$attemptsTouched} attempt(s) would be finalized."
            : "Finalized {$modulesSubmitted} module(s) across {$attemptsTouched} attempt(s).");

        return self::SUCCESS;
    }

    private function isExpired(UserTest $attempt, AttemptEngine $engine): bool
    {
        $module = Module::find($attempt->current_module_id);

        return $module && (bool) $engine->deadline($attempt, $module)?->isPast();
    }
}
