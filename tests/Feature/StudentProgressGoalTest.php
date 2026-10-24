<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_set_and_update_target_score_goal(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->postJson(route('student.progress.goal'), [
                'target_score' => 1450,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'target_score' => 1450,
            ]);

        $this->assertEquals(1450, $student->fresh()->target_score);
    }

    public function test_target_score_rounds_to_nearest_ten(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->postJson(route('student.progress.goal'), [
                'target_score' => 1456,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'target_score' => 1460,
            ]);

        $this->assertEquals(1460, $student->fresh()->target_score);
    }

    public function test_invalid_target_score_is_rejected(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->postJson(route('student.progress.goal'), [
                'target_score' => 350, // Below 400
            ]);

        $response->assertUnprocessable();
    }

    public function test_goal_can_be_cleared_and_both_boundaries_are_saved(): void
    {
        $student = User::factory()->student()->create(['target_score' => 1450]);
        foreach ([null, 400, 1600] as $target) {
            $this->actingAs($student)->postJson(route('student.progress.goal'), ['target_score' => $target])
                ->assertOk()->assertJsonPath('target_score', $target);
            $this->assertSame($target, $student->fresh()->target_score);
        }
    }

    public function test_invalid_goals_preserve_the_previous_score(): void
    {
        $student = User::factory()->student()->create(['target_score' => 1450]);
        foreach ([1601, 1450.5, 'not a score'] as $target) {
            $this->actingAs($student)->postJson(route('student.progress.goal'), ['target_score' => $target])
                ->assertUnprocessable()->assertJsonValidationErrors('target_score');
            $this->assertSame(1450, $student->fresh()->target_score);
        }
    }
}
