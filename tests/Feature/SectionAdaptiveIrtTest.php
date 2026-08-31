<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionAdaptiveIrtTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_adaptive_test_section_returns_null(): void
    {
        $test = Test::create(['title' => 'Linear Test', 'test_type' => Test::TYPE_FULL, 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 1]);

        $this->assertNull($section->adaptiveIrtDifference());
    }

    public function test_adaptive_section_with_empty_branches_returns_has_data_false(): void
    {
        $test = Test::create(['title' => 'Adaptive Test', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1]);

        $m1 = $this->createModule($section, 1, Module::DIFFICULTY_STANDARD);
        $easy = $this->createModule($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->createModule($section, 2, Module::DIFFICULTY_HARD);

        $result = $section->adaptiveIrtDifference();

        $this->assertNotNull($result);
        $this->assertFalse($result['has_data']);
        $this->assertNull($result['diff']);
        $this->assertFalse($result['meets_target']);
    }

    public function test_adaptive_section_computes_difference_and_meets_target(): void
    {
        $test = Test::create(['title' => 'Adaptive Test', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 1]);

        $easy = $this->createModule($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->createModule($section, 2, Module::DIFFICULTY_HARD);

        $this->attachQuestion($easy, -0.4, false);
        $this->attachQuestion($easy, -0.6, false); // easy mean = -0.50

        $this->attachQuestion($hard, 0.2, false);
        $this->attachQuestion($hard, 0.4, false); // hard mean = 0.30

        $result = $section->fresh(['modules.questions'])->adaptiveIrtDifference();

        $this->assertNotNull($result);
        $this->assertTrue($result['has_data']);
        $this->assertEquals(-0.50, $result['easy_mean']);
        $this->assertEquals(0.30, $result['hard_mean']);
        $this->assertEquals(0.80, $result['diff']); // 0.30 - (-0.50) = 0.80
        $this->assertTrue($result['meets_target']);
    }

    public function test_adaptive_section_detects_sub_threshold_separation(): void
    {
        $test = Test::create(['title' => 'Adaptive Low Gap', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1]);

        $easy = $this->createModule($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->createModule($section, 2, Module::DIFFICULTY_HARD);

        $this->attachQuestion($easy, 0.0, false);
        $this->attachQuestion($hard, 0.3, false); // diff = 0.30 < 0.50

        $result = $section->fresh(['modules.questions'])->adaptiveIrtDifference();

        $this->assertNotNull($result);
        $this->assertTrue($result['has_data']);
        $this->assertEquals(0.30, $result['diff']);
        $this->assertFalse($result['meets_target']);
    }

    public function test_adaptive_section_excludes_pretest_questions_from_mean(): void
    {
        $test = Test::create(['title' => 'Adaptive With Pretests', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1]);

        $easy = $this->createModule($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->createModule($section, 2, Module::DIFFICULTY_HARD);

        $this->attachQuestion($easy, -0.5, false);
        $this->attachQuestion($easy, 5.0, true); // pretest, should be ignored

        $this->attachQuestion($hard, 0.5, false);
        $this->attachQuestion($hard, -5.0, true); // pretest, should be ignored

        $result = $section->fresh(['modules.questions'])->adaptiveIrtDifference();

        $this->assertNotNull($result);
        $this->assertEquals(-0.5, $result['easy_mean']);
        $this->assertEquals(0.5, $result['hard_mean']);
        $this->assertEquals(1.0, $result['diff']);
        $this->assertTrue($result['meets_target']);
    }

    private function createModule(Section $section, int $number, string $difficulty): Module
    {
        return Module::create([
            'section_id' => $section->id,
            'module_number' => $number,
            'difficulty_level' => $difficulty,
            'duration_minutes' => 32,
            'total_questions' => 27,
            'order' => $number,
        ]);
    }

    private function attachQuestion(Module $module, float $irtB, bool $isPretest): Question
    {
        $position = $module->questions()->count() + 1;
        $question = Question::create([
            'stem' => "Test item {$position}",
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => $irtB >= 0.5 ? 'hard' : ($irtB <= -0.5 ? 'easy' : 'medium'),
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'is_pretest' => $isPretest,
            'irt_a' => 1.0,
            'irt_b' => $irtB,
            'irt_c' => 0.2,
        ]);
        $module->questions()->attach($question->id, ['position' => $position]);

        return $question;
    }
}
