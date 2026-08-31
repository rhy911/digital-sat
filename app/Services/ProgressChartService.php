<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProgressChartService
{
    public const RW_DOMAINS = [
        'craft_and_structure' => 'Craft and Structure',
        'information_and_ideas' => 'Information and Ideas',
        'standard_english_conventions' => 'Standard English Conventions',
        'expression_of_ideas' => 'Expression of Ideas',
    ];

    public const MATH_DOMAINS = [
        'algebra' => 'Algebra',
        'advanced_math' => 'Advanced Math',
        'problem_solving_data_analysis' => 'Problem-Solving and Data Analysis',
        'geometry_trigonometry' => 'Geometry and Trigonometry',
    ];

    /**
     * Build Chart.js line dataset for score trend (Total, RW, Math).
     * Attempts should already be filtered to comparable scores.
     */
    public function trendData(Collection $attempts, ?int $targetScore = null): array
    {
        $chronological = $attempts->reverse()->values();

        $labels = [];
        $totalScores = [];
        $rwScores = [];
        $mathScores = [];
        $points = [];

        foreach ($chronological as $index => $attempt) {
            $date = $attempt->completed_at ? $attempt->completed_at->format('M j') : '#' . ($index + 1);
            $labels[] = $date;
            $total = $attempt->total_score !== null ? (int) $attempt->total_score : null;
            $rw = $attempt->score_reading_writing !== null ? (int) $attempt->score_reading_writing : null;
            $math = $attempt->score_math !== null ? (int) $attempt->score_math : null;

            $totalScores[] = $total;
            $rwScores[] = $rw;
            $mathScores[] = $math;

            $points[] = [
                'id' => $attempt->id,
                'ulid' => $attempt->ulid,
                'title' => $attempt->test?->title ?? 'Practice Test',
                'date' => $attempt->completed_at ? $attempt->completed_at->format('M j, Y') : null,
                'total' => $total,
                'rw' => $rw,
                'math' => $math,
            ];
        }

        return [
            'labels' => $labels,
            'total' => $totalScores,
            'rw' => $rwScores,
            'math' => $mathScores,
            'points' => $points,
            'targetScore' => $targetScore,
            'count' => count($labels),
        ];
    }

    /**
     * Build radar chart datasets for RW and Math domains.
     */
    public function radarData(array $weakAreaSummaries): array
    {
        // Map domain name or normalized key to percent
        $domainPercentMap = [];
        foreach ($weakAreaSummaries as $row) {
            $domainName = $row['domain'] ?? '';
            $pct = (int) ($row['percentCorrect'] ?? 0);
            $domainPercentMap[$domainName] = $pct;
        }

        $rwLabels = array_values(self::RW_DOMAINS);
        $rwData = [];
        foreach (self::RW_DOMAINS as $key => $label) {
            $rwData[] = $domainPercentMap[$label] ?? 0;
        }

        $mathLabels = [];
        $mathData = [];
        foreach (self::MATH_DOMAINS as $key => $label) {
            if ($key === 'geometry_trigonometry' || $key === 'geometry_and_trigonometry') {
                $hasGeo = isset($domainPercentMap['Geometry and Trigonometry'])
                    || isset($domainPercentMap['Geometry'])
                    || isset($domainPercentMap['Geometry & Trig']);
                $mathLabels[] = $hasGeo ? 'Geometry and Trigonometry' : 'Spatial & Measurement';
            } else {
                $mathLabels[] = $label;
            }

            $val = match ($key) {
                'problem_solving_data_analysis' => $domainPercentMap['Problem-Solving and Data Analysis']
                    ?? $domainPercentMap['Problem-Solving & Data']
                    ?? 0,
                'geometry_trigonometry' => $domainPercentMap['Geometry and Trigonometry']
                    ?? $domainPercentMap['Geometry']
                    ?? $domainPercentMap['Geometry & Trig']
                    ?? 0,
                default => $domainPercentMap[$label] ?? 0,
            };
            $mathData[] = $val;
        }

        return [
            'rw' => [
                'labels' => $rwLabels,
                'data' => $rwData,
            ],
            'math' => [
                'labels' => $mathLabels,
                'data' => $mathData,
            ],
        ];
    }

    /**
     * Build section split doughnut dataset: overall correct percentage for RW vs Math.
     */
    public function doughnutData(Collection $attempts): array
    {
        $rwTotal = 0;
        $rwCorrect = 0;
        $mathTotal = 0;
        $mathCorrect = 0;

        foreach ($attempts as $test) {
            foreach ($test->userAnswers as $answer) {
                $q = $answer->question;
                if (!$q || $q->is_pretest) {
                    continue;
                }

                $isMath = $q->section_type === 'math';
                $isCorrect = (bool) $answer->is_correct;

                if ($isMath) {
                    $mathTotal++;
                    if ($isCorrect) {
                        $mathCorrect++;
                    }
                } else {
                    $rwTotal++;
                    if ($isCorrect) {
                        $rwCorrect++;
                    }
                }
            }
        }

        $rwPct = $rwTotal > 0 ? (int) round(($rwCorrect / $rwTotal) * 100) : 0;
        $mathPct = $mathTotal > 0 ? (int) round(($mathCorrect / $mathTotal) * 100) : 0;
        $overallTotal = $rwTotal + $mathTotal;
        $overallCorrect = $rwCorrect + $mathCorrect;
        $overallPct = $overallTotal > 0 ? (int) round(($overallCorrect / $overallTotal) * 100) : 0;

        return [
            'labels' => ['Reading & Writing', 'Math'],
            'data' => [$rwPct, $mathPct],
            'counts' => [
                'rw' => ['correct' => $rwCorrect, 'total' => $rwTotal, 'pct' => $rwPct],
                'math' => ['correct' => $mathCorrect, 'total' => $mathTotal, 'pct' => $mathPct],
                'overall' => ['correct' => $overallCorrect, 'total' => $overallTotal, 'pct' => $overallPct],
            ],
        ];
    }

    /**
     * Cross-tabulate 8 domains by 3 difficulty tiers (Easy, Medium, Hard).
     */
    public function heatmapData(Collection $attempts): array
    {
        $allDomains = [
            'Reading & Writing' => self::RW_DOMAINS,
            'Math' => self::MATH_DOMAINS,
        ];

        $matrix = [];
        foreach ($allDomains as $section => $domains) {
            foreach ($domains as $key => $label) {
                $matrix[$section][$key] = [
                    'label' => $label,
                    'easy' => ['correct' => 0, 'total' => 0, 'pct' => null],
                    'medium' => ['correct' => 0, 'total' => 0, 'pct' => null],
                    'hard' => ['correct' => 0, 'total' => 0, 'pct' => null],
                    'total' => ['correct' => 0, 'total' => 0, 'pct' => null],
                ];
            }
        }

        foreach ($attempts as $test) {
            foreach ($test->userAnswers as $answer) {
                $q = $answer->question;
                if (!$q || $q->is_pretest) {
                    continue;
                }

                $section = $q->section_type === 'math' ? 'Math' : 'Reading & Writing';
                $domainKey = $this->normalizeDomainKey($q->skill_domain ?? 'other');
                $difficulty = strtolower($q->difficulty ?? 'medium');
                if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                    $difficulty = 'medium';
                }

                if (!isset($matrix[$section][$domainKey])) {
                    continue;
                }

                $isCorrect = (bool) $answer->is_correct;

                $matrix[$section][$domainKey][$difficulty]['total']++;
                if ($isCorrect) {
                    $matrix[$section][$domainKey][$difficulty]['correct']++;
                }

                $matrix[$section][$domainKey]['total']['total']++;
                if ($isCorrect) {
                    $matrix[$section][$domainKey]['total']['correct']++;
                }
            }
        }

        // Compute percentages and filter to domains with at least 1 attempted question
        $filtered = [];
        foreach ($matrix as $section => $domains) {
            foreach ($domains as $key => $row) {
                if ($row['total']['total'] === 0) {
                    continue;
                }
                foreach (['easy', 'medium', 'hard', 'total'] as $tier) {
                    $tot = $row[$tier]['total'];
                    $cor = $row[$tier]['correct'];
                    $row[$tier]['pct'] = $tot > 0 ? (int) round(($cor / $tot) * 100) : null;
                }
                $filtered[$section][$key] = $row;
            }
        }

        return $filtered;
    }

    /**
     * Compute distribution of question time spent across 6 buckets,
     * separated by correct and incorrect answers.
     */
    public function histogramData(Collection $attempts): array
    {
        $buckets = [
            '0-15s' => ['correct' => 0, 'incorrect' => 0],
            '15-30s' => ['correct' => 0, 'incorrect' => 0],
            '30-60s' => ['correct' => 0, 'incorrect' => 0],
            '60-90s' => ['correct' => 0, 'incorrect' => 0],
            '90-120s' => ['correct' => 0, 'incorrect' => 0],
            '120s+' => ['correct' => 0, 'incorrect' => 0],
        ];

        foreach ($attempts as $test) {
            foreach ($test->userAnswers as $answer) {
                $q = $answer->question;
                if (!$q || $q->is_pretest) {
                    continue;
                }

                $time = (int) $answer->time_spent;
                $isCorrect = (bool) $answer->is_correct;
                $status = $isCorrect ? 'correct' : 'incorrect';

                if ($time < 15) {
                    $buckets['0-15s'][$status]++;
                } elseif ($time < 30) {
                    $buckets['15-30s'][$status]++;
                } elseif ($time < 60) {
                    $buckets['30-60s'][$status]++;
                } elseif ($time < 90) {
                    $buckets['60-90s'][$status]++;
                } elseif ($time < 120) {
                    $buckets['90-120s'][$status]++;
                } else {
                    $buckets['120s+'][$status]++;
                }
            }
        }

        $labels = array_keys($buckets);
        $correctData = array_map(fn ($b) => $b['correct'], array_values($buckets));
        $incorrectData = array_map(fn ($b) => $b['incorrect'], array_values($buckets));

        return [
            'labels' => $labels,
            'correct' => $correctData,
            'incorrect' => $incorrectData,
            'totalQuestions' => array_sum($correctData) + array_sum($incorrectData),
        ];
    }

    private function normalizeDomainKey(string $domain): string
    {
        $d = strtolower(trim($domain));
        if (str_contains($d, 'problem')) {
            return 'problem_solving_data_analysis';
        }
        if (str_contains($d, 'geometry') || str_contains($d, 'trig')) {
            return 'geometry_trigonometry';
        }
        if (str_contains($d, 'algebra') && !str_contains($d, 'advanced')) {
            return 'algebra';
        }
        if (str_contains($d, 'advanced')) {
            return 'advanced_math';
        }
        if (str_contains($d, 'craft')) {
            return 'craft_and_structure';
        }
        if (str_contains($d, 'information')) {
            return 'information_and_ideas';
        }
        if (str_contains($d, 'standard') || str_contains($d, 'convention')) {
            return 'standard_english_conventions';
        }
        if (str_contains($d, 'expression')) {
            return 'expression_of_ideas';
        }

        return Str::snake($domain);
    }
}
