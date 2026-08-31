<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\ProgressChartService;
use Illuminate\Support\Collection;
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
        $attempt1 = new UserTest([
            'id' => 1,
            'ulid' => '01H00000000000000000000001',
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => now()->subDays(5),
        ]);
        $attempt2 = new UserTest([
            'id' => 2,
            'ulid' => '01H00000000000000000000002',
            'total_score' => 1350,
            'score_reading_writing' => 670,
            'score_math' => 680,
            'completed_at' => now(),
        ]);

        $attempts = collect([$attempt2, $attempt1]); // newest first
        $trend = $this->service->trendData($attempts, 1450);

        $this->assertCount(2, $trend['labels']);
        $this->assertEquals([1200, 1350], $trend['total']); // chronological order
        $this->assertEquals([600, 670], $trend['rw']);
        $this->assertEquals([600, 680], $trend['math']);
        $this->assertEquals(1450, $trend['targetScore']);
        $this->assertCount(2, $trend['points']);
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

        $this->assertEquals(85, $radar['rw']['data'][0]);
        $this->assertEquals(70, $radar['rw']['data'][1]);
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

    public function test_histogram_data_buckets_time_spent(): void
    {
        $q1 = new Question(['section_type' => 'math', 'is_pretest' => false]);
        $q2 = new Question(['section_type' => 'reading_writing', 'is_pretest' => false]);

        $a1 = new UserTestAnswer(['time_spent' => 10, 'is_correct' => true]);
        $a1->setRelation('question', $q1);

        $a2 = new UserTestAnswer(['time_spent' => 45, 'is_correct' => false]);
        $a2->setRelation('question', $q2);

        $test = new UserTest();
        $test->setRelation('userAnswers', collect([$a1, $a2]));

        $hist = $this->service->histogramData(collect([$test]));

        $this->assertContains('0-15s', $hist['labels']);
        $this->assertContains('30-60s', $hist['labels']);
        $this->assertEquals(1, $hist['correct'][0]); // 0-15s has 1 correct
        $this->assertEquals(1, $hist['incorrect'][2]); // 30-60s has 1 incorrect
        $this->assertEquals(2, $hist['totalQuestions']);
    }
}
