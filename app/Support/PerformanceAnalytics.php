<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cross-attempt weak-area, difficulty, and pacing summaries across a set of
 * completed UserTest attempts (see design/global_design_direction.md).
 * Shared by the student's own Progress page and the teacher's per-student view.
 */
class PerformanceAnalytics
{
    public function summarize(Collection $completedTests): array
    {
        $domainStats = [];
        $difficultyStats = [];
        $pacingStats = [];

        $skippedCount = 0;
        $stuckCount = 0;
        $rushedCount = 0;

        $timeMatrix = [
            'math' => [
                'correct' => ['total' => 0, 'count' => 0],
                'incorrect' => ['total' => 0, 'count' => 0]
            ],
            'reading_and_writing' => [
                'correct' => ['total' => 0, 'count' => 0],
                'incorrect' => ['total' => 0, 'count' => 0]
            ]
        ];

        foreach ($completedTests as $userTest) {
            foreach ($userTest->userAnswers as $answer) {
                $question = $answer->question;
                if (!$question || $question->is_pretest) {
                    continue;
                }

                $section = $question->section_type === 'math' ? 'math' : 'reading_and_writing';
                $domain = $question->skill_domain ?? 'other';
                $subdomain = $question->skill_subdomain ?? 'other';
                $difficulty = strtolower($question->difficulty ?? 'unknown');
                $isCorrect = (bool) $answer->is_correct;
                $timeSpent = (int) $answer->time_spent;
                $hasAnswered = !empty($answer->selected_answer);
                $expectedTime = $question->expected_time ?: QuestionPacing::expectedSeconds($question->section_type, $difficulty);

                if (!$hasAnswered) {
                    $skippedCount++;
                } else {
                    if (!$isCorrect && $timeSpent > $expectedTime * 1.5) {
                        $stuckCount++;
                    }
                    if ($isCorrect && $timeSpent < $expectedTime * 0.4) {
                        $rushedCount++;
                    }
                }

                $timeMatrix[$section][$isCorrect ? 'correct' : 'incorrect']['total'] += $timeSpent;
                $timeMatrix[$section][$isCorrect ? 'correct' : 'incorrect']['count']++;

                $domainKey = "{$section}:{$domain}";
                if (!isset($domainStats[$domainKey])) {
                    $domainStats[$domainKey] = [
                        'section' => $section,
                        'domain' => $domain,
                        'total' => 0,
                        'correct' => 0,
                        'skills' => []
                    ];
                }
                $domainStats[$domainKey]['total']++;
                $domainStats[$domainKey]['correct'] += $isCorrect ? 1 : 0;

                if (!isset($domainStats[$domainKey]['skills'][$subdomain])) {
                    $domainStats[$domainKey]['skills'][$subdomain] = [
                        'subdomain' => $subdomain,
                        'total' => 0,
                        'correct' => 0
                    ];
                }
                $domainStats[$domainKey]['skills'][$subdomain]['total']++;
                $domainStats[$domainKey]['skills'][$subdomain]['correct'] += $isCorrect ? 1 : 0;

                if (!isset($difficultyStats[$difficulty])) {
                    $difficultyStats[$difficulty] = ['total' => 0, 'correct' => 0];
                }
                $difficultyStats[$difficulty]['total']++;
                $difficultyStats[$difficulty]['correct'] += $isCorrect ? 1 : 0;

                if ($expectedTime > 0) {
                    if (!isset($pacingStats[$difficulty])) {
                        $pacingStats[$difficulty] = ['count' => 0, 'timeSpent' => 0, 'expectedTime' => 0];
                    }
                    $pacingStats[$difficulty]['count']++;
                    $pacingStats[$difficulty]['timeSpent'] += $timeSpent;
                    $pacingStats[$difficulty]['expectedTime'] += $expectedTime;
                }
            }
        }

        $weakSummaries = $this->weakAreaSummaries($domainStats);

        // Generate personalized recommendations from poorest sub-skills
        $recommendations = [];
        $flatSkills = [];
        foreach ($weakSummaries as $domainRow) {
            foreach ($domainRow['skills'] as $skill) {
                $flatSkills[] = [
                    'name' => $skill['name'],
                    'pct' => $skill['percentCorrect'],
                    'domain' => $domainRow['domain'],
                ];
            }
        }
        usort($flatSkills, fn ($a, $b) => $a['pct'] <=> $b['pct']);

        $worstSkills = array_slice($flatSkills, 0, 3);
        foreach ($worstSkills as $skill) {
            if ($skill['pct'] < 75) {
                if (Str::contains(strtolower($skill['domain']), 'math')) {
                    $rec = "Focus study on **{$skill['name']}** (accuracy: {$skill['pct']}%). Focus on formulas and practice with the Desmos calculator to lock in speed.";
                } else {
                    $rec = "Practice **{$skill['name']}** (accuracy: {$skill['pct']}%). Re-read passages carefully and mark structural transition keywords to spot correct choices.";
                }
                $recommendations[] = $rec;
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = "Great work! You have strong accuracy across all content domains. Keep taking practice tests to maintain consistency!";
        }

        return [
            'weakAreaSummaries' => $weakSummaries,
            'difficultyPerformanceSummaries' => $this->difficultyPerformanceSummaries($difficultyStats),
            'pacingSummaries' => $this->pacingSummaries($pacingStats),
            'recommendations' => $recommendations,
            'skippedCount' => $skippedCount,
            'stuckCount' => $stuckCount,
            'rushedCount' => $rushedCount,
            'timeMatrix' => [
                'math' => [
                    'correctAvg' => $timeMatrix['math']['correct']['count'] > 0 ? (int) round($timeMatrix['math']['correct']['total'] / $timeMatrix['math']['correct']['count']) : 0,
                    'incorrectAvg' => $timeMatrix['math']['incorrect']['count'] > 0 ? (int) round($timeMatrix['math']['incorrect']['total'] / $timeMatrix['math']['incorrect']['count']) : 0,
                ],
                'reading_and_writing' => [
                    'correctAvg' => $timeMatrix['reading_and_writing']['correct']['count'] > 0 ? (int) round($timeMatrix['reading_and_writing']['correct']['total'] / $timeMatrix['reading_and_writing']['correct']['count']) : 0,
                    'incorrectAvg' => $timeMatrix['reading_and_writing']['incorrect']['count'] > 0 ? (int) round($timeMatrix['reading_and_writing']['incorrect']['total'] / $timeMatrix['reading_and_writing']['incorrect']['count']) : 0,
                ],
            ]
        ];
    }

    private function weakAreaSummaries(array $domainStats): array
    {
        $rows = [];
        foreach ($domainStats as $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;

            $aggregatedSkills = [];
            foreach ($data['skills'] as $skillData) {
                $skillName = $this->subdomainLabel($skillData['subdomain']);
                if (!isset($aggregatedSkills[$skillName])) {
                    $aggregatedSkills[$skillName] = [
                        'name' => $skillName,
                        'total' => 0,
                        'correct' => 0,
                    ];
                }
                $aggregatedSkills[$skillName]['total'] += $skillData['total'];
                $aggregatedSkills[$skillName]['correct'] += $skillData['correct'];
            }

            $skillsList = [];
            foreach ($aggregatedSkills as $skill) {
                $skillPercent = $skill['total'] > 0 ? (int) round(($skill['correct'] / $skill['total']) * 100) : 0;
                $skillsList[] = [
                    'name' => $skill['name'],
                    'total' => $skill['total'],
                    'correct' => $skill['correct'],
                    'percentCorrect' => $skillPercent,
                    'performance' => $skillPercent >= 80 ? 'High' : ($skillPercent >= 50 ? 'Medium' : 'Low'),
                ];
            }
            usort($skillsList, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

            $rows[] = [
                'section' => $data['section'] === 'math' ? 'Math' : 'Reading and Writing',
                'domain' => $this->domainLabel($data['domain']),
                'total' => $data['total'],
                'correct' => $data['correct'],
                'percentCorrect' => $percentCorrect,
                'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
                'skills' => $skillsList,
            ];
        }

        usort($rows, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

        return $rows;
    }

    private function subdomainLabel(string $subdomain): string
    {
        return SatTaxonomy::subdomainLabel($subdomain);
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
        return SatTaxonomy::domainLabel($domain);
    }
}
