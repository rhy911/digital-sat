<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestAnswerReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserTestAnswerReviewController extends Controller
{
    public function update(Request $request, UserTest $userTest, UserTestAnswer $answer): JsonResponse
    {
        abort_unless(Schema::hasTable('user_test_answer_reviews'), 503, 'Answer review storage is not ready. Run database migrations.');
        $this->authorize('view', $userTest);
        abort_unless((int) $answer->user_test_id === (int) $userTest->id, 404);
        abort_if((bool) $answer->is_correct, 422, 'Only missed or omitted answers can be classified.');

        $validated = $request->validate([
            'error_type' => ['nullable', 'string', Rule::in(UserTestAnswerReview::ERROR_TYPES)],
        ]);

        $actor = $request->user();
        $review = $answer->review()->firstOrNew();

        if ((int) $actor->id === (int) $userTest->user_id) {
            $review->student_error_type = $validated['error_type'] ?? null;
        } else {
            abort_unless(in_array($actor->role, ['teacher', 'admin'], true), 403);
            $review->teacher_error_type = $validated['error_type'] ?? null;
            $review->teacher_id = $validated['error_type'] ? $actor->id : null;
        }

        $review->user_test_answer_id = $answer->id;
        $review->save();

        return response()->json([
            'effective_error_type' => $review->effectiveErrorType($answer),
            'student_error_type' => $review->student_error_type,
            'teacher_error_type' => $review->teacher_error_type,
        ]);
    }
}
