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
use App\Services\TestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullTestScoringModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generators_create_four_module_normal_and_six_module_adaptive_forms(): void
    {
        $service = app(TestManagementService::class);
        $normal = $service->generateFullSatStructure('Normal', Test::TYPE_FULL);
        $adaptive = $service->generateFullSatStructure('Adaptive', Test::TYPE_ADAPTIVE_FULL);

        $this->assertSame(4, $normal->sections->sum(fn ($section) => $section->modules->count()));
        $this->assertSame(6, $adaptive->sections->sum(fn ($section) => $section->modules->count()));
        $this->assertTrue($normal->sections->every(fn ($section) => $section->modules->where('module_number', 2)->count() === 1));
        $this->assertTrue($adaptive->sections->every(fn ($section) => $section->modules->where('module_number', 2)->count() === 2));
    }

    public function test_incomplete_adaptive_draft_can_convert_to_normal_but_cannot_publish_as_adaptive(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'One Path', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'created_by' => $teacher->id]);
            foreach ([[1, 'standard'], [2, 'hard']] as $moduleOrder => [$number, $difficulty]) {
                $module = Module::create(['section_id' => $section->id, 'module_number' => $number, 'difficulty_level' => $difficulty, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => $moduleOrder + 1, 'created_by' => $teacher->id]);
                $this->question($module, $type);
            }
        }

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), [
            'title' => $test->title, 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('test_structure');

        $this->actingAs($teacher)->postJson(route('home-dashboard.tests.convert-to-normal', $test))
            ->assertOk()->assertJsonPath('data.test_type', Test::TYPE_FULL);
    }

    public function test_normal_full_cannot_publish_with_non_official_module_sizes(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'Undersized Normal', 'test_type' => Test::TYPE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'created_by' => $teacher->id]);
            foreach ([1, 2] as $moduleNumber) {
                $module = Module::create([
                    'section_id' => $section->id,
                    'module_number' => $moduleNumber,
                    'difficulty_level' => $moduleNumber === 1 ? 'standard' : 'hard',
                    'duration_minutes' => $type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION,
                    'total_questions' => 1,
                    'order' => $moduleNumber,
                    'created_by' => $teacher->id,
                ]);
                $this->question($module, $type);
            }
        }

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), ['status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_structure');

        $this->assertSame('draft', $test->fresh()->status);
    }

    public function test_scoring_type_cannot_change_after_attempts_exist(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Attempted Form', 'test_type' => Test::TYPE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), ['test_type' => Test::TYPE_ADAPTIVE_FULL])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_type');

        $this->assertSame(Test::TYPE_FULL, $test->fresh()->test_type);
    }

    public function test_adaptive_draft_cannot_convert_to_normal_after_attempts_exist(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Attempted Adaptive', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);

        $this->actingAs($teacher)->postJson(route('home-dashboard.tests.convert-to-normal', $test))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_type');

        $this->assertSame(Test::TYPE_ADAPTIVE_FULL, $test->fresh()->test_type);
    }

    public function test_adaptive_result_discloses_provisional_range(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'IRT Result', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);
        $attempt = UserTest::create([
            'user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed', 'completed_at' => now(),
            'total_score' => 1240, 'total_score_lower' => 1170, 'total_score_upper' => 1310,
            'score_reading_writing' => 610, 'score_math' => 630,
            'score_conversion_version' => 'provisional_irt_v1', 'score_estimate_kind' => 'adaptive_irt_provisional',
        ]);

        $this->actingAs($student)->get(route('my-practice.score', $attempt))
            ->assertOk()->assertSee('Provisional IRT estimate')->assertSee('1170–1310')->assertSee('Item parameters and scaled mapping remain provisional.');
    }

    public function test_rescore_command_audits_and_corrects_generic_v1_attempt(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Legacy One Path', 'test_type' => Test::TYPE_FULL, 'status' => 'active']);
        
        $rwSection = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $rwModule = Module::create(['section_id' => $rwSection->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 54, 'order' => 1]);

        $mathSection = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 2]);
        $mathModule = Module::create(['section_id' => $mathSection->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 44, 'order' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed', 'completed_at' => now(),
            'score_reading_writing' => 710, 'score_math' => 700, 'total_score' => 1410,
            'score_conversion_version' => 'generic_ds_v1', 'score_estimate_kind' => 'generic',
        ]);
        $this->answers($attempt, $rwModule, 54, 45);
        $this->answers($attempt, $mathModule, 44, 35);
 
        $this->artisan('sat:rescore-generic-v1')->assertSuccessful();
        $this->assertSame(1410, $attempt->fresh()->total_score);
        $this->artisan('sat:rescore-generic-v1', ['--apply' => true])->assertSuccessful();
 
        $attempt->refresh();
        $this->assertSame(1270, $attempt->total_score);
        $this->assertSame('normal_consensus_v1', $attempt->score_conversion_version);
        $this->assertDatabaseHas('user_test_score_revisions', ['user_test_id' => $attempt->id]);
        $this->assertSame(1410, $attempt->scoreRevisions()->firstOrFail()->previous_score['total_score']);
    }

    private function question(Module $module, string $type): Question
    {
        $question = Question::create(['stem' => 'Fixture', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => $type, 'skill_domain' => 'fixture', 'is_complete' => true]);
        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create(['question_id' => $question->id, 'label' => $label, 'content' => $label, 'is_correct' => $index === 0, 'order' => $index + 1]);
        }
        $module->questions()->attach($question->id, ['position' => 1]);

        return $question;
    }

    private function answers(UserTest $attempt, Module $module, int $total, int $correct): void
    {
        $module->loadMissing('section');
        $sectionType = $module->section->type;
        for ($index = 0; $index < $total; $index++) {
            $question = Question::create(['stem' => "{$sectionType} {$index}", 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => $sectionType, 'skill_domain' => 'fixture', 'is_complete' => true]);
            UserTestAnswer::create([
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'question_id' => $question->id,
                'selected_answer' => 'A',
                'is_correct' => $index < $correct,
                'question_snapshot' => null,
            ]);
        }
    }
}
