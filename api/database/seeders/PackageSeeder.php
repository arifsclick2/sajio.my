<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageLimit;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Seed the three subscription packages with limits from the Sajio plan §3.
     *
     * Prices are DB-driven (editable by Super Admin) — Basic RM299, Premium
     * RM499, Pro RM999 per month. NULL limit = unlimited.
     * Limits are NOT enforced while a restaurant is on trial.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For small shops getting started. POS, table ordering, sales, expenses & basic reports.',
                'price_monthly' => 299.00,
                'sort_order' => 1,
                'limits' => [
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
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'For busy cafes & restaurants. Adds customer QR ordering and advanced reports.',
                'price_monthly' => 499.00,
                'sort_order' => 2,
                'limits' => [
                    'staff_count' => 10,
                    'pos_devices' => 3,
                    'table_count' => 30,
                    'menu_items' => 500,
                    'customer_qr_ordering' => true,
                    'advanced_reports' => true,
                    'table_card_tag_system' => false,
                    'fast_table_scan_at_pos' => false,
                    'nfc_tag_support' => false,
                    'table_card_printing' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For serious F&B operations. Full Table Card / Tag system with QR + NFC-ready backend.',
                'price_monthly' => 999.00,
                'sort_order' => 3,
                'limits' => [
                    'staff_count' => null,
                    'pos_devices' => null,
                    'table_count' => null,
                    'menu_items' => null,
                    'customer_qr_ordering' => true,
                    'advanced_reports' => true,
                    'table_card_tag_system' => true,
                    'fast_table_scan_at_pos' => true,
                    'nfc_tag_support' => true,
                    'table_card_printing' => true,
                ],
            ],
        ];

        foreach ($packages as $data) {
            $limits = $data['limits'];
            unset($data['limits']);

            $package = Package::query()->updateOrCreate(['slug' => $data['slug']], $data);

            $package->limits()->updateOrCreate([], $limits);
        }
    }
}
