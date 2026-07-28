<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use App\Services\AssignmentModuleTimingService;
use Illuminate\Auth\Access\AuthorizationException;
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
    ) {}

    public function submit(SubmitModuleRequest $request)
    {
        $validated = $request->validated();
        $lockKey = "module_submit_lock_{$validated['user_test_id']}_{$validated['module_id']}";
        // TTL must exceed ScoreModuleJob::$timeout (60s). If the lock expired first, a
        // second submit could acquire it while the first job is still running, and that
        // job's forceRelease() in finally/failed() would then release a lock it no
        // longer owns.
        $lock = Cache::lock($lockKey, 90);

        if (! $lock->get()) {
            return response()->json([
                'error' => 'module_progression_conflict',
                'message' => 'This module is already being submitted.',
            ], 409);
        }

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

                    throw new ConflictHttpException('This submission is stale because the attempt has already progressed.');
                }

                [$userTest, $module] = $this->resolveSubmissionContext($validated, $userTest);
                $section = $module->section;
                $timedOut = false;

                if ($userTest->assignment_id) {
                    $timedOut = $this->assignmentTiming->syncElapsed($userTest, $module)['expired'];
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
                ];
            });

            if ($outcome['done']) {
                $result = $outcome['result'];

                return response()->json($result, isset($result['error']) ? 500 : 200);
            }

            // The scoring cache key is per-attempt, not per-module, and lives for 300s.
            // Drop the previous module's result before dispatching so a student who
            // submits the next module inside that window can't be served the stale
            // routing decision (and sent back into a module they already finished).
            // Placed after the receipt branch above so a duplicate submit never clears
            // a live result, and before dispatch() so the sync-queue read below still
            // sees this job's own fresh write.
            $cacheKey = "scoring_result_{$outcome['user_test_id']}";
            Cache::forget($cacheKey);

            // Scoring (IRT compute) runs off the request thread so the row lock above
            // isn't held for the duration. The job releases $lockKey when it finishes;
            // the frontend polls /submit-status until the cache key below appears.
            \App\Jobs\ScoreModuleJob::dispatch(
                $outcome['user_test_id'],
                $outcome['module_id'],
                $outcome['section_id'],
                $outcome['timed_out'],
                $lockKey,
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
                $lock->release();
            }
        }
    }

    public function checkStatus(UserTest $userTest)
    {
        $this->authorize('view', $userTest);
        $cacheKey = "scoring_result_{$userTest->id}";

        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
            return response()->json($result);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }
}
