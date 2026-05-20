<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test linking module to an existing section via section_id.
     */
    public function test_link_module_to_existing_section(): void
    {
        $test = Test::create([
            'title' => 'Test SAT',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'type' => 'reading_writing',
            'name' => 'Reading and Writing',
            'order' => 1,
        ]);

        $module = Module::create([
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'key' => 'MOD_TEST1',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('test-dashboard.sections.link-module'), [
                'module_id' => $module->id,
                'section_id' => $section->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Module linked successfully!',
        ]);

        $this->assertTrue($section->modules()->where('module_id', $module->id)->exists());
    }

    /**
     * Test linking module to a test and type, which auto-creates the section.
     */
    public function test_link_module_to_test_auto_creates_section(): void
    {
        $test = Test::create([
            'title' => 'Test SAT Auto',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);

        $module = Module::create([
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'key' => 'MOD_TEST2',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('test-dashboard.sections.link-module'), [
                'module_id' => $module->id,
                'test_id' => $test->id,
                'section_type' => 'math',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Module linked successfully!',
        ]);

        // Verify the section was auto-created
        $section = Section::where('test_id', $test->id)->where('type', 'math')->first();
        $this->assertNotNull($section);
        $this->assertEquals('Math', $section->name);
        $this->assertEquals(2, $section->order);

        // Verify the module is linked to this section
        $this->assertTrue($section->modules()->where('module_id', $module->id)->exists());
    }

    /**
     * Test duplicate link attempts fail.
     */
    public function test_link_duplicate_module_fails(): void
    {
        $test = Test::create([
            'title' => 'Test SAT Dup',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'type' => 'reading_writing',
            'name' => 'Reading and Writing',
            'order' => 1,
        ]);

        $module = Module::create([
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'key' => 'MOD_TEST3',
            'order' => 1,
        ]);

        // Attach first time
        $section->modules()->attach($module->id);

        // Attempt via API
        $response = $this->actingAs($this->user)
            ->postJson(route('test-dashboard.sections.link-module'), [
                'module_id' => $module->id,
                'section_id' => $section->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'This module is already linked to this section.',
        ]);
    }
}
