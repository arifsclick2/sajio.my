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
 * Sent to newly created staff with their temporary password.
 */
class StaffWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Restaurant $restaurant,
        public string $staffName,
        public string $temporaryPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been added to '.$this->restaurant->name.' on Sajio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.staff-welcome',
            with: [
                'restaurantName' => $this->restaurant->name,
                'staffName' => $this->staffName,
                'temporaryPassword' => $this->temporaryPassword,
                'dashboardUrl' => config('app.frontend_url'),
            ],
        );
    }
}
