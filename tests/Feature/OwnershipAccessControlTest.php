<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher1;
    private User $teacher2;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher1 = User::factory()->create([
            'role' => 'teacher',
            'email' => 'teacher1@bluebook.com',
            'email_verified_at' => now(),
        ]);

        $this->teacher2 = User::factory()->create([
            'role' => 'teacher',
            'email' => 'teacher2@bluebook.com',
            'email_verified_at' => now(),
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@bluebook.com',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test teachers can only see their own tests plus public ones.
     */
    public function test_teachers_visibility_scope_on_tests(): void
    {
        // 1. Private test owned by Teacher 1
        $test1 = Test::create([
            'title' => 'Teacher 1 Private Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher1->id,
            'is_public' => false,
        ]);

        // 2. Public test owned by Teacher 1
        $test2 = Test::create([
            'title' => 'Teacher 1 Public Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher1->id,
            'is_public' => true,
        ]);

        // 3. Private test owned by Teacher 2
        $test3 = Test::create([
            'title' => 'Teacher 2 Private Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher2->id,
            'is_public' => false,
        ]);

        // Verify query scope as Teacher 1
        $visibleTestsTeacher1 = Test::visibleTo($this->teacher1)->pluck('id')->toArray();
        $this->assertContains($test1->id, $visibleTestsTeacher1);
        $this->assertContains($test2->id, $visibleTestsTeacher1);
        $this->assertNotContains($test3->id, $visibleTestsTeacher1);

        // Verify query scope as Teacher 2
        $visibleTestsTeacher2 = Test::visibleTo($this->teacher2)->pluck('id')->toArray();
        $this->assertNotContains($test1->id, $visibleTestsTeacher2);
        $this->assertContains($test2->id, $visibleTestsTeacher2);
        $this->assertContains($test3->id, $visibleTestsTeacher2);

        // Verify query scope as Admin
        $visibleTestsAdmin = Test::visibleTo($this->admin)->pluck('id')->toArray();
        $this->assertContains($test1->id, $visibleTestsAdmin);
        $this->assertContains($test2->id, $visibleTestsAdmin);
        $this->assertContains($test3->id, $visibleTestsAdmin);
    }

    /**
     * Test teachers can create tests and they are assigned created_by automatically.
     */
    public function test_teacher_creates_test_with_ownership(): void
    {
        $this->actingAs($this->teacher1);

        $response = $this->postJson(route('test-dashboard.tests.store'), [
            'title' => 'Newly Created Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'is_public' => true,
        ]);

        $response->assertStatus(201);
        
        $test = Test::where('title', 'Newly Created Test')->first();
        $this->assertNotNull($test);
        $this->assertEquals($this->teacher1->id, $test->created_by);
        $this->assertTrue((bool)$test->is_public);
    }

    /**
     * Test teachers cannot update tests they do not own.
     */
    public function test_teacher_cannot_update_test_owned_by_others(): void
    {
        // Test owned by Teacher 2
        $test = Test::create([
            'title' => 'Teacher 2 Private Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher2->id,
            'is_public' => true, // Shared but read-only
        ]);

        $this->actingAs($this->teacher1);

        $response = $this->putJson(route('test-dashboard.tests.update', ['id' => $test->id]), [
            'title' => 'Attempted Edit By Teacher 1',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Teacher 2 Private Test', $test->fresh()->title);
    }

    /**
     * Test teacher can update their own test.
     */
    public function test_teacher_can_update_their_own_test(): void
    {
        $test = Test::create([
            'title' => 'Teacher 1 Private Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher1->id,
            'is_public' => false,
        ]);

        $this->actingAs($this->teacher1);

        $response = $this->putJson(route('test-dashboard.tests.update', ['id' => $test->id]), [
            'title' => 'Updated Title',
            'is_public' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $test->fresh()->title);
        $this->assertTrue((bool)$test->fresh()->is_public);
    }

    /**
     * Test teachers cannot delete tests they do not own.
     */
    public function test_teacher_cannot_delete_test_owned_by_others(): void
    {
        $test = Test::create([
            'title' => 'Teacher 2 Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher2->id,
            'is_public' => true,
        ]);

        $this->actingAs($this->teacher1);

        $response = $this->deleteJson(route('test-dashboard.tests.delete', ['id' => $test->id]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tests', ['id' => $test->id]);
    }

    /**
     * Test admins can delete any test.
     */
    public function test_admin_can_delete_any_test(): void
    {
        $test = Test::create([
            'title' => 'Teacher 2 Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'draft',
            'created_by' => $this->teacher2->id,
            'is_public' => true,
        ]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson(route('test-dashboard.tests.delete', ['id' => $test->id]));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
    }
}
