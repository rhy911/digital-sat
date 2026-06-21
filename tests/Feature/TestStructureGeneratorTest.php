<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestStructureGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_full_sat_structure()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-full'), [
            'title' => 'Mock SAT Auto Generation Test',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'message' => 'SAT Structure created successfully.',
        ]);

        $this->assertDatabaseHas('tests', [
            'title' => 'Mock SAT Auto Generation Test',
            'test_type' => 'full_length'
        ]);

        $testId = $response->json('data.id');

        // Verify sections
        $this->assertDatabaseHas('sections', ['test_id' => $testId, 'type' => 'reading_writing']);
        $this->assertDatabaseHas('sections', ['test_id' => $testId, 'type' => 'math']);

        // Assert 6 modules total attached to the sections
        $rwSectionId = \App\Models\Section::where('test_id', $testId)->where('type', 'reading_writing')->first()->id;
        $mathSectionId = \App\Models\Section::where('test_id', $testId)->where('type', 'math')->first()->id;

        $rwModulesCount = \Illuminate\Support\Facades\DB::table('section_modules')->where('section_id', $rwSectionId)->count();
        $this->assertEquals(3, $rwModulesCount);

        $mathModulesCount = \Illuminate\Support\Facades\DB::table('section_modules')->where('section_id', $mathSectionId)->count();
        $this->assertEquals(3, $mathModulesCount);
    }

    public function test_can_generate_short_sat_structure()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-full'), [
            'title' => 'Mock Short SAT Test',
            'test_type' => 'short_test',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tests', [
            'title' => 'Mock Short SAT Test',
            'test_type' => 'short_test'
        ]);

        $testId = $response->json('data.id');
        
        // Verify modules have reduced time/questions
        $module = \App\Models\Module::whereHas('section', function($q) use ($testId) {
            $q->where('test_id', $testId)->where('type', 'reading_writing');
        })->first();

        $this->assertEquals(20, $module->duration_minutes);
        $this->assertEquals(15, $module->total_questions);

        $rwModulesCount = \Illuminate\Support\Facades\DB::table('section_modules')
            ->where('section_id', \App\Models\Section::where('test_id', $testId)->where('type', 'reading_writing')->value('id'))
            ->count();
        $mathModulesCount = \Illuminate\Support\Facades\DB::table('section_modules')
            ->where('section_id', \App\Models\Section::where('test_id', $testId)->where('type', 'math')->value('id'))
            ->count();

        $this->assertEquals(1, $rwModulesCount);
        $this->assertEquals(1, $mathModulesCount);
    }

    public function test_configured_short_test_respects_inline_module_values()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-configured'), [
            'title' => 'Inline Short Test',
            'test_type' => 'short_test',
            'modules' => [
                [
                    'section_type' => 'reading_writing',
                    'module_number' => 1,
                    'difficulty_level' => 'standard',
                    'duration_minutes' => 18,
                    'total_questions' => 11,
                ],
                [
                    'section_type' => 'math',
                    'module_number' => 1,
                    'difficulty_level' => 'standard',
                    'duration_minutes' => 22,
                    'total_questions' => 9,
                ],
            ],
        ]);

        $response->assertCreated();
        $testId = $response->json('data.id');

        $this->assertDatabaseHas('modules', [
            'duration_minutes' => 18,
            'total_questions' => 11,
        ]);
        $this->assertDatabaseHas('modules', [
            'duration_minutes' => 22,
            'total_questions' => 9,
        ]);
        $this->assertDatabaseHas('tests', [
            'id' => $testId,
            'total_duration_minutes' => 40,
        ]);
    }

    public function test_configured_test_defaults_to_draft_and_returns_a_builder_module()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-configured'), [
            'title' => 'Teacher Draft',
            'test_type' => 'module_only',
            'modules' => [[
                'section_type' => 'reading_writing',
                'module_number' => 1,
                'difficulty_level' => 'standard',
                'duration_minutes' => 32,
                'total_questions' => 27,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonStructure(['data' => ['sections' => [['modules' => [['id']]]]]]);

        $this->assertDatabaseHas('tests', [
            'title' => 'Teacher Draft',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function test_custom_test_populates_questions_from_pool()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 3) as $index) {
            Question::create([
                'stem' => "Pool reading question {$index}",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => 'easy',
                'is_pretest' => false,
                'is_complete' => true,
                'section_type' => 'reading_writing',
                'skill_domain' => 'information_and_ideas',
                'created_by' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-configured'), [
            'title' => 'Pool Custom Test',
            'test_type' => 'custom_test',
            'populate_from_pool' => true,
            'modules' => [
                [
                    'section_type' => 'reading_writing',
                    'module_number' => 1,
                    'difficulty_level' => 'standard',
                    'duration_minutes' => 12,
                    'total_questions' => 2,
                ],
            ],
        ]);

        $response->assertCreated();
        $moduleId = $response->json('data.sections.0.modules.0.id');
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('module_questions')->where('module_id', $moduleId)->count());
    }

    public function test_custom_test_fails_when_pool_is_too_small()
    {
        $this->withoutMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        Question::create([
            'stem' => 'Pool math question',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('home-dashboard.tests.generate-configured'), [
            'title' => 'Too Large Pool Test',
            'test_type' => 'custom_test',
            'populate_from_pool' => true,
            'modules' => [
                [
                    'section_type' => 'math',
                    'module_number' => 1,
                    'difficulty_level' => 'standard',
                    'duration_minutes' => 12,
                    'total_questions' => 2,
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('tests', ['title' => 'Too Large Pool Test']);
    }
}
