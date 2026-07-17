<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
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
    public function index()
    {
        session(['teacher_workspace.section' => 'classes']);
        session(['teacher_home.tab' => 'classes']);

        $user = auth()->user();

        if ($user->role !== 'admin') {
            $classroom = $this->teacherClassroomsQuery($user)->where('status', 'active')->latest('updated_at')->first()
                ?? $this->teacherClassroomsQuery($user)->latest('updated_at')->first();

            if ($classroom) {
                return redirect()->route('teacher.classes.show', $classroom);
            }
        }

        return redirect()->route('home');
    }

    private function teacherClassroomsQuery(User $user)
    {
        return Classroom::query()->where(fn ($scope) => $scope
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
        ])->loadCount([
            'activeMemberships',
            'coTeachers',
            'documents',
            'assignments',
            'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
        ]);

        $user = auth()->user();
        $classrooms = Classroom::query()
            ->when($user->role !== 'admin', fn ($query) => $query->where(fn ($scope) => $scope
                ->where('owner_id', $user->id)
                ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id))))
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
            'assignmentsPage'
        ));
    }

    public function noteUpdate(Request $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        $data = $request->validate(['body' => 'nullable|string|max:2000']);

        $note = $classroom->note()->firstOrNew([]);
        $note->body = $data['body'] ?? '';
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
