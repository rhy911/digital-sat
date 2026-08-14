<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\Module;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use App\Services\AssignmentModuleTimingService;
use App\Services\ModuleScoringService;
use App\Services\TestProgressionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmissionController extends Controller
{
    use HandlesAnswers;

    public function __construct(
        private AssignmentModuleTimingService $assignmentTiming,
        private TestProgressionService $progression,
        private ModuleScoringService $scoring,
    ) {}

    public function submit(SubmitModuleRequest $request)
    {
        $validated = $request->validated();
        $lockKey = "module_submit_lock_{$validated['user_test_id']}_{$validated['module_id']}";
        // TTL must cover the worst-case queue wait PLUS the job timeout, so the lock
        // never lapses while its job is still queued — a lapsed lock lets a resubmit
        // through and dispatches a DUPLICATE job. See the I2 invariant in
        // config/scoring.php for how to re-derive this from measured job times.
        $lock = Cache::lock($lockKey, (int) config('scoring.lock_ttl'));

        if (! $lock->get()) {
            // Someone else is mid-submission for this exact attempt+module. The job
            // may have finished and simply not released the lock yet (it releases in
            // finally, after writing the cache), so check for a result before
            // answering — that saves the client a whole poll cycle.
            $ready = $this->readyResultFor(
                (int) $validated['user_test_id'],
                (int) $validated['module_id']
            );

            if ($ready !== null) {
                return response()->json($ready, isset($ready['error']) ? 500 : 200);
            }

            Log::channel('queue')->info('conflict.lock_held', [
                'user_test_id' => (int) $validated['user_test_id'],
                'requested_module_id' => (int) $validated['module_id'],
            ]);

            // NOT module_progression_conflict: nothing is wrong, the work is simply
            // still in flight. The client must keep polling /submit-status and must
            // NEVER re-POST — a re-POST after the lock lapses is what dispatched
            // duplicate jobs during the 70-student mock exam.
            return response()->json([
                'error' => 'submission_in_progress',
                'message' => 'Submission is processing',
            ], 409)->header('Retry-After', 2);
        }

        // Tells autosave to stop writing for this attempt+module. The lock alone is
        // not enough: autosave does not (and must not) contend for it, or a submit
        // arriving mid-autosave would be answered `submission_in_progress` with no
        // job behind it and the client would poll until its budget ran out. See
        // ModuleScoringService::submitMarkerKey().
        $markerKey = ModuleScoringService::submitMarkerKey(
            (int) $validated['user_test_id'],
            (int) $validated['module_id']
        );
        Cache::put($markerKey, 1, (int) config('scoring.lock_ttl'));

        $jobDispatched = false;

        try {
            Log::info("submitModule called via SubmissionController", [
                'user_test_id' => $validated['user_test_id'],
                'module_id' => $validated['module_id']
            ]);

            $outcome = DB::transaction(function () use ($validated) {
                $userTest = UserTest::where('id', $validated['user_test_id'])
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (!$userTest) {
                    throw new AuthorizationException('This test attempt is no longer available.');
                }

                $receipt = UserTestModuleSubmission::where('user_test_id', $userTest->id)
                    ->where('module_id', $validated['module_id'])
                    ->first();

                if ($receipt) {
                    $isCompletedTransition = !empty($receipt->result['test_completed'])
                        && $userTest->status === 'completed';
                    $isCurrentTransition = $receipt->issued_next_module_id
                        && (int) $receipt->issued_next_module_id === (int) $userTest->current_module_id;

                    if ($isCompletedTransition || $isCurrentTransition) {
                        return ['done' => true, 'result' => $receipt->result];
                    }

                    Log::channel('queue')->info('conflict.stale_receipt', [
                        'user_test_id' => $userTest->id,
                        'requested_module_id' => (int) $validated['module_id'],
                        'current_module_id' => (int) $userTest->current_module_id,
                        'issued_next_module_id' => (int) $receipt->issued_next_module_id,
                        'attempt_status' => $userTest->status,
                    ]);

                    throw new ConflictHttpException('This submission is stale because the attempt has already progressed.');
                }

                [$userTest, $module] = $this->resolveSubmissionContext($validated, $userTest);
                $section = $module->section;
                $timedOut = false;
                $nextStartsAt = null;

                if ($userTest->runsOnChainedClock()) {
                    $timedOut = $this->assignmentTiming->syncElapsed($userTest, $module)['expired'];
                    // On a timed-out submission the next module's clock starts at
                    // this module's deadline, not at now(). The POST can land well
                    // after the deadline — a suspended laptop wakes and syncTimer
                    // fires late, a flaky connection retries — and dating the next
                    // module from arrival time would hand back the minutes the
                    // student already lost. Untimed submissions keep now().
                    if ($timedOut) {
                        $nextStartsAt = $this->assignmentTiming->deadline($userTest, $module);
                    }
                } elseif ($userTest->current_module_started_at) {
                    $test = $module->section->test;
                    $duration = ($test && $test->title === 'Test Preview') ? 0 : ($module->duration_minutes ?? ($section->type === 'math' ? 35 : 32));
                    if ($duration > 0) {
                        $maxAllowedTime = $userTest->current_module_started_at->copy()->addMinutes($duration + 5);

                        if (now()->greaterThan($maxAllowedTime)) {
                            throw new AuthorizationException('Module submission time has expired.');
                        }
                    }
                }

                // Assignment answers arriving after the server deadline are not accepted.
                $questionTimes = $validated['question_times'] ?? [];

                if (!$timedOut) {
                    $this->saveModuleAnswers($userTest, $module, $validated['answers'], $questionTimes);
                } else {
                    // Preserve autosaved work and materialize every unanswered item as omitted.
                    $this->saveModuleAnswers($userTest, $module, [], [], true);
                }

                return [
                    'done' => false,
                    'user_test_id' => $userTest->id,
                    'module_id' => $module->id,
                    'section_id' => $section->id,
                    'timed_out' => $timedOut,
                    'next_starts_at' => $nextStartsAt,
                ];
            });

            if ($outcome['done']) {
                $result = $outcome['result'];

                return response()->json($result, isset($result['error']) ? 500 : 200);
            }

            // The scoring cache key is per-attempt, not per-module. Drop the previous
            // module's result before scoring so a student who submits the next
            // module inside the result TTL can't be served the stale routing decision
            // (and sent back into a module they already finished). Placed after the
            // receipt branch above so a duplicate submit never clears a live result,
            // and before dispatch() so the sync-queue read below still sees this
            // job's own fresh write. checkStatus() defends the same case a second
            // way, via scored_module_id.
            $cacheKey = "scoring_result_{$outcome['user_test_id']}";
            Cache::forget($cacheKey);

            // Routing after a non-terminal module is one theta estimate over ~27
            // items — cheap enough to answer in the request, which removes the queue
            // (and the cron worker's cold start) from most module transitions. Only
            // the terminal submission, which runs finalize(), stays async.
            //
            // Any doubt falls back to the queue: a thrown terminality check, a
            // thrown inline score, or the kill switch being off all take the old
            // path. This must never degrade into an error screen.
            if (config('scoring.inline_non_final')) {
                try {
                    if (! $this->progression->isTerminalSubmission(
                        UserTest::findOrFail($outcome['user_test_id']),
                        Module::findOrFail($outcome['module_id'])
                    )) {
                        $result = $this->scoring->scoreAndAdvance(
                            $outcome['user_test_id'],
                            $outcome['module_id'],
                            $outcome['timed_out'],
                            $outcome['next_starts_at'],
                        );

                        return response()->json($result, isset($result['error']) ? 500 : 200);
                    }
                } catch (\Throwable $e) {
                    Log::channel('queue')->warning('inline_scoring_fell_back_to_queue', [
                        'user_test_id' => $outcome['user_test_id'],
                        'module_id' => $outcome['module_id'],
                        'reason' => $e->getMessage(),
                    ]);
                }
            }

            // Scoring (IRT compute) runs off the request thread so the row lock above
            // isn't held for the duration. The job releases $lockKey when it finishes;
            // the frontend polls /submit-status until the cache key below appears.
            // The lock owner travels with the job so it can release the lock it
            // actually holds rather than force-deleting whatever is there.
            \App\Jobs\ScoreModuleJob::dispatch(
                $outcome['user_test_id'],
                $outcome['module_id'],
                $outcome['section_id'],
                $outcome['timed_out'],
                $lockKey,
                $lock->owner(),
                startNextAtIso: $outcome['next_starts_at']?->toIso8601String(),
            );
            $jobDispatched = true;

            // On a "sync" queue connection (local artisan tinker, tests) the job above has
            // already run and released the lock by the time dispatch() returns. Serve the
            // ready result immediately instead of making the caller poll for it needlessly.
            if (Cache::has($cacheKey)) {
                $result = Cache::get($cacheKey);

                return response()->json($result, isset($result['error']) ? 500 : 200);
            }

            return response()->json(['status' => 'scoring', 'message' => 'Scoring in progress...']);

        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized submission.',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => 'module_progression_conflict',
                'message' => $e->getMessage(),
            ], 409);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("EXCEPTION in SubmissionController@submit", ['exception' => $e]);
            return response()->json([
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ], 500);
        } finally {
            if (! $jobDispatched) {
                // Same condition as the lock: while a job owns the submission the
                // marker has to outlive this request, and the job drops it.
                $lock->release();
                Cache::forget($markerKey);
            }
        }
    }

    public function checkStatus(Request $request, UserTest $userTest)
    {
        $this->authorize('view', $userTest);

        $ready = $this->readyResultFor($userTest->id, (int) $request->query('module_id', 0));

        if ($ready !== null) {
            return response()->json($ready);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }

    /**
     * Return the finished scoring result for an attempt+module, or null if it is
     * genuinely still in progress.
     *
     * Two things this closes that a bare cache read does not:
     *
     * 1. The cache key is per-ATTEMPT. A client waiting on module A must not be
     *    handed module B's result and routed backwards into a module it already
     *    finished. `scored_module_id` is checked with isset() on purpose —
     *    entries written before this deploy do not carry the field, and demanding
     *    an unconditional match would reject every one of them and leave clients
     *    polling for the whole result TTL.
     *
     * 2. The cache is not durable. A job that committed its receipt but died
     *    before Cache::put, or whose result a racing submit forgot, would leave
     *    the client polling forever. UserTestModuleSubmission is written inside
     *    the same transaction as the advance, so it is the source of truth.
     *
     * A single Cache::get() replaces the old has()+get() pair — one fewer MySQL
     * round trip on a route that 70 students hit on a loop.
     *
     * @return array<string, mixed>|null
     */
    private function readyResultFor(int $userTestId, int $wantedModuleId): ?array
    {
        $result = Cache::get("scoring_result_{$userTestId}");

        if (is_array($result)) {
            $isForAnotherModule = $wantedModuleId > 0
                && isset($result['scored_module_id'])
                && (int) $result['scored_module_id'] !== $wantedModuleId;

            if (! $isForAnotherModule) {
                return $result;
            }
        }

        if ($wantedModuleId > 0) {
            $receipt = UserTestModuleSubmission::where('user_test_id', $userTestId)
                ->where('module_id', $wantedModuleId)
                ->first();

            if ($receipt && is_array($receipt->result)) {
                return $receipt->result;
            }
        }

        return null;
    }
}
