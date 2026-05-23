<?php

namespace Tests\Feature;

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

        $response = $this->actingAs($user)->postJson(route('test-dashboard.tests.generate-full'), [
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

        $response = $this->actingAs($user)->postJson(route('test-dashboard.tests.generate-full'), [
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
    }
}
