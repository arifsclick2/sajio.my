<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantBranding;
use App\Models\RestaurantSetting;

/**
 * Ensures a restaurant has its 1:1 settings + branding rows, creating them
 * with Malaysian defaults on first access.
 */
class RestaurantProfileService
{
    public function settings(Restaurant $restaurant): RestaurantSetting
    {
        return $restaurant->settings()->firstOrCreate(
            ['restaurant_id' => $restaurant->id],
            ['country' => 'MY'],
        );
    }

    public function branding(Restaurant $restaurant): RestaurantBranding
    {
        return $restaurant->branding()->firstOrCreate(
            ['restaurant_id' => $restaurant->id],
            ['brand_color' => '#0d9488'],
        );
    }
}
