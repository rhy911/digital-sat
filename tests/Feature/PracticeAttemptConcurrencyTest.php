<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class PracticeAttemptConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->test = Test::create([
            'title' => 'SAT Practice Test',
            'test_type' => 'module_only',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);

        $section = \App\Models\Section::create([
            'test_id' => $this->test->id,
            'name' => 'Math Section',
            'type' => 'math',
            'order' => 1,
        ]);

        $module = \App\Models\Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'title' => 'Module 1',
            'duration_minutes' => 20,
            'is_public' => true,
        ]);

        $question = \App\Models\Question::create([
            'stem' => 'What is 1+1?',
            'question_type' => 'student_produced_response',
            'difficulty' => 'medium',
            'is_pretest' => false,
            'section_type' => 'math',
            'skill_domain' => 'algebra',
        ]);
        $module->questions()->attach($question->id, ['position' => 1]);
    }

    /**
     * Test that database uniqueness invariant enforces single active practice attempt.
     */
    public function test_database_enforces_single_active_practice_attempt(): void
    {
        // 1. Create first in-progress practice attempt
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        // 2. Attempt to create second in-progress practice attempt for same user/test should fail due to unique key
        $this->expectException(QueryException::class);
        
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test that multiple completed or abandoned attempts are allowed.
     */
    public function test_multiple_completed_or_abandoned_attempts_allowed(): void
    {
        // 1. Create completed attempt
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'completed',
        ]);

        // 2. Create abandoned attempt
        UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'abandoned',
        ]);

        // 3. Create current active attempt
        $active = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseCount('user_tests', 3);
        $this->assertEquals('in_progress', $active->status);
    }

    /**
     * Test that starting fresh marks old attempts as abandoned and works cleanly.
     */
    public function test_start_fresh_reconciles_active_attempts_safely(): void
    {
        // 1. Create first active attempt
        $attempt1 = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
        ]);

        // 2. Simulate start fresh request
        $response = $this->actingAs($this->student)
            ->post(route('engine.test.start', $this->test->id), ['mode' => 'fresh']);

        $response->assertOk();
        
        // 3. Verify attempt 1 is now abandoned, and a new one exists
        $attempt1->refresh();
        $this->assertEquals('abandoned', $attempt1->status);

        $attempt2Ulid = $response->json('user_test_ulid');
        $attempt2 = UserTest::where('ulid', $attempt2Ulid)->first();
        
        $this->assertNotNull($attempt2);
        $this->assertEquals('in_progress', $attempt2->status);
        $this->assertNotEquals($attempt1->id, $attempt2->id);
    }
}
