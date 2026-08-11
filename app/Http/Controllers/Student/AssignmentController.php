<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\UserTest;
use App\Services\AssignmentAttemptService;
use App\Services\AssignmentAttemptTimeoutService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $classroom = null;

        if ($request->filled('classroom')) {
            $classroom = Classroom::query()
                ->whereKey($request->integer('classroom'))
                ->whereHas('memberships', fn ($query) => $query
                    ->where('student_id', $user->id)
                    ->where('status', 'active'))
                ->firstOrFail();
        }

        $assignments = Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'))
            ->when($classroom, fn ($query) => $query->where('classroom_id', $classroom->id))
            ->whereIn('status', ['published', 'closed'])
            ->with(['classroom', 'test', 'attempts' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->get();

        $assignment = $assignments->first();
        if ($assignment) {
            $assignment->load(['classroom', 'test.sections.modules', 'attempts' => fn ($query) => $query->where('user_id', $user->id)->latest()]);
        }

        return view('student.assignments.show', compact('user', 'assignment', 'assignments', 'classroom'));
    }
    public function show(Request $request, Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        $user = $request->user();
        $classroom = null;

        if ($request->filled('classroom')) {
            $classroom = Classroom::query()
                ->whereKey($request->integer('classroom'))
                ->whereHas('memberships', fn ($query) => $query
                    ->where('student_id', $user->id)
                    ->where('status', 'active'))
                ->firstOrFail();
        }

        $assignments = Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'))
            ->when($classroom, fn ($query) => $query->where('classroom_id', $classroom->id))
            ->whereIn('status', ['published', 'closed'])
            ->with(['classroom', 'test', 'attempts' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->get();

        $assignment->load(['classroom', 'test.sections.modules', 'attempts' => fn ($query) => $query->where('user_id', $user->id)->latest()]);

        return view('student.assignments.show', compact('user', 'assignment', 'assignments', 'classroom'));
    }
    public function start(Assignment $assignment, AssignmentAttemptService $service, AssignmentAttemptTimeoutService $timeouts)
    {
        $this->authorize('view', $assignment);

        // Catch-up before resuming. The scheduled sweep normally gets here first,
        // but this route is the one place a student can be holding an attempt whose
        // time ran out while the tab was closed — so it must close it out itself
        // rather than reopen a module the clock already ended. Runs before
        // startOrResume() so an exhausted attempt is `completed` by the time the
        // attempt-limit check reads it.
        $finalized = null;
        foreach ($this->runningAttempts($assignment) as $running) {
            if ($timeouts->finalizeExpired($running) > 0) {
                $finalized = $running->fresh();
            }
        }

        if ($finalized && $finalized->status === 'completed') {
            return redirect()->route('student.scores.show', $finalized)
                ->with('success', 'Your time ran out, so this attempt was submitted automatically.');
        }

        $attempt = $service->startOrResume($assignment, auth()->user());
        $module = $attempt->currentModule;
        abort_unless($module, 422, 'Assigned test has no module.');
        return redirect()->route('engine.session', ['ulid' => $module->ulid, 'attempt' => $attempt->ulid]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserTest>
     */
    private function runningAttempts(Assignment $assignment)
    {
        return UserTest::where('assignment_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->get();
    }
}
