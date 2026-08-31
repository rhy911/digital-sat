<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTestAnswerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_classify_own_incorrect_answer_and_another_student_cannot(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        [$attempt, $answer] = $this->attemptWithWrongAnswer($student);
        $route = route('student.scores.answers.review', [$attempt, $answer]);

        $this->actingAs($student)->patchJson($route, ['error_type' => 'careless'])
            ->assertOk()
            ->assertJsonPath('effective_error_type', 'careless');

        $this->assertDatabaseHas('user_test_answer_reviews', [
            'user_test_answer_id' => $answer->id,
            'student_error_type' => 'careless',
            'teacher_error_type' => null,
        ]);

        $this->actingAs($other)->patchJson($route, ['error_type' => 'guess'])->assertForbidden();
    }

    private function attemptWithWrongAnswer(User $student): array
    {
        $teacher = User::factory()->teacher()->create();
        $test = Test::create(['title' => 'Review Mock', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);
        $question = Question::create(['stem' => 'Solve x.', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'skill_subdomain' => 'linear_equations_in_one_variable', 'is_complete' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => '1', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);
        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'attempt_type' => 'full', 'status' => 'completed', 'completed_at' => now(), 'total_score' => 1000, 'score_reading_writing' => 500, 'score_math' => 500]);
        $answer = UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $question->id, 'selected_answer' => 'B', 'is_correct' => false, 'time_spent' => 30]);

        return [$attempt, $answer];
    }
}
