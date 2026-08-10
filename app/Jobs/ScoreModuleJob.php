<?php

namespace App\Jobs;

use App\Services\ModuleScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Async scoring for the TERMINAL module submission.
 *
 * Non-terminal modules are scored inline in the request (see
 * SubmissionController) because routing is a single theta estimate. The terminal
 * submission runs finalize() — two IRT estimates plus conversion plus a possible
 * auto-merge — so it stays off the request thread.
 *
 * The actual work lives in ModuleScoringService, shared with the inline path so
 * the two can never drift apart.
 */
class ScoreModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retries are deliberately off: a second run risks advancing the attempt
    // twice. The recovery mechanism is the UserTestModuleSubmission receipt,
    // not the retry counter.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $userTestId,
        public int $moduleId,
        public int $sectionId,
        public bool $timedOut = false,
        public ?string $lockKey = null,
        public ?string $lockOwner = null,
        public ?int $queuedAtMs = null,
    ) {
        // The worker reads `timeout` off the serialized payload, so assigning it
        // here is honoured. Keeps the whole timing set in config/scoring.php.
        $this->timeout = (int) config('scoring.job_timeout', 120);
        // Stamped in the web request, so queue_wait_ms below is a true
        // end-to-end wait rather than just the worker's own view.
        $this->queuedAtMs ??= (int) (microtime(true) * 1000);
    }

    /**
     * Score the submitted module and advance the attempt. Runs off the request
     * thread so the HTTP submit no longer holds a row lock for the duration of
     * the IRT computation; the frontend polls /submit-status until this writes
     * the cache key.
     */
    public function handle(ModuleScoringService $scoring): void
    {
        $startedAt = hrtime(true);
        $outcome = 'ok';

        try {
            $scoring->scoreAndAdvance($this->userTestId, $this->moduleId, $this->timedOut);
        } catch (\Throwable $e) {
            $outcome = 'error';
            $scoring->recordFailure($this->userTestId, $this->moduleId, $e, 'EXCEPTION in ScoreModuleJob');
        } finally {
            $this->releaseSubmitLock();

            // The two numbers that let invariant I2 in config/scoring.php be
            // re-derived after a load test: how long the job took, and how long
            // it waited before starting. Safe scalars only — no payloads.
            Log::channel('queue')->info('score_module_job', [
                'user_test_id' => $this->userTestId,
                'module_id' => $this->moduleId,
                'outcome' => $outcome,
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1e6),
                'queue_wait_ms' => $this->queuedAtMs
                    ? max(0, (int) (microtime(true) * 1000) - $this->queuedAtMs)
                    : null,
            ]);
        }
    }

    /**
     * Safety net for cases handle()'s own try/catch/finally never runs — e.g. the
     * queue worker hard-kills this job for exceeding $timeout. Without this, a
     * timed-out job would leave the polling client spinning forever and the
     * submission lock held until it expires on its own.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScoreModuleJob failed permanently', [
            'user_test_id' => $this->userTestId,
            'module_id' => $this->moduleId,
            'exception' => $exception,
        ]);

        Cache::put("scoring_result_{$this->userTestId}", [
            'status' => 'error',
            'error' => 'Server error during submission.',
            'message' => 'An unexpected server error occurred.',
            'scored_module_id' => $this->moduleId,
        ], (int) config('scoring.result_ttl', 900));

        $this->releaseSubmitLock();
    }

    /**
     * Release the submit lock, checking ownership.
     *
     * forceRelease() deletes the row for ANY owner, so if this job's lock had
     * already lapsed and been re-acquired by a newer request, the old job would
     * steal the new holder's lock on its way out. restoreLock()->release() is
     * owner-checked and no-ops in that case.
     *
     * The forceRelease fallback covers jobs already sitting in the `jobs` table
     * at deploy time, whose payloads were serialized without an owner.
     */
    private function releaseSubmitLock(): void
    {
        if (! $this->lockKey) {
            return;
        }

        if ($this->lockOwner) {
            Cache::restoreLock($this->lockKey, $this->lockOwner)->release();

            return;
        }

        Cache::lock($this->lockKey)->forceRelease();
    }
}
