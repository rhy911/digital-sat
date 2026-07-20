<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Models\UserTest;
use App\Support\PerformanceAnalytics;
use Illuminate\Http\Request;

class StudentProgressController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, User $student, PerformanceAnalytics $analytics)
    {
        $this->authorize('viewStudent', [$classroom, $student]);
        $teacher = $request->user();

        $completedTests = UserTest::query()
            ->where('user_id', $student->id)
            ->where('status', 'completed')
            ->visibleToTeacher($teacher)
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'scoreConversionSet', 'userAnswers.question', 'assignment.classroom'])
            ->orderBy('completed_at', 'desc')
            ->get();

        $rosterItems = $classroom->activeMemberships()->with('student')->orderByDesc('decided_at')->get();

        return view('teacher.students.progress', array_merge(
            [
                'classroom' => $classroom,
                'student' => $student,
                'completedTests' => $completedTests,
                'rosterItems' => $rosterItems,
            ],
            $analytics->summarize($completedTests)
        ));
    }
}
