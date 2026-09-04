<?php

namespace App\Http\Controllers\Api;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhook endpoint (https://api.sajio.my/stripe/webhook).
 *
 * The VerifyWebhookSignature middleware (from the parent constructor) checks
 * the Stripe signature using STRIPE_WEBHOOK_SECRET. We do NOT call the base
 * handle* methods because our billable model is Restaurant, not User — we
 * apply the events to our restaurant subscription state directly.
 */
class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {
        // Keep parent's constructor behavior: applies VerifyWebhookSignature
        // when a webhook secret is configured.
        parent::__construct();
    }

    /**
     * Handle a Stripe webhook call.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return new Response('Invalid payload', 400);
        }

        WebhookReceived::dispatch($payload);

        try {
            $this->subscriptions->handleSubscriptionPayload($payload);
        } catch (\Throwable $e) {
            report($e);
        }

        WebhookHandled::dispatch($payload);

        return new Response('Webhook Handled');
    }
}

