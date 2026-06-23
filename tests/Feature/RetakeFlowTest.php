<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetakeFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Test $test;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->test = Test::create([
            'title' => 'SAT Math Practice',
            'test_type' => 'module_only',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);

        $section = Section::create([
            'test_id' => $this->test->id,
            'name' => 'Math Section',
            'type' => 'math',
            'order' => 1,
        ]);

        $this->module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'title' => 'Module 1',
            'duration_minutes' => 20,
            'is_public' => true,
        ]);

        // Attach a dummy question so the take-test page doesn't throw a 404 for empty module
        $question = \App\Models\Question::create([
            'stem' => 'What is 1+1?',
            'question_type' => 'student_produced_response',
            'difficulty' => 'medium',
            'is_pretest' => false,
            'section_type' => 'math',
            'skill_domain' => 'algebra',
        ]);
        $this->module->questions()->attach($question->id, ['position' => 1]);
    }

    public function test_attempt_options_when_no_attempt_exists(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('engine.test.attempt-options', $this->test->id));

        $response->assertOk()
            ->assertJson([
                'has_in_progress' => false,
                'can_continue' => false,
                'can_start_fresh' => true,
                'first_module_ulid' => $this->module->ulid,
            ]);
    }

    public function test_attempt_options_when_in_progress_attempt_exists(): void
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('engine.test.attempt-options', $this->test->id));

        $response->assertOk()
            ->assertJson([
                'has_in_progress' => true,
                'latest_in_progress_ulid' => $userTest->ulid,
                'can_continue' => true,
                'can_start_fresh' => true,
            ]);
    }

    public function test_attempt_options_when_only_completed_attempt_exists(): void
    {
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('engine.test.attempt-options', $this->test->id));

        $response->assertOk()
            ->assertJson([
                'has_in_progress' => false,
                'can_continue' => false,
                'can_start_fresh' => true,
            ]);
    }

    public function test_start_test_in_fresh_mode_creates_new_attempt(): void
    {
        // 1. Create a completed attempt
        $completedAttempt = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // 2. Start fresh
        $response = $this->actingAs($this->student)
            ->post(route('engine.test.start', $this->test->id), ['mode' => 'fresh']);

        $response->assertOk();
        $data = $response->json();

        $this->assertNotEquals($completedAttempt->ulid, $data['user_test_ulid']);

        $newAttempt = UserTest::where('ulid', $data['user_test_ulid'])->first();
        $this->assertNotNull($newAttempt);
        $this->assertEquals('in_progress', $newAttempt->status);
        $this->assertSame($this->module->id, $newAttempt->current_module_id);
    }

    public function test_start_test_in_fresh_mode_deletes_ongoing_attempt(): void
    {
        // 1. Create an in-progress attempt
        $ongoingAttempt = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        // 2. Start fresh
        $response = $this->actingAs($this->student)
            ->post(route('engine.test.start', $this->test->id), ['mode' => 'fresh']);

        $response->assertOk();
        $data = $response->json();

        // 3. Assert the ongoing attempt is marked abandoned
        $ongoingAttempt->refresh();
        $this->assertEquals('abandoned', $ongoingAttempt->status);

        // 4. Assert new attempt is created
        $newAttempt = UserTest::where('ulid', $data['user_test_ulid'])->first();
        $this->assertNotNull($newAttempt);
        $this->assertEquals('in_progress', $newAttempt->status);
    }

    public function test_show_module_with_valid_owned_attempt(): void
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->module->id,
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $this->module->ulid, 'attempt' => $userTest->ulid]));

        $response->assertOk();
    }

    public function test_show_module_with_unowned_attempt_fails(): void
    {
        $otherUser = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $userTest = UserTest::create([
            'user_id' => $otherUser->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $this->module->ulid, 'attempt' => $userTest->ulid]));

        $response->assertStatus(403);
    }

    public function test_show_module_with_mismatched_test_attempt_fails(): void
    {
        $otherTest = Test::create([
            'title' => 'Other Test',
            'test_type' => 'short_test',
            'status' => 'active',
            'is_public' => true,
        ]);

        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $otherTest->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $this->module->ulid, 'attempt' => $userTest->ulid]));

        $response->assertNotFound();
    }

    public function test_show_module_without_attempt_parameter_is_rejected(): void
    {
        // 1. Create a completed attempt
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Bare module URLs must never select or create progression state.
        $this->assertEquals(1, UserTest::count());

        $response = $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $this->module->ulid]));

        $response->assertStatus(409);
        $this->assertEquals(1, UserTest::count());
    }
}
