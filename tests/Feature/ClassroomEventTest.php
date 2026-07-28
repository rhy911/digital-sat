<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\User;
use App\Support\ClassroomCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomEventTest extends TestCase
{
    use RefreshDatabase;

    private function classroomWithStudent(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Prep', 'status' => 'active']);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        return [$teacher, $student, $classroom];
    }

    public function test_teacher_can_create_event(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();

        $this->actingAs($teacher)
            ->post(route('teacher.events.store', $classroom), [
                'title' => 'Mock exam',
                'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'location' => 'Room 4',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_events', [
            'classroom_id' => $classroom->id,
            'title' => 'Mock exam',
            'location' => 'Room 4',
        ]);
    }

    public function test_student_cannot_create_event(): void
    {
        [, $student, $classroom] = $this->classroomWithStudent();

        $this->actingAs($student)
            ->post(route('teacher.events.store', $classroom), [
                'title' => 'x',
                'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();
    }

    public function test_end_before_start_is_rejected(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();

        $this->actingAs($teacher)
            ->post(route('teacher.events.store', $classroom), [
                'title' => 'Bad window',
                'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_teacher_can_delete_event(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $event = $classroom->events()->create([
            'created_by' => $teacher->id,
            'title' => 'Remove me',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($teacher)
            ->delete(route('teacher.events.destroy', [$classroom, $event]))
            ->assertRedirect();

        $this->assertSoftDeleted($event);
    }

    public function test_cannot_delete_event_from_another_class(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $other = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Other', 'status' => 'active']);
        $foreign = $other->events()->create(['created_by' => $teacher->id, 'title' => 'Elsewhere', 'starts_at' => now()->addDay()]);

        $this->actingAs($teacher)
            ->delete(route('teacher.events.destroy', [$classroom, $foreign]))
            ->assertNotFound();
    }

    public function test_calendar_merges_events_and_assignment_dates(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $event = $classroom->events()->create([
            'created_by' => $teacher->id,
            'title' => 'Study session',
            'starts_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $assignment = new \App\Models\Assignment([
            'title' => 'Quiz 1',
            'available_at' => now()->addDay(),
            'due_at' => now()->addDays(5),
        ]);

        $items = ClassroomCalendar::build(collect([$event]), collect([$assignment]));

        $types = collect($items)->pluck('type')->all();
        $this->assertContains('event', $types);
        $this->assertContains('opens', $types);
        $this->assertContains('due', $types);
        $this->assertCount(3, $items);
    }
}
