<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * A restaurant's Stripe subscription (Cashier). Scoped to Restaurant (tenant).
 */
class Subscription extends CashierSubscription
{
    /**
     * The "type" column is always 'main' for Sajio (one subscription per restaurant).
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function package(): ?Package
    {
        if (! $this->stripe_price) {
            return null;
        }

        return Package::where('stripe_price_id', $this->stripe_price)->first();
    }
}
