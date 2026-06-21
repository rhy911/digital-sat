<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Assignment $assignment) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("New assignment: {$this->assignment->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->assignment->classroom->name} has assigned {$this->assignment->title}.");
        if ($this->assignment->due_at) $mail->line('Due '.$this->assignment->due_at->format('M j, Y g:i A').' (Asia/Ho_Chi_Minh).');
        return $mail->action('View assignment', route('student.assignments.show', $this->assignment));
    }
}
