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
use Tests\Support\ScoringFormBuilder;
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

        $rwAttempt = new UserTest([
            'score_reading_writing' => 600,
            'score_reading_writing_lower' => 550,
            'score_reading_writing_upper' => 640,
        ]);
        $mathAttempt = new UserTest([
            'score_math' => 600,
            'score_math_lower' => 560,
            'score_math_upper' => 650,
        ]);

        $range = $pipeline->combinedTotalRange($rwAttempt, $mathAttempt, 1200);

        $this->assertNotNull($range['lower']);
        $this->assertNotNull($range['upper']);
        $this->assertLessThanOrEqual(1200, $range['lower']);
        $this->assertGreaterThanOrEqual(1200, $range['upper']);
    }

    public function test_combined_total_range_preserves_boundary_adjustments_from_section_scores(): void
    {
        $rwAttempt = new UserTest([
            'score_reading_writing' => 800,
            'score_reading_writing_lower' => 720,
            'score_reading_writing_upper' => 800,
        ]);
        $mathAttempt = new UserTest([
            'score_math' => 800,
            'score_math_lower' => 720,
            'score_math_upper' => 800,
        ]);

        $range = app(SatScoringPipeline::class)->combinedTotalRange($rwAttempt, $mathAttempt, 1600);

        $this->assertSame(['lower' => 1490, 'upper' => 1600], $range);
    }

    public function test_combined_total_range_omits_bounds_if_a_section_band_is_missing(): void
    {
        $rwAttempt = new UserTest(['score_reading_writing' => 800, 'score_reading_writing_lower' => 720, 'score_reading_writing_upper' => 800]);
        $mathAttempt = new UserTest(['score_math' => 800, 'score_math_lower' => null, 'score_math_upper' => 800]);

        $this->assertSame(['lower' => null, 'upper' => null], app(SatScoringPipeline::class)->combinedTotalRange($rwAttempt, $mathAttempt, 1600));
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
        $this->assertSame('irt_curve_v2', $scores['score_conversion_version']);
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
        $this->assertSame('irt_curve_v2', $scores['score_conversion_version']);
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

    public function test_rescore_legacy_correctly_scores_complete_normal_and_adaptive_attempts(): void
    {
        $student = User::factory()->student()->create();
        $pipeline = app(SatScoringPipeline::class);
        $test = ScoringFormBuilder::normalFull('Legacy Normal');
        $attempt = ScoringFormBuilder::play($test, $student, fn ($module) => $module->section->type === Section::TYPE_RW
                ? ((int) $module->module_number === 1 ? 23 : 22)
                : ((int) $module->module_number === 1 ? 18 : 17));
        $attempt->update(['score_conversion_version' => 'generic_ds_v1']);

        $rescored = $pipeline->rescoreLegacy($attempt);
        $this->assertSame('ready', $rescored['status']);
        $this->assertSame(630, $rescored['score_reading_writing']);
        $this->assertSame(640, $rescored['score_math']);
        $this->assertSame(1270, $rescored['total_score']);
        $this->assertSame('normal_consensus_v1', $rescored['score_conversion_version']);

        $adaptiveTest = ScoringFormBuilder::adaptiveFull('Legacy Adaptive');
        $adaptiveAttempt = ScoringFormBuilder::play($adaptiveTest, $student,
            fn ($module) => $module->questions->where('is_pretest', false)->count());
        $this->assertSame(1600, $adaptiveAttempt->total_score);
        $adaptiveAttempt->update(['score_conversion_version' => 'generic_ds_v1']);

        $adaptiveRescored = $pipeline->rescoreLegacy($adaptiveAttempt);
        $this->assertSame('ready', $adaptiveRescored['status']);
        $this->assertSame(800, $adaptiveRescored['score_reading_writing']);
        $this->assertSame(800, $adaptiveRescored['score_math']);
        $this->assertSame(1600, $adaptiveRescored['total_score']);
        $this->assertSame('irt_curve_v2', $adaptiveRescored['score_conversion_version']);
        $this->assertSame('adaptive_irt_provisional', $adaptiveRescored['score_estimate_kind']);
        $this->assertSame('eap_3pl_v1', $adaptiveRescored['scoring_method']);
    }
}
