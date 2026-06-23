<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReusableTestContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_derives_independent_section_test_with_shared_questions(): void
    {
        $teacher = User::factory()->teacher()->create(['teacher_approval_status' => 'approved', 'email_verified_at' => now()]);
        [$source, $section, $module, $question] = $this->source($teacher, false);

        $response = $this->actingAs($teacher)->postJson(route('home-dashboard.sections.derive-test', $section), ['title' => 'Derived Section'])->assertCreated();
        $copy = Test::with('sections.modules.questions')->findOrFail($response->json('data.id'));
        $copiedSection = $copy->sections->first();
        $copiedModule = $copiedSection->modules->first();

        $this->assertSame('section_only', $copy->test_type);
        $this->assertSame('draft', $copy->status);
        $this->assertSame($copiedSection->id, $copiedModule->section_id);
        $this->assertNotSame($module->id, $copiedModule->id);
        $this->assertSame([$question->id], $copiedModule->questions->pluck('id')->all());
        $copiedModule->update(['duration_minutes' => 99]);
        $this->assertNotSame(99, $module->fresh()->duration_minutes);
    }

    public function test_teacher_can_copy_public_module_but_not_private_module(): void
    {
        $owner = User::factory()->teacher()->create(['teacher_approval_status' => 'approved', 'email_verified_at' => now()]);
        $teacher = User::factory()->teacher()->create(['teacher_approval_status' => 'approved', 'email_verified_at' => now()]);
        [, $publicSection, $publicModule] = $this->source($owner, true);
        [, $privateSection, $privateModule] = $this->source($owner, false);

        $this->actingAs($teacher)->postJson(route('home-dashboard.modules.derive-test', $publicModule), ['title' => 'Public Copy', 'source_section_id' => $publicSection->id])->assertCreated();
        $this->actingAs($teacher)->postJson(route('home-dashboard.modules.derive-test', $privateModule), ['title' => 'Private Copy', 'source_section_id' => $privateSection->id])->assertForbidden();
    }

    public function test_reuse_rejects_duplicate_module_in_destination(): void
    {
        $teacher = User::factory()->teacher()->create(['teacher_approval_status' => 'approved', 'email_verified_at' => now()]);
        [, $sourceSection, $sourceModule] = $this->source($teacher, false);
        $destination = Test::create(['title' => 'Destination', 'test_type' => 'custom_test', 'status' => 'draft', 'created_by' => $teacher->id]);
        $target = Section::create(['test_id' => $destination->id, 'name' => 'RW', 'type' => Section::TYPE_RW, 'order' => 1, 'created_by' => $teacher->id]);
        Module::create(['section_id' => $target->id, 'module_number' => $sourceModule->module_number, 'difficulty_level' => $sourceModule->difficulty_level, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);

        $this->actingAs($teacher)->postJson(route('home-dashboard.modules.reuse', $sourceModule), ['destination_test_id' => $destination->id, 'source_section_id' => $sourceSection->id])
            ->assertUnprocessable()->assertJsonValidationErrors('destination_test_id');
    }

    private function source(User $owner, bool $public): array
    {
        $test = Test::create(['title' => uniqid('Source '), 'test_type' => 'module_only', 'status' => 'draft', 'created_by' => $owner->id, 'is_public' => $public]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading and Writing', 'type' => Section::TYPE_RW, 'order' => 1, 'created_by' => $owner->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => 1, 'created_by' => $owner->id]);
        $question = Question::create(['stem' => 'Reusable', 'question_type' => Question::TYPE_SPR, 'difficulty' => 'easy', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'information_and_ideas', 'is_complete' => true, 'created_by' => $owner->id]);
        $module->questions()->attach($question->id, ['position' => 1]);

        return [$test, $section, $module, $question];
    }
}
