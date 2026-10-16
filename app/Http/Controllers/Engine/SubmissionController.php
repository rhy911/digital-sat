<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmissionController extends Controller
{
    public function __construct(private AttemptEngine $engine) {}

    public function submit(SubmitModuleRequest $request)
    {
        try {
            [$attempt, $module] = $this->engine->resolveActiveModule(
                (int) $request->input('user_test_id'),
                (int) $request->input('module_id'),
                $request->user()?->id,
                allowReceipt: true,
            );

            $result = $this->engine->submitModule(
                $attempt,
                $module,
                $request->input('answers', []),
                $request->input('question_times', []),
            );

            if (($result['status'] ?? null) === 'in_flight') {
                return response()->json([
                    'error' => 'submission_in_progress',
                    'message' => 'Submission is processing',
                ], 409)->header('Retry-After', 2);
            }

            if (in_array($result['status'] ?? null, ['ready', 'completed'], true)) {
                $data = $result['result'];

                return response()->json($data, isset($data['error']) ? 500 : 200);
            }

            return response()->json([
                'status' => 'scoring',
                'message' => 'Scoring in progress...',
            ]);
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
        }
    }

    public function checkStatus(Request $request, UserTest $userTest)
    {
        $this->authorize('view', $userTest);

        $ready = $this->engine->readyResultFor($userTest->id, (int) $request->query('module_id', 0));

        if ($ready !== null) {
            return response()->json($ready);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }
}
