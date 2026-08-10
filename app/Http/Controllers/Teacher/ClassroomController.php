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
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Admins oversee every class; teachers only their own or co-taught ones.
        $visible = fn () => Classroom::query()
            ->when($user->role !== 'admin', fn ($query) => $this->scopeToTeacher($query, $user));

        $classroom = $visible()->where('status', 'active')->latest('updated_at')->first()
            ?? $visible()->latest('updated_at')->first();

        if ($classroom) {
            // `new` is forwarded so a "Create a class" link lands with the form
            // already open, instead of on a page where the user hunts for it.
            return redirect()->route('teacher.classes.show', array_filter([
                'classroom' => $classroom,
                'new' => $request->boolean('new') ? 1 : null,
            ]));
        }

        // Nothing to show yet. This is the first screen a newly approved teacher
        // reaches from their approval email, so it has to carry the create form.
        return view('teacher.classes.index', ['user' => $user]);
    }

    private function scopeToTeacher($query, User $user)
    {
        return $query->where(fn ($scope) => $scope
            ->where('owner_id', $user->id)
            ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id)));
    }

    /**
     * Kept as a redirect: the embedded workspace pane it used to open is gone,
     * but the route is linked from older mail and bookmarks.
     */
    public function workspace()
    {
        return redirect()->route('teacher.classes.index');
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

        $documentsPage = $classroom->documents()
            ->with('creator')
            ->latest('created_at')
            ->paginate(15, ['*'], 'docs_page')
            ->withQueryString()
            ->appends(['tab' => 'docs']);

        $assignmentsPage = $classroom->assignments()
            ->with('test')
            ->latest('created_at')
            ->paginate(12, ['*'], 'assign_page')
            ->withQueryString()
            ->appends(['tab' => 'assign']);

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
