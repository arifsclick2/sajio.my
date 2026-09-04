<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Package;
use App\Models\Restaurant;
use Laravel\Cashier\Cashier;
use RuntimeException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

/**
 * Handles everything Stripe for SAJIO's OWN package subscriptions
 * (restaurants buying Sajio plans). This is NOT the payment gateway
 * restaurants use to charge their own customers — that is a separate
 * later milestone (default: TNG QR manual).
 */
class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = Cashier::stripe();
    }

    /* ------------------------------------------------------------------ */
    /*  Package <-> Stripe Price sync                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Create/update a Stripe Product + recurring monthly Price for a package,
     * and store the Stripe Price ID back on the package.
     */
    public function syncPackageToStripe(Package $package): Package
    {
        $this->guardEnabled();

        $productData = [
            'name' => 'Sajio '.$package->name.' Plan',
            'description' => $package->description,
            'metadata' => [
                'package_id' => (string) $package->id,
                'package_slug' => $package->slug,
                'sajio' => 'true',
            ],
        ];

        // Find or create the Stripe Product by the package slug.
        // NOTE: must use the client instance ($this->stripe) — the static
        // Product::all() SDK call needs a global Stripe::setApiKey(), which
        // Cashier's client does not set.
        $existing = $this->stripe->products->all(['limit' => 100, 'active' => true])->data;
        $product = collect($existing)->first(fn ($p) => ($p->metadata['package_slug'] ?? null) === $package->slug);

        if (! $product) {
            $product = $this->stripe->products->create($productData);
        } else {
            $this->stripe->products->update($product->id, $productData);
        }

        // Create a fresh monthly recurring Price for the current amount.
        // (Super Admin price changes apply to NEW subscriptions.)
        $price = $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $package->priceMonthlyInSen(),
            'currency' => 'myr',
            'recurring' => ['interval' => 'month'],
            'metadata' => [
                'package_slug' => $package->slug,
                'sajio' => 'true',
            ],
        ]);

        $package->forceFill(['stripe_price_id' => $price->id])->save();

        return $package;
    }

    /**
     * Ensure all active packages have a Stripe Price ID.
     */
    public function syncAllPackages(): void
    {
        Package::query()
            ->where('is_active', true)
            ->get()
            ->each(fn (Package $package) => $this->syncPackageToStripe($package));
    }

    /* ------------------------------------------------------------------ */
    /*  Checkout                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Create a Stripe Checkout Session for the restaurant to subscribe to a
     * package (monthly recurring). Applies a coupon if the restaurant holds
     * an unused registration coupon.
     *
     * @return array{url: string, session_id: string}
     */
    public function createCheckout(Restaurant $restaurant, Package $package, ?string $successUrl = null, ?string $cancelUrl = null): array
    {
        $this->guardEnabled();

        if (! $package->stripe_price_id) {
            $this->syncPackageToStripe($package);
        }

        // Get-or-create the Stripe Customer for this restaurant (Cashier).
        $customer = $restaurant->createOrGetStripeCustomer([
            'name' => $restaurant->name,
            'metadata' => [
                'restaurant_id' => (string) $restaurant->id,
                'subdomain' => $restaurant->subdomain,
            ],
        ]);

        $coupon = $this->findUnusedCoupon($restaurant);

        $sessionData = [
            'mode' => 'subscription',
            'customer' => $customer->id,
            'line_items' => [[
                'price' => $package->stripe_price_id,
                'quantity' => 1,
            ]],
            'subscription_data' => [
                'metadata' => [
                    'restaurant_id' => (string) $restaurant->id,
                    'package_slug' => $package->slug,
                ],
            ],
            'success_url' => $successUrl ?? config('app.frontend_url').'/billing?status=success',
            'cancel_url' => $cancelUrl ?? config('app.frontend_url').'/billing?status=cancelled',
            'allow_promotion_codes' => false,
            'client_reference_id' => (string) $restaurant->id,
            // Sajio is the merchant selling its own SaaS subscriptions.
            // Managed Payments (marketplace-style) is not used — disable it
            // so no product tax code is required for Checkout.
            'managed_payments' => ['enabled' => false],
        ];

        // Apply an active Stripe Coupon if we have a valid Sajio coupon.
        $stripeCoupon = $coupon ? $this->syncCouponToStripe($coupon) : null;
        if ($stripeCoupon) {
            $sessionData['discounts'] = [['coupon' => $stripeCoupon->id]];
        }

        /** @var StripeCheckoutSession $session */
        $session = $this->stripe->checkout->sessions->create($sessionData);

        return [
            'url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Coupon sync                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Map a Sajio coupon to a Stripe Coupon (percent or fixed MYR).
     */
    public function syncCouponToStripe(Coupon $coupon): \Stripe\Coupon
    {
        $this->guardEnabled();

        $data = [
            'name' => $coupon->name ?? $coupon->code,
            'metadata' => [
                'sajio_coupon_id' => (string) $coupon->id,
                'code' => $coupon->code,
            ],
            'applies_to' => ['products' => $this->stripeProductIds()],
        ];

        if ($coupon->type === \App\Enums\CouponType::Percent) {
            $data['percent_off'] = (float) $coupon->value;
        } else {
            $data['amount_off'] = (int) round(((float) $coupon->value) * 100);
            $data['currency'] = 'myr';
        }

        if ($coupon->max_uses) {
            $data['max_redemptions'] = $coupon->max_uses;
        }

        if ($coupon->expires_at) {
            $data['redeem_by'] = $coupon->expires_at->getTimestamp();
        }

        return $this->stripe->coupons->create($data);
    }

    /* ------------------------------------------------------------------ */
    /*  Billing portal                                                     */
    /* ------------------------------------------------------------------ */

    public function billingPortal(Restaurant $restaurant, ?string $returnUrl = null): string
    {
        $this->guardEnabled();

        $customer = $restaurant->createOrGetStripeCustomer();

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customer->id,
            'return_url' => $returnUrl ?? config('app.frontend_url').'/billing',
        ]);

        return $session->url;
    }

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                   */
    /* ------------------------------------------------------------------ */

    private function findUnusedCoupon(Restaurant $restaurant): ?Coupon
    {
        $usage = CouponUsage::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $usage || ! $usage->coupon || ! $usage->coupon->isValid()) {
            return null;
        }

        return $usage->coupon;
    }

    private function stripeProductIds(): array
    {
        return Package::query()
            ->whereNotNull('stripe_price_id')
            ->get()
            ->map(fn (Package $p) => $this->stripe->prices->retrieve($p->stripe_price_id)->product)
            ->all();
    }

    private function guardEnabled(): void
    {
        if (! config('cashier.secret')) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }
    }
}
