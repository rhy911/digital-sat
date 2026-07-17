<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('student.analytics.index', $this->homeData($request));
    }

    private function homeData(Request $request): array
    {
        $user = $request->user();
        $completedTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test', 'scoreConversionSet', 'userAnswers.question'])
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(15)
            ->get();

        $inProgressTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['test.sections.modules', 'currentModule'])
            ->where('status', 'in_progress')
            ->orderBy('updated_at', 'desc')
            ->get();

        return array_merge([
            'user' => $user,
            'completedTests' => $completedTests,
            'inProgressTests' => $inProgressTests,
        ], $this->performanceSummaries($completedTests));
    }

    /**
     * Cross-attempt weak-area, difficulty, and pacing summaries across the
     * student's recent completed attempts (see design/global_design_direction.md).
     */
    private function performanceSummaries(Collection $completedTests): array
    {
        $domainStats = [];
        $difficultyStats = [];
        $pacingStats = [];

        foreach ($completedTests as $userTest) {
            foreach ($userTest->userAnswers as $answer) {
                $question = $answer->question;
                if (!$question || $question->is_pretest) {
                    continue;
                }

                $section = $question->section_type === 'math' ? 'math' : 'reading_and_writing';
                $domain = $question->skill_domain ?? 'other';
                $difficulty = strtolower($question->difficulty ?? 'unknown');
                $isCorrect = (bool) $answer->is_correct;

                $domainKey = "{$section}:{$domain}";
                if (!isset($domainStats[$domainKey])) {
                    $domainStats[$domainKey] = ['section' => $section, 'domain' => $domain, 'total' => 0, 'correct' => 0];
                }
                $domainStats[$domainKey]['total']++;
                $domainStats[$domainKey]['correct'] += $isCorrect ? 1 : 0;

                if (!isset($difficultyStats[$difficulty])) {
                    $difficultyStats[$difficulty] = ['total' => 0, 'correct' => 0];
                }
                $difficultyStats[$difficulty]['total']++;
                $difficultyStats[$difficulty]['correct'] += $isCorrect ? 1 : 0;

                $expectedTime = $question->expected_time ?? 0;
                if ($expectedTime > 0) {
                    if (!isset($pacingStats[$difficulty])) {
                        $pacingStats[$difficulty] = ['count' => 0, 'timeSpent' => 0, 'expectedTime' => 0];
                    }
                    $pacingStats[$difficulty]['count']++;
                    $pacingStats[$difficulty]['timeSpent'] += (int) $answer->time_spent;
                    $pacingStats[$difficulty]['expectedTime'] += $expectedTime;
                }
            }
        }

        return [
            'weakAreaSummaries' => $this->weakAreaSummaries($domainStats),
            'difficultyPerformanceSummaries' => $this->difficultyPerformanceSummaries($difficultyStats),
            'pacingSummaries' => $this->pacingSummaries($pacingStats),
        ];
    }

    private function weakAreaSummaries(array $domainStats): array
    {
        $rows = [];
        foreach ($domainStats as $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;

            $rows[] = [
                'section' => $data['section'] === 'math' ? 'Math' : 'Reading and Writing',
                'domain' => $this->domainLabel($data['domain']),
                'total' => $data['total'],
                'correct' => $data['correct'],
                'percentCorrect' => $percentCorrect,
                'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
            ];
        }

        usort($rows, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

        return $rows;
    }

    private function difficultyPerformanceSummaries(array $difficultyStats): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $rows = [];

        foreach ($difficultyStats as $difficulty => $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;

            $rows[] = [
                'difficulty' => $difficulty,
                'label' => ucfirst($difficulty),
                'total' => $data['total'],
                'correct' => $data['correct'],
                'percentCorrect' => $percentCorrect,
                'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function pacingSummaries(array $pacingStats): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $rows = [];

        foreach ($pacingStats as $difficulty => $data) {
            $avgTimeSpent = $data['count'] > 0 ? (int) round($data['timeSpent'] / $data['count']) : 0;
            $avgExpectedTime = $data['count'] > 0 ? (int) round($data['expectedTime'] / $data['count']) : 0;
            $pacePercent = $avgExpectedTime > 0 ? (int) round(($avgTimeSpent / $avgExpectedTime) * 100) : 0;

            $rows[] = [
                'difficulty' => $difficulty,
                'label' => ucfirst($difficulty),
                'avgTimeSpent' => $avgTimeSpent,
                'avgExpectedTime' => $avgExpectedTime,
                'pacePercent' => $pacePercent,
                'pace' => $pacePercent > 120 ? 'Slow' : ($pacePercent < 80 ? 'Fast' : 'On pace'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function domainLabel(string $domain): string
    {
        return [
            'craft_and_structure' => 'Craft and Structure',
            'information_and_ideas' => 'Information and Ideas',
            'standard_english_conventions' => 'Standard English Conventions',
            'expression_of_ideas' => 'Expression of Ideas',
            'algebra' => 'Algebra',
            'advanced_math' => 'Advanced Math',
            'problem_solving' => 'Problem-Solving and Data Analysis',
            'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
            'geometry' => 'Geometry and Trigonometry',
            'geometry_and_trigonometry' => 'Geometry and Trigonometry',
        ][$domain] ?? Str::of($domain)->replace('_', ' ')->title()->toString();
    }
}
