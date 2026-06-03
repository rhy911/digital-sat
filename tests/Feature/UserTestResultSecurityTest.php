<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTestResultSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_practice_result_by_ulid(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Owned Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('my-practice', $userTest))
            ->assertOk();
    }

    public function test_other_user_cannot_view_practice_result_by_ulid(): void
    {
        $owner = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $intruder = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Private Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $owner->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('my-practice', $userTest))
            ->assertForbidden();
    }

    public function test_numeric_practice_result_id_no_longer_resolves(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Numeric Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/my-practice/'.$userTest->id)
            ->assertNotFound();
    }
}
