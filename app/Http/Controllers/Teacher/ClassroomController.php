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
use App\Support\ClassroomCohortAnalytics;
use App\Support\ClassroomLeaderboard;
use App\Support\ClassroomRosterStats;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $visible = fn () => Classroom::query()
            ->when($user->role !== 'admin', fn ($query) => $this->scopeToTeacher($query, $user));

        $classrooms = $visible()
            ->withCount([
                'activeMemberships',
                'assignments',
                'coTeachers',
                'documents',
                'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest('updated_at')
            ->get();

        $activeClassrooms = $classrooms->where('status', 'active')->values();
        $archivedClassrooms = $classrooms->where('status', 'archived')->values();

        return view('teacher.classes.index', compact('user', 'classrooms', 'activeClassrooms', 'archivedClassrooms'));
    }

    /** @param Builder<Classroom> $query
     *  @return Builder<Classroom> */
    private function scopeToTeacher(Builder $query, User $user): Builder
    {
        return $query->where(fn ($scope) => $scope
            ->where('owner_id', $user->id)
            ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id)));
    }

    /**
     * Kept as a redirect: the embedded workspace pane it used to open is gone,
     * but the route is linked from older mail and bookmarks.
     */
    public function workspace(): RedirectResponse
    {
        return redirect()->route('teacher.classes.index');
    }

    public function progress(): RedirectResponse
    {
        session(['teacher_home.tab' => 'progress']);

        return redirect()->route('home');
    }

    public function store(StoreClassroomRequest $request): RedirectResponse
    {
        Classroom::create($request->validated() + ['owner_id' => $request->user()->id]);
        return back()->with('success', 'Class created. Share its code when ready.');
    }

    public function show(Classroom $classroom): View
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

        $assignableTeacher = $user->role === 'admin' ? $classroom->owner : $user;
        $tests = Test::assignableTo($assignableTeacher)
            ->with('shares')
            ->latest()
            ->get(['id', 'title', 'content_locked_at', 'created_by']);

        $leaderboard = ClassroomLeaderboard::topScores($classroom);
        $cohortStats = ClassroomCohortAnalytics::forClassroom($classroom);
        $units = $classroom->studyUnits();

        return view('teacher.classes.show', compact(
            'classroom',
            'classrooms',
            'activeClassroomsCount',
            'archivedClassroomsCount',
            'topScore',
            'tests',
            'leaderboard',
            'cohortStats',
            'rosterStats',
            'units',
            'documentsPage',
            'assignmentsPage',
            'announcementsPage'
        ));
    }

    public function linkTest(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');

        $validated = $request->validate([
            'test_id' => 'required|exists:tests,id',
            'unit_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $assignableTeacher = $user->role === 'admin' ? $classroom->owner : $user;
        $test = Test::assignableTo($assignableTeacher)->whereKey($validated['test_id'])->first();
        if (! $test) {
            throw ValidationException::withMessages(['test_id' => 'Select one of your active or shared active tests.']);
        }

        $unitName = trim((string) ($validated['unit_name'] ?? ''));

        $classroom->exclusiveTests()->syncWithoutDetaching([
            $test->id => [
                'unit_name' => $unitName !== '' ? $unitName : null,
                'order' => $classroom->exclusiveTests()->count() + 1,
            ],
        ]);

        return back()->with('success', 'Practice test linked to class materials.');
    }

    public function unlinkTest(Classroom $classroom, Test $test): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');

        $classroom->exclusiveTests()->detach($test->id);

        return back()->with('success', 'Practice test removed from class materials.');
    }

    public function submitFeedback(Request $request, Classroom $classroom, \App\Models\UserTest $attempt): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        $isClassAssignmentAttempt = $attempt->assignment_id && $attempt->assignment()->where('classroom_id', $classroom->id)->exists();
        $isClassStudentIndependentAttempt = ! $attempt->assignment_id && $classroom->memberships()->where('student_id', $attempt->user_id)->where('status', 'active')->exists();

        abort_unless($isClassAssignmentAttempt || $isClassStudentIndependentAttempt, 404);

        $validated = $request->validate([
            'teacher_feedback' => 'required|string|max:2000',
        ]);

        $attempt->update([
            'teacher_feedback' => trim($validated['teacher_feedback']),
            'feedback_at' => now(),
            'feedback_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Feedback note saved for student attempt.');
    }

    public function grantRetry(Classroom $classroom, \App\Models\AssignmentRecipient $recipient): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');
        abort_unless($recipient->assignment()->where('classroom_id', $classroom->id)->exists(), 404);

        $recipient->increment('extra_attempts');

        return back()->with('success', '+1 attempt retry granted for ' . ($recipient->student->name ?? 'student') . '.');
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

    public function update(UpdateClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update($request->validated());
        return back()->with('success', 'Class details updated.');
    }

    public function rotateCode(Classroom $classroom): RedirectResponse
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update(['join_code' => Classroom::generateJoinCode(), 'join_code_rotated_at' => now()]);
        return back()->with('success', 'Join code rotated.');
    }

    public function archive(Classroom $classroom, ClassroomService $service): RedirectResponse
    {
        $this->authorize('manageTeam', $classroom);
        $service->archive($classroom);
        return redirect()->route('teacher.classes.index')->with('success', 'Class archived. History remains available.');
    }

    public function restore(Classroom $classroom): RedirectResponse
    {
        $this->authorize('manageTeam', $classroom);
        $classroom->update(['status' => 'active']);
        return back()->with('success', 'Class restored. Closed assignments remain closed.');
    }
}
