<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email verification OTP — sent after registration. 6-digit code.
 */
class EmailOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purposeLabel = 'verify your email',
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Sajio verification code')
            ->greeting('Hi '.($notifiable->name ?? 'there').',')
            ->line("Welcome to Sajio! Use the code below to {$this->purposeLabel}. It expires in 10 minutes.")
            ->line('**'.$this->code.'**')
            ->line('If you did not create this account, you can safely ignore this email.')
            ->salutation('— The Sajio team');
    }
}
