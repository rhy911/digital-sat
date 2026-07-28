<?php

namespace App\Notifications;

use App\Models\Classroom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Classroom $classroom, public bool $approved) {}
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'membership_decision',
            'title' => $this->approved ? 'Class request approved' : 'Class request declined',
            'body' => $this->approved
                ? "You are now enrolled in {$this->classroom->name}."
                : "Your request to join {$this->classroom->name} was not approved.",
            'url' => route('student.classes.index'),
            'icon' => 'classroom',
        ];
    }
    public function toMail(object $notifiable): MailMessage
    {
        $name = str_replace(["\r", "\n"], ' ', $notifiable->name);
        $classroomName = str_replace(["\r", "\n"], ' ', $this->classroom->name);

        return (new MailMessage)
            ->subject('Class request update')
            ->greeting("Hello {$name},")
            ->line($this->approved ? "You are now enrolled in {$classroomName}." : "Your request to join {$classroomName} was not approved.")
            ->action('View classes', route('student.classes.index'));
    }
}
