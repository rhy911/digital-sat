<?php

namespace App\Services;

use App\Models\Module;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Score a submitted module, advance the attempt, and record the outcome.
 *
 * Extracted from ScoreModuleJob so the HTTP request can run it inline for
 * non-terminal modules while the terminal one stays on the queue. Both paths MUST
 * go through here: ModuleProgressionSecurityTest asserts that resubmitting a
 * module returns byte-identical JSON from the receipt, which only holds if the
 * inline path writes that receipt exactly the way the job does.
 */
class ModuleScoringService
{
    public function __construct(
        private TestProgressionService $progression,
        private AttemptProgressionService $attemptProgression,
    ) {}

    /**
     * Cache key marking "a submission for this attempt+module is being processed".
     *
     * Autosave has to know about it: the submit lock is held for the whole
     * submission, but the attempt only leaves the module inside advanceAndRecord()
     * below. In the window between submit committing its answers and that advance,
     * a straggler autosave still passes every check in resolveSubmissionContext()
     * and overwrites the just-submitted answers with the values it collected
     * before the student's last edit — silent mis-grading. AnswerController skips
     * writing while this key exists.
     */
    public static function submitMarkerKey(int $userTestId, int $moduleId): string
    {
        return "module_submit_active_{$userTestId}_{$moduleId}";
    }

    /**
     * @param  ?CarbonInterface  $startNextAt  When the next module's clock should start. Only
     *                                the timeout sweeper passes it, so a late
     *                                cascade reproduces the on-time boundaries.
     * @return array<string, mixed> the result payload, also written to the cache
     */
    public function scoreAndAdvance(int $userTestId, int $moduleId, bool $timedOut, ?CarbonInterface $startNextAt = null): array
    {
        $cacheKey = "scoring_result_{$userTestId}";
        $resultTtl = (int) config('scoring.result_ttl', 900);

        $userTest = UserTest::findOrFail($userTestId);
        $module = Module::findOrFail($moduleId);

        $result = $this->progression->submit($userTest, $module);
        $result['timed_out'] = $timedOut;
        // The cache key is per-attempt, so every result must say which module it
        // belongs to; checkStatus() uses this to avoid handing a client waiting on
        // module A the result for module B. Set before advanceAndRecord() so it
        // lands in the receipt as well as the cache.
        $result['scored_module_id'] = $moduleId;

        if (! isset($result['error'])) {
            $result = $this->advanceAndRecord($userTestId, $moduleId, $result, $startNextAt);
        }

        Cache::put($cacheKey, $result, $resultTtl);

        // Both the inline path and the job go through here, so this is the one
        // place the marker can be dropped for either. Safe now: advanceAndRecord()
        // has committed, so any later autosave for this module is rejected by the
        // current_module_id check in resolveSubmissionContext() instead.
        Cache::forget(self::submitMarkerKey($userTestId, $moduleId));

        return $result;
    }

    /**
     * Persist the routing decision and a submission receipt. If a duplicate
     * request raced this one and already recorded a receipt for this module, fall
     * back to that receipt's result instead of erroring.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function advanceAndRecord(int $userTestId, int $moduleId, array $result, ?CarbonInterface $startNextAt = null): array
    {
        return DB::transaction(function () use ($userTestId, $moduleId, $result, $startNextAt) {
            $existing = UserTestModuleSubmission::where('user_test_id', $userTestId)
                ->where('module_id', $moduleId)
                ->first();

            if ($existing) {
                return $existing->result;
            }

            $userTest = UserTest::where('id', $userTestId)->lockForUpdate()->first();

            if (! $userTest) {
                // Section-attempt auto-merge (TestProgressionService::autoMergeIfEligible)
                // can delete this attempt during finalize when it is the second half of a
                // completed pair. The result already points at the surviving merged
                // attempt, so just return it.
                return $result;
            }

            $nextModule = $this->attemptProgression->advance($userTest, $result, $startNextAt);

            try {
                UserTestModuleSubmission::create([
                    'user_test_id' => $userTestId,
                    'module_id' => $moduleId,
                    'issued_next_module_id' => $nextModule?->id,
                    'result' => $result,
                    'submitted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return UserTestModuleSubmission::where('user_test_id', $userTestId)
                    ->where('module_id', $moduleId)
                    ->first()
                    ->result;
            }

            return $result;
        });
    }

    /**
     * Cache the generic failure payload. Shared so the inline path, the job's
     * catch block and its failed() hook all report the same shape.
     *
     * @return array<string, mixed>
     */
    public function recordFailure(int $userTestId, int $moduleId, \Throwable $e, string $context): array
    {
        Log::error($context, [
            'user_test_id' => $userTestId,
            'module_id' => $moduleId,
            'exception' => $e,
        ]);

        $payload = [
            'status' => 'error',
            'error' => 'Server error during submission.',
            'message' => 'An unexpected server error occurred.',
            'scored_module_id' => $moduleId,
        ];

        Cache::put("scoring_result_{$userTestId}", $payload, (int) config('scoring.result_ttl', 900));

        return $payload;
    }
}
