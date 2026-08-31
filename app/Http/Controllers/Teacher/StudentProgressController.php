<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ProgressChartService;
use App\Support\PerformanceAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class StudentProgressController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom, User $student, PerformanceAnalytics $analytics, ProgressChartService $chartService)
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

        $analyticsSummary = $analytics->summarize($completedTests);

        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $comparableScores = $scoredAttempts;

        $trendData = $chartService->trendData($comparableScores, $student->target_score);
        $radarData = $chartService->radarData($analyticsSummary['weakAreaSummaries']);
        $doughnutData = $chartService->doughnutData($completedTests);
        $heatmapData = $chartService->heatmapData($completedTests);
        $histogramData = $chartService->histogramData($completedTests);

        return view('teacher.students.progress', array_merge(
            [
                'classroom' => $classroom,
                'student' => $student,
                'completedTests' => $completedTests,
                'rosterItems' => $rosterItems,
                'comparableScores' => $comparableScores,
                'chartTrend' => $trendData,
                'chartRadar' => $radarData,
                'chartDoughnut' => $doughnutData,
                'chartHeatmap' => $heatmapData,
                'chartHistogram' => $histogramData,
            ],
            $analyticsSummary
        ));
    }

    public function exportPdf(
        Request $request,
        Classroom $classroom,
        User $student,
        \App\Services\ProgressDossierService $dossierService,
        \App\Services\ProgressPdfChartService $pdfChartService
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
        $history = $dossier['history'];
        $svgTotalTrend = $pdfChartService->renderDossierLineChartSvg($history, [
            'total' => ['label' => 'Total SAT', 'color' => '#2563eb', 'values' => array_column($history, 'total')],
            'rolling' => ['label' => '3-Test Avg', 'color' => '#0891b2', 'values' => array_column($history, 'rollingAverage')],
        ], 400, 1600, 546, 140);
        $svgSectionTrend = $pdfChartService->renderDossierLineChartSvg($history, [
            'rw' => ['label' => 'RW Section', 'color' => '#0284c7', 'values' => array_column($history, 'rw')],
            'math' => ['label' => 'Math Section', 'color' => '#c2410c', 'values' => array_column($history, 'math')],
        ], 200, 800, 268, 125);

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
            'svgTotalTrend' => $svgTotalTrend,
            'svgSectionTrend' => $svgSectionTrend,
        ])
            ->setPaper('letter')
            ->download($filename);

        return $response->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
