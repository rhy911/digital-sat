<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use App\Http\Controllers\Engine\Concerns\ResolvesRouting;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\UserTest;
use App\Services\AssignmentModuleTimingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    use HandlesAnswers, ResolvesRouting;

    public function __construct(private AssignmentModuleTimingService $assignmentTiming) {}

    public function submit(SubmitModuleRequest $request)
    {
        try {
            Log::info("submitModule called via SubmissionController", [
                'user_test_id' => $request->input('user_test_id'),
                'module_id' => $request->input('module_id')
            ]);

            $validated = $request->validated();

            $result = DB::transaction(function () use ($validated) {
                [$userTest, $module] = $this->resolveSubmissionContext($validated);
                $section = $module->section;
                $timedOut = false;

                if ($userTest->assignment_id) {
                    if ((int) $userTest->current_module_id !== (int) $module->id) {
                        throw new AuthorizationException('This module is not active for the assignment attempt.');
                    }

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
                if (!$timedOut) {
                    $this->saveModuleAnswers($userTest, $module, $validated['answers']);
                }

                // 2. Logic for Routing or Finalizing - Run synchronously
                \App\Jobs\ScoreModuleJob::dispatchSync($userTest->id, $module->id, $section->id);

                // 3. Return the synchronous result from cache immediately
                $cacheKey = "scoring_result_{$userTest->id}";
                if (Cache::has($cacheKey)) {
                    $result = Cache::get($cacheKey);
                    Cache::forget($cacheKey); // Clean up
                    return $result + ['timed_out' => $timedOut];
                }

                return [
                    'error' => 'Scoring failed',
                    'message' => 'Unable to determine test routing.',
                    'timed_out' => $timedOut,
                ];
            });

            return response()->json($result, isset($result['error']) ? 500 : 200);

        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized submission.',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("EXCEPTION in SubmissionController@submit", ['exception' => $e]);
            return response()->json([
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ], 500);
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
