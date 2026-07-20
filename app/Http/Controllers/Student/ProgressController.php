<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\PerformanceAnalytics;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __invoke(Request $request, PerformanceAnalytics $analytics)
    {
        $user = $request->user();

        $completedTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'scoreConversionSet', 'userAnswers.question'])
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(15)
            ->get();

        return view('student.progress.index', array_merge(
            ['user' => $user, 'completedTests' => $completedTests],
            $analytics->summarize($completedTests)
        ));
    }
}
