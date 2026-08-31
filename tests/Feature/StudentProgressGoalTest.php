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
}
