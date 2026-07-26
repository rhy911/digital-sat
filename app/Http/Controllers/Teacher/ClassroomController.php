<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\NoteUpdateRequest;
use App\Http\Requests\Teacher\StoreClassroomRequest;
use App\Http\Requests\Teacher\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\User;
use App\Services\ClassroomService;
use App\Support\ClassroomLeaderboard;
use App\Support\ClassroomRosterStats;

class ClassroomController extends Controller
{
    public function index()
    {
        session(['teacher_workspace.section' => 'classes']);
        session(['teacher_home.tab' => 'classes']);

        $user = auth()->user();

        if ($user->role !== 'admin') {
            $classroom = $this->scopeToTeacher(Classroom::query(), $user)->where('status', 'active')->latest('updated_at')->first()
                ?? $this->scopeToTeacher(Classroom::query(), $user)->latest('updated_at')->first();

            if ($classroom) {
                return redirect()->route('teacher.classes.show', $classroom);
            }
        }

        return redirect()->route('home');
    }

    private function scopeToTeacher($query, User $user)
    {
        return $query->where(fn ($scope) => $scope
            ->where('owner_id', $user->id)
            ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id)));
    }

    public function workspace()
    {
        session(['teacher_workspace.section' => 'classes']);
        session(['teacher_home.tab' => 'classes']);

        return redirect()->route('home');
    }

    public function progress()
    {
        session(['teacher_home.tab' => 'progress']);

        return redirect()->route('home');
    }

    public function store(StoreClassroomRequest $request)
    {
        Classroom::create($request->validated() + ['owner_id' => $request->user()->id]);
        return back()->with('success', 'Class created. Share its code when ready.');
    }

    public function show(Classroom $classroom)
    {
        $this->authorize('view', $classroom);
        $classroom->load([
            'owner',
            'coTeachers',
            'memberships.student',
            'assignments.test',
            'documents.creator',
            'note',
            'events' => fn ($query) => $query->orderBy('starts_at'),
        ])->loadCount([
            'activeMemberships',
            'coTeachers',
            'documents',
            'assignments',
            'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
        ]);

        $user = auth()->user();
        $classrooms = Classroom::query()
            ->when($user->role !== 'admin', fn ($query) => $this->scopeToTeacher($query, $user))
            ->withCount([
                'activeMemberships',
                'assignments',
                'coTeachers',
                'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest()
            ->get();

        $activeClassroomsCount = $classrooms->where('status', 'active')->count();
        $archivedClassroomsCount = $classrooms->where('status', 'archived')->count();

        $rosterStats = ClassroomRosterStats::forClassroom($classroom);
        $topScore = ClassroomRosterStats::topScore($rosterStats, $classroom);

        $rosterPage = $classroom->memberships()
            ->where('status', 'active')
            ->with('student')
            ->orderByDesc('decided_at')
            ->paginate(15, ['*'], 'roster_page')
            ->withQueryString();

        $documentsPage = $classroom->documents()
            ->with('creator')
            ->latest('created_at')
            ->paginate(15, ['*'], 'docs_page')
            ->withQueryString();

        $assignmentsPage = $classroom->assignments()
            ->with('test')
            ->latest('created_at')
            ->paginate(12, ['*'], 'assign_page')
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
            fn ($assignment) => route('teacher.assignments.show', $assignment),
        );

        $assignableTeacher = $user->role === 'admin' ? $classroom->owner : $user;
        $tests = Test::assignableTo($assignableTeacher)
            ->with('shares')
            ->latest()
            ->get(['id', 'title', 'content_locked_at', 'created_by']);

        $leaderboard = ClassroomLeaderboard::topScores($classroom);

        return view('teacher.classes.show', compact(
            'classroom',
            'classrooms',
            'activeClassroomsCount',
            'archivedClassroomsCount',
            'topScore',
            'tests',
            'leaderboard',
            'rosterPage',
            'documentsPage',
            'assignmentsPage',
            'announcementsPage',
            'calendarItems'
        ));
    }

    public function noteUpdate(NoteUpdateRequest $request, Classroom $classroom): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $classroom);

        $note = $classroom->note()->firstOrNew([]);
        $note->body = trim($request->validated()['body'] ?? '');
        $note->created_by ??= $request->user()->id;
        $note->save();

        return back()->with('success', 'Class note updated.');
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update($request->validated());
        return back()->with('success', 'Class details updated.');
    }

    public function rotateCode(Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update(['join_code' => Classroom::generateJoinCode(), 'join_code_rotated_at' => now()]);
        return back()->with('success', 'Join code rotated.');
    }

    public function archive(Classroom $classroom, ClassroomService $service)
    {
        $this->authorize('manageTeam', $classroom);
        $service->archive($classroom);
        return redirect()->route('teacher.classes.index')->with('success', 'Class archived. History remains available.');
    }

    public function restore(Classroom $classroom)
    {
        $this->authorize('manageTeam', $classroom);
        $classroom->update(['status' => 'active']);
        return back()->with('success', 'Class restored. Closed assignments remain closed.');
    }
}
