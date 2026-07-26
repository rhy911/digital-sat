<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomAnnouncement;
use App\Models\ClassroomAnnouncementComment;
use App\Models\User;
use App\Notifications\AnnouncementPostedNotification;
use Illuminate\Support\Facades\Notification;

class AnnouncementService
{
    /**
     * Post a new announcement and notify every active student in the class.
     */
    public function post(Classroom $classroom, User $author, string $body): ClassroomAnnouncement
    {
        $announcement = $classroom->announcements()->create([
            'author_id' => $author->id,
            'body' => trim($body),
        ]);

        $students = $classroom->activeMemberships()
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter();

        if ($students->isNotEmpty()) {
            Notification::send($students, new AnnouncementPostedNotification($announcement));
        }

        return $announcement;
    }

    public function comment(ClassroomAnnouncement $announcement, User $author, string $body): ClassroomAnnouncementComment
    {
        return $announcement->comments()->create([
            'author_id' => $author->id,
            'body' => trim($body),
        ]);
    }

    public function deleteComment(ClassroomAnnouncementComment $comment): void
    {
        $comment->delete();
    }

    public function togglePin(ClassroomAnnouncement $announcement): ClassroomAnnouncement
    {
        $announcement->update(['pinned' => ! $announcement->pinned]);

        return $announcement;
    }
}
