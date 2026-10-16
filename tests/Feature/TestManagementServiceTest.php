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
        \App\Models\User::factory()->create(['id' => 1]);
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

        $clonedFromModel = $this->service->cloneTest($original);
        $this->assertDatabaseHas('tests', ['id' => $clonedFromModel->id]);
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

        $clonedFromModel = $this->service->cloneModule($module);
        $this->assertDatabaseHas('modules', ['id' => $clonedFromModel->id]);
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

    public function test_derive_from_section()
    {
        $original = $this->service->generateFullSatStructure('Original Test', 'full_length');
        $section = $original->sections->first();

        $derived = $this->service->deriveFromSection($section, 'Derived Test', 1);

        $this->assertDatabaseHas('tests', [
            'id' => $derived->id,
            'title' => 'Derived Test',
            'test_type' => 'section_only',
        ]);
        $this->assertCount(1, $derived->sections);
        $this->assertEquals($section->type, $derived->sections->first()->type);
    }

    public function test_derive_from_module()
    {
        $original = $this->service->generateFullSatStructure('Original Test', 'full_length');
        $section = $original->sections->first();
        $module = $section->modules->first();

        $derived = $this->service->deriveFromModule($module, $section, 'Derived Module Test', 1);

        $this->assertDatabaseHas('tests', [
            'id' => $derived->id,
            'title' => 'Derived Module Test',
            'test_type' => 'module_only',
        ]);
        $this->assertCount(1, $derived->sections);
        $this->assertCount(1, $derived->sections->first()->modules);
    }

    public function test_copy_section()
    {
        $source = $this->service->generateFullSatStructure('Source Test', 'full_length');
        $destination = \App\Models\Test::create([
            'title' => 'Empty Destination',
            'test_type' => \App\Models\Test::TYPE_FULL,
            'status' => 'draft',
        ]);
        $section = $source->sections->where('type', Section::TYPE_RW)->first();

        $copiedSection = $this->service->copySection($section, $destination, 1);

        $this->assertDatabaseHas('sections', [
            'id' => $copiedSection->id,
            'test_id' => $destination->id,
            'type' => Section::TYPE_RW,
        ]);
    }

    public function test_test_model_sync_content_lock()
    {
        $test = $this->service->generateFullSatStructure('Lockable Test', 'full_length');
        $this->assertNull($test->content_locked_at);
        $this->assertFalse($test->isContentLocked());

        $classroom = \App\Models\Classroom::create([
            'owner_id' => 1,
            'name' => 'Lock Test Class',
            'code' => 'LOCK'.rand(100, 999),
            'status' => 'active',
        ]);
        $assignment = \App\Models\Assignment::create([
            'classroom_id' => $classroom->id,
            'test_id' => $test->id,
            'status' => 'published',
            'teacher_id' => 1,
            'title' => 'Lock Test',
        ]);

        $test->syncContentLock();
        $this->assertTrue($test->isContentLocked());
        $this->assertNotNull($test->fresh()->content_locked_at);

        $assignment->update(['status' => 'closed']);
        $test->syncContentLock();
        $this->assertFalse($test->isContentLocked());
        $this->assertNull($test->fresh()->content_locked_at);

        // Verify approved ScoreConversionSet also locks full_length tests
        $conversionSet = \App\Models\ScoreConversionSet::create([
            'test_id' => $test->id,
            'version' => 1,
            'status' => \App\Models\ScoreConversionSet::STATUS_APPROVED,
            'source_name' => 'Official Table',
            'checksum' => 'fake-checksum',
        ]);
        $test->syncContentLock();
        $this->assertTrue($test->isContentLocked());
        $this->assertNotNull($test->fresh()->content_locked_at);

        $conversionSet->update(['status' => \App\Models\ScoreConversionSet::STATUS_RETIRED]);
        $test->syncContentLock();
        $this->assertFalse($test->isContentLocked());
        $this->assertNull($test->fresh()->content_locked_at);
    }
}
