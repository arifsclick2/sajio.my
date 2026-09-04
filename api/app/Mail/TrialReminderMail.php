<?php

namespace App\Mail;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Trial reminder — sent at day 7 / day 10 / day 13 of the trial.
 */
class TrialReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Restaurant $restaurant,
        public string $ownerName,
        public int $daysRemaining,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->daysRemaining <= 1 => "Last day of your Sajio trial — subscribe to keep selling",
            $this->daysRemaining <= 4 => "Only {$this->daysRemaining} days left on your Sajio trial",
            default => "Your Sajio free trial ends in {$this->daysRemaining} days",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trial-reminder',
            with: [
                'ownerName' => $this->ownerName,
                'restaurantName' => $this->restaurant->name,
                'daysRemaining' => $this->daysRemaining,
                'trialEndsAt' => $this->restaurant->trial_ends_at?->format('j M Y'),
                'billingUrl' => config('app.frontend_url').'/billing',
            ],
        );
    }
}
