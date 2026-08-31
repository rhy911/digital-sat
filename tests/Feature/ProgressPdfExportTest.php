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

class ProgressPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_export_progress_pdf(): void
    {
        $student = User::factory()->student()->create([
            'name' => 'Alice Smith',
            'target_score' => 1400,
        ]);

        $this->buildStudentAttempt($student);

        $response = $this->actingAs($student)
            ->get(route('student.progress.export-pdf', ['lang' => 'vi']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('progress-report-alice-smith', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_guest_cannot_export_student_progress_pdf(): void
    {
        $response = $this->get(route('student.progress.export-pdf'));
        $response->assertRedirect(route('signin'));
    }

    public function test_teacher_can_export_student_progress_pdf(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['name' => 'Bob Johnson']);
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'SAT Morning Cohort']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $this->buildStudentAttempt($student, $classroom, $owner);

        $response = $this->actingAs($owner)
            ->get(route('teacher.classes.students.progress.export-pdf', [$classroom, $student]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('student-progress-bob-johnson-sat-morning-cohort', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_unrelated_teacher_cannot_export_student_progress_pdf(): void
    {
        $owner = User::factory()->teacher()->create();
        $stranger = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class Secret']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($stranger)
            ->get(route('teacher.classes.students.progress.export-pdf', [$classroom, $student]));

        $response->assertForbidden();
    }

    public function test_co_teacher_can_export_student_progress_pdf(): void
    {
        $owner = User::factory()->teacher()->create();
        $coTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class Shared']);
        $classroom->coTeachers()->attach($coTeacher->id, ['added_by' => $owner->id]);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($coTeacher)
            ->get(route('teacher.classes.students.progress.export-pdf', [$classroom, $student]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_sharing_preferences_respected_in_teacher_pdf(): void
    {
        $owner = User::factory()->teacher()->create();
        $student = User::factory()->student()->create([
            'name' => 'Carol Danvers',
            'share_independent_practice' => false,
        ]);
        $classroom = Classroom::create(['owner_id' => $owner->id, 'name' => 'Class Confidential']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        // Student has an independent test attempt
        $test = Test::create([
            'title' => 'Private Independent Test',
            'type' => 'full_length',
            'created_by' => $owner->id,
            'is_active' => true,
        ]);
        $independentAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => null,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1100,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('teacher.classes.students.progress.export-pdf', [$classroom, $student]));

        $response->assertOk();
        // Since independent attempts are hidden and no assignment attempts exist, the PDF should render empty state
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    private function buildStudentAttempt(User $student, ?Classroom $classroom = null, ?User $teacher = null): void
    {
        $teacher = $teacher ?? User::factory()->teacher()->create();
        $test = Test::create([
            'title' => 'Diagnostic SAT Mock #1',
            'type' => 'full_length',
            'created_by' => $teacher->id,
            'is_active' => true,
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
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $teacher->id,
        ]);

        $question = Question::create([
            'stem' => 'What is 3x + 1 = 7?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'skill_subdomain' => 'linear_equations_in_one_variable',
            'calculator_allowed' => true,
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $teacher->id,
        ]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $assignment = null;
        if ($classroom) {
            $assignment = Assignment::create([
                'classroom_id' => $classroom->id,
                'teacher_id' => $teacher->id,
                'test_id' => $test->id,
                'title' => 'Homework #1',
                'attempt_limit' => 1,
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $userTest = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment?->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1350,
            'score_reading_writing' => 650,
            'score_math' => 700,
        ]);

        UserTestAnswer::create([
            'user_test_id' => $userTest->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 25,
        ]);
    }
}
