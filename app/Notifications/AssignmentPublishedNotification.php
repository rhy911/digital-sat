<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Notifications\Notification;

class AssignmentPublishedNotification extends Notification
{
    public function __construct(public Assignment $assignment) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        $title = str_replace(["\r", "\n"], ' ', $this->assignment->title);

        return [
            'type' => 'assignment_published',
            'title' => 'New assignment',
            'body' => "{$this->assignment->classroom->name}: {$title}",
            'url' => route('student.assignments.show', $this->assignment),
            'icon' => 'assignment',
        ];
    }
}
