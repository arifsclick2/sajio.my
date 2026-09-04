<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Restaurant;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BillingController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {
    }

    /**
     * The authenticated owner's restaurant.
     */
    private function restaurantOf(Request $request): Restaurant
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $user || ! $user->isOwner() || ! $restaurant) {
            throw ValidationException::withMessages([
                'auth' => ['Only the restaurant owner can manage billing.'],
            ]);
        }

        return $restaurant;
    }

    /**
     * Start a Stripe Checkout subscription for a package.
     * (Owner chooses Basic / Premium / Pro — recurring monthly.)
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $restaurant = $this->restaurantOf($request);

        $package = Package::query()
            ->where('id', $validated['package_id'])
            ->where('is_active', true)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => ['That package is not available.'],
            ]);
        }

        try {
            $result = $this->stripeService->createCheckout($restaurant, $package);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'message' => 'Redirecting to secure checkout…',
            'checkout_url' => $result['url'],
            'session_id' => $result['session_id'],
        ]);
    }

    /**
     * Current billing status for the dashboard:
     * status, package, trial countdown, links.
     */
    public function status(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $status = $restaurant->subscriptionStatus();
        $subscription = $restaurant->activeSubscription;
        $package = $subscription?->package();

        // Renewal date comes from Stripe; guard so a missing/offline Stripe
        // sub (or a test fixture without one) never 500s the dashboard.
        $renewsAt = null;
        if ($subscription) {
            try {
                $renewsAt = $subscription->asStripeSubscription()?->current_period_end ?? null;
            } catch (\Throwable) {
                $renewsAt = null;
            }
        }

        return response()->json([
            'status' => $status->value,
            'status_label' => $status->label(),
            'can_operate' => $restaurant->canOperate(),
            'needs_subscription' => $restaurant->needsSubscription(),
            'is_subscribed' => $restaurant->isSubscribed(),
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'price_monthly' => $package->price_monthly,
            ] : null,
            'trial' => [
                'is_on_trial' => $restaurant->isOnTrial(),
                'ends_at' => $restaurant->trial_ends_at?->toISOString(),
                'days_remaining' => $restaurant->trialDaysRemaining(),
            ],
            'stripe_subscription' => $subscription ? [
                'stripe_status' => $subscription->stripe_status,
                'renews_at' => $renewsAt,
            ] : null,
        ]);
    }

    /**
     * Link to the Stripe Billing Portal (manage card / cancel / invoices).
     */
    public function portal(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        try {
            $url = $this->stripeService->billingPortal($restaurant);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * Public list of packages (for the pricing/billing page).
     */
    public function packages(): JsonResponse
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Package $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'price_monthly' => $p->price_monthly,
                'features' => $p->limits ? [
                    'staff_count' => $p->limits->staff_count,
                    'pos_devices' => $p->limits->pos_devices,
                    'table_count' => $p->limits->table_count,
                    'menu_items' => $p->limits->menu_items,
                    'customer_qr_ordering' => (bool) $p->limits->customer_qr_ordering,
                    'advanced_reports' => (bool) $p->limits->advanced_reports,
                    'table_card_tag_system' => (bool) $p->limits->table_card_tag_system,
                ] : null,
            ]);

        return response()->json(['packages' => $packages]);
    }
}
