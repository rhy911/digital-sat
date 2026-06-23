<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Services\AssignmentAttemptService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $classroom = null;

        if ($request->filled('classroom')) {
            $classroom = Classroom::query()
                ->whereKey($request->integer('classroom'))
                ->whereHas('memberships', fn ($query) => $query
                    ->where('student_id', $request->user()->id)
                    ->where('status', 'active'))
                ->firstOrFail();
        }

        $assignments = Assignment::whereHas('recipients', fn ($query) => $query->where('student_id', $request->user()->id)->where('status', 'active'))
            ->when($classroom, fn ($query) => $query->where('classroom_id', $classroom->id))
            ->with(['classroom', 'test', 'attempts' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->whereIn('status', ['published', 'closed'])->latest('published_at')->paginate(20);
        return view('student.assignments.index', ['user' => $request->user(), 'assignments' => $assignments, 'classroom' => $classroom]);
    }
    public function show(Request $request, Assignment $assignment)
    {
        $this->authorize('view', $assignment);
        $assignment->load(['classroom', 'test.sections.modules', 'attempts' => fn ($query) => $query->where('user_id', $request->user()->id)->latest()]);
        return view('student.assignments.show', ['user' => $request->user(), 'assignment' => $assignment]);
    }
    public function start(Assignment $assignment, AssignmentAttemptService $service)
    {
        $this->authorize('view', $assignment);
        $attempt = $service->startOrResume($assignment, auth()->user());
        $module = $attempt->currentModule;
        abort_unless($module, 422, 'Assigned test has no module.');
        return redirect()->route('engine.session', ['ulid' => $module->ulid, 'attempt' => $attempt->ulid]);
    }
}
