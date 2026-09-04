<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 — Customer QR ordering (§15).
 *
 * A guest scans a table's public token (no account, no login), browses the
 * public menu and places an order that drops into the kitchen with no staff
 * attached (staff_id NULL, source = customer_qr). The table session is
 * auto-opened so the cashier can settle it later.
 *
 * Gates: restaurant must be operating and the plan must include customer QR
 * ordering (trial = all features on; Premium/Pro yes; Basic no).
 */
class CustomerQrTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->onTrial()->create(['timezone' => 'UTC']);
    }

    private function addTable(Restaurant $r, string $number = '1', bool $active = true): RestaurantTable
    {
        return RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => $number,
            'capacity' => 4,
            'public_token' => RestaurantTable::generateToken(),
            'is_active' => $active,
        ]);
    }

    private function addProduct(Restaurant $r, string $name = 'Nasi Lemak', float $price = 12.5, bool $sellable = true, ?Category $category = null): Product
    {
        $cat = $category ?? Category::query()->create(['restaurant_id' => $r->id, 'name' => 'Makanan']);
        $p = Product::query()->create([
            'restaurant_id' => $r->id,
            'category_id' => $cat->id,
            'name' => $name,
            'price' => $price,
            'is_active' => $sellable,
            'available' => true,
        ]);

        return $p;
    }

    /** Attach an active subscription mapped to a package with the given slug. */
    private function subscribeTo(Restaurant $r, string $slug): Package
    {
        $package = Package::query()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'description' => 'Test package',
            'price_monthly' => 100.00,
            'stripe_price_id' => 'price_test_'.$slug,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'restaurant_id' => $r->id,
            'type' => 'main',
            'stripe_id' => 'sub_test_'.$slug,
            'stripe_status' => 'active',
            'stripe_price' => $package->stripe_price_id,
        ]);

        return $package;
    }

    /* ------------------------------------------------------------------ */
    /*  Public menu                                                        */
    /* ------------------------------------------------------------------ */

    public function test_guest_can_fetch_public_menu_for_active_table(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);
        $cat = Category::query()->create(['restaurant_id' => $r->id, 'name' => 'Makanan']);
        $p1 = $this->addProduct($r, 'Nasi Lemak', 12.5, category: $cat);
        $p2 = $this->addProduct($r, 'Teh Tarik', 2.5, category: $cat);

        $menu = $this->getJson("/api/v1/public/table/{$table->public_token}/menu")
            ->assertOk()
            ->assertJsonPath('restaurant.name', $r->name)
            ->assertJsonPath('table.number', '1')
            ->assertJsonPath('currency', 'RM')
            ->assertJsonCount(1, 'categories')
            ->json();

        $names = collect($menu['categories'][0]['products'])->pluck('name');
        $this->assertTrue($names->contains('Nasi Lemak'));
        $this->assertTrue($names->contains('Teh Tarik'));
    }

    public function test_public_menu_hides_unavailable_products_and_categories(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);
        $this->addProduct($r, 'Sold Out', 5.0, sellable: false);

        $menu = $this->getJson("/api/v1/public/table/{$table->public_token}/menu")
            ->assertOk()
            ->json();

        // No sellable products -> no categories returned.
        $this->assertCount(0, $menu['categories']);
    }

    public function test_unknown_or_inactive_table_token_returns_404(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);
        $inactive = $this->addTable($r, '2', active: false);

        $this->getJson('/api/v1/public/table/DOESNOTEXIST/menu')->assertNotFound();
        $this->getJson("/api/v1/public/table/{$inactive->public_token}/menu")->assertNotFound();
    }

    public function test_suspended_restaurant_menu_is_blocked(): void
    {
        $r = Restaurant::factory()->onTrial()->create(['status' => 'suspended']);
        $table = $this->addTable($r);

        $this->getJson("/api/v1/public/table/{$table->public_token}/menu")
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Feature gate                                                       */
    /* ------------------------------------------------------------------ */

    public function test_basic_plan_cannot_use_customer_qr_ordering(): void
    {
        $r = $this->restaurant();
        $this->subscribeTo($r, 'basic');
        $table = $this->addTable($r);
        $p = $this->addProduct($r);

        $this->getJson("/api/v1/public/table/{$table->public_token}/menu")
            ->assertForbidden();

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('restaurant');
    }

    public function test_premium_and_pro_plans_allow_customer_qr_ordering(): void
    {
        foreach (['premium', 'pro'] as $slug) {
            $r = $this->restaurant();
            $this->subscribeTo($r, $slug);
            $table = $this->addTable($r);
            $p = $this->addProduct($r);

            $this->getJson("/api/v1/public/table/{$table->public_token}/menu")->assertOk();

            $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
                'items' => [['product_id' => $p->id, 'quantity' => 1]],
            ])->assertCreated();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Placing a customer order                                           */
    /* ------------------------------------------------------------------ */

    public function test_guest_order_creates_anonymous_dine_in_order(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);
        $p1 = $this->addProduct($r, 'Nasi Lemak', 12.5);
        $p2 = $this->addProduct($r, 'Teh Tarik', 2.5);

        $resp = $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
            'customer_name' => 'Aiman',
            'customer_phone' => '0123456789',
        ])->assertCreated()->json('order');

        $this->assertSame('dine_in', $resp['type']);
        $this->assertSame('new', $resp['status']);
        $this->assertSame('#1001', $resp['order_no']);
        $this->assertSame('27.50', $resp['total']); // 2*12.5 + 2.5
        $this->assertCount(2, $resp['items']);

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $r->id,
            'order_no' => '#1001',
            'source' => 'customer_qr',
            'staff_id' => null,
            'type' => 'dine_in',
            'customer_name' => 'Aiman',
            'customer_phone' => '0123456789',
            'table_id' => $table->id,
        ]);

        // Kitchen audit trail records the anonymous origin.
        $this->assertDatabaseHas('order_status_history', [
            'to_status' => 'new',
            'changed_by' => null,
            'reason' => 'customer_qr_order',
        ]);

        // Table session auto-opened with no operator.
        $this->assertDatabaseHas('table_sessions', [
            'restaurant_id' => $r->id,
            'table_id' => $table->id,
            'opened_by' => null,
            'status' => 'open',
        ]);
    }

    public function test_second_customer_order_reuses_open_table_session_and_increments_no(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);
        $p = $this->addProduct($r);

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('order.order_no', '#1001');

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.order_no', '#1002');

        $this->assertSame(1, TableSession::query()->where('table_id', $table->id)->where('status', 'open')->count());
        $this->assertDatabaseHas('orders', ['order_no' => '#1002', 'source' => 'customer_qr']);
    }

    public function test_customer_cannot_order_products_from_another_restaurant(): void
    {
        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        $table = $this->addTable($r1);
        $foreign = $this->addProduct($r2);

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_customer_order_requires_valid_items_and_quantities(): void
    {
        $r = $this->restaurant();
        $table = $this->addTable($r);

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [],
        ])->assertUnprocessable();

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [],
        ])->assertUnprocessable();
    }

    public function test_suspended_restaurant_cannot_receive_customer_orders(): void
    {
        $r = Restaurant::factory()->onTrial()->create(['status' => 'suspended']);
        $table = $this->addTable($r);
        $p = $this->addProduct($r);

        $this->postJson("/api/v1/public/table/{$table->public_token}/orders", [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('restaurant');
    }
}
