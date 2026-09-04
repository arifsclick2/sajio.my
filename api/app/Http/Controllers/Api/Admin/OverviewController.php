<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Super Admin — platform overview & stats (Sajio plan §24).
 */
class OverviewController extends Controller
{
    /**
     * Dashboard stats: restaurants, packages, subscriptions, payments, MRR-ish.
     */
    public function stats(Request $request): JsonResponse
    {
        $now = now();

        $totalRestaurants = Restaurant::query()->count();
        $activeRestaurants = Restaurant::query()
            ->where('status', 'active')
            ->whereNull('trial_ends_at')
            ->count();
        $onTrial = Restaurant::query()
            ->where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now)
            ->count();
        $expired = Restaurant::query()
            ->where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->count();
        $suspended = Restaurant::query()->where('status', 'suspended')->count();

        // Package distribution via active Stripe subscriptions.
        $subsByPackage = DB::table('subscriptions as s')
            ->join('packages as p', 'p.stripe_price_id', '=', 's.stripe_price')
            ->select('p.slug', 'p.name', DB::raw('count(*) as total'))
            ->whereIn('s.stripe_status', ['active', 'past_due', 'trialing'])
            ->groupBy('p.slug', 'p.name')
            ->orderBy('total', 'desc')
            ->get();

        // Monthly recurring revenue (MYR) from active subs.
        $mrr = DB::table('subscriptions as s')
            ->join('packages as p', 'p.stripe_price_id', '=', 's.stripe_price')
            ->whereIn('s.stripe_status', ['active', 'trialing'])
            ->sum('p.price_monthly');

        $superAdmins = User::query()->where('role', \App\Enums\UserRole::SuperAdmin)->count();
        $owners = User::query()->where('role', \App\Enums\UserRole::Owner)->count();

        return response()->json([
            'restaurants' => [
                'total' => $totalRestaurants,
                'active_subscribed' => $activeRestaurants,
                'on_trial' => $onTrial,
                'trial_expired' => $expired,
                'suspended' => $suspended,
            ],
            'subscriptions' => [
                'by_package' => $subsByPackage,
                'mrr_myr' => round((float) $mrr, 2),
            ],
            'users' => [
                'super_admins' => $superAdmins,
                'owners' => $owners,
            ],
        ]);
    }

    /**
     * Paginated list of restaurants (admin view).
     */
    public function restaurants(Request $request): JsonResponse
    {
        $query = Restaurant::query()
            ->withCount('users');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$q}%")
                ->orWhere('subdomain', 'ilike', "%{$q}%"));
        }

        $restaurants = $query
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        $items = collect($restaurants->items())->map(function (Restaurant $r) {
            $status = $r->subscriptionStatus();

            return [
                'id' => $r->id,
                'name' => $r->name,
                'subdomain' => $r->subdomain,
                'currency' => $r->currency,
                'timezone' => $r->timezone,
                'status' => $status->value,
                'status_label' => $status->label(),
                'can_operate' => $r->canOperate(),
                'trial_ends_at' => $r->trial_ends_at?->toISOString(),
                'users_count' => $r->users_count,
                'created_at' => $r->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'total' => $restaurants->total(),
            ],
        ]);
    }

    /**
     * Suspend / reactivate a restaurant (admin enforcement).
     */
    public function setRestaurantStatus(Request $request, Restaurant $restaurant): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $restaurant->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Restaurant updated.',
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'status' => $restaurant->status->value,
            ],
        ]);
    }

    /**
     * Sync all packages to Stripe (prices) — used after admin price edits.
     */
    public function syncPackagesToStripe(): JsonResponse
    {
        try {
            app(StripeService::class)->syncAllPackages();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['message' => 'Packages synced to Stripe.']);
    }
}
