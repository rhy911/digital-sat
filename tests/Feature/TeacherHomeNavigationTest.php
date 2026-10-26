<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The teacher overview on /home used to flip an in-page Alpine tab into the
 * Livewire workspace, which is a surface with no route of its own. Every
 * control now navigates to the same destination the icon rail uses.
 */
class TeacherHomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function classroomFor(User $teacher): Classroom
    {
        return Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Cohort']);
    }

    public function test_home_class_controls_link_to_the_rail_destination_rather_than_flipping_a_tab(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->classroomFor($teacher);

        $response = $this->actingAs($teacher)->get('/home')->assertOk();
        $response->assertSee('product-ui')->assertSee('teacher-workspace');
        $response->assertSee('study-home')->assertSee('Getting started')->assertSee('Explore your study tools');
        $response->assertSee("x-show=\"tab === 'progress'\"", escape: false);
        $response->assertSee(route('teacher.classes.index'), escape: false);
        $response->assertDontSee(route('student.classes.index'), escape: false);

        $response->assertSee(route('teacher.classes.index'), escape: false);
        $response->assertSee(route('teacher.assignments.index'), escape: false);
        // The Livewire workspace still *registers* this listener — the header nav
        // dispatches to it — so only the in-page Alpine dispatch should be gone.
        $response->assertDontSee("\$dispatch('teacher-workspace-section'", escape: false);
    }

    public function test_classes_link_lands_on_catalog(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = $this->classroomFor($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee($classroom->name)
            ->assertDontSee('x-init="$dispatch(\'open-modal\'', false);
    }

    public function test_teacher_without_classes_still_reaches_a_create_form(): void
    {
        $teacher = User::factory()->teacher()->create();

        // There is no class page to open yet, so the classes route renders its own
        // create form rather than bouncing to /home (which no longer carries one).
        $this->actingAs($teacher)
            ->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee('Create your first class')
            ->assertSee('action="'.route('teacher.classes.store').'"', false);
    }
}
