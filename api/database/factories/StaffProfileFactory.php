<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'staff_code' => 'S001',
            'position' => 'Waiter',
            'phone' => '+60 12-345 6789',
            'joined_at' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
