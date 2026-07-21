<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherScoreAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_access_student_score_report_for_assignment_attempt(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Cohort']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        $test = Test::create(['title' => 'Sample SAT', 'test_type' => 'custom_test', 'status' => 'active', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);
        $question = Question::create(['stem' => 'What is 1 + 1?', 'question_type' => 'student_produced_response', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'calculator_allowed' => true, 'is_complete' => true, 'created_by' => $teacher->id]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id, 'title' => 'Weekly Assignment']);
        $recipient = AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'assigned_at' => now()]);

        $userTest = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 600,
            'score_math' => 700,
            'total_score' => 1300,
        ]);

        // Teacher accesses score show page directly
        $response = $this->actingAs($teacher)->get(route('student.scores.show', $userTest));
        $response->assertOk()
            ->assertSee($student->name)
            ->assertSee('Sample SAT');

        // Teacher accesses student attempt view and sees target="_blank" score report link
        $attemptView = $this->actingAs($teacher)->get(route('teacher.assignments.students.show', [$assignment, $student]));
        $attemptView->assertOk()
            ->assertSee(route('student.scores.show', $userTest))
            ->assertSee('target="_blank"', false);
    }
}
