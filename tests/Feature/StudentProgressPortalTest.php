<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_scored_attempts_sees_portal_latest_score_trend_data_and_progress_link(): void
    {
        $student = User::factory()->student()->create([
            'target_score' => 1450,
        ]);

        $test = Test::create([
            'title' => 'Digital SAT Practice Test 1',
            'test_type' => 'custom_test',
            'status' => 'active',
        ]);

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1100,
            'score_reading_writing' => 550,
            'score_math' => 550,
            'completed_at' => now()->subDays(3),
        ]);

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1250,
            'score_reading_writing' => 600,
            'score_math' => 650,
            'completed_at' => now()->subDays(2),
        ]);

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1380,
            'score_reading_writing' => 680,
            'score_math' => 700,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get('/home');

        $response->assertOk()
            ->assertSee('Progress at a glance')
            ->assertSee('Latest score')
            ->assertSee('1380')
            ->assertSee('Personal best')
            ->assertSee('Target goal')
            ->assertSee('1450')
            ->assertSee('View full progress')
            ->assertSee('home-sparkline');

        /** @var array $homeScoreTrend */
        $homeScoreTrend = $response->viewData('homeScoreTrend');
        $this->assertNotNull($homeScoreTrend);
        $this->assertCount(3, $homeScoreTrend['attempts']);
        $this->assertEquals(1100, $homeScoreTrend['attempts'][0]['score']);
        $this->assertEquals(1250, $homeScoreTrend['attempts'][1]['score']);
        $this->assertEquals(1380, $homeScoreTrend['attempts'][2]['score']);
        $this->assertEquals(1450, $homeScoreTrend['target_score']);
    }

    public function test_student_with_no_scored_attempts_sees_empty_state_and_practice_cta(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/home');

        $response->assertOk()
            ->assertSee('Progress at a glance')
            ->assertSee('No score trend yet')
            ->assertSee('Explore the test library');

        /** @var array $homeScoreTrend */
        $homeScoreTrend = $response->viewData('homeScoreTrend');
        $this->assertNotNull($homeScoreTrend);
        $this->assertEmpty($homeScoreTrend['attempts']);
    }

    public function test_progress_link_resolves_to_progress_analytics(): void
    {
        $student = User::factory()->student()->create();

        $this->assertEquals(url('/student/progress-analytics'), route('student.progress'));

        $response = $this->actingAs($student)->get('/home');

        $response->assertOk()
            ->assertSee(route('student.progress'), false);
    }
}
