<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test as SatTest;
use App\Models\TestShare;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestScoreRevision;
use App\Notifications\AssignmentPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClassroomAccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_classroom_test_linking_uses_owner_shared_and_admin_permissions_and_normalizes_unit_name(): void
    {
        $owner = User::factory()->teacher()->create();
        $coTeacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create();
        $foreignTeacher = User::factory()->teacher()->create();
        $classroom = $this->classroom($owner);
        $classroom->coTeachers()->attach($coTeacher->id);
        $privateTest = $this->test($foreignTeacher);

        $this->actingAs($owner)->from('/teacher/classes')->post(route('teacher.classes.exclusive-tests.store', $classroom), ['test_id' => $privateTest->id])
            ->assertSessionHasErrors('test_id');
        $this->assertDatabaseMissing('classroom_tests', ['classroom_id' => $classroom->id, 'test_id' => $privateTest->id]);

        $sharedTest = $this->test($foreignTeacher);
        TestShare::create(['test_id' => $sharedTest->id, 'user_id' => $coTeacher->id, 'shared_by' => $foreignTeacher->id]);
        $this->actingAs($coTeacher)->post(route('teacher.classes.exclusive-tests.store', $classroom), ['test_id' => $sharedTest->id])
            ->assertRedirect();
        $this->assertDatabaseHas('classroom_tests', ['classroom_id' => $classroom->id, 'test_id' => $sharedTest->id, 'unit_name' => null]);

        $ownerTest = $this->test($owner);
        $this->actingAs($admin)->post(route('teacher.classes.exclusive-tests.store', $classroom), ['test_id' => $ownerTest->id, 'unit_name' => '   '])
            ->assertRedirect();
        $this->assertDatabaseHas('classroom_tests', ['classroom_id' => $classroom->id, 'test_id' => $ownerTest->id, 'unit_name' => null]);
    }

    public function test_specific_publish_keeps_only_selected_active_students_and_notifies_them(): void
    {
        Notification::fake();
        $teacher = User::factory()->teacher()->create();
        $selected = User::factory()->student()->create();
        $unselected = User::factory()->student()->create();
        $classroom = $this->classroom($teacher);
        $this->activeMember($classroom, $selected);
        $this->activeMember($classroom, $unselected);
        $test = $this->completeTest($teacher);

        $this->actingAs($teacher)->post(route('teacher.assignments.store', $classroom), [
            'test_id' => $test->id,
            'title' => 'Targeted worksheet',
            'attempt_limit' => 1,
            'target_audience' => 'specific',
            'student_ids' => [$selected->id],
        ])->assertRedirect();

        $assignment = Assignment::where('title', 'Targeted worksheet')->firstOrFail();
        $this->assertSame([$selected->id], $assignment->recipients()->pluck('student_id')->all());
        Notification::assertSentTo($selected, AssignmentPublishedNotification::class);
        Notification::assertNotSentTo($unselected, AssignmentPublishedNotification::class);

        $this->actingAs($teacher)->post(route('teacher.assignments.publish', $assignment))->assertRedirect();
        $this->assertSame([$selected->id], $assignment->fresh()->recipients()->where('status', 'active')->pluck('student_id')->all());
        Notification::assertNotSentTo($unselected, AssignmentPublishedNotification::class);
    }

    public function test_specific_publish_rejects_empty_or_nonmember_selection(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $outsider = User::factory()->student()->create();
        $inactive = User::factory()->student()->create();
        $classroom = $this->classroom($teacher);
        $this->activeMember($classroom, $student);
        $this->activeMember($classroom, $inactive, 'left');
        $test = $this->completeTest($teacher);

        foreach ([[], [$outsider->id], [$inactive->id]] as $ids) {
            $this->actingAs($teacher)->post(route('teacher.assignments.store', $classroom), [
                'test_id' => $test->id,
                'title' => 'Invalid targeted worksheet',
                'attempt_limit' => 1,
                'target_audience' => 'specific',
                'student_ids' => $ids,
            ])->assertSessionHasErrors('student_ids');
        }

        $this->assertDatabaseMissing('assignments', ['title' => 'Invalid targeted worksheet']);
        $this->assertDatabaseCount('assignment_recipients', 0);
    }

    public function test_unselected_student_does_not_receive_targeted_assignment_or_count(): void
    {
        $teacher = User::factory()->teacher()->create();
        $selected = User::factory()->student()->create();
        $unselected = User::factory()->student()->create();
        $classroom = $this->classroom($teacher);
        $this->activeMember($classroom, $selected);
        $this->activeMember($classroom, $unselected);
        $test = $this->test($teacher);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id,
            'title' => 'Private targeted task', 'target_audience' => 'specific', 'status' => 'published',
        ]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $selected->id, 'status' => 'active', 'assigned_at' => now()]);

        $response = $this->actingAs($unselected)->get(route('student.classes.show', $classroom));
        $response->assertOk()->assertDontSee('Private targeted task');
        $response->assertViewHas('assignmentsPage', fn ($page) => $page->total() === 0);
        $response->assertViewHas('classroom', fn ($viewClassroom) => $viewClassroom->assignments_count === 0);
    }

    public function test_classroom_access_audit_reports_only_and_does_not_change_records(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $outside = User::factory()->student()->create();
        $classroom = $this->classroom($teacher);
        $this->activeMember($classroom, $student);
        $test = $this->test($teacher);
        $classroom->exclusiveTests()->attach($test->id);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id,
            'title' => 'Needs review', 'target_audience' => 'specific', 'status' => 'published',
        ]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $outside->id, 'status' => 'active', 'assigned_at' => now()]);
        $counts = [
            'assignments' => Assignment::count(), 'recipients' => AssignmentRecipient::count(),
            'attempts' => UserTest::count(), 'revisions' => UserTestScoreRevision::count(),
        ];
        $path = tempnam(sys_get_temp_dir(), 'classroom-audit-');

        try {
            $this->artisan('classrooms:audit-access', ['--output' => $path])->assertSuccessful();
            $csv = file_get_contents($path);
            $this->assertStringContainsString('active_recipient_outside_roster', $csv);
            $this->assertStringContainsString('specific_audience_covers_roster', $csv);
            $this->assertSame($counts, [
                'assignments' => Assignment::count(), 'recipients' => AssignmentRecipient::count(),
                'attempts' => UserTest::count(), 'revisions' => UserTestScoreRevision::count(),
            ]);
        } finally {
            @unlink($path);
        }
    }

    private function classroom(User $owner): Classroom
    {
        return Classroom::create(['owner_id' => $owner->id, 'name' => 'Access audit class']);
    }

    private function test(User $teacher): SatTest
    {
        return SatTest::create(['title' => 'Private test', 'test_type' => 'custom_test', 'status' => 'active', 'created_by' => $teacher->id]);
    }

    private function completeTest(User $teacher): SatTest
    {
        $test = $this->test($teacher);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1]);
        $question = Question::create(['stem' => '1 + 1?', 'question_type' => 'student_produced_response', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true]);
        $module->questions()->attach($question->id, ['position' => 1]);

        return $test;
    }

    private function activeMember(Classroom $classroom, User $student, string $status = 'active'): void
    {
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => $status]);
    }
}
