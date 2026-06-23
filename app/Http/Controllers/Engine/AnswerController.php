<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\AssignmentModuleTimingService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AnswerController extends Controller
{
    use HandlesAnswers;

    public function __construct(private AssignmentModuleTimingService $assignmentTiming) {}

    public function autosave(Request $request)
    {
        $validated = $request->validate([
            'user_test_id' => 'required|exists:user_tests,id',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'present|array|max:100',
            'answers.*' => 'nullable|string|max:100',
            'elapsed_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $request) {
                [$userTest, $module] = $this->resolveSubmissionContext($validated);

                if ($userTest->assignment_id) {
                    $timing = $this->assignmentTiming->syncElapsed($userTest, $module);
                    if ($timing['expired']) {
                        return ['expired' => true, 'saved_count' => 0];
                    }
                } elseif ($request->has('elapsed_seconds')) {
                    $userTest->current_module_elapsed_seconds = (int) $request->input('elapsed_seconds');
                    $userTest->save();
                }

                return [
                    'expired' => false,
                    'saved_count' => $this->saveModuleAnswers($userTest, $module, $validated['answers']),
                ];
            });

            if ($result['expired']) {
                return response()->json([
                    'error' => 'module_expired',
                    'message' => 'Module time has expired. Saved answers will be submitted.',
                ], 409);
            }

            return response()->json([
                'status' => 'success',
                'saved_count' => $result['saved_count'],
                'message' => 'Answers autosaved.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized autosave.',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => 'module_progression_conflict',
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
