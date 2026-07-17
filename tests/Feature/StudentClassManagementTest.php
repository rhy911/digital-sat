<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User { return User::factory()->teacher()->create(); }
    private function student(): User { return User::factory()->student()->create(); }
    private function classroom(User $teacher): Classroom { return Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Cohort']); }
    private function testFor(User $teacher): Test
    {
        return Test::create(['title' => 'Assigned SAT', 'test_type' => 'custom_test', 'status' => 'active', 'created_by' => $teacher->id, 'is_public' => false]);
    }

    public function test_index_redirects_to_first_active_classroom(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        $this->actingAs($student)
            ->get(route('student.classes.index'))
            ->assertRedirect(route('student.classes.show', $classroom));
    }

    public function test_index_shows_empty_state_when_no_active_classroom(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('student.classes.index'))
            ->assertOk()
            ->assertSee('No classes yet');
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $teacher = $this->teacher();
        $outsider = $this->student();
        $classroom = $this->classroom($teacher);

        $this->actingAs($outsider)->get(route('student.classes.show', $classroom))->assertForbidden();
    }

    public function test_classmates_tab_excludes_self_pending_and_email(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classmate = $this->student();
        $pending = $this->student();
        $classroom = $this->classroom($teacher);

        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $classmate->id, 'status' => 'active']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $pending->id, 'status' => 'pending']);

        $response = $this->actingAs($student)->get(route('student.classes.show', $classroom))->assertOk();

        $response->assertSee($classmate->name)
            ->assertDontSee($classmate->email)
            ->assertDontSee($pending->name);
    }

    public function test_nickname_update_only_affects_own_membership(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $other = $this->student();
        $classroom = $this->classroom($teacher);

        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        $otherMembership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $other->id, 'status' => 'active']);

        $this->actingAs($student)
            ->put(route('student.classes.nickname.update', $classroom), ['display_name' => 'Nicky'])
            ->assertRedirect();

        $this->assertSame('Nicky', $membership->fresh()->display_name);
        $this->assertNull($otherMembership->fresh()->display_name);
    }

    public function test_leaderboard_only_counts_scores_within_seven_days(): void
    {
        $teacher = $this->teacher();
        $recentScorer = $this->student();
        $staleScorer = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id, 'title' => 'Weekly SAT', 'attempt_limit' => 2, 'status' => 'published']);

        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $recentScorer->id, 'status' => 'active']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $staleScorer->id, 'status' => 'active']);

        UserTest::create(['user_id' => $recentScorer->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'completed', 'total_score' => 1400, 'completed_at' => now()->subDays(2)]);
        UserTest::create(['user_id' => $staleScorer->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'completed', 'total_score' => 1550, 'completed_at' => now()->subDays(10)]);

        $response = $this->actingAs($recentScorer)->get(route('student.classes.show', $classroom))->assertOk();

        $response->assertSee('1400');
        $response->assertDontSee('1550');
    }

    public function test_teacher_note_round_trips_to_student_corkboard(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        $this->actingAs($teacher)
            ->put(route('teacher.classes.note.update', $classroom), ['body' => 'Read chapter 3 before Friday.'])
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('student.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Read chapter 3 before Friday.');

        $this->actingAs($teacher)
            ->put(route('teacher.classes.note.update', $classroom), ['body' => 'Updated note.'])
            ->assertRedirect();

        $response = $this->actingAs($student)->get(route('student.classes.show', $classroom))->assertOk();
        $response->assertSee('Updated note.')->assertDontSee('Read chapter 3 before Friday.');
    }
}
