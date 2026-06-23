<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\ScoreConversionSet;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Services\FormScoringAuditService;
use App\Services\ScoreConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreConversionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_import_draft_but_invalid_form_cannot_approve_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = $this->smallFullLength($admin);
        $response = $this->actingAs($admin)->postJson(route('home-dashboard.tests.score-conversions.store', $test), [
            'source_name' => 'Reviewed practice estimate v1',
            'source_url' => 'https://example.test/source',
            'rows' => $this->rows(2),
        ])->assertCreated()->assertJsonPath('data.status', ScoreConversionSet::STATUS_DRAFT);

        $set = ScoreConversionSet::findOrFail($response->json('data.id'));
        $this->actingAs($admin)
            ->postJson(route('home-dashboard.score-conversions.approve', $set))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score_conversion');
    }

    public function test_approved_lookup_is_exact_and_changed_form_uses_generic_fallback(): void
    {
        $test = Test::create(['title' => 'Lookup', 'test_type' => 'full_length', 'status' => 'active']);
        $set = ScoreConversionSet::create([
            'test_id' => $test->id,
            'version' => 1,
            'status' => ScoreConversionSet::STATUS_APPROVED,
            'source_name' => 'Fixture',
            'form_checksum' => app(FormScoringAuditService::class)->formChecksum($test),
        ]);
        $set->rows()->create([
            'section_type' => Section::TYPE_MATH,
            'm2_difficulty' => Module::DIFFICULTY_STANDARD,
            'raw_score' => 30,
            'scaled_score' => 650,
        ]);

        $result = app(ScoreConversionService::class)->convert($test, Section::TYPE_MATH, 30, 44);
        $this->assertSame(650, $result['scaled_score']);
        $this->assertSame($set->id, $result['conversion_set_id']);

        $test->sections()->create(['name' => 'Changed', 'type' => Section::TYPE_MATH, 'order' => 1]);
        $fallback = app(ScoreConversionService::class)->convert($test->fresh(), Section::TYPE_MATH, 30, 44);
        $this->assertNull($fallback['conversion_set_id']);
        $this->assertSame('normal_consensus_v1', $fallback['conversion_version']);
        $this->assertSame('normal_generic', $fallback['estimate_kind']);
    }

    public function test_official_shape_and_complete_conversion_pass_form_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'Audited Form', 'test_type' => 'full_length', 'status' => 'draft', 'created_by' => $admin->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order]);
            $count = $type === Section::TYPE_RW ? Module::RW_QUESTIONS : Module::MATH_QUESTIONS;
            $duration = $type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION;
            foreach ([[1, Module::DIFFICULTY_STANDARD], [2, Module::DIFFICULTY_STANDARD]] as $moduleOrder => [$number, $difficulty]) {
                $module = Module::create([
                    'section_id' => $section->id,
                    'module_number' => $number,
                    'difficulty_level' => $difficulty,
                    'duration_minutes' => $duration,
                    'total_questions' => $count,
                    'order' => $moduleOrder + 1,
                ]);
                for ($position = 1; $position <= $count; $position++) {
                    $question = Question::create([
                        'stem' => "{$type} {$difficulty} {$position}",
                        'question_type' => Question::TYPE_MCQ,
                        'difficulty' => $difficulty === Module::DIFFICULTY_STANDARD ? 'medium' : $difficulty,
                        'section_type' => $type,
                        'skill_domain' => 'fixture',
                        'is_complete' => true,
                        'is_pretest' => false,
                        'irt_b' => 0.0,
                    ]);
                    $module->questions()->attach($question->id, ['position' => $position]);
                }
            }
        }

        $set = ScoreConversionSet::create(['test_id' => $test->id, 'version' => 1, 'status' => 'draft', 'source_name' => 'Fixture']);
        foreach ([Section::TYPE_RW => 54, Section::TYPE_MATH => 44] as $section => $total) {
            foreach ($this->rowsFor($section, $total) as $row) {
                $set->rows()->create($row);
            }
        }

        $report = app(FormScoringAuditService::class)->audit($test, $set);
        $this->assertTrue($report['eligible'], implode("\n", $report['errors']));
        $this->actingAs($admin)
            ->postJson(route('home-dashboard.score-conversions.approve', $set))
            ->assertOk()
            ->assertJsonPath('data.status', ScoreConversionSet::STATUS_APPROVED);
        $this->assertNotNull($test->fresh()->content_locked_at);
    }

    public function test_score_page_labels_numeric_result_as_estimated_practice_score(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Estimated Result', 'test_type' => 'full_length', 'status' => 'active']);
        $set = ScoreConversionSet::create([
            'test_id' => $test->id,
            'version' => 2,
            'status' => ScoreConversionSet::STATUS_APPROVED,
            'source_name' => 'Fixture',
        ]);
        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'score_conversion_set_id' => $set->id,
        ]);

        $this->actingAs($student)->get(route('my-practice.score', $attempt))
            ->assertOk()
            ->assertSee('Estimated practice score')
            ->assertSee('Not an official College Board score.');
    }

    public function test_score_page_discloses_generic_conversion(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Generic Result', 'test_type' => 'full_length', 'status' => 'active']);
        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1180,
            'score_reading_writing' => 590,
            'score_math' => 590,
            'score_conversion_version' => 'normal_consensus_v1',
            'score_estimate_kind' => 'normal_generic',
        ]);

        $this->actingAs($student)->get(route('my-practice.score', $attempt))
            ->assertOk()
            ->assertSee('Route-neutral estimate using all presented questions and built-in conversion')
            ->assertSee('A form-specific table may improve accuracy.')
            ->assertSee('Not an official College Board score.');
    }

    private function smallFullLength(User $owner): Test
    {
        $test = Test::create(['title' => 'Small', 'test_type' => 'full_length', 'status' => 'draft', 'created_by' => $owner->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'created_by' => $owner->id]);
            foreach ([[1, 'standard'], [2, 'standard']] as $moduleOrder => [$number, $difficulty]) {
                $module = Module::create(['section_id' => $section->id, 'module_number' => $number, 'difficulty_level' => $difficulty, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => $moduleOrder + 1]);
                $question = Question::create(['stem' => 'Fixture', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => $type, 'skill_domain' => 'fixture', 'is_complete' => true]);
                $module->questions()->attach($question->id, ['position' => 1]);
            }
        }

        return $test;
    }

    private function rows(int $total): array
    {
        return collect([Section::TYPE_RW, Section::TYPE_MATH])->flatMap(fn ($section) => $this->rowsFor($section, $total))->all();
    }

    private function rowsFor(string $section, int $total): array
    {
        return collect(range(0, $total))->map(fn ($raw) => [
            'section_type' => $section,
            'm2_difficulty' => Module::DIFFICULTY_STANDARD,
            'raw_score' => $raw,
            'scaled_score' => (int) min(800, 200 + round(($raw / max(1, $total)) * 60) * 10),
        ])->all();
    }
}
