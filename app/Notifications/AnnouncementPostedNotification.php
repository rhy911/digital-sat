<?php

namespace App\Notifications;

use App\Models\ClassroomAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassroomAnnouncement $announcement) {}

    public function via(object $notifiable): array { return ['mail', 'database']; }

    public function toMail(object $notifiable): MailMessage
    {
        $className = str_replace(["\r", "\n"], ' ', $this->announcement->classroom->name);
        $name = str_replace(["\r", "\n"], ' ', $notifiable->name);

        return (new MailMessage)
            ->subject("New announcement in {$className}")
            ->greeting("Hello {$name},")
            ->line("{$className} posted a new announcement.")
            ->line(Str::limit($this->announcement->body, 160))
            ->action('View class', route('student.classes.show', $this->announcement->classroom));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement_posted',
            'title' => 'New announcement',
            'body' => "{$this->announcement->classroom->name}: ".Str::limit($this->announcement->body, 80),
            'url' => route('student.classes.show', $this->announcement->classroom),
            'icon' => 'announcement',
        ];
    }
}
