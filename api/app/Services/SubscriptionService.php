<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;

/**
 * Applies Stripe webhook events to Sajio restaurant state:
 *  - upserts the local restaurant-scoped Cashier Subscription row
 *    (so Restaurant::subscriptionStatus() reflects Stripe reality)
 *  - logs every status transition in `subscription_events`
 */
class SubscriptionService
{
    /**
     * Handle a subscription lifecycle event from a Stripe webhook payload.
     */
    public function handleSubscriptionPayload(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;

        if (! $object) {
            return;
        }

        $restaurantId = (int) ($object['metadata']['restaurant_id']
            ?? $object['client_reference_id']
            ?? $payload['data']['object']['metadata']['restaurant_id'] ?? null);

        $restaurant = Restaurant::find($restaurantId);

        if (! $restaurant) {
            return; // Unknown restaurant — ignore.
        }

        $eventType = $payload['type'] ?? '';
        $stripeStatus = $object['status'] ?? null;

        switch (true) {
            // A Checkout session completed — subscription created/updated event follows.
            case $eventType === 'checkout.session.completed':
                if (! empty($object['subscription'])) {
                    $this->upsertSubscription($restaurant, $object['subscription'], 'trialing');
                    $this->transition($restaurant, null, SubscriptionStatus::Active, 'checkout_completed');
                }
                break;

            case str_contains($eventType, 'customer.subscription.updated')
                || str_contains($eventType, 'customer.subscription.created'):
                $this->upsertSubscription($restaurant, $object['id'], $stripeStatus);

                $to = $this->mapStripeStatus($stripeStatus);
                $this->transition($restaurant, null, $to, 'subscription_'.$stripeStatus);

                // Once a real subscription is active/trialing/past_due, trial is over.
                if (in_array($stripeStatus, ['active', 'trialing', 'past_due'], true)) {
                    $restaurant->forceFill(['trial_ends_at' => null])->save();
                }
                break;

            case str_contains($eventType, 'customer.subscription.deleted'):
                Subscription::where('stripe_id', $object['id'] ?? '')->delete();
                $this->transition($restaurant, null, SubscriptionStatus::Cancelled, 'subscription_deleted');
                break;

            case $eventType === 'invoice.payment_failed':
                $this->transition($restaurant, null, SubscriptionStatus::PastDue, 'invoice_payment_failed');
                break;

            case $eventType === 'invoice.payment_succeeded':
                $subId = $object['subscription'] ?? null;
                if ($subId) {
                    $this->upsertSubscription($restaurant, $subId, 'active');
                }
                $this->transition($restaurant, null, SubscriptionStatus::Active, 'invoice_payment_succeeded');
                break;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function upsertSubscription(Restaurant $restaurant, string $stripeId, string $status): void
    {
        Subscription::updateOrCreate(
            ['stripe_id' => $stripeId],
            [
                'restaurant_id' => $restaurant->id,
                'type' => 'main',
                'stripe_status' => $status,
            ],
        );
    }

    private function mapStripeStatus(?string $status): SubscriptionStatus
    {
        return match ($status) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled', 'incomplete_expired' => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Expired,
        };
    }

    private function transition(Restaurant $restaurant, ?SubscriptionStatus $from, SubscriptionStatus $to, string $reason): void
    {
        if ($from === $to) {
            return;
        }

        SubscriptionEvent::create([
            'restaurant_id' => $restaurant->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
        ]);
    }
}
