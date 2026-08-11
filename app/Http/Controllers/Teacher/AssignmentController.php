<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\User;
use App\Notifications\AssignmentPublishedNotification;
use App\Services\AssignmentAttemptTimeoutService;
use App\Services\AssignmentReportService;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    private function getTeacherAssignmentsQuery(User $user)
    {
        return Assignment::query()
            ->whereHas('classroom', function ($query) use ($user) {
                $query->when($user->role !== 'admin', fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('owner_id', $user->id)
                    ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($user->id))));
            });
    }

    public function index()
    {
        $user = auth()->user();
        $assignment = $this->getTeacherAssignmentsQuery($user)->latest('updated_at')->first();

        if ($assignment) {
            return redirect()->route('teacher.assignments.show', $assignment);
        }

        $assignments = collect();
        $report = null;
        $origin = 'workspace';

        return view('teacher.assignments.show', compact('assignment', 'assignments', 'report', 'origin'));
    }

    public function store(StoreAssignmentRequest $request, Classroom $classroom, AssignmentService $service)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $assignableTeacher = $request->user()->role === 'admin' ? $classroom->owner : $request->user();
        $test = Test::assignableTo($assignableTeacher)->whereKey($request->integer('test_id'))->first();
        if (!$test) throw ValidationException::withMessages(['test_id' => 'Select one of your active or shared active tests.']);
        $teacherId = $request->user()->role === 'admin' ? $classroom->owner_id : $request->user()->id;
        $assignment = DB::transaction(function () use ($request, $classroom, $service, $teacherId) {
            $data = $request->validated();
            $data['assign_type'] = $data['assign_type'] ?? 'full';
            if ($data['assign_type'] === 'full') {
                $data['section_type'] = null;
            }
            $assignment = Assignment::create($data + [
                'classroom_id' => $classroom->id,
                'teacher_id' => $teacherId,
            ]);

            return $service->publish($assignment);
        });
        $assignment->load('recipients.student');
        $assignment->recipients->each(fn ($recipient) => $recipient->student->notify(new AssignmentPublishedNotification($assignment)));
        return back()->with('success', 'Assignment created. Students were notified.');
    }

    public function show(Assignment $assignment, AssignmentReportService $reports, AssignmentAttemptTimeoutService $timeouts)
    {
        $this->authorize('view', $assignment);

        // Third catch-up point. The report is where "in progress" is read as a fact
        // about a student, so an attempt whose clock expired hours ago must not
        // still be listed that way because cron is down.
        foreach ($timeouts->candidateAttemptsQuery()->where('assignment_id', $assignment->id)->get() as $running) {
            $timeouts->finalizeExpired($running);
        }

        $user = auth()->user();
        $assignments = $this->getTeacherAssignmentsQuery($user)
            ->with(['classroom', 'test'])
            ->withCount('attempts')
            ->latest('updated_at')
            ->get();

        $report = $reports->build($assignment);
        $origin = request('from') === 'workspace' ? 'workspace' : 'class';
        return view('teacher.assignments.show', compact('assignment', 'assignments', 'report', 'origin'));
    }

    public function exportCsv(Assignment $assignment, AssignmentReportService $reports)
    {
        $this->authorize('view', $assignment);
        $report = $reports->build($assignment, perPage: null);
        $filename = sprintf('assignment-results-%s.csv', \Illuminate\Support\Str::slug($assignment->title ?: 'assignment'));

        return response()->streamDownload(function () use ($report, $assignment) {
            $handle = fopen('php://output', 'w');
            $headers = ['Student', 'Email', 'Status', 'Attempts', 'Best estimate'];
            if ($assignment->assign_type !== 'section') {
                $headers[] = 'Est. R&W';
                $headers[] = 'Est. Math';
            }
            fputcsv($handle, $headers);

            foreach ($report['rows'] as $row) {
                $status = $row['recipient']->status === 'withdrawn'
                    ? 'Withdrawn'
                    : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started'));
                $best = $row['best'];
                $bestScore = $best
                    ? ($assignment->assign_type === 'section'
                        ? ($assignment->section_type === 'reading_writing' ? $best->score_reading_writing : $best->score_math)
                        : $best->total_score)
                    : null;

                $line = [
                    $row['recipient']->student->name,
                    $row['recipient']->student->email,
                    $status,
                    $row['completed_count'].' / '.$assignment->attempt_limit,
                    $bestScore ?? '—',
                ];
                if ($assignment->assign_type !== 'section') {
                    $line[] = $best?->score_reading_writing ?? '—';
                    $line[] = $best?->score_math ?? '—';
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportPrint(Assignment $assignment, AssignmentReportService $reports)
    {
        $this->authorize('view', $assignment);
        $report = $reports->build($assignment, perPage: null);
        $filename = sprintf('assignment-results-%s.pdf', \Illuminate\Support\Str::slug($assignment->title ?: 'assignment'));

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('teacher.assignments.export-print', compact('assignment', 'report'))
            ->setPaper('letter', 'landscape')
            ->stream($filename);
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

    public function studentAttempt(Request $request, Assignment $assignment, User $student, AssignmentReportService $reports)
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

        $studentIdsWithAttempts = $assignment->attempts()->distinct()->pluck('user_id');
        $orderedStudentIds = $assignment->recipients()->orderBy('id')->pluck('student_id')->values()
            ->filter(fn ($id) => $studentIdsWithAttempts->contains($id))
            ->values();
        $currentIndex = $orderedStudentIds->search($student->id);
        $prevStudentId = $currentIndex !== false && $currentIndex > 0 ? $orderedStudentIds[$currentIndex - 1] : null;
        $nextStudentId = $currentIndex !== false && $currentIndex < $orderedStudentIds->count() - 1 ? $orderedStudentIds[$currentIndex + 1] : null;

        $user = auth()->user();
        $assignments = $this->getTeacherAssignmentsQuery($user)
            ->with(['classroom', 'test'])
            ->withCount('attempts')
            ->latest('updated_at')
            ->get();

        return view('teacher.assignments.student-attempt', compact(
            'assignment', 'assignments', 'row', 'initialAttempt', 'attemptModalId', 'student', 'prevStudentId', 'nextStudentId'
        ));
    }

    public function questionPreview(Request $request, Assignment $assignment, \App\Models\UserTestAnswer $userAnswer)
    {
        $this->authorize('view', $assignment);
        abort_unless($userAnswer->userTest->assignment_id === $assignment->id, 403);

        $userAnswer->loadMissing([
            'userTest',
            'question.passage',
            'question.answerChoices',
            'question.sprCorrectAnswers',
            'question.explanation',
        ]);

        $isInProgress = $userAnswer->userTest->status === 'in_progress';
        $correct = $userAnswer->question?->sprCorrectAnswers->pluck('answer')->implode(', ') 
            ?: $userAnswer->question?->answerChoices->firstWhere('is_correct', true)?->label;

        return view('teacher.assignments.partials.question-preview', compact('userAnswer', 'isInProgress', 'correct'));
    }

    public function update(StoreAssignmentRequest $request, Assignment $assignment)
    {
        $this->authorize('manage', $assignment);
        abort_if($assignment->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $testChanged = $request->integer('test_id') !== (int) $assignment->test_id;
        $assignableTeacher = $request->user()->role === 'admin' ? $assignment->teacher : $request->user();
        $test = Test::assignableTo($assignableTeacher)->whereKey($request->integer('test_id'))->first();
        if ($testChanged && ! $test) throw ValidationException::withMessages(['test_id' => 'Select one of your active or shared active tests.']);
        if ($assignment->attempts()->exists() && $testChanged) {
            throw ValidationException::withMessages(['test_id' => 'Test cannot change after an attempt starts.']);
        }
        $used = (int) $assignment->attempts()->max('attempt_number');
        if ($request->integer('attempt_limit') < $used) throw ValidationException::withMessages(['attempt_limit' => "Attempt limit cannot be lower than {$used}."]);
        $assignment->update($request->validated());
        return back()->with('success', 'Assignment updated.');
    }

    public function publish(Assignment $assignment, AssignmentService $service): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $assignment);
        $assignment = $service->publish($assignment);
        $assignment->load('recipients.student');
        $assignment->recipients->each(fn ($recipient) => $recipient->student->notify(new AssignmentPublishedNotification($assignment)));
        return back()->with('success', 'Assignment published. Students were notified.');
    }

    public function close(Assignment $assignment, AssignmentService $service): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $assignment);
        $service->close($assignment);
        return back()->with('success', 'Assignment closed.');
    }

    public function reopen(Assignment $assignment, AssignmentService $service): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $assignment);
        $service->reopen($assignment);
        return back()->with('success', 'Assignment reopened.');
    }

    public function destroy(Assignment $assignment, AssignmentService $service): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $assignment);
        abort_if($assignment->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $classroom = $assignment->classroom;
        $service->delete($assignment);
        return redirect()->route('teacher.classes.show', $classroom)->with('success', 'Assignment deleted.');
    }
}
