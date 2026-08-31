<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Services\DifficultyDistributionAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DifficultyDistributionAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_module_with_no_difficulty_spread_warns(): void
    {
        $test = Test::create(['title' => 'All Hard', 'test_type' => 'full_length', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $module = $this->module($section, 1, Module::DIFFICULTY_STANDARD);
        $this->fill($module, $section->type, 'hard', 10);

        $warnings = app(DifficultyDistributionAdvisor::class)->warnings($test->fresh());

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('hard', $warnings[0]['message']);
        $this->assertSame(1, $warnings[0]['module_number']);
    }

    public function test_balanced_standard_module_does_not_warn(): void
    {
        $test = Test::create(['title' => 'Balanced', 'test_type' => 'full_length', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 1]);
        $module = $this->module($section, 1, Module::DIFFICULTY_STANDARD);
        $this->fill($module, $section->type, 'easy', 4);
        $this->fill($module, $section->type, 'medium', 3);
        $this->fill($module, $section->type, 'hard', 3);

        $this->assertSame([], app(DifficultyDistributionAdvisor::class)->warnings($test->fresh()));
    }

    public function test_intentional_hard_module_two_branch_is_not_flagged(): void
    {
        $test = Test::create(['title' => 'Adaptive Branch', 'test_type' => 'adaptive_full_length', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $module = $this->module($section, 2, Module::DIFFICULTY_HARD);
        $this->fill($module, $section->type, 'hard', 10);

        $this->assertSame([], app(DifficultyDistributionAdvisor::class)->warnings($test->fresh()));
    }

    public function test_small_module_is_not_judged(): void
    {
        $test = Test::create(['title' => 'Tiny', 'test_type' => 'module_only', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => Section::TYPE_MATH, 'order' => 1]);
        $module = $this->module($section, 1, Module::DIFFICULTY_STANDARD);
        $this->fill($module, $section->type, 'hard', 3);

        $this->assertSame([], app(DifficultyDistributionAdvisor::class)->warnings($test->fresh()));
    }

    public function test_publishing_surfaces_advisory_warnings_without_blocking(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'Skewed Custom', 'test_type' => 'custom_test', 'status' => 'draft', 'created_by' => $admin->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $module = $this->module($section, 1, Module::DIFFICULTY_STANDARD);
        $this->fill($module, $section->type, 'hard', 10);

        $response = $this->actingAs($admin)->putJson(route('home-dashboard.tests.update', $test->id), ['status' => 'active']);

        // Publication succeeds (not blocked) but carries the advisory warning.
        $response->assertOk();
        $this->assertSame('active', $test->fresh()->status);
        $this->assertNotEmpty($response->json('warnings'));
        $this->assertStringContainsString('hard', $response->json('warnings.0.message'));
    }

    public function test_adaptive_module_two_low_separation_surfaces_warning(): void
    {
        $test = Test::create(['title' => 'Low Separation Adaptive', 'test_type' => 'adaptive_full_length', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $easy = $this->module($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->module($section, 2, Module::DIFFICULTY_HARD);

        $this->fillWithIrt($easy, Section::TYPE_RW, 0.1, 5);
        $this->fillWithIrt($hard, Section::TYPE_RW, 0.4, 5); // difference = 0.3 < 0.5

        $warnings = app(DifficultyDistributionAdvisor::class)->warnings($test->fresh());

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('separation', $warnings[0]['message']);
        $this->assertSame(2, $warnings[0]['module_number']);
    }

    public function test_adaptive_module_two_adequate_separation_does_not_warn(): void
    {
        $test = Test::create(['title' => 'Good Separation Adaptive', 'test_type' => 'adaptive_full_length', 'status' => 'draft']);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading & Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        $easy = $this->module($section, 2, Module::DIFFICULTY_EASY);
        $hard = $this->module($section, 2, Module::DIFFICULTY_HARD);

        $this->fillWithIrt($easy, Section::TYPE_RW, -0.5, 5);
        $this->fillWithIrt($hard, Section::TYPE_RW, 0.5, 5); // difference = 1.0 >= 0.5

        $warnings = app(DifficultyDistributionAdvisor::class)->warnings($test->fresh());

        $this->assertSame([], $warnings);
    }

    private function module(Section $section, int $number, string $difficulty): Module
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

    private function fill(Module $module, string $sectionType, string $difficulty, int $count): void
    {
        $base = $module->questions()->count();
        for ($i = 0; $i < $count; $i++) {
            $question = Question::create([
                'stem' => "{$difficulty} {$i}",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => $difficulty,
                'section_type' => $sectionType,
                'skill_domain' => 'fixture',
                'is_complete' => true,
                'is_pretest' => false,
            ]);
            $module->questions()->attach($question->id, ['position' => $base + $i + 1]);
        }
    }

    private function fillWithIrt(Module $module, string $sectionType, float $irtB, int $count): void
    {
        $base = $module->questions()->count();
        for ($i = 0; $i < $count; $i++) {
            $question = Question::create([
                'stem' => "IRT item {$i}",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => $irtB >= 0.5 ? 'hard' : ($irtB <= -0.5 ? 'easy' : 'medium'),
                'section_type' => $sectionType,
                'skill_domain' => 'fixture',
                'is_complete' => true,
                'is_pretest' => false,
                'irt_a' => 1.0,
                'irt_b' => $irtB,
                'irt_c' => 0.2,
            ]);
            $module->questions()->attach($question->id, ['position' => $base + $i + 1]);
        }
    }
}
