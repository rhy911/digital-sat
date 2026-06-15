<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

class AnswerController extends Controller
{
    use HandlesAnswers;

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
            $savedCount = DB::transaction(function () use ($validated, $request) {
                [$userTest, $module] = $this->resolveSubmissionContext($validated);

                if ($request->has('elapsed_seconds')) {
                    $userTest->current_module_elapsed_seconds = (int) $request->input('elapsed_seconds');
                    $userTest->save();
                }

                return $this->saveModuleAnswers($userTest, $module, $validated['answers']);
            });

            return response()->json([
                'status' => 'success',
                'saved_count' => $savedCount,
                'message' => 'Answers autosaved.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized autosave.',
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
