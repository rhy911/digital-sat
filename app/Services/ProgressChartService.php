<?php

namespace App\Services;

use App\Support\SatTaxonomy;

class ProgressChartService
{
    /**
     * Build all Chart.js view payloads from the canonical dossier contract.
     */
    public function buildWebCharts(array $dossier): array
    {
        $targetScore = $dossier['score']['target'] ?? null;

        return [
            'chartTrend' => $this->trendData($dossier['history'] ?? [], $targetScore),
            'chartRadar' => $this->radarData($dossier['weakAreaSummaries'] ?? []),
            'chartDoughnut' => $dossier['sectionAccuracy'],
            'chartHeatmap' => $this->heatmapData($dossier['domainDifficultyStats'] ?? []),
            'chartHistogram' => $this->histogramData($dossier['timeBuckets'] ?? []),
        ];
    }

    /**
     * Build Chart.js line dataset for score trend (Total, RW, Math) from dossier history.
     *
     * @param list<array<string, mixed>> $history
     */
    public function trendData(array $history, ?int $targetScore = null): array
    {
        $labels = [];
        $totalScores = [];
        $rwScores = [];
        $mathScores = [];
        $points = [];

        foreach ($history as $item) {
            $labels[] = $item['shortDate'] ?? $item['date'] ?? ('#' . ($item['index'] ?? 1));
            $totalScores[] = $item['total'] ?? null;
            $rwScores[] = $item['rw'] ?? null;
            $mathScores[] = $item['math'] ?? null;
            $points[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? 'Practice Test',
                'date' => $item['date'] ?? null,
                'total' => $item['total'] ?? null,
                'rw' => $item['rw'] ?? null,
                'math' => $item['math'] ?? null,
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
     * Build radar chart datasets for RW and Math domains from weakAreaSummaries.
     *
     * @param list<array<string, mixed>> $weakAreaSummaries
     */
    public function radarData(array $weakAreaSummaries): array
    {
        $domainPercentMap = [];
        foreach ($weakAreaSummaries as $row) {
            $domainName = $row['domain'] ?? '';
            $pct = (int) ($row['percentCorrect'] ?? 0);
            $domainPercentMap[$domainName] = $pct;
        }

        $rwDomains = SatTaxonomy::domains('reading_writing');
        $rwLabels = array_map(fn ($k) => SatTaxonomy::domainLabel($k), $rwDomains);
        $rwData = [];
        foreach ($rwLabels as $label) {
            $rwData[] = $domainPercentMap[$label] ?? 0;
        }

        $mathDomains = SatTaxonomy::domains('math');
        $mathLabels = [];
        $mathData = [];
        foreach ($mathDomains as $key) {
            $label = SatTaxonomy::domainLabel($key);
            if ($key === 'geometry_trigonometry') {
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
     * Adapt domain difficulty statistics into a presentation matrix for views.
     */
    public function heatmapData(array $domainDifficultyStats): array
    {
        $allDomains = SatTaxonomy::domainMatrix();
        $matrix = [];

        foreach ($allDomains as $section => $domains) {
            foreach ($domains as $key => $label) {
                $stats = $domainDifficultyStats[$section][$key] ?? [];
                $row = [
                    'label' => $label,
                    'easy' => ['correct' => $stats['easy']['correct'] ?? 0, 'total' => $stats['easy']['total'] ?? 0, 'pct' => null],
                    'medium' => ['correct' => $stats['medium']['correct'] ?? 0, 'total' => $stats['medium']['total'] ?? 0, 'pct' => null],
                    'hard' => ['correct' => $stats['hard']['correct'] ?? 0, 'total' => $stats['hard']['total'] ?? 0, 'pct' => null],
                    'total' => ['correct' => 0, 'total' => 0, 'pct' => null],
                ];

                $totalCorrect = 0;
                $totalQuestions = 0;
                foreach (['easy', 'medium', 'hard'] as $tier) {
                    $tot = $row[$tier]['total'];
                    $cor = $row[$tier]['correct'];
                    $row[$tier]['pct'] = $tot > 0 ? (int) round(($cor / $tot) * 100) : null;
                    $totalCorrect += $cor;
                    $totalQuestions += $tot;
                }

                if ($totalQuestions === 0) {
                    continue;
                }

                $row['total'] = [
                    'correct' => $totalCorrect,
                    'total' => $totalQuestions,
                    'pct' => (int) round(($totalCorrect / $totalQuestions) * 100),
                ];

                $matrix[$section][$key] = $row;
            }
        }

        return $matrix;
    }

    /**
     * Adapt dossier time distribution buckets for Chart.js.
     */
    public function histogramData(array $timeBuckets): array
    {
        $labels = array_keys($timeBuckets);
        $correctData = array_map(fn ($b) => is_array($b) ? ($b['correct'] ?? 0) : 0, array_values($timeBuckets));
        $incorrectData = array_map(fn ($b) => is_array($b) ? ($b['incorrect'] ?? 0) : 0, array_values($timeBuckets));

        return [
            'labels' => !empty($labels) ? $labels : ['0-15s', '15-30s', '30-60s', '60-90s', '90-120s', '120s+'],
            'correct' => !empty($correctData) ? $correctData : [0, 0, 0, 0, 0, 0],
            'incorrect' => !empty($incorrectData) ? $incorrectData : [0, 0, 0, 0, 0, 0],
            'totalQuestions' => array_sum($correctData) + array_sum($incorrectData),
        ];
    }
}
