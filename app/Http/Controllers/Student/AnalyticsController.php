<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ForumController;
use App\Models\BlogPost;
use App\Models\ForumThread;
use App\Models\Test;
use App\Support\PerformanceAnalytics;
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
        $attemptedTestIds = $user->userTests()->pluck('test_id');

        $baseTests = Test::visibleTo($user)
            ->where('status', 'active')
            ->where('title', '!=', 'Test Preview')
            ->with('sections.modules');

        $featuredTest = (clone $baseTests)->whereNotIn('id', $attemptedTestIds)->inRandomOrder()->first()
            ?? (clone $baseTests)->inRandomOrder()->first();

        $inProgressAttempt = $user->userTests()
            ->where('status', 'in_progress')
            ->with(['test.sections.modules'])
            ->orderBy('updated_at', 'desc')
            ->first();

        $recentCompleted = $user->userTests()
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with('userAnswers.question')
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        $tip = $recentCompleted->isNotEmpty()
            ? (new PerformanceAnalytics)->summarize($recentCompleted)['recommendations'][0]
            : 'Take your first practice test to unlock a personalized study tip here.';

        // Ledger stats in one aggregate query (was two separate queries run inside the Blade view).
        $completedStats = $user->userTests()
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->selectRaw('COUNT(*) as completed_count, MAX(score_reading_writing + score_math) as best_score')
            ->first();

        $hasClassroom = $user->classroomMemberships()->where('status', 'active')->exists();
        $primaryClassroom = $hasClassroom
            ? $user->classroomMemberships()->where('status', 'active')->with('classroom.owner')->first()?->classroom
            : null;

        $assignments = collect();
        if ($user->role === 'student' || $user->role === 'teacher') {
            $assignments = \App\Models\Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'))
                ->whereIn('status', ['published', 'closed'])
                ->with(['classroom', 'test', 'attempts' => fn ($query) => $query->where('user_id', $user->id)])
                ->latest('published_at')
                ->limit(3)
                ->get();
        }

        return [
            'user' => $user,
            'featuredTest' => $featuredTest,
            'inProgressAttempt' => $inProgressAttempt,
            'latestPosts' => BlogPost::with('teacher')->latest('published_at')->limit(3)->get(),
            'latestThreads' => ForumThread::withCount('replies')->latest()->limit(5)->get(),
            'categories' => ForumController::CATEGORIES,
            'tip' => $tip,
            'lastCompletedAttempt' => $recentCompleted->first(),
            'hasClassroom' => $hasClassroom,
            'primaryClassroom' => $primaryClassroom,
            'completedCount' => (int) ($completedStats->completed_count ?? 0),
            'bestScore' => $completedStats->best_score ? (int) $completedStats->best_score : null,
            'assignments' => $assignments,
        ];
    }
}
