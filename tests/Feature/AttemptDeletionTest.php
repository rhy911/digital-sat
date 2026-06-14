<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AttemptDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_own_in_progress_attempt()
    {
        $user = \App\Models\User::factory()->create(['role' => 'student']);
        $test = \App\Models\Test::create([
            'title' => 'Sample Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);
        
        $userTest = \App\Models\UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('my-practice.destroy', $userTest));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_tests', ['id' => $userTest->id]);
    }

    public function test_user_cannot_delete_completed_attempt()
    {
        $user = \App\Models\User::factory()->create(['role' => 'student']);
        $test = \App\Models\Test::create([
            'title' => 'Sample Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);
        
        $userTest = \App\Models\UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('my-practice.destroy', $userTest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('user_tests', ['id' => $userTest->id]);
    }

    public function test_user_cannot_delete_others_attempt()
    {
        $user = \App\Models\User::factory()->create(['role' => 'student']);
        $otherUser = \App\Models\User::factory()->create(['role' => 'student']);
        $test = \App\Models\Test::create([
            'title' => 'Sample Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);
        
        $userTest = \App\Models\UserTest::create([
            'user_id' => $otherUser->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('my-practice.destroy', $userTest));

        $response->assertStatus(403);
        $this->assertDatabaseHas('user_tests', ['id' => $userTest->id]);
    }
}
