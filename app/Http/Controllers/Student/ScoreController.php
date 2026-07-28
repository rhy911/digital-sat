<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Services\ScoreReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ScoreController extends Controller
{
    public function __construct(private readonly ScoreReportService $reportService) {}

    public function index(): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        $latest = UserTest::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc')
            ->first();

        if (! $latest) {
            return view('student.scores.empty', ['user' => Auth::user()]);
        }

        return redirect()->route('student.scores.show', $latest);
    }

    public function show(UserTest $userTest): \Illuminate\View\View
    {
        $authUser = Auth::user();
        $this->authorize('view', $userTest);
        $userTest->loadMissing(['user', 'assignment.classroom']);

        $attempts = UserTest::with(['test', 'assignment.classroom'])
            ->where('user_id', $userTest->user_id)
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc')
            ->get();

        // An absorbed section attempt is hidden from the list but can still be
        // opened directly (e.g. from an assignment report) — keep it visible so
        // the sidebar always contains the attempt being shown.
        if (! $attempts->contains('id', $userTest->id)) {
            $attempts = $attempts->push($userTest->loadMissing('test'))->sortByDesc('completed_at')->values();
        }

        return view('student.scores.show', array_merge(
            ['user' => $userTest->user, 'authUser' => $authUser, 'attempts' => $attempts, 'selectedUlid' => $userTest->ulid],
            $this->reportService->buildReportData($this->loadForReport($userTest))
        ));
    }

    public function exportPdf(UserTest $userTest): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('view', $userTest);

        $report   = $this->reportService->buildReportData($this->loadForReport($userTest));
        $filename = sprintf(
            'score-report-%s-%s.pdf',
            Str::slug($report['userTest']->test->title ?: 'test-result'),
            optional($report['userTest']->completed_at)->format('Y-m-d') ?: now()->format('Y-m-d')
        );

        return Pdf::loadView('student.scores.export-pdf', $report)
            ->setPaper('letter')
            ->download($filename);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loadForReport(UserTest $userTest): UserTest
    {
        $userTest->load([
            'test',
            'user',
            'scoreConversionSet',
            'userAnswers.module',
            'userAnswers.question.explanation',
            'userAnswers.question.answerChoices',
            'userAnswers.question.sprCorrectAnswers',
        ]);

        return $userTest;
    }
}
