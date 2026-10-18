<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Services\ScoreReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

    public function show(UserTest $userTest): \Illuminate\Http\RedirectResponse|\Illuminate\View\View|\Illuminate\Http\Response
    {
        $authUser = Auth::user();
        $this->authorize('view', $userTest);

        if ($userTest->status !== 'completed') {
            if ($authUser->role === 'student') {
                if ($userTest->status === 'in_progress') {
                    return redirect()->route('my-practice', $userTest);
                }

                return redirect()->route('home.practice')->with('error', 'That practice attempt is no longer available.');
            }

            return response()->view('student.scores.unavailable', [
                'user' => $userTest->user,
                'authUser' => $authUser,
                'userTest' => $userTest,
            ], 409);
        }

        $userTest->loadMissing(['user', 'assignment.classroom', 'examSession']);

        $attemptsQuery = UserTest::with(['test', 'assignment.classroom', 'examSession'])
            ->where('user_id', $userTest->user_id)
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc');

        if ($authUser->role === 'teacher') {
            $attemptsQuery->where(function ($query) use ($authUser) {
                $query->whereHas('assignment.classroom', function ($cq) use ($authUser) {
                    $cq->where('owner_id', $authUser->id)
                        ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($authUser->id));
                })->orWhereHas('examSession', function ($eq) use ($authUser) {
                    $eq->where('teacher_id', $authUser->id);
                });
            });
        }

        $attempts = $attemptsQuery->get();

        // An absorbed section attempt is hidden from the list but can still be
        // opened directly (e.g. from an assignment report) — keep it visible so
        // the sidebar always contains the attempt being shown.
        if (! $attempts->contains('id', $userTest->id)) {
            $attempts = $attempts->push($userTest->loadMissing('test'))->sortByDesc('completed_at')->values();
        }

        $classroom = $userTest->assignment?->classroom;
        $progressUrl = match (true) {
            $authUser->role === 'student' => route('student.progress'),
            $authUser->role === 'teacher' && (bool) $classroom => route('teacher.classes.students.progress', [
                'classroom' => $classroom,
                'student' => $userTest->user_id,
            ]),
            $authUser->role === 'teacher' => route('teacher.progress'),
            default => route('student.progress'),
        };

        return view('student.scores.show', array_merge(
            [
                'user' => $userTest->user,
                'authUser' => $authUser,
                'attempts' => $attempts,
                'selectedUlid' => $userTest->ulid,
                'progressUrl' => $progressUrl,
            ],
            $this->reportService->buildReportData($this->loadForReport($userTest))
        ));
    }

    public function exportPdf(UserTest $userTest): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('view', $userTest);
        abort_if($userTest->status !== 'completed', 409, 'Score report PDF is unavailable until the attempt is completed.');

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
        $relations = [
            'test',
            'user',
            'examSession',
            'assignment.classroom',
            'scoreConversionSet',
            'userAnswers.module',
            'userAnswers.question.explanation',
            'userAnswers.question.answerChoices',
            'userAnswers.question.sprCorrectAnswers',
            'userAnswers.question.passage',
        ];
        if (Schema::hasTable('user_test_answer_reviews')) {
            $relations[] = 'userAnswers.review';
        }
        $userTest->load($relations);

        return $userTest;
    }
}
