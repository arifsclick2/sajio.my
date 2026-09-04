<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => 'Shift A',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A shift that crosses midnight (e.g. 22:00 -> 06:00).
     */
    public function overnight(): static
    {
        return $this->state(fn (array $a) => [
            'name' => 'Malam',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'crosses_midnight' => true,
        ]);
    }
}
