<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestTypeProgressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_full_length_uses_fixed_progression_and_route_neutral_table(): void
    {
        [$student, $test] = $this->base('full_length');
        $rw = $this->section($test, Section::TYPE_RW, 1);
        $math = $this->section($test, Section::TYPE_MATH, 2);
        $rw1 = $this->module($rw, 1, Module::DIFFICULTY_STANDARD, 1);
        $rw2 = $this->module($rw, 2, Module::DIFFICULTY_STANDARD, 2);
        $math1 = $this->module($math, 1, Module::DIFFICULTY_STANDARD, 3);
        $math2 = $this->module($math, 2, Module::DIFFICULTY_STANDARD, 4);
        $rw1Question = $this->fillModule($rw1, Section::TYPE_RW, 27);
        $rw2Question = $this->fillModule($rw2, Section::TYPE_RW, 27);
        $math1Question = $this->fillModule($math1, Section::TYPE_MATH, 22);
        $math2Question = $this->fillModule($math2, Section::TYPE_MATH, 22);
        $attempt = $this->attempt($student, $test, $rw1);

        $this->assertSame($rw2->ulid, $this->submit($student, $attempt, $rw1, $rw1Question)->assertOk()->json('next_module_id'));
        $this->assertSame($math1->ulid, $this->submit($student, $attempt, $rw2, $rw2Question)->assertOk()->json('next_module_id'));
        $this->assertSame($math2->ulid, $this->submit($student, $attempt, $math1, $math1Question)->assertOk()->json('next_module_id'));
        $this->submit($student, $attempt, $math2, $math2Question)->assertOk()->assertJsonPath('test_completed', true);

        $attempt->refresh();
        $this->assertNotNull($attempt->total_score);
        $this->assertNotNull($attempt->score_reading_writing);
        $this->assertNotNull($attempt->score_math);
        $this->assertNull($attempt->score_conversion_set_id);
        $this->assertSame('normal_consensus_v1', $attempt->score_conversion_version);
        $this->assertSame('normal_generic', $attempt->score_estimate_kind);
        $this->assertNull($attempt->rw_theta);
        $this->assertNull($attempt->math_theta);
        $this->assertSame('raw_table_v1', $attempt->scoring_method);
    }

    public function test_adaptive_full_length_uses_irt_score_and_uncertainty_range(): void
    {
        [$student, $test] = $this->base('adaptive_full_length');
        $rw = $this->section($test, Section::TYPE_RW, 1);
        $math = $this->section($test, Section::TYPE_MATH, 2);
        $rwModules = $this->adaptiveModules($rw, Section::TYPE_RW, 1);
        $mathModules = $this->adaptiveModules($math, Section::TYPE_MATH, 4);
        $attempt = $this->attempt($student, $test, $rwModules['standard'][0]);

        $rwNext = $this->submit($student, $attempt, $rwModules['standard'][0], $rwModules['standard'][1])->assertOk();
        $rwIssued = Module::where('ulid', $rwNext->json('next_module_id'))->firstOrFail();
        $this->submit($student, $attempt, $rwIssued, $rwIssued->questions()->firstOrFail())->assertOk();
        $mathNext = $this->submit($student, $attempt, $mathModules['standard'][0], $mathModules['standard'][1])->assertOk();
        $mathIssued = Module::where('ulid', $mathNext->json('next_module_id'))->firstOrFail();
        $this->submit($student, $attempt, $mathIssued, $mathIssued->questions()->firstOrFail())->assertOk();

        $attempt->refresh();
        $this->assertSame('adaptive_irt_provisional', $attempt->score_estimate_kind);
        $this->assertSame('provisional_irt_v1', $attempt->score_conversion_version);
        $this->assertNotNull($attempt->rw_theta);
        $this->assertNotNull($attempt->total_score_lower);
        $this->assertGreaterThanOrEqual($attempt->total_score_lower, $attempt->total_score);
        $this->assertLessThanOrEqual($attempt->total_score_upper, $attempt->total_score);
    }

    public function test_module_only_completes_selected_module_without_sat_score(): void
    {
        [$student, $test] = $this->base('module_only');
        $section = $this->section($test, Section::TYPE_MATH, 1);
        $module = $this->module($section, 7, Module::DIFFICULTY_HARD, 1);
        $question = $this->question($module, Section::TYPE_MATH);
        $attempt = $this->attempt($student, $test, $module);

        $this->submit($student, $attempt, $module, $question)
            ->assertOk()->assertJsonPath('test_completed', true);

        $attempt->refresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertNull($attempt->total_score);
        $this->assertNull($attempt->score_math);
        $this->assertNotNull($attempt->math_theta);
        $this->actingAs($student)->get(route('my-practice.score', $attempt))
            ->assertOk()->assertSee('Practice performance, not a calibrated SAT score.');
    }

    public function test_partial_submission_materializes_unanswered_questions_as_omitted(): void
    {
        [$student, $test] = $this->base('module_only');
        $section = $this->section($test, Section::TYPE_MATH, 1);
        $module = $this->module($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $answered = $this->question($module, Section::TYPE_MATH);
        $omitted = $this->question($module, Section::TYPE_MATH);
        $attempt = $this->attempt($student, $test, $module);

        $this->submit($student, $attempt, $module, $answered)->assertOk();

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $omitted->id,
            'selected_answer' => null,
            'is_correct' => false,
        ]);
    }

    public function test_missing_requested_module_two_path_falls_back_to_available_path(): void
    {
        [$student, $test] = $this->base('section_only');
        $section = $this->section($test, Section::TYPE_MATH, 1);
        $moduleOne = $this->module($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $hardModule = $this->module($section, 2, Module::DIFFICULTY_HARD, 2);
        $moduleOneQuestion = $this->question($moduleOne, Section::TYPE_MATH);
        $this->question($hardModule, Section::TYPE_MATH);
        $attempt = $this->attempt($student, $test, $moduleOne);

        $this->actingAs($student)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $moduleOne->id,
            'answers' => [(string) $moduleOneQuestion->id => 'B'],
        ])->assertOk()
            ->assertJsonPath('next_module_id', $hardModule->ulid)
            ->assertJsonPath('fallback_module_id', $hardModule->ulid)
            ->assertJsonPath('path', Module::DIFFICULTY_EASY)
            ->assertJsonPath('actual_path', Module::DIFFICULTY_HARD);

        $this->assertSame(Module::DIFFICULTY_HARD, $attempt->fresh()->math_m2_path);
    }

    public function test_short_test_advances_between_single_module_sections(): void
    {
        [$student, $test] = $this->base('short_test');
        $rw = $this->section($test, Section::TYPE_RW, 1);
        $math = $this->section($test, Section::TYPE_MATH, 2);
        $rwModule = $this->module($rw, 1, Module::DIFFICULTY_STANDARD, 1);
        $mathModule = $this->module($math, 1, Module::DIFFICULTY_STANDARD, 2);
        $rwQuestion = $this->question($rwModule, Section::TYPE_RW);
        $mathQuestion = $this->question($mathModule, Section::TYPE_MATH);
        $attempt = $this->attempt($student, $test, $rwModule);

        $first = $this->submit($student, $attempt, $rwModule, $rwQuestion)->assertOk();
        $this->assertSame($mathModule->ulid, $first->json('next_module_id'));
        $this->submit($student, $attempt, $mathModule, $mathQuestion)
            ->assertOk()->assertJsonPath('test_completed', true);
        $this->assertNull($attempt->fresh()->total_score);
    }

    public function test_custom_test_advances_through_linear_modules(): void
    {
        [$student, $test] = $this->base('custom_test');
        $section = $this->section($test, Section::TYPE_RW, 1);
        $first = $this->module($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $second = $this->module($section, 2, Module::DIFFICULTY_STANDARD, 2);
        $firstQuestion = $this->question($first, Section::TYPE_RW);
        $secondQuestion = $this->question($second, Section::TYPE_RW);
        $attempt = $this->attempt($student, $test, $first);

        $response = $this->submit($student, $attempt, $first, $firstQuestion)->assertOk();
        $this->assertSame($second->ulid, $response->json('next_module_id'));
        $this->submit($student, $attempt, $second, $secondQuestion)
            ->assertOk()->assertJsonPath('test_completed', true);
    }

    public function test_linear_section_only_preserves_source_order(): void
    {
        [$student, $test] = $this->base('section_only');
        $section = $this->section($test, Section::TYPE_MATH, 1);
        $first = $this->module($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $second = $this->module($section, 2, Module::DIFFICULTY_STANDARD, 2);
        $firstQuestion = $this->question($first, Section::TYPE_MATH);
        $secondQuestion = $this->question($second, Section::TYPE_MATH);
        $attempt = $this->attempt($student, $test, $first);

        $response = $this->submit($student, $attempt, $first, $firstQuestion)->assertOk();
        $this->assertSame($second->ulid, $response->json('next_module_id'));
        $this->submit($student, $attempt, $second, $secondQuestion)
            ->assertOk()->assertJsonPath('test_completed', true);
    }

    public function test_invalid_active_structure_blocks_new_start_but_not_existing_resume(): void
    {
        [$student, $test] = $this->base('custom_test');
        $section = $this->section($test, Section::TYPE_RW, 1);
        $first = $this->module($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $third = $this->module($section, 3, Module::DIFFICULTY_STANDARD, 2);
        $this->question($first, Section::TYPE_RW);
        $this->question($third, Section::TYPE_RW);

        $this->actingAs($student)->postJson(route('engine.test.start', $test->id), ['mode' => 'fresh'])
            ->assertUnprocessable()->assertJsonValidationErrors('test_structure');

        $attempt = $this->attempt($student, $test, $first);
        $this->actingAs($student)->get(route('engine.session', ['ulid' => $first->ulid, 'attempt' => $attempt->ulid]))
            ->assertOk();
    }

    public function test_invalid_custom_blueprint_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->postJson(route('home-dashboard.tests.generate-configured'), [
            'title' => 'Ambiguous Custom',
            'test_type' => 'custom_test',
            'modules' => [
                ['section_type' => Section::TYPE_RW, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 10, 'total_questions' => 1],
                ['section_type' => Section::TYPE_RW, 'module_number' => 3, 'difficulty_level' => 'standard', 'duration_minutes' => 10, 'total_questions' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('test_structure');
    }

    private function base(string $type): array
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => "{$type} flow", 'test_type' => $type, 'status' => 'active', 'is_public' => true]);

        return [$student, $test];
    }

    private function section(Test $test, string $type, int $order): Section
    {
        return Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'is_public' => true]);
    }

    private function module(Section $section, int $number, string $difficulty, int $order): Module
    {
        return Module::create(['section_id' => $section->id, 'module_number' => $number, 'difficulty_level' => $difficulty, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => $order, 'is_public' => true]);
    }

    private function adaptiveModules(Section $section, string $type, int $startOrder): array
    {
        $standard = $this->module($section, 1, Module::DIFFICULTY_STANDARD, $startOrder);
        $easy = $this->module($section, 2, Module::DIFFICULTY_EASY, $startOrder + 1);
        $hard = $this->module($section, 2, Module::DIFFICULTY_HARD, $startOrder + 2);
        $standardQuestion = $this->question($standard, $type);
        $this->question($easy, $type);
        $this->question($hard, $type);

        return ['standard' => [$standard, $standardQuestion], 'easy' => $easy, 'hard' => $hard];
    }

    private function question(Module $module, string $type): Question
    {
        $position = $module->questions()->count() + 1;
        $question = Question::create(['stem' => 'Choose A.', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'easy', 'section_type' => $type, 'skill_domain' => $type === Section::TYPE_MATH ? 'algebra' : 'information_and_ideas', 'is_complete' => true]);
        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create(['question_id' => $question->id, 'label' => $label, 'content' => $label, 'is_correct' => $index === 0, 'order' => $index + 1]);
        }
        $module->questions()->attach($question->id, ['position' => $position]);

        return $question;
    }

    private function fillModule(Module $module, string $type, int $count): Question
    {
        $first = $this->question($module, $type);
        for ($index = 1; $index < $count; $index++) {
            $this->question($module, $type);
        }

        return $first;
    }

    private function attempt(User $student, Test $test, Module $module): UserTest
    {
        return UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress', 'current_module_id' => $module->id, 'current_module_started_at' => now()]);
    }

    private function submit(User $student, UserTest $attempt, Module $module, Question $question)
    {
        return $this->actingAs($student)->postJson(route('engine.test.submit-module'), ['user_test_id' => $attempt->id, 'module_id' => $module->id, 'answers' => [(string) $question->id => 'A']]);
    }
}
