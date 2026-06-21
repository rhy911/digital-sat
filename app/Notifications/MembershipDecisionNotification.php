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
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Class request update')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->approved ? "You are now enrolled in {$this->classroom->name}." : "Your request to join {$this->classroom->name} was not approved.")
            ->action('View classes', route('student.classes.index'));
    }
}
