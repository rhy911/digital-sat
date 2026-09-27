<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatScoringPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_attempt_returns_null_scores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $test = Test::create(['title' => 'Test', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);
        $attempt = UserTest::create(['user_id' => $admin->id, 'test_id' => $test->id, 'status' => 'in_progress']);

        $pipeline = app(SatScoringPipeline::class);
        $this->assertNull($pipeline->scoreAttempt($test, $attempt));
    }

    public function test_route_adaptive_module_returns_routed_difficulty(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = Test::create(['title' => 'Test', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $m1 = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 1, 'order' => 1]);
        $q = Question::create([
            'stem' => 'Q1',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'irt_a' => 1.5,
            'irt_b' => 0.0,
            'irt_c' => 0.2,
        ]);
        $m1->questions()->attach($q->id, ['position' => 1]);

        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $m1->id,
            'question_id' => $q->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $pipeline = app(SatScoringPipeline::class);
        $routed = $pipeline->routeAdaptiveModule($attempt, $section, $m1);

        $this->assertSame(Module::DIFFICULTY_HARD, $routed);
    }

    public function test_combined_total_range_calculates_bounds(): void
    {
        $pipeline = app(SatScoringPipeline::class);

        $rwAttempt = new UserTest(['rw_theta' => 0.5, 'rw_theta_se' => 0.3, 'rw_m2_path' => 'hard']);
        $mathAttempt = new UserTest(['math_theta' => 0.5, 'math_theta_se' => 0.3, 'math_m2_path' => 'hard']);

        $range = $pipeline->combinedTotalRange($rwAttempt, $mathAttempt, 1200);

        $this->assertNotNull($range['lower']);
        $this->assertNotNull($range['upper']);
        $this->assertLessThanOrEqual(1200, $range['lower']);
        $this->assertGreaterThanOrEqual(1200, $range['upper']);
    }

    public function test_score_attempt_adaptive_full_produces_scaled_scores_with_bands(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = Test::create(['title' => 'Adaptive Full Test', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);

        $rw = Section::create(['test_id' => $test->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1]);
        $rwM1 = Module::create(['section_id' => $rw->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 1, 'order' => 1]);
        $rwM2 = Module::create(['section_id' => $rw->id, 'module_number' => 2, 'difficulty_level' => 'hard', 'duration_minutes' => 32, 'total_questions' => 1, 'order' => 2]);
        $qRw1 = Question::create(['stem' => 'RW1', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'craft_and_structure', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.0, 'irt_c' => 0.2]);
        $qRw2 = Question::create(['stem' => 'RW2', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'hard', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'craft_and_structure', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.5, 'irt_c' => 0.2]);
        $rwM1->questions()->attach($qRw1->id, ['position' => 1]);
        $rwM2->questions()->attach($qRw2->id, ['position' => 1]);

        $math = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 2]);
        $mathM1 = Module::create(['section_id' => $math->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1]);
        $mathM2 = Module::create(['section_id' => $math->id, 'module_number' => 2, 'difficulty_level' => 'hard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 2]);
        $qMath1 = Question::create(['stem' => 'M1', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_MATH, 'skill_domain' => 'algebra', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.0, 'irt_c' => 0.2]);
        $qMath2 = Question::create(['stem' => 'M2', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'hard', 'section_type' => Section::TYPE_MATH, 'skill_domain' => 'algebra', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.5, 'irt_c' => 0.2]);
        $mathM1->questions()->attach($qMath1->id, ['position' => 1]);
        $mathM2->questions()->attach($qMath2->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'rw_m2_path' => 'hard',
            'math_m2_path' => 'hard',
        ]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $rwM1->id, 'question_id' => $qRw1->id, 'selected_answer' => 'A', 'is_correct' => true]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $rwM2->id, 'question_id' => $qRw2->id, 'selected_answer' => 'A', 'is_correct' => true]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $mathM1->id, 'question_id' => $qMath1->id, 'selected_answer' => 'A', 'is_correct' => true]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $mathM2->id, 'question_id' => $qMath2->id, 'selected_answer' => 'A', 'is_correct' => true]);

        $pipeline = app(SatScoringPipeline::class);
        $scores = $pipeline->scoreAttempt($test, $attempt);

        $this->assertNotNull($scores['score_reading_writing']);
        $this->assertNotNull($scores['score_reading_writing_lower']);
        $this->assertNotNull($scores['score_reading_writing_upper']);
        $this->assertNotNull($scores['score_math']);
        $this->assertNotNull($scores['total_score']);
        $this->assertNotNull($scores['total_score_lower']);
        $this->assertNotNull($scores['total_score_upper']);
        $this->assertSame('irt_curve_v1', $scores['score_conversion_version']);
    }

    public function test_score_attempt_normal_full_produces_scaled_scores(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = Test::create(['title' => 'Normal Full Test', 'test_type' => Test::TYPE_FULL, 'status' => 'active']);

        $rw = Section::create(['test_id' => $test->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1]);
        $rwM = Module::create(['section_id' => $rw->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 64, 'total_questions' => 1, 'order' => 1]);
        $qRw = Question::create(['stem' => 'RW', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'craft_and_structure', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.0, 'irt_c' => 0.2]);
        $rwM->questions()->attach($qRw->id, ['position' => 1]);

        $math = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 2]);
        $mathM = Module::create(['section_id' => $math->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 70, 'total_questions' => 1, 'order' => 1]);
        $qMath = Question::create(['stem' => 'M', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_MATH, 'skill_domain' => 'algebra', 'is_complete' => true, 'irt_a' => 1.0, 'irt_b' => 0.0, 'irt_c' => 0.2]);
        $mathM->questions()->attach($qMath->id, ['position' => 1]);

        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed']);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $rwM->id, 'question_id' => $qRw->id, 'selected_answer' => 'A', 'is_correct' => true]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $mathM->id, 'question_id' => $qMath->id, 'selected_answer' => 'A', 'is_correct' => true]);

        $pipeline = app(SatScoringPipeline::class);
        $scores = $pipeline->scoreAttempt($test, $attempt);

        $this->assertNotNull($scores['score_reading_writing']);
        $this->assertNotNull($scores['score_math']);
        $this->assertNotNull($scores['total_score']);
        $this->assertSame('irt_curve_v1', $scores['score_conversion_version']);
    }

    public function test_score_attempt_ability_only_when_not_full_test(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = Test::create(['title' => 'Section Only Test', 'test_type' => 'section_only', 'status' => 'active']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 1]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1]);
        $question = Question::create([
            'stem' => 'Q1',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
            'irt_a' => 1.0,
            'irt_b' => 0.0,
            'irt_c' => 0.2,
        ]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $pipeline = app(SatScoringPipeline::class);
        $scores = $pipeline->scoreAttempt($test, $attempt);

        $this->assertNotNull($scores);
        $this->assertNull($scores['score_math']);
        $this->assertNull($scores['total_score']);
        $this->assertNotNull($scores['math_theta']);
        $this->assertNotNull($scores['math_theta_se']);
    }

    public function test_rescore_legacy_correctly_scores_normal_and_adaptive_attempts(): void
    {
        $student = User::factory()->student()->create();
        $test = Test::create(['title' => 'Legacy Normal', 'test_type' => Test::TYPE_FULL, 'status' => 'active']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 54, 'order' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 710,
            'score_math' => 700,
            'total_score' => 1410,
            'score_conversion_version' => 'generic_ds_v1',
            'score_estimate_kind' => 'generic',
        ]);

        $mathSection = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 2]);
        $mathModule = Module::create(['section_id' => $mathSection->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 44, 'order' => 1]);

        for ($i = 0; $i < 54; $i++) {
            $q = Question::create(['stem' => "RW {$i}", 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'fixture', 'is_complete' => true]);
            UserTestAnswer::create([
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'question_id' => $q->id,
                'selected_answer' => 'A',
                'is_correct' => $i < 45,
            ]);
        }

        for ($i = 0; $i < 44; $i++) {
            $q = Question::create(['stem' => "Math {$i}", 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_MATH, 'skill_domain' => 'fixture', 'is_complete' => true]);
            UserTestAnswer::create([
                'user_test_id' => $attempt->id,
                'module_id' => $mathModule->id,
                'question_id' => $q->id,
                'selected_answer' => 'A',
                'is_correct' => $i < 35,
            ]);
        }

        $pipeline = app(SatScoringPipeline::class);
        $rescored = $pipeline->rescoreLegacy($attempt);

        $this->assertNotNull($rescored);
        $this->assertSame(630, $rescored['score_reading_writing']);
        $this->assertSame(640, $rescored['score_math']);
        $this->assertSame(1270, $rescored['total_score']);
        $this->assertSame('normal_consensus_v1', $rescored['score_conversion_version']);

        // Adaptive full length legacy rescore test
        $adaptiveTest = Test::create(['title' => 'Legacy Adaptive', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);
        $adaptiveAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $adaptiveTest->id,
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 700,
            'score_math' => 700,
            'total_score' => 1400,
            'score_conversion_version' => 'generic_ds_v1',
            'score_estimate_kind' => 'generic',
            'rw_m2_path' => 'hard',
            'math_m2_path' => 'hard',
        ]);

        for ($i = 0; $i < 54; $i++) {
            $q = Question::create([
                'stem' => "Adaptive RW {$i}",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => 'medium',
                'section_type' => Section::TYPE_RW,
                'skill_domain' => 'fixture',
                'is_complete' => true,
                'irt_a' => 1.0,
                'irt_b' => 0.0,
                'irt_c' => 0.0,
                'is_pretest' => false,
            ]);
            UserTestAnswer::create([
                'user_test_id' => $adaptiveAttempt->id,
                'module_id' => $module->id,
                'question_id' => $q->id,
                'selected_answer' => 'A',
                'is_correct' => $i < 40,
            ]);
        }

        for ($i = 0; $i < 44; $i++) {
            $q = Question::create([
                'stem' => "Adaptive Math {$i}",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => 'medium',
                'section_type' => Section::TYPE_MATH,
                'skill_domain' => 'fixture',
                'is_complete' => true,
                'irt_a' => 1.0,
                'irt_b' => 0.0,
                'irt_c' => 0.0,
                'is_pretest' => false,
            ]);
            UserTestAnswer::create([
                'user_test_id' => $adaptiveAttempt->id,
                'module_id' => $mathModule->id,
                'question_id' => $q->id,
                'selected_answer' => 'A',
                'is_correct' => $i < 30,
            ]);
        }

        $adaptiveRescored = $pipeline->rescoreLegacy($adaptiveAttempt);
        $this->assertNotNull($adaptiveRescored);
        $this->assertNotNull($adaptiveRescored['score_reading_writing']);
        $this->assertNotNull($adaptiveRescored['score_math']);
        $this->assertNotNull($adaptiveRescored['total_score']);
        $this->assertSame('irt_curve_v1', $adaptiveRescored['score_conversion_version']);
        $this->assertSame('adaptive_irt_provisional', $adaptiveRescored['score_estimate_kind']);
        $this->assertSame('eap_3pl_v1', $adaptiveRescored['scoring_method']);
    }
}
