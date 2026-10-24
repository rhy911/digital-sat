<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Support\ClassroomCohortAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomLmsOverhaulTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User { return User::factory()->teacher()->create(); }
    private function student(): User { return User::factory()->student()->create(); }
    private function classroom(User $teacher): Classroom { return Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Prep']); }
    private function activeTest(User $teacher): Test
    {
        return Test::create([
            'title' => 'Math & Verbal Practice',
            'test_type' => 'custom_test',
            'status' => 'active',
            'created_by' => $teacher->id,
            'is_public' => false,
        ]);
    }

    public function test_user_test_runs_on_chained_clock_respects_assignment_clock_mode(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->activeTest($teacher);

        $chainedAssignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Timed Full SAT',
            'clock_mode' => 'chained',
            'attempt_limit' => 1,
            'status' => 'published',
        ]);

        $pausedAssignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Homework Practice',
            'clock_mode' => 'paused',
            'attempt_limit' => 1,
            'status' => 'published',
        ]);

        $chainedAttempt = new UserTest(['assignment_id' => $chainedAssignment->id]);
        $chainedAttempt->setRelation('assignment', $chainedAssignment);

        $pausedAttempt = new UserTest(['assignment_id' => $pausedAssignment->id]);
        $pausedAttempt->setRelation('assignment', $pausedAssignment);

        $this->assertTrue($chainedAttempt->runsOnChainedClock());
        $this->assertFalse($pausedAttempt->runsOnChainedClock());
    }

    public function test_assignment_recipient_factors_in_extra_attempts(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->activeTest($teacher);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Diagnostic Test',
            'attempt_limit' => 2,
            'status' => 'published',
        ]);

        $recipient = new AssignmentRecipient([
            'assignment_id' => $assignment->id,
            'extra_attempts' => 1,
        ]);
        $recipient->setRelation('assignment', $assignment);

        $this->assertEquals(3, $recipient->maxAllowedAttempts());
    }

    public function test_teacher_can_link_and_unlink_exclusive_tests_to_classroom_units(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->activeTest($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.classes.exclusive-tests.store', $classroom), [
                'test_id' => $test->id,
                'unit_name' => 'Unit 1: Advanced Math & Desmos',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_tests', [
            'classroom_id' => $classroom->id,
            'test_id' => $test->id,
            'unit_name' => 'Unit 1: Advanced Math & Desmos',
        ]);

        $this->actingAs($teacher)
            ->delete(route('teacher.classes.exclusive-tests.destroy', [$classroom, $test]))
            ->assertRedirect();

        $this->assertDatabaseMissing('classroom_tests', [
            'classroom_id' => $classroom->id,
            'test_id' => $test->id,
        ]);
    }

    public function test_teacher_can_grant_extra_attempt_to_student(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->activeTest($teacher);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
            'attempt_limit' => 1,
            'status' => 'published',
        ]);

        $recipient = AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
            'extra_attempts' => 0,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.classes.recipients.grant-retry', [$classroom, $recipient]))
            ->assertRedirect();

        $this->assertEquals(1, $recipient->fresh()->extra_attempts);
    }

    public function test_teacher_can_submit_private_feedback_on_student_attempt(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
        $test = $this->activeTest($teacher);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'total_score' => 1280,
            'attempt_number' => 1,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.classes.attempts.feedback', [$classroom, $attempt]), [
                'teacher_feedback' => 'Great improvement on Algebra! Focus on Craft & Structure next.',
            ])
            ->assertRedirect();

        $this->assertEquals(
            'Great improvement on Algebra! Focus on Craft & Structure next.',
            $attempt->fresh()->teacher_feedback
        );
        $this->assertEquals($teacher->id, $attempt->fresh()->feedback_by);
    }

    public function test_teacher_cannot_submit_feedback_on_student_outside_classroom(): void
    {
        $teacher = $this->teacher();
        $otherStudent = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->activeTest($teacher);

        $attempt = UserTest::create([
            'user_id' => $otherStudent->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'total_score' => 1100,
            'attempt_number' => 1,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.classes.attempts.feedback', [$classroom, $attempt]), [
                'teacher_feedback' => 'Unauthorized feedback attempt.',
            ])
            ->assertNotFound();

        $this->assertNull($attempt->fresh()->teacher_feedback);
    }

    public function test_cohort_analytics_computes_empty_stats_gracefully(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);

        $stats = ClassroomCohortAnalytics::forClassroom($classroom);

        $this->assertNull($stats['composite_avg']);
        $this->assertNull($stats['math_avg']);
        $this->assertNull($stats['rw_avg']);
        $this->assertEquals(0, $stats['completed_tests_count']);
        $this->assertEmpty($stats['domain_proficiencies']);
    }
}
