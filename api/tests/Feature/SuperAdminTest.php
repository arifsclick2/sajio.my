<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(UserRole::SuperAdmin)->create(['restaurant_id' => null]);
    }

    private function owner(): User
    {
        $restaurant = Restaurant::factory()->onTrial()->create();

        return User::factory()->restaurant($restaurant)->owner()->create();
    }

    /* ------------------------------------------------------------------ */
    /*  Access control                                                     */
    /* ------------------------------------------------------------------ */

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/admin/stats')->assertForbidden();
        $this->getJson('/api/v1/admin/packages')->assertForbidden();
        $this->postJson('/api/v1/admin/coupons', ['code' => 'X'])->assertForbidden();
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/stats')->assertUnauthorized();
    }

    /* ------------------------------------------------------------------ */
    /*  Overview / stats                                                   */
    /* ------------------------------------------------------------------ */

    public function test_admin_stats_overview(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        // Two restaurants: one on trial, one subscribed (active stripe sub).
        $r1 = Restaurant::factory()->onTrial()->create();
        User::factory()->restaurant($r1)->owner()->create();

        $r2 = Restaurant::factory()->create();
        Subscription::query()->create([
            'restaurant_id' => $r2->id,
            'type' => 'main',
            'stripe_id' => 'sub_admin_1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_admin_1',
        ]);
        User::factory()->restaurant($r2)->owner()->create();

        // A package with a stripe_price_id so the sub joins for MRR.
        $this->seed(PackageSeeder::class);
        $pro = Package::where('slug', 'pro')->first();
        $pro->forceFill(['stripe_price_id' => 'price_admin_1'])->save();

        $response = $this->getJson('/api/v1/admin/stats');

        $response->assertOk()
            ->assertJsonPath('restaurants.total', 2)
            ->assertJsonPath('restaurants.on_trial', 1)
            ->assertJsonPath('subscriptions.by_package.0.slug', 'pro')
            ->assertJsonPath('users.owners', 2);
    }

    /* ------------------------------------------------------------------ */
    /*  Package management (prices editable by admin)                      */
    /* ------------------------------------------------------------------ */

    public function test_admin_can_update_package_price(): void
    {
        $this->seed(PackageSeeder::class);
        $basic = Package::where('slug', 'basic')->first();

        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/packages/{$basic->id}", [
            'price_monthly' => 349.00,
            'limits' => ['staff_count' => 8],
        ])->assertOk()
            ->assertJsonPath('package.price_monthly', '349.00')
            ->assertJsonPath('package.limits.staff_count', 8);

        $this->assertDatabaseHas('packages', ['id' => $basic->id, 'price_monthly' => 349.00]);
    }

    public function test_admin_can_list_packages_with_limits(): void
    {
        $this->seed(PackageSeeder::class);
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/packages')
            ->assertOk()
            ->assertJsonCount(3, 'packages')
            ->assertJsonPath('packages.0.limits.staff_count', 5);
    }

    /* ------------------------------------------------------------------ */
    /*  Coupon management                                                  */
    /* ------------------------------------------------------------------ */

    public function test_admin_can_create_and_deactivate_coupon(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'RAMADHAN20',
            'name' => 'Ramadhan 20%',
            'type' => CouponType::Percent->value,
            'value' => 20,
            'max_uses' => 100,
            'expires_at' => '2027-01-01',
        ])->assertCreated()
            ->assertJsonPath('coupon.code', 'RAMADHAN20');

        $coupon = Coupon::where('code', 'RAMADHAN20')->first();
        $this->assertNotNull($coupon);
        $this->assertTrue($coupon->isValid());

        // Deactivate.
        $this->putJson("/api/v1/admin/coupons/{$coupon->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('coupon.is_active', false);

        $this->assertFalse($coupon->fresh()->isValid());
    }

    public function test_admin_can_create_fixed_coupon_and_delete(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/coupons', [
            'code' => 'RM50OFF',
            'type' => CouponType::Fixed->value,
            'value' => 50,
        ])->assertCreated();

        $coupon = Coupon::where('code', 'RM50OFF')->first();
        $this->assertSame('50.00', $coupon->value);

        $this->deleteJson("/api/v1/admin/coupons/{$coupon->id}")->assertOk();
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_duplicate_coupon_code_rejected(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/coupons', ['code' => 'DUP10', 'type' => 'percent', 'value' => 10])->assertCreated();
        $this->postJson('/api/v1/admin/coupons', ['code' => 'dup10', 'type' => 'percent', 'value' => 15])->assertUnprocessable();
    }

    /* ------------------------------------------------------------------ */
    /*  Restaurant admin                                                   */
    /* ------------------------------------------------------------------ */

    public function test_admin_can_list_and_suspend_restaurants(): void
    {
        $admin = $this->admin();
        $r = Restaurant::factory()->onTrial()->create();
        User::factory()->restaurant($r)->owner()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/restaurants')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/admin/restaurants/{$r->id}/status", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('restaurant.status', 'suspended');

        $r->refresh();
        $this->assertSame('suspended', $r->status->value);
        $this->assertFalse($r->canOperate());
    }
}
