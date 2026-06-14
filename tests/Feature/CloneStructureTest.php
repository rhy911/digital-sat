<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Test;
use App\Models\Section;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloneStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_clone_test_hierarchy_without_questions()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Stub original test
        $test = Test::create(['title' => 'Original SAT Test', 'test_type' => 'full_length', 'status' => 'active']);
        $section = Section::create(['test_id' => $test->id, 'type' => 'math', 'name' => 'Math Section']);
        $module = Module::create(['module_number' => 1, 'difficulty_level' => 'standard', 'key' => 'ORIGINAL_KEY']);
        $module->sections()->attach($section->id);
        
        Question::create([
            'section_type' => 'math',
            'stem' => 'What is 1+1?',
            'question_type' => 'multiple_choice',
            'skill_domain' => 'algebra',
            'is_pretest' => false,
            'is_complete' => true
        ])->modules()->attach($module->id, ['position' => 1]);

        $this->assertEquals(1, Test::count());
        $this->assertEquals(1, Section::count());
        $this->assertEquals(1, Module::count());
        $this->assertEquals(1, Question::count());

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.clone', $test->id));
        $response->assertStatus(201);
        
        $this->assertEquals(2, Test::count());
        $this->assertEquals(2, Section::count());
        $this->assertEquals(2, Module::count());
        $this->assertEquals(1, Question::count()); // Questions should remain exactly 1 (not duplicated)

        $clonedTest = Test::where('id', '!=', $test->id)->first();
        $this->assertEquals('Original SAT Test (Clone)', $clonedTest->title);
        $this->assertEquals('draft', $clonedTest->status);

        $clonedSection = Section::where('test_id', $clonedTest->id)->first();
        $clonedModule = $clonedSection->modules()->first();
        $this->assertStringContainsString('_CLONE_', $clonedModule->key);
        
        // Assert cloned module has no questions
        $this->assertEquals(0, $clonedModule->questions()->count());
    }
}
