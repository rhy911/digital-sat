<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomAnnouncement;
use App\Models\ClassroomMembership;
use App\Models\User;
use App\Notifications\AnnouncementPostedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClassroomAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function classroomWithStudent(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'SAT Prep',
            'status' => 'active',
        ]);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
            'decided_at' => now(),
        ]);

        return [$teacher, $student, $classroom];
    }

    public function test_teacher_can_post_and_active_students_are_notified(): void
    {
        Notification::fake();
        [$teacher, $student, $classroom] = $this->classroomWithStudent();

        $this->actingAs($teacher)
            ->post(route('teacher.announcements.store', $classroom), ['body' => 'Test tomorrow at 9am.'])
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_announcements', [
            'classroom_id' => $classroom->id,
            'author_id' => $teacher->id,
            'body' => 'Test tomorrow at 9am.',
        ]);

        Notification::assertSentTo($student, AnnouncementPostedNotification::class);
    }

    public function test_student_cannot_post_announcement(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();

        $this->actingAs($student)
            ->post(route('teacher.announcements.store', $classroom), ['body' => 'Hi'])
            ->assertForbidden();
    }

    public function test_member_can_comment(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();
        $announcement = $classroom->announcements()->create(['author_id' => $teacher->id, 'body' => 'Welcome']);

        $this->actingAs($student)
            ->post(route('announcements.comments.store', [$classroom, $announcement]), ['body' => 'Thanks!'])
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_announcement_comments', [
            'announcement_id' => $announcement->id,
            'author_id' => $student->id,
            'body' => 'Thanks!',
        ]);
    }

    public function test_non_member_cannot_comment(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $outsider = User::factory()->student()->create();
        $announcement = $classroom->announcements()->create(['author_id' => $teacher->id, 'body' => 'Welcome']);

        $this->actingAs($outsider)
            ->post(route('announcements.comments.store', [$classroom, $announcement]), ['body' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_teacher_can_toggle_pin(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $announcement = $classroom->announcements()->create(['author_id' => $teacher->id, 'body' => 'Pin me']);

        $this->actingAs($teacher)
            ->post(route('teacher.announcements.pin', [$classroom, $announcement]))
            ->assertRedirect();

        $this->assertTrue($announcement->fresh()->pinned);
    }

    public function test_teacher_can_delete_announcement(): void
    {
        [$teacher, , $classroom] = $this->classroomWithStudent();
        $announcement = $classroom->announcements()->create(['author_id' => $teacher->id, 'body' => 'Bye']);

        $this->actingAs($teacher)
            ->delete(route('teacher.announcements.destroy', [$classroom, $announcement]))
            ->assertRedirect();

        $this->assertSoftDeleted($announcement);
    }

    public function test_cannot_comment_on_announcement_from_another_class(): void
    {
        [$teacher, $student, $classroom] = $this->classroomWithStudent();
        $otherClass = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Other', 'status' => 'active']);
        $foreign = $otherClass->announcements()->create(['author_id' => $teacher->id, 'body' => 'Elsewhere']);

        // Announcement belongs to $otherClass but posted against $classroom route.
        $this->actingAs($student)
            ->post(route('announcements.comments.store', [$classroom, $foreign]), ['body' => 'x'])
            ->assertForbidden();
    }
}
