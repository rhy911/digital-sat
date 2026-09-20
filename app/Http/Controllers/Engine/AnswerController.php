<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Services\ExamAttemptEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AnswerController extends Controller
{
    public function __construct(private ExamAttemptEngine $engine) {}

    public function autosave(Request $request)
    {
        $validated = $request->validate([
            'user_test_id' => 'required|exists:user_tests,id',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'present|array|max:100',
            'answers.*' => 'nullable|string|max:100',
            'question_times' => 'nullable|array|max:100',
            'question_times.*' => 'nullable|integer|min:0',
            'elapsed_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            $result = $this->engine->autosave(
                $validated,
                $request->has('elapsed_seconds') ? (int) $request->input('elapsed_seconds') : null
            );

            if ($result['expired'] ?? false) {
                return response()->json([
                    'error' => 'module_expired',
                    'message' => 'Module time has expired. Saved answers will be submitted.',
                ], 409);
            }

            if (($result['status'] ?? null) === 'submitting') {
                return response()->json([
                    'error' => 'submission_in_progress',
                    'message' => 'Submission is processing',
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
