<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStudentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_assignment_linked_data_but_not_independent_when_sharing_off(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['share_independent_practice' => false]);
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class A']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        [$assignmentAttempt, $independentAttempt] = $this->buildAttempts($owner, $student, $classroom);

        $response = $this->actingAs($owner)
            ->get(route('teacher.classes.students.progress', [$classroom, $student]));

        $response->assertOk();
        $response->assertSee('Algebra');
        $response->assertDontSee('Geometry');
    }

    public function test_owner_sees_independent_attempts_badged_when_sharing_on(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['share_independent_practice' => true]);
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class B']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $this->buildAttempts($owner, $student, $classroom);

        $response = $this->actingAs($owner)
            ->get(route('teacher.classes.students.progress', [$classroom, $student]));

        $response->assertOk();
        $response->assertSee('Algebra');
        $response->assertSee('Geometry');
        $response->assertSee('Shared practice');
    }

    public function test_co_teacher_can_view_student_progress(): void
    {
        $owner = User::factory()->teacher()->create();
        $coTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class C']);
        $classroom->coTeachers()->attach($coTeacher->id, ['added_by' => $owner->id]);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $this->actingAs($coTeacher)
            ->get(route('teacher.classes.students.progress', [$classroom, $student]))
            ->assertOk();
    }

    public function test_unrelated_teacher_is_forbidden(): void
    {
        $owner = User::factory()->teacher()->create();
        $stranger = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class D']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $this->actingAs($stranger)
            ->get(route('teacher.classes.students.progress', [$classroom, $student]))
            ->assertForbidden();
    }

    public function test_non_active_member_is_forbidden(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class E']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->get(route('teacher.classes.students.progress', [$classroom, $student]))
            ->assertForbidden();
    }

    /**
     * @return array{0: UserTest, 1: UserTest} [assignment-linked attempt, independent attempt]
     */
    private function buildAttempts(User $teacher, User $student, Classroom $classroom): array
    {
        $test = Test::create([
            'title' => 'Teacher Progress Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => false,
            'created_by' => $teacher->id,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => 'math',
            'order' => 1,
            'created_by' => $teacher->id,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 35,
            'total_questions' => 2,
            'order' => 1,
            'created_by' => $teacher->id,
        ]);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Progress Test Assignment',
            'attempt_limit' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $assignmentQuestion = Question::create([
            'stem' => 'Assignment question',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $teacher->id,
        ]);
        AnswerChoice::create(['question_id' => $assignmentQuestion->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($assignmentQuestion->id, ['position' => 1]);

        $independentQuestion = Question::create([
            'stem' => 'Independent question',
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'section_type' => 'math',
            'skill_domain' => 'geometry',
            'calculator_allowed' => true,
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $teacher->id,
        ]);
        AnswerChoice::create(['question_id' => $independentQuestion->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($independentQuestion->id, ['position' => 2]);

        $assignmentAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1200,
        ]);
        UserTestAnswer::create([
            'user_test_id' => $assignmentAttempt->id,
            'module_id' => $module->id,
            'question_id' => $assignmentQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $independentAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => null,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1000,
        ]);
        UserTestAnswer::create([
            'user_test_id' => $independentAttempt->id,
            'module_id' => $module->id,
            'question_id' => $independentQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        return [$assignmentAttempt, $independentAttempt];
    }
}
