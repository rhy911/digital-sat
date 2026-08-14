<?php

namespace App\Services;

use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use App\Models\Module;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finish chained-clock attempts (see UserTest::runsOnChainedClock — classroom
 * assignments and standalone exam-session attempts) whose module time ran out
 * while nobody was watching.
 *
 * Both are fixed-time exam simulators, so the clock does not care whether the
 * browser is open. Auto-submit used to live only in the client
 * (`test-timer-expired` in resources/js/test/navigation.js), which meant closing
 * the tab froze the attempt in `in_progress` forever: the student had to come
 * back, open the module, and let the timer expire again for EACH remaining module.
 *
 * This cascades instead — submit the expired module, advance, and repeat while the
 * next module is already past its own deadline too, until the attempt finalizes.
 *
 * Every module goes through the same submit path a real student takes
 * (saveModuleAnswers + ModuleScoringService), so receipts, routing and scoring are
 * identical to a live timed-out submission. It also takes the SAME submit lock as
 * SubmissionController, so a student who reconnects mid-cascade can never be
 * double-submitted; whoever gets there first wins and the other side backs off.
 */
class AssignmentAttemptTimeoutService
{
    use HandlesAnswers;

    /**
     * Cascade guard. A full-length SAT is 4 modules; anything past this means the
     * attempt is not converging and must not be looped on.
     */
    private const MAX_CASCADE_STEPS = 12;

    public function __construct(
        private AssignmentModuleTimingService $timing,
        private ModuleScoringService $scoring,
    ) {}

    /**
     * Candidate attempts for a sweep: every running assignment attempt whose clock
     * has started.
     *
     * Deliberately NOT pre-filtered by elapsed time in SQL. Module durations vary
     * per module, so any single cutoff either misses short modules or delays them;
     * exact expiry is decided per attempt by AssignmentModuleTimingService. The set
     * is naturally small — only attempts a student has actually opened and not
     * finished.
     *
     * @return \Illuminate\Database\Eloquent\Builder<UserTest>
     */
    public function candidateAttemptsQuery()
    {
        return UserTest::query()
            ->where(fn ($q) => $q->whereNotNull('assignment_id')->orWhereNotNull('exam_session_id'))
            ->where('status', 'in_progress')
            ->whereNotNull('current_module_id')
            ->whereNotNull('current_module_started_at');
    }

    /**
     * Submit and advance every module of this attempt whose time has already run
     * out, in order, until one is still live or the attempt finishes.
     *
     * @return int modules submitted
     */
    public function finalizeExpired(UserTest $attempt): int
    {
        if (! $attempt->runsOnChainedClock() || $attempt->status !== 'in_progress') {
            return 0;
        }

        $submitted = 0;

        for ($step = 0; $step < self::MAX_CASCADE_STEPS; $step++) {
            $attempt->refresh();

            if ($attempt->status !== 'in_progress' || ! $attempt->current_module_id) {
                break;
            }

            $module = Module::find($attempt->current_module_id);
            if (! $module) {
                break;
            }

            $deadline = $this->timing->deadline($attempt, $module);
            // No clock yet: the student was issued this module but never opened it
            // and the chained clock does not apply (module 1 of a fresh attempt).
            // Nothing has expired, so there is nothing to submit.
            if (! $deadline || $deadline->isFuture()) {
                break;
            }

            if (! $this->submitExpiredModule($attempt, $module, $deadline)) {
                break;
            }

            $submitted++;
        }

        return $submitted;
    }

    /**
     * @return bool whether the cascade may continue to the next module
     */
    private function submitExpiredModule(UserTest $attempt, Module $module, CarbonInterface $deadline): bool
    {
        // Same key SubmissionController uses. Failing to take it means a live
        // submission for this exact attempt+module is in flight — the student beat
        // us to it, which is the better outcome. Back off entirely; the next sweep
        // finds whatever state that submission left behind.
        $lockKey = "module_submit_lock_{$attempt->id}_{$module->id}";
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            return false;
        }

        $markerKey = ModuleScoringService::submitMarkerKey($attempt->id, $module->id);
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        try {
            // A receipt with the attempt still parked on the same module means an
            // earlier submission scored but died before advancing. Repairing that
            // is not this service's job, and re-submitting would fight the receipt's
            // unique constraint on every sweep, so stop and make it visible.
            $receipt = UserTestModuleSubmission::where('user_test_id', $attempt->id)
                ->where('module_id', $module->id)
                ->exists();

            if ($receipt) {
                Log::channel('queue')->warning('timeout_sweep.stuck_receipt', [
                    'user_test_id' => $attempt->id,
                    'module_id' => $module->id,
                ]);

                return false;
            }

            DB::transaction(function () use ($attempt, $module) {
                $this->timing->syncElapsed($attempt, $module);
                // Keeps whatever autosave captured before the student vanished and
                // materializes every remaining question as omitted — byte-identical
                // to the timed-out branch of SubmissionController::submit().
                $this->saveModuleAnswers($attempt, $module, [], [], true);
            });

            $result = $this->scoring->scoreAndAdvance($attempt->id, $module->id, true, $deadline);

            if (isset($result['error'])) {
                Log::channel('queue')->error('timeout_sweep.scoring_failed', [
                    'user_test_id' => $attempt->id,
                    'module_id' => $module->id,
                ]);

                return false;
            }

            Log::channel('queue')->info('timeout_sweep.module_submitted', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'deadline' => $deadline->toIso8601String(),
                'late_by_seconds' => max(0, now()->getTimestamp() - $deadline->getTimestamp()),
                'test_completed' => (bool) ($result['test_completed'] ?? false),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('queue')->error('timeout_sweep.exception', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'exception' => $e,
            ]);

            return false;
        } finally {
            $lock->release();
            Cache::forget($markerKey);
        }
    }
}
