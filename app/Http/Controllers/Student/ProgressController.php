<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\ProgressChartService;
use App\Support\PerformanceAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ProgressController extends Controller
{
    public function __invoke(Request $request, PerformanceAnalytics $analytics, ProgressChartService $chartService)
    {
        $user = $request->user();

        $completedTests = $user->userTests()
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'scoreConversionSet', 'userAnswers.question'])
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc')
            ->limit(30)
            ->get();

        $analyticsSummary = $analytics->summarize($completedTests);

        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $comparableScores = $scoredAttempts;

        $trendData = $chartService->trendData($comparableScores, $user->target_score);
        $radarData = $chartService->radarData($analyticsSummary['weakAreaSummaries']);
        $doughnutData = $chartService->doughnutData($completedTests);
        $heatmapData = $chartService->heatmapData($completedTests);
        $histogramData = $chartService->histogramData($completedTests);

        return view('student.progress.index', array_merge(
            [
                'user' => $user,
                'completedTests' => $completedTests,
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

    public function updateGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_score' => ['nullable', 'integer', 'min:400', 'max:1600'],
        ]);

        $target = $validated['target_score'] ? (int) $validated['target_score'] : null;
        if ($target !== null) {
            // Round to nearest 10
            $target = (int) (round($target / 10) * 10);
        }

        $request->user()->update([
            'target_score' => $target,
        ]);

        return response()->json([
            'success' => true,
            'target_score' => $target,
        ]);
    }

    public function exportPdf(
        Request $request,
        \App\Services\ProgressDossierService $dossierService,
        \App\Services\ProgressPdfChartService $pdfChartService
    ): Response
    {
        $user = $request->user();

        $attemptQuery = $user->userTests()
            ->whereHas('test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'userAnswers'])
            ->where('status', 'completed')
            ->excludingAbsorbedSections()
            ->orderBy('completed_at', 'desc');
        if (Schema::hasTable('user_test_answer_reviews')) {
            $attemptQuery->with('userAnswers.review');
        }
        $completedTests = $attemptQuery->get();

        $dossier = $dossierService->build($completedTests, $user->target_score);
        $history = $dossier['history'];
        $svgTotalTrend = $pdfChartService->renderDossierLineChartSvg($history, [
            'total' => ['label' => 'Total SAT', 'color' => '#2563eb', 'values' => array_column($history, 'total')],
            'rolling' => ['label' => '3-Test Avg', 'color' => '#0891b2', 'values' => array_column($history, 'rollingAverage')],
        ], 400, 1600, 546, 140);
        $svgSectionTrend = $pdfChartService->renderDossierLineChartSvg($history, [
            'rw' => ['label' => 'RW Section', 'color' => '#0284c7', 'values' => array_column($history, 'rw')],
            'math' => ['label' => 'Math Section', 'color' => '#c2410c', 'values' => array_column($history, 'math')],
        ], 200, 800, 268, 125);

        $classroomMembership = $user->classroomMemberships()
            ->where('status', 'active')
            ->with('classroom.owner')
            ->first();
        $classroom = $classroomMembership?->classroom;
        $teacher = $classroom?->owner;

        $lang = strtolower($request->query('lang', 'vi'));
        $viewName = $lang === 'en' ? 'pdf.progress-report' : 'pdf.progress-report-vi';

        $filename = sprintf(
            'progress-report-%s-%s.pdf',
            Str::slug($user->name ?: 'student'),
            now()->format('Y-m-d')
        );

        /** @var \Illuminate\Http\Response $response */
        $response = Pdf::loadView($viewName, [
            'student' => $user,
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
