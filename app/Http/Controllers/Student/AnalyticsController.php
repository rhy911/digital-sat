<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('student.analytics.index', $this->homeData($request));
    }

    private function homeData(Request $request): array
    {
        $user = $request->user();
        $completedTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with('test')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        $inProgressTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test.sections.modules', 'currentModule'])
            ->where('status', 'in_progress')
            ->orderBy('updated_at', 'desc')
            ->get();

        return [
            'user' => $user,
            'completedTests' => $completedTests,
            'inProgressTests' => $inProgressTests,
        ];
    }
}
