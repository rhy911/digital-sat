<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Services\TestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TestManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TestManagementService::class);
    }

    public function test_generate_full_sat_structure()
    {
        $test = $this->service->generateFullSatStructure('Digital SAT Test Alpha', 'full_length');

        $this->assertDatabaseHas('tests', [
            'id' => $test->id,
            'title' => 'Digital SAT Test Alpha',
            'test_type' => 'full_length',
            'status' => 'draft',
        ]);

        $this->assertCount(2, $test->sections);

        $rwSection = $test->sections->where('type', Section::TYPE_RW)->first();
        $this->assertNotNull($rwSection);
        $this->assertCount(2, $rwSection->modules); // Fixed Module 1 and Module 2

        $mathSection = $test->sections->where('type', Section::TYPE_MATH)->first();
        $this->assertNotNull($mathSection);
        $this->assertCount(2, $mathSection->modules); // Fixed Module 1 and Module 2
    }

    public function test_clone_test()
    {
        $original = $this->service->generateFullSatStructure('Original Test', 'full_length');
        $cloned = $this->service->cloneTest($original->id);

        $this->assertDatabaseHas('tests', [
            'id' => $cloned->id,
            'title' => 'Original Test (Clone)',
        ]);

        $this->assertCount(2, $cloned->sections);
    }

    public function test_clone_module()
    {
        $module = Module::create([
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 30,
            'total_questions' => 20,
            'key' => 'MOD_TEST_KEY',
            'order' => 1,
        ]);

        $cloned = $this->service->cloneModule($module->id);

        $this->assertDatabaseHas('modules', [
            'id' => $cloned->id,
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
        ]);

        $this->assertNotEquals($module->key, $cloned->key);
    }

    public function test_cascade_delete_test()
    {
        $test = $this->service->generateFullSatStructure('Test to Delete', 'full_length');
        $sectionId = $test->sections->first()->id;

        $this->service->deleteTest($test->id, true);

        $this->assertSoftDeleted('tests', ['id' => $test->id]);
        $this->assertSoftDeleted('sections', ['id' => $sectionId]);
    }

    public function test_cascade_delete_test_with_attempts_fails()
    {
        $test = $this->service->generateFullSatStructure('Test to Delete With Attempts', 'full_length');
        $user = \App\Models\User::factory()->create(['role' => 'student']);
        
        \App\Models\UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->deleteTest($test->id, true);
    }

    public function test_admin_force_cascade_delete_test_with_attempts_succeeds()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $test = $this->service->generateFullSatStructure('Test to Force Delete', 'full_length');
        $sectionId = $test->sections->first()->id;
        $user = \App\Models\User::factory()->create(['role' => 'student']);
        
        $userTest = \App\Models\UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
        ]);

        $this->service->deleteTest($test->id, true, true);

        // Verify hard deletion of test, sections, and user tests
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
        $this->assertDatabaseMissing('sections', ['id' => $sectionId]);
        $this->assertDatabaseMissing('user_tests', ['id' => $userTest->id]);
    }
}
