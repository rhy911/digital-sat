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
            ->with(['test.sections.modules', 'currentModule.section'])
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
        $assignmentFocus = ['open' => 0, 'overdue' => 0, 'dueSoon' => 0];

        if ($user->role === 'student' || $user->role === 'teacher') {
            $recipientScope = \App\Models\Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'))
                ->whereIn('status', ['published', 'closed']);

            $assignments = (clone $recipientScope)
                ->with(['classroom', 'test', 'attempts' => fn ($query) => $query->where('user_id', $user->id)])
                ->latest('published_at')
                ->limit(3)
                ->get();

            // Counted separately from the list above, which is capped at 3 and
            // includes completed work — deriving a headline count from it lies.
            // "Open" means still finishable: published, already available, not
            // completed, and either in a live class or holding a resumable
            // attempt (archiving blocks new starts but preserves resume).
            $openDueDates = (clone $recipientScope)
                ->where('status', 'published')
                ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
                ->whereDoesntHave('attempts', fn ($query) => $query->where('user_id', $user->id)->where('status', 'completed'))
                ->where(fn ($query) => $query
                    ->whereHas('classroom', fn ($classroom) => $classroom->where('status', 'active'))
                    ->orWhereHas('attempts', fn ($attempt) => $attempt->where('user_id', $user->id)->where('status', 'in_progress')))
                ->pluck('due_at');

            $assignmentFocus = [
                'open' => $openDueDates->count(),
                'overdue' => $openDueDates->filter(fn ($due) => $due && now()->gte($due))->count(),
                'dueSoon' => $openDueDates->filter(fn ($due) => $due && now()->lt($due) && $due->lte(now()->addDays(2)))->count(),
            ];
        }

        $teacherStats = null;
        if ($user->role === 'teacher' || $user->role === 'admin') {
            $classQuery = \App\Models\Classroom::query()->when(
                $user->role !== 'admin',
                fn ($query) => $query->where(fn ($scope) => $scope->where('owner_id', $user->id)
                    ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id)))
            );

            $activeClassIds = (clone $classQuery)->where('status', 'active')->pluck('id');

            $teacherStats = [
                'activeClassesCount' => $activeClassIds->count(),
                'enrolledStudentsCount' => \App\Models\ClassroomMembership::whereIn('classroom_id', $activeClassIds)->where('status', 'active')->count(),
                'pendingRequestsCount' => \App\Models\ClassroomMembership::whereIn('classroom_id', $activeClassIds)->where('status', 'pending')->count(),
                'publishedAssignmentsCount' => \App\Models\Assignment::whereIn('classroom_id', $activeClassIds)->where('status', 'published')->count(),
                'recentClasses' => (clone $classQuery)->where('status', 'active')->withCount(['activeMemberships', 'assignments', 'memberships as pending_memberships_count' => fn ($q) => $q->where('status', 'pending')])->latest()->limit(4)->get(),
                'recentAssignments' => \App\Models\Assignment::whereIn('classroom_id', $activeClassIds)->with(['classroom', 'test'])->withCount(['recipients', 'attempts'])->latest()->limit(5)->get(),
            ];
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
            'assignmentFocus' => $assignmentFocus,
            'teacherStats' => $teacherStats,
        ];
    }
}
