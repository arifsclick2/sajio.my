<?php

namespace App\Providers;

use App\Models\Subscription;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Configure Cashier for Sajio's tenant (Restaurant) billing model.
     *
     * Sajio bills each RESTAURANT as a Stripe customer. We:
     *  - point Cashier's subscription model at our App\Models\Subscription
     *  - disable Cashier's auto webhook route (we register our own
     *    StripeWebhookController, which resolves the Restaurant itself)
     */
    public function register(): void
    {
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::ignoreRoutes();
    }
}
