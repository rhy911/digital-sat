<?php

namespace Tests\Unit;

use App\Services\ProgressChartService;
use Tests\TestCase;

class ProgressChartServiceTest extends TestCase
{
    private ProgressChartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgressChartService();
    }

    public function test_trend_data_structures_correctly(): void
    {
        $history = [
            [
                'index' => 1,
                'id' => 1,
                'shortDate' => 'Oct 1',
                'date' => 'Oct 1, 2026',
                'total' => 1200,
                'rw' => 600,
                'math' => 600,
            ],
            [
                'index' => 2,
                'id' => 2,
                'shortDate' => 'Oct 6',
                'date' => 'Oct 6, 2026',
                'total' => 1350,
                'rw' => 670,
                'math' => 680,
            ],
        ];

        $trend = $this->service->trendData($history, 1450);

        $this->assertCount(2, $trend['labels']);
        $this->assertEquals([1200, 1350], $trend['total']);
        $this->assertEquals([600, 670], $trend['rw']);
        $this->assertEquals([600, 680], $trend['math']);
        $this->assertEquals(1450, $trend['targetScore']);
        $this->assertCount(2, $trend['points']);
        $this->assertArrayNotHasKey('ulid', $trend['points'][0]);
    }

    public function test_radar_data_extracts_domain_percents(): void
    {
        $summaries = [
            ['domain' => 'Craft and Structure', 'percentCorrect' => 85],
            ['domain' => 'Information and Ideas', 'percentCorrect' => 70],
            ['domain' => 'Algebra', 'percentCorrect' => 90],
            ['domain' => 'Advanced Math', 'percentCorrect' => 60],
        ];

        $radar = $this->service->radarData($summaries);

        $this->assertArrayHasKey('rw', $radar);
        $this->assertArrayHasKey('math', $radar);
        $this->assertCount(4, $radar['rw']['labels']);
        $this->assertCount(4, $radar['math']['labels']);

        $craftIndex = array_search('Craft and Structure', $radar['rw']['labels'], true);
        $infoIndex = array_search('Information and Ideas', $radar['rw']['labels'], true);
        $this->assertEquals(85, $radar['rw']['data'][$craftIndex]);
        $this->assertEquals(70, $radar['rw']['data'][$infoIndex]);
        $this->assertEquals(90, $radar['math']['data'][0]);
        $this->assertEquals(60, $radar['math']['data'][1]);
    }

    public function test_radar_data_does_not_copy_one_math_domain_into_another(): void
    {
        $radar = $this->service->radarData([
            ['domain' => 'Algebra', 'percentCorrect' => 90],
        ]);

        $this->assertSame([90, 0, 0, 0], $radar['math']['data']);
    }

    public function test_heatmap_data_adapts_domain_difficulty_counts_into_table_matrix(): void
    {
        $stats = [
            'Math' => [
                'algebra' => [
                    'easy' => ['correct' => 2, 'total' => 2],
                    'hard' => ['correct' => 1, 'total' => 2],
                ],
            ],
        ];

        $matrix = $this->service->heatmapData($stats);

        $this->assertArrayHasKey('Math', $matrix);
        $this->assertArrayHasKey('algebra', $matrix['Math']);
        $this->assertSame('Algebra', $matrix['Math']['algebra']['label']);
        $this->assertSame(100, $matrix['Math']['algebra']['easy']['pct']);
        $this->assertSame(50, $matrix['Math']['algebra']['hard']['pct']);
        $this->assertSame(75, $matrix['Math']['algebra']['total']['pct']);
    }

    public function test_histogram_data_buckets_time_spent(): void
    {
        $hist = $this->service->histogramData([
            '0-15s' => ['correct' => 1, 'incorrect' => 0],
            '30-60s' => ['correct' => 0, 'incorrect' => 1],
        ]);

        $this->assertContains('0-15s', $hist['labels']);
        $this->assertContains('30-60s', $hist['labels']);
        $this->assertEquals(1, $hist['correct'][0]);
        $this->assertEquals(1, $hist['incorrect'][1]);
        $this->assertEquals(2, $hist['totalQuestions']);
    }

    public function test_build_web_charts_transforms_dossier(): void
    {
        $dossier = [
            'history' => [
                [
                    'index' => 1,
                    'id' => 1,
                    'shortDate' => 'Oct 1',
                    'date' => 'Oct 1, 2026',
                    'title' => 'Test 1',
                    'total' => 1200,
                    'rw' => 600,
                    'math' => 600,
                ],
            ],
            'score' => ['target' => 1400],
            'weakAreaSummaries' => [
                ['domain' => 'Algebra', 'percentCorrect' => 90],
            ],
            'sectionAccuracy' => [
                'rw' => ['correct' => 15, 'total' => 20, 'pct' => 75],
                'math' => ['correct' => 16, 'total' => 20, 'pct' => 80],
                'overall' => ['correct' => 31, 'total' => 40, 'pct' => 78],
            ],
            'domainDifficultyStats' => [
                'Math' => [
                    'algebra' => [
                        'easy' => ['correct' => 2, 'total' => 2],
                    ],
                ],
            ],
            'timeBuckets' => [
                '0-15s' => ['correct' => 1, 'incorrect' => 0],
                '15-30s' => ['correct' => 2, 'incorrect' => 1],
            ],
        ];

        $charts = $this->service->buildWebCharts($dossier);

        $this->assertArrayHasKey('chartTrend', $charts);
        $this->assertArrayHasKey('chartRadar', $charts);
        $this->assertArrayHasKey('chartDoughnut', $charts);
        $this->assertArrayHasKey('chartHeatmap', $charts);
        $this->assertArrayHasKey('chartHistogram', $charts);

        $this->assertEquals([1200], $charts['chartTrend']['total']);
        $this->assertEquals(1400, $charts['chartTrend']['targetScore']);
        $this->assertEquals(90, $charts['chartRadar']['math']['data'][0]);
        $this->assertEquals([75, 80], $charts['chartDoughnut']['data']);
        $this->assertArrayHasKey('Math', $charts['chartHeatmap']);
        $this->assertEquals(4, $charts['chartHistogram']['totalQuestions']);
    }
}
