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
        $membership = $request->user()->classroomMemberships()
            ->where('status', 'active')
            ->with('classroom')
            ->latest()
            ->first();

        if (!$membership) {
            return view('student.classes.empty', ['user' => $request->user()]);
        }

        return redirect()->route('student.classes.show', $membership->classroom);
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
            'documents' => fn ($query) => $query->with('creator')->latest(),
            'assignments' => fn ($query) => $query->with([
                'test',
                'attempts' => fn ($attemptsQuery) => $attemptsQuery->where('user_id', $request->user()->id),
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
            fn ($assignment) => [$assignment->id => AssignmentStatusResolver::resolve($assignment, $assignment->attempts)]
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
            ->withQueryString();

        $assignmentsPage = $classroom->assignments()
            ->with('test')
            ->whereIn('status', ['published', 'closed'])
            ->latest('created_at')
            ->paginate(12, ['*'], 'assign_page')
            ->withQueryString();

        $classmatesPage = $classroom->memberships()
            ->where('status', 'active')
            ->where('student_id', '!=', $request->user()->id)
            ->with('student')
            ->orderByDesc('decided_at')
            ->paginate(15, ['*'], 'mates_page')
            ->withQueryString();

        $announcementsPage = $classroom->announcements()
            ->with(['author', 'comments' => fn ($query) => $query->with('author')->oldest()])
            ->orderByDesc('pinned')
            ->latest()
            ->paginate(10, ['*'], 'announce_page')
            ->withQueryString();

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
