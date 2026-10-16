<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAssignmentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $classA = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Class A',
            'code' => 'CLASSA',
            'status' => 'active',
        ]);
        ClassroomMembership::create([
            'classroom_id' => $classA->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $classB = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Class B',
            'code' => 'CLASSB',
            'status' => 'active',
        ]);
        ClassroomMembership::create([
            'classroom_id' => $classB->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $unauthorizedClass = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Class Secret',
            'code' => 'SECRET',
            'status' => 'active',
        ]);

        $test = Test::create([
            'title' => 'SAT Test',
            'test_type' => 'custom_test',
            'status' => 'active',
            'created_by' => $teacher->id,
        ]);

        $assignA = Assignment::create([
            'classroom_id' => $classA->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Assignment in Class A',
            'status' => 'published',
            'published_at' => now(),
        ]);
        AssignmentRecipient::create([
            'assignment_id' => $assignA->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $assignB = Assignment::create([
            'classroom_id' => $classB->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Assignment in Class B',
            'status' => 'published',
            'published_at' => now(),
        ]);
        AssignmentRecipient::create([
            'assignment_id' => $assignB->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        return compact('teacher', 'student', 'classA', 'classB', 'unauthorizedClass', 'assignA', 'assignB');
    }

    public function test_index_without_classroom_parameter_shows_all_classes(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($student)->get(route('student.assignments.index'));

        $response->assertOk();
        $response->assertViewHas('classroom', null);
        $response->assertViewHas('assignment');
        // Back link should derive from the selected assignment's actual classroom
        $currentAssignment = $response->viewData('assignment');
        $expectedBackUrl = route('student.classes.show', $currentAssignment->classroom);
        $response->assertSee($expectedBackUrl);
    }

    public function test_index_with_valid_classroom_filter(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($student)->get(route('student.assignments.index', ['classroom' => $classB->id]));

        $response->assertOk();
        $response->assertViewHas('classroom', fn ($c) => $c->id === $classB->id);
        $response->assertViewHas('assignment', fn ($a) => $a->classroom_id === $classB->id);
    }

    public function test_index_aborts_404_for_unauthorized_classroom(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($student)->get(route('student.assignments.index', ['classroom' => $unauthorizedClass->id]));

        $response->assertNotFound();
    }

    public function test_show_without_classroom_keeps_classroom_null_and_derives_backlink_from_assignment_classroom(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($student)->get(route('student.assignments.show', $assignA));

        $response->assertOk();
        $response->assertViewHas('classroom', null);
        $expectedBackUrl = route('student.classes.show', $assignA->classroom);
        $response->assertSee($expectedBackUrl);
    }

    public function test_show_with_active_classroom_filter_preserves_backlink_to_assignment_classroom(): void
    {
        extract($this->setupData());

        // Student opens assignment A (which is in classA) while having classB filtered
        $response = $this->actingAs($student)->get(route('student.assignments.show', [
            'assignment' => $assignA,
            'classroom' => $classB->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('classroom', fn ($c) => $c->id === $classB->id);
        // Back link must derive from assignment A's classroom (classA), NOT the filter classroom (classB)
        $expectedBackUrl = route('student.classes.show', $assignA->classroom);
        $response->assertSee($expectedBackUrl);
    }

    public function test_show_aborts_404_for_unauthorized_classroom_parameter(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($student)->get(route('student.assignments.show', [
            'assignment' => $assignA,
            'classroom' => $unauthorizedClass->id,
        ]));

        $response->assertNotFound();
    }

    public function test_show_forbidden_for_non_recipient_student(): void
    {
        extract($this->setupData());

        $outsider = User::factory()->student()->create();

        $response = $this->actingAs($outsider)->get(route('student.assignments.show', $assignA));

        $response->assertForbidden();
    }
}
