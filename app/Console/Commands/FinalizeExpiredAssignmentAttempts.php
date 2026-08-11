<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\UserTest;
use App\Services\AssignmentAttemptTimeoutService;
use App\Services\AssignmentModuleTimingService;
use Illuminate\Console\Command;

/**
 * Close out assignment attempts whose time expired with nobody watching.
 *
 * This is what makes the exam clock server-authoritative: without it, an
 * assignment attempt only ever advances while the student's browser is open, so
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

    protected $description = 'Auto-submit and advance assignment attempts whose module time has run out';

    private AssignmentModuleTimingService $timing;

    public function handle(AssignmentAttemptTimeoutService $timeouts, AssignmentModuleTimingService $timing): int
    {
        $this->timing = $timing;

        $dryRun = (bool) $this->option('dry-run');
        $attemptsTouched = 0;
        $modulesSubmitted = 0;

        $timeouts->candidateAttemptsQuery()
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use ($timeouts, $dryRun, &$attemptsTouched, &$modulesSubmitted) {
                foreach ($attempts as $attempt) {
                    if ($dryRun) {
                        if ($this->isExpired($attempt)) {
                            $attemptsTouched++;
                            $this->line("attempt {$attempt->id} (module {$attempt->current_module_id}) is past its deadline");
                        }

                        continue;
                    }

                    $submitted = $timeouts->finalizeExpired($attempt);

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

    private function isExpired(UserTest $attempt): bool
    {
        $module = Module::find($attempt->current_module_id);

        return $module && $this->timing->timing($attempt, $module)['expired'];
    }
}
