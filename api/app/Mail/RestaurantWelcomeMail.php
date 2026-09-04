<?php

namespace App\Mail;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestaurantWelcomeMail extends Mailable implements ShouldQueue
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
            subject: 'Welcome to Sajio — your restaurant is ready!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.restaurant-welcome',
            with: [
                'ownerName' => $this->ownerName,
                'restaurantName' => $this->restaurant->name,
                'subdomain' => $this->restaurant->subdomain,
                'trialEndsAt' => $this->restaurant->trial_ends_at?->format('j M Y'),
                'dashboardUrl' => config('app.frontend_url'),
            ],
        );
    }
}
