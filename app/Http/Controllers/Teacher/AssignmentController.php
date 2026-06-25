<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\User;
use App\Notifications\AssignmentPublishedNotification;
use App\Services\AssignmentReportService;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index()
    {
        session(['teacher_workspace.section' => 'assignments']);
        session(['teacher_home.tab' => 'reports']);

        return redirect()->route('home');
    }

    public function store(StoreAssignmentRequest $request, Classroom $classroom, AssignmentService $service)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $test = Test::assignableTo($classroom->owner)->whereKey($request->integer('test_id'))->first();
        if (!$test) throw ValidationException::withMessages(['test_id' => 'Select one of your active or shared active tests.']);
        $assignment = DB::transaction(function () use ($request, $classroom, $service) {
            $assignment = Assignment::create($request->validated() + ['classroom_id' => $classroom->id, 'teacher_id' => $classroom->owner_id]);

            return $service->publish($assignment);
        });
        $assignment->recipients->each(fn ($recipient) => $recipient->student->notify(new AssignmentPublishedNotification($assignment)));
        return back()->with('success', 'Assignment created. Students were notified.');
    }

    public function show(Assignment $assignment, AssignmentReportService $reports)
    {
        $this->authorize('view', $assignment);
        $report = $reports->build($assignment);
        $origin = request('from') === 'workspace' ? 'workspace' : 'class';
        return view('teacher.assignments.show', compact('assignment', 'report', 'origin'));
    }

    public function attemptMonitor(Request $request, Assignment $assignment, User $student, AssignmentReportService $reports)
    {
        $this->authorize('view', $assignment);
        $recipient = $assignment->recipients()->where('student_id', $student->id)->firstOrFail();
        $row = $reports->buildRecipient($assignment, $recipient);
        abort_if($row['attempts']->isEmpty(), 404);

        $requestedAttemptId = $request->integer('active_attempt');
        $initialAttempt = $row['attempts']->firstWhere('id', $requestedAttemptId)
            ?? $row['attempts']->firstWhere('status', 'in_progress')
            ?? $row['attempts']->sortByDesc('attempt_number')->first();
        $attemptModalId = 'attempts-'.$assignment->id.'-'.$student->id;

        return response()->json([
            'html' => view('teacher.assignments.partials.attempt-monitor', compact(
                'assignment', 'row', 'initialAttempt', 'attemptModalId'
            ))->render(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreAssignmentRequest $request, Assignment $assignment)
    {
        $this->authorize('manage', $assignment);
        abort_if($assignment->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $test = Test::assignableTo($assignment->teacher)->whereKey($request->integer('test_id'))->first();
        if (!$test) throw ValidationException::withMessages(['test_id' => 'Select one of the teacher owner\'s active or shared active tests.']);
        if ($assignment->attempts()->exists() && $request->integer('test_id') !== $assignment->test_id) {
            throw ValidationException::withMessages(['test_id' => 'Test cannot change after an attempt starts.']);
        }
        $used = (int) $assignment->attempts()->max('attempt_number');
        if ($request->integer('attempt_limit') < $used) throw ValidationException::withMessages(['attempt_limit' => "Attempt limit cannot be lower than {$used}."]);
        $assignment->update($request->validated());
        return back()->with('success', 'Assignment updated.');
    }

    public function publish(Assignment $assignment, AssignmentService $service)
    {
        $this->authorize('manage', $assignment);
        $assignment = $service->publish($assignment);
        $assignment->recipients->each(fn ($recipient) => $recipient->student->notify(new AssignmentPublishedNotification($assignment)));
        return back()->with('success', 'Assignment published. Students were notified.');
    }
    public function close(Assignment $assignment, AssignmentService $service) { $this->authorize('manage', $assignment); $service->close($assignment); return back()->with('success', 'Assignment closed.'); }
    public function reopen(Assignment $assignment, AssignmentService $service) { $this->authorize('manage', $assignment); $service->reopen($assignment); return back()->with('success', 'Assignment reopened.'); }
    public function destroy(Assignment $assignment, AssignmentService $service)
    {
        $this->authorize('manage', $assignment);
        abort_if($assignment->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $classroom = $assignment->classroom;
        $service->delete($assignment);
        return redirect()->route('teacher.classes.show', $classroom)->with('success', 'Assignment deleted.');
    }
}
