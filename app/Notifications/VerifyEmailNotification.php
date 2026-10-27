<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ?string $trialAttemptUlid = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $name = str_replace(["\r", "\n"], ' ', $notifiable->name);

        return (new MailMessage)
            ->subject('Verify your DigiSAT email address')
            ->greeting('Hello ' . $name . ',')
            ->line('Select the button below to verify your email address.')
            ->action('Verify email', $verificationUrl)
            ->line('If you did not create an account, you can ignore this email.')
            ->line('This link expires in 60 minutes.');
    }

    protected function verificationUrl(object $notifiable): string
    {
        $parameters = [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ];
        if ($this->trialAttemptUlid) {
            $parameters['trial'] = $this->trialAttemptUlid;
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            $parameters
        );
    }
}
