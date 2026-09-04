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
 * Sent when the trial has ended with no active subscription — the system is
 * locked and the owner must subscribe to continue (Sajio plan §4).
 */
class TrialExpiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Restaurant $restaurant,
        public string $ownerName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Sajio trial has ended — choose a package to continue',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trial-expired',
            with: [
                'ownerName' => $this->ownerName,
                'restaurantName' => $this->restaurant->name,
                'billingUrl' => config('app.frontend_url').'/billing',
            ],
        );
    }
}
