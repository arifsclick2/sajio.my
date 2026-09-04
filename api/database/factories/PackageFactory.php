<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => Str($slug)->title()->toString(),
            'slug' => $slug,
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomElement([299, 499, 999]),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    /**
     * Attach default limits.
     */
    public function withLimits(array $overrides = []): static
    {
        return $this->afterCreating(function (Package $package) use ($overrides): void {
            PackageLimit::query()->create(array_merge([
                'package_id' => $package->id,
                'staff_count' => 5,
                'pos_devices' => 1,
                'table_count' => 10,
                'menu_items' => 100,
                'customer_qr_ordering' => false,
                'advanced_reports' => false,
                'table_card_tag_system' => false,
                'fast_table_scan_at_pos' => false,
                'nfc_tag_support' => false,
                'table_card_printing' => false,
            ], $overrides));
        });
    }
}
