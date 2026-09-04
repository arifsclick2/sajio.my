<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\EmailOtp;
use App\Models\Package;
use App\Models\PackageLimit;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreModelsTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /*  Restaurant + tenant fields                                         */
    /* ------------------------------------------------------------------ */

    public function test_restaurant_can_be_created_with_malaysian_defaults(): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Kopitiam Sajio',
            'subdomain' => 'kopitiam-sajio',
        ]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'subdomain' => 'kopitiam-sajio',
            'currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
            'country' => 'MY',
        ]);
    }

    public function test_subdomain_is_unique(): void
    {
        Restaurant::factory()->create(['subdomain' => 'kedai-abc']);
        $this->expectExceptionMessageMatches('/duplicate key/i');

        Restaurant::query()->create([
            'name' => 'Another',
            'subdomain' => 'kedai-abc',
        ]);
    }

    public function test_user_belongs_to_restaurant_with_role(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();
        $staff = User::factory()->restaurant($restaurant)->role(UserRole::Staff)->create();

        $this->assertTrue($owner->isOwner());
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($owner->belongsToRestaurant($restaurant));
        $this->assertSame($restaurant->id, $owner->restaurant->id);
        $this->assertCount(2, $restaurant->users);
    }

    public function test_super_admin_has_no_restaurant(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create([
            'restaurant_id' => null,
        ]);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertNull($admin->restaurant);
    }

    /* ------------------------------------------------------------------ */
    /*  Trial helpers                                                      */
    /* ------------------------------------------------------------------ */

    public function test_trial_helpers(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->assertFalse($restaurant->isOnTrial());

        $restaurant->startTrial(14);
        $this->assertTrue($restaurant->isOnTrial());
        $this->assertSame(14, $restaurant->trialDaysRemaining());

        $this->travel(15)->days();
        $this->assertTrue($restaurant->trialEnded());
        $this->assertFalse($restaurant->isOnTrial());
        $this->assertSame(0, $restaurant->trialDaysRemaining());
    }

    /* ------------------------------------------------------------------ */
    /*  Packages + limits (seeded with live prices)                        */
    /* ------------------------------------------------------------------ */

    public function test_package_seeder_creates_three_packages_with_prices(): void
    {
        $this->seed(PackageSeeder::class);

        $basic = Package::where('slug', 'basic')->first();
        $premium = Package::where('slug', 'premium')->first();
        $pro = Package::where('slug', 'pro')->first();

        $this->assertNotNull($basic);
        $this->assertSame('299.00', $basic->price_monthly);
        $this->assertSame('499.00', $premium->price_monthly);
        $this->assertSame('999.00', $pro->price_monthly);

        $this->assertSame(100, $basic->limits->menu_items);
        $this->assertSame(1, $basic->limits->pos_devices);
        $this->assertFalse((bool) $basic->limits->customer_qr_ordering);

        $this->assertTrue((bool) $premium->limits->customer_qr_ordering);
        $this->assertTrue((bool) $premium->limits->advanced_reports);

        $this->assertNull($pro->limits->staff_count); // unlimited
        $this->assertTrue((bool) $pro->limits->table_card_tag_system);
        $this->assertTrue((bool) $pro->limits->nfc_tag_support);
    }

    public function test_package_limit_belongs_to_package(): void
    {
        $package = Package::factory()->withLimits(['staff_count' => 7])->create();

        $this->assertInstanceOf(PackageLimit::class, $package->limits);
        $this->assertSame(7, $package->limits->staff_count);
    }

    public function test_price_monthly_in_sen(): void
    {
        $package = Package::factory()->create(['price_monthly' => 299.00]);
        $this->assertSame(29900, $package->priceMonthlyInSen());
    }

    /* ------------------------------------------------------------------ */
    /*  Coupons                                                            */
    /* ------------------------------------------------------------------ */

    public function test_coupon_validity_rules(): void
    {
        Coupon::query()->create([
            'code' => 'SAJIO10',
            'name' => 'Launch 10%',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 5,
            'used_count' => 5,
            'is_active' => true,
        ]);

        Coupon::query()->create([
            'code' => 'PROMO50',
            'name' => 'RM50 off',
            'type' => 'fixed',
            'value' => 50,
            'max_uses' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        Coupon::query()->create([
            'code' => 'EXPIRED1',
            'name' => 'Expired',
            'type' => 'percent',
            'value' => 10,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->assertFalse(Coupon::where('code', 'SAJIO10')->first()->isValid()); // used up
        $this->assertTrue(Coupon::where('code', 'PROMO50')->first()->isValid());
        $this->assertFalse(Coupon::where('code', 'EXPIRED1')->first()->isValid());
    }

    /* ------------------------------------------------------------------ */
    /*  Email OTP                                                          */
    /* ------------------------------------------------------------------ */

    public function test_email_otp_issue_and_verify(): void
    {
        $code = EmailOtp::issue('owner@kedai.my');

        $this->assertDatabaseHas('email_otps', ['email' => 'owner@kedai.my', 'purpose' => 'verify_email']);
        $this->assertTrue(EmailOtp::verify('owner@kedai.my', $code));
        $this->assertDatabaseHas('email_otps', [
            'email' => 'owner@kedai.my',
            'used_at' => now(),
        ]);
    }

    public function test_email_otp_rejects_wrong_code_and_blocks_after_attempts(): void
    {
        $code = EmailOtp::issue('owner@kedai.my');

        $this->assertFalse(EmailOtp::verify('owner@kedai.my', '000000'));

        // 5 wrong attempts total (1 above + 4 more) exhausts the OTP.
        for ($i = 0; $i < 4; $i++) {
            EmailOtp::verify('owner@kedai.my', '000000');
        }

        // Even the correct code is now rejected (attempt limit reached).
        $this->assertFalse(EmailOtp::verify('owner@kedai.my', $code));
    }

    public function test_email_otp_rejects_expired_code(): void
    {
        EmailOtp::issue('owner@kedai.my');

        $this->travel(EmailOtp::TTL_MINUTES + 1)->minutes();

        $this->assertFalse(EmailOtp::verify('owner@kedai.my', '123456'));
    }
}
