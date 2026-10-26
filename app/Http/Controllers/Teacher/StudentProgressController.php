<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ProgressChartService;
use App\Services\ProgressDossierService;
use App\Services\ProgressPdfChartService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentProgressController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, User $student, ProgressDossierService $dossierService, ProgressChartService $chartService): View
    {
        $this->authorize('viewStudent', [$classroom, $student]);
        $teacher = $request->user();

        $completedTests = UserTest::query()
            ->where('user_id', $student->id)
            ->where('status', 'completed')
            ->visibleToTeacher($teacher)
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'scoreConversionSet', 'userAnswers.question', 'assignment.classroom'])
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc')
            ->limit(30)
            ->get();

        $rosterItems = $classroom->activeMemberships()->with('student')->orderByDesc('decided_at')->get();

        $dossier = $dossierService->build($completedTests, $student->target_score);
        $charts = $chartService->buildWebCharts($dossier);

        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $comparableScores = $scoredAttempts;

        return view('teacher.students.progress', array_merge(
            [
                'classroom' => $classroom,
                'student' => $student,
                'completedTests' => $completedTests,
                'rosterItems' => $rosterItems,
                'comparableScores' => $comparableScores,
            ],
            $charts,
            $dossier
        ));
    }

    public function exportPdf(
        Request $request,
        Classroom $classroom,
        User $student,
        ProgressDossierService $dossierService,
        ProgressPdfChartService $pdfChartService
    ): Response
    {
        $this->authorize('viewStudent', [$classroom, $student]);
        $teacher = $request->user();

        $attemptQuery = UserTest::query()
            ->where('user_id', $student->id)
            ->where('status', 'completed')
            ->visibleToTeacher($teacher)
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'userAnswers', 'assignment.classroom'])
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc');
        if (Schema::hasTable('user_test_answer_reviews')) {
            $attemptQuery->with('userAnswers.review');
        }
        $completedTests = $attemptQuery->get();

        $dossier = $dossierService->build($completedTests, $student->target_score);
        $pdfCharts = $pdfChartService->forDossier($dossier);

        $lang = strtolower($request->query('lang', 'vi'));
        $viewName = $lang === 'en' ? 'pdf.progress-report' : 'pdf.progress-report-vi';

        $filename = sprintf(
            'student-progress-%s-%s-%s.pdf',
            Str::slug($student->name ?: 'student'),
            Str::slug($classroom->name ?: 'class'),
            now()->format('Y-m-d')
        );

        /** @var \Illuminate\Http\Response $response */
        $response = Pdf::loadView($viewName, [
            'student' => $student,
            'teacher' => $teacher,
            'classroom' => $classroom,
            'dossier' => $dossier,
            'svgTotalTrend' => $pdfCharts['svgTotalTrend'],
            'svgSectionTrend' => $pdfCharts['svgSectionTrend'],
        ])
            ->setPaper('letter')
            ->download($filename);

        return $response->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
