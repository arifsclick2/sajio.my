<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\RestaurantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner/manager manages the restaurant profile + branding (plan §6).
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly RestaurantProfileService $profiles,
    ) {
    }

    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    /**
     * GET /profile — full profile + branding + core restaurant fields.
     */
    public function show(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $settings = $this->profiles->settings($restaurant);
        $branding = $this->profiles->branding($restaurant);

        return response()->json([
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'subdomain' => $restaurant->subdomain,
                'currency' => $restaurant->currency,
                'timezone' => $restaurant->timezone,
                'country' => $restaurant->country,
            ],
            'settings' => $settings,
            'branding' => $branding,
        ]);
    }

    /**
     * PUT /profile/settings — update profile fields.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $settings = $this->profiles->settings($restaurant);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'size:2'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
        ]);

        // Core restaurant fields (name/currency/timezone) live on restaurants.
        $restaurantData = array_intersect_key($validated, array_flip(['name', 'currency', 'timezone']));
        if ($restaurantData) {
            $restaurant->update($restaurantData);
        }

        $settingsData = array_intersect_key($validated, array_flip([
            'phone', 'email', 'address', 'city', 'state', 'postcode', 'country', 'opening_hours',
        ]));
        if ($settingsData) {
            $settings->update($settingsData);
        }

        return response()->json([
            'restaurant' => $restaurant->fresh(),
            'settings' => $settings->fresh(),
        ]);
    }

    /**
     * PUT /profile/branding — logo/colors/receipt text.
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $branding = $this->profiles->branding($restaurant);

        $validated = $request->validate([
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'brand_color' => ['sometimes', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'receipt_header' => ['sometimes', 'nullable', 'string', 'max:500'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $branding->update($validated);

        return response()->json(['branding' => $branding->fresh()]);
    }
}
