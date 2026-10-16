<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Services\ClassroomService;
use App\Support\AssignmentStatusResolver;
use App\Support\ClassroomLeaderboard;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $memberships = $request->user()->classroomMemberships()
            ->with(['classroom' => fn ($q) => $q->with(['owner'])->withCount(['activeMemberships', 'assignments'])])
            ->latest()
            ->get();

        if ($memberships->isEmpty()) {
            return view('student.classes.empty', ['user' => $request->user()]);
        }

        $activeMemberships = $memberships->where('status', 'active')->values();
        $pendingMemberships = $memberships->where('status', 'pending')->values();

        if ($activeMemberships->isNotEmpty() && ! $request->has('all')) {
            return redirect()->route('student.classes.show', $activeMemberships->first()->classroom);
        }

        return view('student.classes.index', [
            'user' => $request->user(),
            'activeMemberships' => $activeMemberships,
            'pendingMemberships' => $pendingMemberships,
        ]);
    }

    public function show(Request $request, Classroom $classroom)
    {
        $membership = $classroom->memberships()
            ->where('student_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($membership, 403);

        $classroom->load([
            'owner',
            'coTeachers',
            'exclusiveTests' => fn ($query) => $query->where('status', 'active'),
            'documents' => fn ($query) => $query->with('creator')->latest(),
            'assignments' => fn ($query) => $query->with([
                'test',
                'recipients' => fn ($r) => $r->where('student_id', $request->user()->id),
                'attempts' => fn ($attemptsQuery) => $attemptsQuery->where('user_id', $request->user()->id)->with('feedbackAuthor'),
            ])->whereIn('status', ['published', 'closed']),
            'memberships.student',
            'note',
            'events' => fn ($query) => $query->orderBy('starts_at'),
        ])->loadCount([
            'documents',
            'assignments' => fn ($query) => $query->whereIn('status', ['published', 'closed']),
        ]);

        $siblingMemberships = $request->user()->classroomMemberships()
            ->with(['classroom' => fn ($query) => $query->withCount(['activeMemberships', 'assignments'])])
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
            ->whereIn('status', ['published', 'closed'])
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

        $calendarItems = \App\Support\ClassroomCalendar::build(
            $classroom->events,
            $classroom->assignments,
            fn ($assignment) => route('student.assignments.show', $assignment),
        );

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
            'calendarItems' => $calendarItems,
            'units' => $classroom->studyUnits(),
        ]);
    }

    public function join(Request $request, ClassroomService $service)
    {
        $data = $request->validate(['join_code' => 'required|string|size:8']);
        $service->requestMembership($request->user(), $data['join_code']);
        return back()->with('success', 'Join request sent. Your teacher must approve it.');
    }

    public function leave(ClassroomMembership $membership, ClassroomService $service)
    {
        abort_unless((int) $membership->student_id === (int) auth()->id() && $membership->status === 'active', 403);
        $service->endMembership($membership, auth()->user(), 'left');
        return redirect()->route('student.classes.index')->with('success', 'You left the class. Previous results remain recorded.');
    }

    public function updateNickname(Request $request, Classroom $classroom)
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
