<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherApprovalDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public bool $approved, public ?string $reason = null) {}
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'teacher_approval',
            'title' => $this->approved ? 'Teacher account approved' : 'Teacher account not approved',
            'body' => $this->approved
                ? 'Your teacher account has been approved.'
                : ($this->reason ?: 'Contact an administrator for more information.'),
            'url' => $this->approved ? route('teacher.classes.index') : route('teacher.application.status'),
            'icon' => 'teacher',
        ];
    }
    public function toMail(object $notifiable): MailMessage
    {
        $name = str_replace(["\r", "\n"], ' ', $notifiable->name);
        
        $mail = (new MailMessage)->subject('Teacher account review')->greeting("Hello {$name},");
        if ($this->approved) return $mail->line('Your teacher account has been approved.')->action('Open teacher workspace', route('teacher.classes.index'));
        return $mail->line('Your teacher account request was not approved.')->line($this->reason ?: 'Contact an administrator for more information.');
    }
}
