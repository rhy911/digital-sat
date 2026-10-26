<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Services\ClassroomService;
use App\Support\AssignmentStatusResolver;
use App\Support\ClassroomLeaderboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $activeRecipient = fn ($query) => $query->whereHas('recipients', fn ($recipients) => $recipients
            ->where('student_id', $request->user()->id)
            ->where('status', 'active'));
        $memberships = $request->user()->classroomMemberships()
            ->with(['classroom' => fn ($q) => $q->with(['owner'])->withCount([
                'activeMemberships',
                'assignments' => fn ($assignments) => $assignments
                    ->whereIn('status', ['published', 'closed'])
                    ->where($activeRecipient),
            ])])
            ->latest()
            ->get();

        if ($memberships->isEmpty()) {
            return view('student.classes.empty', ['user' => $request->user()]);
        }

        $activeMemberships = $memberships->where('status', 'active')->values();
        $pendingMemberships = $memberships->where('status', 'pending')->values();

        return view('student.classes.index', [
            'user' => $request->user(),
            'activeMemberships' => $activeMemberships,
            'pendingMemberships' => $pendingMemberships,
        ]);
    }

    public function show(Request $request, Classroom $classroom): View
    {
        $membership = $classroom->memberships()
            ->where('student_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless((bool) $membership, 403);

        $activeRecipient = fn ($query) => $query->whereHas('recipients', fn ($recipients) => $recipients
            ->where('student_id', $request->user()->id)
            ->where('status', 'active'));
        $visibleAssignments = fn ($query) => $query->whereIn('status', ['published', 'closed'])->where($activeRecipient);

        $classroom->load([
            'owner',
            'coTeachers',
            'exclusiveTests' => fn ($query) => $query->where('status', 'active'),
            'documents' => fn ($query) => $query->with('creator')->latest(),
            'assignments' => fn ($query) => $query->with([
                'test',
                'recipients' => fn ($r) => $r->where('student_id', $request->user()->id)->where('status', 'active'),
                'attempts' => fn ($attemptsQuery) => $attemptsQuery->where('user_id', $request->user()->id)->with('feedbackAuthor'),
            ])->where($visibleAssignments),
            'memberships.student',
            'note',
        ])->loadCount([
            'documents',
            'assignments' => $visibleAssignments,
        ]);

        $siblingMemberships = $request->user()->classroomMemberships()
            ->with(['classroom' => fn ($query) => $query->withCount([
                'activeMemberships',
                'assignments' => $visibleAssignments,
            ])])
            ->get();

        $classrooms = $siblingMemberships->pluck('classroom')->filter()->unique('id')->values();
        $activeClassroomsCount = $classrooms->where('status', 'active')->count();
        $archivedClassroomsCount = $classrooms->where('status', 'archived')->count();

        $assignmentStatuses = $classroom->assignments->mapWithKeys(
            fn ($assignment) => [$assignment->id => AssignmentStatusResolver::resolve($assignment, $assignment->attempts, $assignment->recipients->first())]
        );

        $completedCount = $assignmentStatuses->filter(fn ($s) => $s['state'] === 'Completed')->count();
        $latestScore = $classroom->assignments->flatMap->attempts
            ->where('status', 'completed')
            ->sortByDesc('completed_at')
            ->first()?->total_score;

        $leaderboard = ClassroomLeaderboard::topScores($classroom);

        $dueSoon = $classroom->assignments
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->sortBy('due_at')
            ->take(3);

        $documentsPage = $classroom->documents()
            ->with('creator')
            ->latest('created_at')
            ->paginate(15, ['*'], 'docs_page')
            ->withQueryString()
            ->appends(['tab' => 'docs']);

        $assignmentsPage = $classroom->assignments()
            ->with('test')
            ->where($visibleAssignments)
            ->latest('created_at')
            ->paginate(12, ['*'], 'assign_page')
            ->withQueryString()
            ->appends(['tab' => 'assign']);

        $classmatesPage = $classroom->memberships()
            ->where('status', 'active')
            ->where('student_id', '!=', $request->user()->id)
            ->with('student')
            ->orderByDesc('decided_at')
            ->paginate(15, ['*'], 'mates_page')
            ->withQueryString()
            ->appends(['tab' => 'classmates']);

        $announcementsPage = $classroom->announcements()
            ->with(['author', 'comments' => fn ($query) => $query->with('author')->oldest()])
            ->orderByDesc('pinned')
            ->latest()
            ->paginate(10, ['*'], 'announce_page')
            ->withQueryString()
            ->appends(['tab' => 'announce']);

        return view('student.classes.show', [
            'user' => $request->user(),
            'classroom' => $classroom,
            'membership' => $membership,
            'classrooms' => $classrooms,
            'activeClassroomsCount' => $activeClassroomsCount,
            'archivedClassroomsCount' => $archivedClassroomsCount,
            'assignmentStatuses' => $assignmentStatuses,
            'completedCount' => $completedCount,
            'latestScore' => $latestScore,
            'leaderboard' => $leaderboard,
            'dueSoon' => $dueSoon,
            'documentsPage' => $documentsPage,
            'assignmentsPage' => $assignmentsPage,
            'classmatesPage' => $classmatesPage,
            'announcementsPage' => $announcementsPage,
            'units' => $classroom->studyUnits(),
        ]);
    }

    public function join(Request $request, ClassroomService $service): RedirectResponse
    {
        $data = $request->validate(['join_code' => 'required|string|size:8']);
        $service->requestMembership($request->user(), $data['join_code']);
        return back()->with('success', 'Join request sent. Your teacher must approve it.');
    }

    public function leave(ClassroomMembership $membership, ClassroomService $service): RedirectResponse
    {
        abort_unless((int) $membership->student_id === (int) auth()->id() && $membership->status === 'active', 403);
        $service->endMembership($membership, auth()->user(), 'left');
        return redirect()->route('student.classes.index')->with('success', 'You left the class. Previous results remain recorded.');
    }

    public function updateNickname(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate(['display_name' => 'nullable|string|max:100']);

        $membership = $classroom->memberships()
            ->where('student_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $membership->update(['display_name' => $data['display_name'] ?: null]);

        return back()->with('success', 'Nickname updated.');
    }
}
