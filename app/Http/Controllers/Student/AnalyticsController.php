<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ForumController;
use App\Models\BlogPost;
use App\Models\ForumThread;
use App\Services\ProgressDossierService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, ProgressDossierService $dossierService): View
    {
        return view('student.analytics.index', $this->homeData($request, $dossierService));
    }

    /** @return array<string, mixed> */
    private function homeData(Request $request, ProgressDossierService $dossierService): array
    {
        $user = $request->user();

        $inProgressAttempt = $user->userTests()
            ->visibleInGuestAnalytics($user)
            ->where('status', 'in_progress')
            ->with(['test.sections.modules', 'currentModule.section'])
            ->orderBy('updated_at', 'desc')
            ->first();

        // 1. Comparable cohort: completed, non-preview, non-section attempts with both section scores and total score
        $baseComparableQuery = $user->userTests()
            ->visibleInGuestAnalytics($user)
            ->where('status', 'completed')
            ->where('attempt_type', '!=', 'section')
            ->whereNotNull('total_score')
            ->whereNotNull('score_reading_writing')
            ->whereNotNull('score_math')
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->excludingAbsorbedSections();

        $comparableCohort = (clone $baseComparableQuery)
            ->with(['test.sections'])
            ->orderBy('completed_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $latestComparableAttempt = $comparableCohort->first();
        $bestScore = $comparableCohort->isNotEmpty() ? (int) $comparableCohort->max('total_score') : null;

        $chartAttempts = $comparableCohort->take(5)->reverse()->values();

        $homeScoreTrend = [
            'attempts' => $chartAttempts->map(fn ($attempt) => [
                'score' => (int) $attempt->total_score,
                'date' => $attempt->completed_at ? $attempt->completed_at->format('M j') : null,
                'dateFull' => $attempt->completed_at?->format('M j, Y'),
                'timestamp' => $attempt->completed_at?->getTimestamp(),
            ])->all(),
            'target_score' => $user->target_score ? (int) $user->target_score : null,
        ];

        // 2. Recent completed work (up to 3 completed attempts including section attempts)
        $recentWorkAttempts = $user->userTests()
            ->visibleInGuestAnalytics($user)
            ->where('status', 'completed')
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->excludingAbsorbedSections()
            ->with(['test.sections', 'userAnswers'])
            ->orderBy('completed_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get();

        $lastCompletedAttempt = $recentWorkAttempts->first();

        // Total practice completed count (all qualifying attempts)
        $completedCount = $user->userTests()
            ->visibleInGuestAnalytics($user)
            ->where('status', 'completed')
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->excludingAbsorbedSections()
            ->count();

        // 3. Weekly activity totals in Vietnam timezone (Monday 00:00 to following Monday 00:00)
        $tz = 'Asia/Ho_Chi_Minh';
        $nowInTz = now($tz);
        $startOfWeek = (clone $nowInTz)->startOfWeek(\Carbon\Carbon::MONDAY);
        $endOfWeek = (clone $startOfWeek)->addWeek();

        $weeklyAttemptsQuery = $user->userTests()
            ->visibleInGuestAnalytics($user)
            ->where('status', 'completed')
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->excludingAbsorbedSections()
            ->where('completed_at', '>=', $startOfWeek)
            ->where('completed_at', '<', $endOfWeek);

        $weeklyAttempts = (clone $weeklyAttemptsQuery)->get(['id', 'completed_at']);
        $weeklyCompletedCount = $weeklyAttempts->count();
        $weeklyAttemptIds = $weeklyAttempts->pluck('id');

        $weeklyAnsweredCount = 0;
        if ($weeklyAttemptIds->isNotEmpty()) {
            $weeklyAnsweredCount = \App\Models\UserTestAnswer::whereIn('user_test_id', $weeklyAttemptIds)
                ->whereNotNull('selected_answer')
                ->where('selected_answer', '!=', '')
                ->count();
        }

        $weeklyActivity = [
            'practiceCount' => $weeklyCompletedCount,
            'questionsCount' => $weeklyAnsweredCount,
            'days' => collect(range(0, 6))->map(function ($offset) use ($startOfWeek, $nowInTz, $weeklyAttempts, $tz) {
                $date = $startOfWeek->copy()->addDays($offset);
                return [
                    'label' => $date->format('D'),
                    'date' => $date->toDateString(),
                    'today' => $date->isSameDay($nowInTz),
                    'completed' => $weeklyAttempts->contains(fn ($attempt) => $attempt->completed_at->copy()->timezone($tz)->isSameDay($date)),
                ];
            })->all(),
        ];

        $suggestion = $dossierService->studySuggestion($recentWorkAttempts);

        $hasClassroom = $user->classroomMemberships()->where('status', 'active')->exists();
        $primaryClassroom = $hasClassroom
            ? $user->classroomMemberships()->where('status', 'active')->with('classroom.owner')->first()?->classroom
            : null;

        $assignments = collect();
        $assignmentFocus = ['open' => 0, 'overdue' => 0, 'dueSoon' => 0];
        $hasAnyAssignment = false;

        if ($user->role === 'student' || $user->role === 'teacher') {
            $recipientScope = \App\Models\Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'))
                ->whereIn('status', ['published', 'closed']);

            // Preload student assignments with relations
            $allStudentAssignments = (clone $recipientScope)
                ->with([
                    'classroom',
                    'test.sections',
                    'attempts' => fn ($query) => $query->where('user_id', $user->id),
                    'recipients' => fn ($query) => $query->where('student_id', $user->id),
                ])
                ->get();

            $hasAnyAssignment = $allStudentAssignments->isNotEmpty();

            // Authoritative resolved eligibility for each assignment
            $allStudentAssignments->each(function ($a) {
                $recipient = $a->recipients->first();
                $a->resolved = \App\Support\AssignmentStatusResolver::resolve($a, $a->attempts, $recipient);
            });

            // Actionable pool: can actually be started or resumed, excluding completed without in-progress attempt
            $actionablePool = $allStudentAssignments->filter(function ($a) {
                return $a->resolved['canStart'] && ($a->resolved['completed']->isEmpty() || $a->resolved['inProgress'] !== null);
            });

            // Derive headline counts directly from actionable pool before visual deduplication or display limits
            $openDueDates = $actionablePool->pluck('due_at');
            $assignmentFocus = [
                'open' => $openDueDates->count(),
                'overdue' => $actionablePool->filter(fn ($a) => $a->resolved['state'] === 'Overdue' || ($a->due_at && now()->gte($a->due_at)))->count(),
                'dueSoon' => $openDueDates->filter(fn ($due) => $due && now()->lt($due) && $due->lte(now()->addDays(2)))->count(),
            ];

            // Deduplicate if $inProgressAttempt is already for this assignment on the current work card
            $actionable = $actionablePool->reject(function ($a) use ($inProgressAttempt) {
                return $inProgressAttempt && $inProgressAttempt->assignment_id && (int) $inProgressAttempt->assignment_id === (int) $a->id;
            });

            // Prioritize: resumable work (has in_progress attempt), then overdue that remain available, then nearest deadlines
            $sortedActionable = $actionable->sort(function ($a, $b) {
                $rankA = $a->resolved['inProgress'] ? 1 : ($a->resolved['state'] === 'Overdue' ? 2 : ($a->due_at ? 3 : 4));
                $rankB = $b->resolved['inProgress'] ? 1 : ($b->resolved['state'] === 'Overdue' ? 2 : ($b->due_at ? 3 : 4));

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                if ($a->due_at && $b->due_at) {
                    return $a->due_at <=> $b->due_at;
                }

                return $b->published_at <=> $a->published_at;
            });

            $assignments = $sortedActionable->take(3)->values();
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
            'inProgressAttempt' => $inProgressAttempt,
            'latestPosts' => BlogPost::with('teacher')->latest('published_at')->limit(3)->get(),
            'latestThreads' => ForumThread::withCount('replies')->latest()->limit(5)->get(),
            'categories' => ForumController::CATEGORIES,
            'suggestion' => $suggestion,
            'lastCompletedAttempt' => $lastCompletedAttempt,
            'latestComparableAttempt' => $latestComparableAttempt,
            'recentWorkAttempts' => $recentWorkAttempts,
            'weeklyActivity' => $weeklyActivity,
            'hasClassroom' => $hasClassroom,
            'primaryClassroom' => $primaryClassroom,
            'completedCount' => $completedCount,
            'comparableCount' => $comparableCohort->count(),
            'homeGuide' => [
                'previewOpened' => $user->home_preview_opened_at !== null,
                'practiceCompleted' => $completedCount > 0 || $user->home_report_opened_at !== null,
                'reportOpened' => $user->home_report_opened_at !== null,
                'preference' => $user->home_guide_preference ?? 'auto',
                'expanded' => $user->home_guide_preference === 'expanded' ||
                    (($user->home_guide_preference ?? 'auto') === 'auto' && $user->home_report_opened_at === null),
            ],
            'bestScore' => $bestScore,
            'assignments' => $assignments,
            'hasAnyAssignment' => $hasAnyAssignment,
            'assignmentFocus' => $assignmentFocus,
            'teacherStats' => $teacherStats,
            'homeScoreTrend' => $homeScoreTrend,
        ];
    }
}
