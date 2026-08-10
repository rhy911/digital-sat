<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the aggregate behind the /home subtitle. The unit test pins the copy
 * ladder; this pins the query that feeds it — specifically that the counts come
 * from every assignment rather than the three the card list renders, and that
 * completed work stops being counted as outstanding.
 */
class HomeFocusLineTest extends TestCase
{
    use RefreshDatabase;

    private function assignTo(User $student, User $teacher, array $extra = [], string $classStatus = 'active'): Assignment
    {
        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'SAT Cohort '.uniqid(),
            'status' => $classStatus,
        ]);

        $test = Test::create([
            'title' => 'Assigned SAT',
            'test_type' => 'custom_test',
            'status' => 'active',
            'created_by' => $teacher->id,
            'is_public' => false,
        ]);

        $assignment = Assignment::create(array_merge([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Weekly SAT',
            'attempt_limit' => 2,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $extra));

        AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now()->subDay(),
        ]);

        return $assignment;
    }

    public function test_counts_span_every_assignment_not_just_the_three_on_the_card(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        foreach (range(1, 5) as $ignored) {
            $this->assignTo($student, $teacher);
        }

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('5 class assignments left to finish.');
    }

    public function test_completed_work_stops_counting_as_outstanding(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $done = $this->assignTo($student, $teacher);
        $this->assignTo($student, $teacher);

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $done->test_id,
            'assignment_id' => $done->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('1 class assignment left to finish.');
    }

    public function test_all_work_finished_reports_caught_up_rather_than_outstanding(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $assignment = $this->assignTo($student, $teacher);

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $assignment->test_id,
            'assignment_id' => $assignment->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('You are caught up on class work.')
            ->assertDontSee('left to finish');
    }

    public function test_past_due_work_outranks_the_plain_open_count(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $this->assignTo($student, $teacher, ['due_at' => now()->subDays(2)]);
        $this->assignTo($student, $teacher);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('1 class assignment past due.');
    }

    public function test_work_due_within_two_days_is_surfaced_before_undated_work(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $this->assignTo($student, $teacher, ['due_at' => now()->addDay()]);
        $this->assignTo($student, $teacher);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('Finish 1 class assignment due in the next two days.');
    }

    public function test_work_in_an_archived_class_is_not_counted_as_finishable(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $this->assignTo($student, $teacher, [], 'archived');

        // No assignments were ever startable, so the student sees the first-run line.
        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertDontSee('left to finish');
    }

    public function test_an_upcoming_assignment_is_not_counted_until_it_opens(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $this->assignTo($student, $teacher, ['available_at' => now()->addWeek()]);
        $this->assignTo($student, $teacher);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            ->assertSee('1 class assignment left to finish.');
    }

    public function test_teacher_overview_leads_with_pending_join_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Cohort']);
        $classroom->memberships()->create([
            'student_id' => $student->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($teacher)->get('/home')
            ->assertOk()
            ->assertSee('Approve 1 student waiting to join.');
    }

    public function test_teacher_with_no_classes_is_told_to_create_one(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get('/home')
            ->assertOk()
            ->assertSee('Create your first class to start assigning tests.');
    }
}
