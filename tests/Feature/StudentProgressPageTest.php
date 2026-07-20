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

class StudentProgressPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_page_shows_weak_area_difficulty_and_pacing_charts(): void
    {
        $student = User::factory()->student()->create();

        $test = Test::create([
            'title' => 'Analytics Dashboard Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => 'math',
            'order' => 1,
            'created_by' => $test->created_by,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 35,
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $test->created_by,
        ]);
        $question = Question::create([
            'stem' => 'What is 1 + 1?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'expected_time' => 60,
            'calculator_allowed' => true,
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $test->created_by,
        ]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => '3', 'is_correct' => false, 'order' => 2]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'score_conversion_version' => 'normal_consensus_v1',
            'score_estimate_kind' => 'normal_generic',
        ]);

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 30,
        ]);

        $this->actingAs($student)
            ->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Weak areas by domain')
            ->assertSee('Difficulty breakdown')
            ->assertSee('Pacing')
            ->assertSee('Algebra')
            ->assertSee('Easy');

        // Regression guard: the dashboard no longer renders this content.
        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Weak areas by domain');
    }

    public function test_progress_page_shows_empty_states_with_no_completed_tests(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('student.progress'))
            ->assertOk()
            ->assertSee('No domain data yet')
            ->assertSee('No difficulty data yet')
            ->assertSee('No pacing data yet');

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('No domain data yet');
    }
}
