<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use App\Services\AttemptProgressionService;
use App\Services\TestProgressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScoreModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $userTestId,
        public int $moduleId,
        public int $sectionId,
        public bool $timedOut = false,
        public ?string $lockKey = null,
    ) {}

    /**
     * Score the submitted module and advance the attempt. Runs off the request
     * thread so the HTTP submit no longer holds a row lock for the duration of
     * the IRT computation; the frontend polls /submit-status until this writes
     * the cache key below.
     */
    public function handle(TestProgressionService $progression, AttemptProgressionService $attemptProgression): void
    {
        $cacheKey = "scoring_result_{$this->userTestId}";

        try {
            $userTest = UserTest::findOrFail($this->userTestId);
            $module = Module::findOrFail($this->moduleId);

            $result = $progression->submit($userTest, $module);
            $result['timed_out'] = $this->timedOut;

            if (! isset($result['error'])) {
                $result = $this->advanceAndRecord($attemptProgression, $result);
            }

            Cache::put($cacheKey, $result, 300);
        } catch (\Throwable $e) {
            Log::error('EXCEPTION in ScoreModuleJob', [
                'user_test_id' => $this->userTestId,
                'module_id' => $this->moduleId,
                'exception' => $e,
            ]);
            Cache::put($cacheKey, [
                'status' => 'error',
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.',
            ], 300);
        } finally {
            if ($this->lockKey) {
                Cache::lock($this->lockKey)->forceRelease();
            }
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
        ], 300);

        if ($this->lockKey) {
            Cache::lock($this->lockKey)->forceRelease();
        }
    }

    /**
     * Persist the routing decision and a submission receipt. If a duplicate
     * request raced this job and already recorded a receipt for this module,
     * fall back to that receipt's result instead of erroring.
     */
    private function advanceAndRecord(AttemptProgressionService $attemptProgression, array $result): array
    {
        return DB::transaction(function () use ($attemptProgression, $result) {
            $existing = UserTestModuleSubmission::where('user_test_id', $this->userTestId)
                ->where('module_id', $this->moduleId)
                ->first();
            if ($existing) {
                return $existing->result;
            }

            $userTest = UserTest::where('id', $this->userTestId)->lockForUpdate()->first();
            if (! $userTest) {
                // Section-attempt auto-merge (TestProgressionService::autoMergeIfEligible) can
                // delete this attempt during finalize when it's the second half of a completed
                // pair. The result already points at the surviving merged attempt, so just cache it.
                return $result;
            }

            $nextModule = $attemptProgression->advance($userTest, $result);

            try {
                UserTestModuleSubmission::create([
                    'user_test_id' => $this->userTestId,
                    'module_id' => $this->moduleId,
                    'issued_next_module_id' => $nextModule?->id,
                    'result' => $result,
                    'submitted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return UserTestModuleSubmission::where('user_test_id', $this->userTestId)
                    ->where('module_id', $this->moduleId)
                    ->first()
                    ->result;
            }

            return $result;
        });
    }
}
