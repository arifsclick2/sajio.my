<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RestaurantSetupTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Restaurant $restaurant): User
    {
        return User::factory()->restaurant($restaurant)->owner()->create();
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->create(['timezone' => 'UTC']);
    }

    /* ------------------------------------------------------------------ */
    /*  Profile & branding                                                 */
    /* ------------------------------------------------------------------ */

    public function test_profile_auto_creates_settings_with_defaults(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('restaurant.name', $r->name)
            ->assertJsonPath('settings.country', 'MY')
            ->assertJsonPath('branding.brand_color', '#0d9488');
    }

    public function test_owner_updates_profile_and_branding(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/profile/settings', [
            'name' => 'Kopitiam Maju',
            'phone' => '+60 12-345 6789',
            'city' => 'Kuala Lumpur',
            'opening_hours' => ['mon' => [['09:00', '22:00']]],
        ])->assertOk()
            ->assertJsonPath('settings.city', 'Kuala Lumpur');

        $this->putJson('/api/v1/profile/branding', [
            'brand_color' => '#0f766e',
            'receipt_header' => 'Terima kasih!',
        ])->assertOk()
            ->assertJsonPath('branding.receipt_header', 'Terima kasih!');

        $this->assertDatabaseHas('restaurants', ['id' => $r->id, 'name' => 'Kopitiam Maju']);
    }

    /* ------------------------------------------------------------------ */
    /*  Menu                                                               */
    /* ------------------------------------------------------------------ */

    public function test_owner_can_manage_categories_and_products(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $cat = $this->postJson('/api/v1/menu/categories', ['name' => 'Nasi'])
            ->assertCreated()->json('category');

        $product = $this->postJson('/api/v1/menu/products', [
            'category_id' => $cat['id'],
            'name' => 'Nasi Lemak Ayam',
            'price' => 12.50,
        ])->assertCreated()->json('product');

        $this->assertSame('12.50', $product['price']);

        $this->putJson("/api/v1/menu/products/{$product['id']}", ['available' => false])
            ->assertOk()
            ->assertJsonPath('product.available', false);

        $this->deleteJson("/api/v1/menu/categories/{$cat['id']}")->assertStatus(422);

        $this->deleteJson("/api/v1/menu/products/{$product['id']}")->assertOk();
        $this->deleteJson("/api/v1/menu/categories/{$cat['id']}")->assertOk();
    }

    public function test_menu_is_tenant_isolated(): void
    {
        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        $o1 = $this->owner($r1);

        Sanctum::actingAs($o1);
        $cat = $this->postJson('/api/v1/menu/categories', ['name' => 'Minuman'])->json('category');

        $o2 = $this->owner($r2);
        Sanctum::actingAs($o2);
        $this->postJson('/api/v1/menu/products', [
            'category_id' => $cat['id'],
            'name' => 'X',
            'price' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $r2cat = \App\Models\Category::query()->create(['restaurant_id' => $r2->id, 'name' => 'Roti']);
        Sanctum::actingAs($o1);
        $this->putJson("/api/v1/menu/categories/{$r2cat->id}", ['name' => 'Hacked'])->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Tables                                                             */
    /* ------------------------------------------------------------------ */

    public function test_owner_bulk_creates_tables_with_unique_tokens(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tables/bulk', ['from' => 1, 'to' => 12, 'capacity' => 4])
            ->assertCreated()
            ->assertJsonCount(12, 'tables');

        $tables = RestaurantTable::where('restaurant_id', $r->id)->get();
        $this->assertCount(12, $tables);
        $this->assertSame(12, $tables->pluck('public_token')->unique()->count());

        $this->getJson('/api/v1/tables')->assertOk()->assertJsonCount(12, 'tables');
    }

    public function test_table_token_regeneration_changes_token(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => '1',
            'capacity' => 2,
            'public_token' => RestaurantTable::generateToken(),
        ]);
        $old = $table->public_token;

        $this->postJson("/api/v1/tables/{$table->id}/regenerate-token")
            ->assertOk();

        $this->assertNotSame($old, $table->fresh()->public_token);
    }

    /* ------------------------------------------------------------------ */
    /*  Table Tags                                                         */
    /* ------------------------------------------------------------------ */

    public function test_tag_lifecycle_assign_and_scan(): void
    {
        $r = $this->restaurant();
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => '25',
            'public_token' => RestaurantTable::generateToken(),
        ]);

        $tag = $this->postJson('/api/v1/table-tags', ['table_id' => $table->id, 'tag_type' => 'qr'])
            ->assertCreated()->json('tag');

        $this->assertSame($table->id, $tag['table_id']);
        $this->assertSame('active', $tag['status']);

        $new = $this->postJson("/api/v1/table-tags/{$tag['id']}/regenerate-token")
            ->assertOk()->json('tag');
        $this->assertNotSame($tag['public_token'], $new['public_token']);

        $this->postJson("/api/v1/table-tags/{$tag['id']}/unassign")->assertOk();
        $this->postJson("/api/v1/table-tags/{$tag['id']}/assign", ['table_id' => $table->id])->assertOk();

        $staff = User::factory()->restaurant($r)->role(\App\Enums\UserRole::Staff)->create();
        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/sessions/scan-tag', ['token' => $new['public_token']])
            ->assertOk()
            ->assertJsonPath('table.number', '25');
    }

    /* ------------------------------------------------------------------ */
    /*  Table Sessions                                                     */
    /* ------------------------------------------------------------------ */

    public function test_session_open_scan_close_flow(): void
    {
        $r = $this->restaurant();
        $staff = User::factory()->restaurant($r)->role(\App\Enums\UserRole::Staff)->create();
        // Payment/close is a duty-gated action: clock the cashier in first.
        \App\Models\StaffProfile::query()->create([
            'restaurant_id' => $r->id,
            'user_id' => $staff->id,
            'staff_code' => \App\Models\StaffProfile::nextStaffCode($r->id),
            'is_active' => true,
        ]);
        app(\App\Services\AttendanceService::class)->clockIn(
            $staff,
            now: \Carbon\CarbonImmutable::parse('2026-09-04 10:00', 'UTC'),
        );
        Sanctum::actingAs($staff);

        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => '3',
            'public_token' => RestaurantTable::generateToken(),
        ]);

        $session = $this->postJson('/api/v1/sessions/open', ['table_id' => $table->id])
            ->assertCreated()->json('session');
        $this->assertSame('open', $session['status']);

        $this->postJson('/api/v1/sessions/open', ['table_id' => $table->id])
            ->assertUnprocessable();

        $this->getJson('/api/v1/sessions/open-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'sessions');

        // Empty session close: no billable orders -> no payment recorded.
        $this->postJson("/api/v1/sessions/{$session['id']}/close", ['method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('session.status', 'closed')
            ->assertJsonPath('session.total_amount', '0.00')
            ->assertJsonPath('payment', null);

        $this->getJson('/api/v1/sessions/open-sessions')->assertOk()->assertJsonCount(0, 'sessions');
    }
}
