<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreExamSessionRequest;
use App\Http\Requests\Teacher\UpdateExamSessionStatusRequest;
use App\Models\ExamSession;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\ExamSessionService;
use Illuminate\Support\Str;

class ExamSessionController extends Controller
{
    public function __construct(private ExamSessionService $service) {}

    public function index()
    {
        $user = auth()->user();

        $sessions = ExamSession::where('teacher_id', $user->id)
            ->with('test')
            ->withCount(['userTests as candidate_count'])
            ->latest()
            ->get();

        $tests = Test::assignableTo($user)->orderBy('title')->get(['id', 'title']);

        return view('teacher.exam-sessions.index', compact('sessions', 'tests'));
    }

    public function store(StoreExamSessionRequest $request)
    {
        $session = $this->service->create($request->user(), $request->validated());

        return redirect()->route('teacher.exam-sessions.show', $session)
            ->with('success', 'Exam session created. Share the code with candidates.');
    }

    public function show(ExamSession $examSession)
    {
        $this->authorize('view', $examSession);

        $examSession->load('test');

        return view('teacher.exam-sessions.show', [
            'examSession' => $examSession,
            'candidates' => $this->rosterFor($examSession),
            'totalQuestions' => $this->questionsInTest($examSession->test),
        ]);
    }

    /**
     * How many questions one candidate actually sits for this test — 98 on a
     * standard full-length SAT (27+27 R&W, 22+22 Math).
     *
     * Deliberately derived from the test rather than hardcoded: modules are
     * de-duplicated by `module_number` because an adaptive test stores both an
     * easy and a hard Module 2 per section while any single candidate is routed
     * through exactly one of them — counting both would inflate the denominator
     * and permanently cap progress below 100%. Same precedent as
     * Test::refreshTotalDuration(). Short and custom tests therefore report
     * their own real totals instead of a borrowed 98.
     */
    private function questionsInTest(Test $test): int
    {
        $test->loadMissing('sections.modules');

        $countFor = fn ($module) => (int) ($module->total_questions ?: $module->questions()->count());

        return (int) $test->sections->sum(
            fn ($section) => $section->modules->unique('module_number')->sum($countFor)
        );
    }

    /**
     * The roster query, shared by the page and the live poll so the two can
     * never diverge in what they load (an eager-load missing from one of them
     * is an N+1 that only appears on that path).
     */
    private function rosterFor(ExamSession $examSession)
    {
        return $examSession->userTests()
            ->with(['user', 'currentModule.section', 'currentModule.sections'])
            // Answered-so-far is what stands in for a score while an attempt is
            // still running — a partial score would be meaningless and is not
            // computed until the module is submitted anyway.
            ->withCount(['userAnswers as answered_count' => fn ($query) => $query
                ->whereNotNull('selected_answer')
                ->where('selected_answer', '!=', '')])
            ->latest('updated_at')
            ->get();
    }

    public function updateStatus(UpdateExamSessionStatusRequest $request, ExamSession $examSession)
    {
        $this->authorize('manage', $examSession);
        $this->service->updateStatus($examSession, $request->validated()['status']);

        return back()->with('success', 'Session status updated.');
    }

    public function resetAttempt(ExamSession $examSession, UserTest $userTest)
    {
        $this->authorize('manage', $examSession);
        abort_unless((int) $userTest->exam_session_id === (int) $examSession->id, 404);

        $this->service->resetAttempt($userTest);

        return back()->with('success', 'Attempt reset. The candidate can retake the exam.');
    }

    public function forceSubmit(ExamSession $examSession, UserTest $userTest)
    {
        $this->authorize('manage', $examSession);
        abort_unless((int) $userTest->exam_session_id === (int) $examSession->id, 404);

        $this->service->forceSubmit($userTest);

        return back()->with('success', 'Attempt submitted.');
    }

    /**
     * Per-candidate attempt detail — the exam-session equivalent of
     * Teacher\AssignmentController::studentAttempt. It reuses that feature's
     * two leaf partials (`attempt-monitor-answer`, `question-preview`), which
     * are assignment-agnostic; the surrounding page is its own because the
     * assignment version is built around AssignmentReportService::buildRecipient
     * and multiple numbered attempts, neither of which exists here — an exam
     * session gives each candidate exactly one attempt.
     */
    public function showAttempt(ExamSession $examSession, UserTest $userTest)
    {
        $this->authorize('view', $examSession);
        abort_unless((int) $userTest->exam_session_id === (int) $examSession->id, 404);

        $examSession->load('test');
        $userTest->load([
            'user',
            'currentModule.section',
            'moduleSubmissions',
            'userAnswers.question.answerChoices',
            'userAnswers.question.sprCorrectAnswers',
            'userAnswers.module.section',
        ]);

        return view('teacher.exam-sessions.attempt', [
            'examSession' => $examSession,
            'attempt' => $userTest,
        ]);
    }

    public function questionPreview(ExamSession $examSession, UserTestAnswer $userAnswer)
    {
        $this->authorize('view', $examSession);
        // Ownership is checked through the answer's own attempt rather than
        // trusting the route pairing — otherwise any answer id in the app would
        // render for a teacher who owns any session at all.
        abort_unless(
            (int) $userAnswer->userTest?->exam_session_id === (int) $examSession->id,
            403
        );

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

        $module = $userAnswer->module()->with('section')->first();

        return view('teacher.assignments.partials.question-preview', [
            'userAnswer' => $userAnswer,
            'isInProgress' => $isInProgress,
            'correct' => $correct,
            // Opt-in metadata strip; the assignment monitor passes none and so
            // keeps its original header.
            'context' => [
                'sectionName' => $module?->section?->name,
                'moduleNumber' => $module?->module_number,
                'questionNumber' => $module
                    ? $module->questions()->pluck('questions.id')->search($userAnswer->question_id) + 1
                    : null,
            ],
        ]);
    }

    public function exportCsv(ExamSession $examSession)
    {
        $this->authorize('view', $examSession);

        $candidates = $examSession->userTests()->with('user')->latest('updated_at')->get();
        $filename = sprintf('exam-session-%s.csv', Str::slug($examSession->title ?: 'session'));

        return response()->streamDownload(function () use ($candidates) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Candidate', 'Status', 'R&W', 'Math', 'Total']);

            foreach ($candidates as $attempt) {
                fputcsv($handle, [
                    $attempt->guest_name ?: $attempt->user->name,
                    ucfirst(str_replace('_', ' ', $attempt->status)),
                    $attempt->score_reading_writing ?? '—',
                    $attempt->score_math ?? '—',
                    $attempt->total_score ?? '—',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Poll target for the live monitor (every 4s per open dashboard).
     *
     * Ships the roster as rendered HTML from the same Blade partial the page
     * uses on first paint, rather than JSON the client re-templates: the two
     * copies of that markup previously had to be kept in sync by hand, and any
     * drift only showed up four seconds after load, when the poll silently
     * replaced the styled rows.
     */
    public function liveStatus(ExamSession $examSession)
    {
        $this->authorize('view', $examSession);

        $candidates = $this->rosterFor($examSession);

        $scored = $candidates->whereNotNull('total_score');

        return response()->json([
            'session_status' => $examSession->status,
            'candidate_count' => $candidates->count(),
            'in_progress_count' => $candidates->where('status', 'in_progress')->count(),
            'completed_count' => $candidates->where('status', 'completed')->count(),
            'average_score' => $scored->isNotEmpty() ? round($scored->avg('total_score')) : null,
            'html' => view('teacher.exam-sessions.partials.candidate-rows', [
                'examSession' => $examSession,
                'candidates' => $candidates,
                'totalQuestions' => $this->questionsInTest($examSession->test),
            ])->render(),
        ]);
    }
}
