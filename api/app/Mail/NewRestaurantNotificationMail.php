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
 * Sent to the Sajio Super Admin when a new restaurant registers.
 */
class NewRestaurantNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Restaurant $restaurant,
        public string $ownerName,
        public string $ownerEmail,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New restaurant registered: '.$this->restaurant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-restaurant-notification',
            with: [
                'restaurantName' => $this->restaurant->name,
                'subdomain' => $this->restaurant->subdomain,
                'ownerName' => $this->ownerName,
                'ownerEmail' => $this->ownerEmail,
                'registeredAt' => $this->restaurant->created_at?->format('j M Y, g:i a'),
            ],
        );
    }
}
