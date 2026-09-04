<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->onTrial()->create(['timezone' => 'UTC']);
    }

    private function addProduct(Restaurant $r, string $name = 'Nasi Lemak', float $price = 12.5): Product
    {
        $cat = Category::query()->create(['restaurant_id' => $r->id, 'name' => 'Nasi']);
        $p = Product::query()->create([
            'restaurant_id' => $r->id,
            'category_id' => $cat->id,
            'name' => $name,
            'price' => $price,
        ]);

        return $p;
    }

    private function staff(Restaurant $r, bool $active = true): User
    {
        $u = User::factory()->restaurant($r)->role(UserRole::Staff)->create();
        StaffProfile::query()->create([
            'restaurant_id' => $r->id,
            'user_id' => $u->id,
            'staff_code' => StaffProfile::nextStaffCode($r->id),
            'is_active' => $active,
        ]);

        return $u;
    }

    /* ------------------------------------------------------------------ */
    /*  Order creation — takeaway                                          */
    /* ------------------------------------------------------------------ */

    public function test_owner_can_create_takeaway_order(): void
    {
        $r = $this->restaurant();
        $p1 = $this->addProduct($r, 'Teh Tarik', 2.5);
        $p2 = $this->addProduct($r, 'Roti Canai', 1.8);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $resp = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
            'note' => 'Bungkus',
        ])->assertCreated()->json('order');

        $this->assertSame('takeaway', $resp['type']);
        $this->assertSame('new', $resp['status']);
        $this->assertSame('6.80', $resp['total']);   // 2*2.5 + 1.8
        $this->assertSame('6.80', $resp['subtotal']);
        $this->assertStringStartsWith('#1', $resp['order_no']); // #1001
        $this->assertNull($resp['table']);
        $this->assertCount(2, $resp['items']);

        $this->assertDatabaseHas('orders', ['restaurant_id' => $r->id, 'staff_id' => $owner->id]);
        $this->assertDatabaseHas('order_status_history', ['to_status' => 'new']);
    }

    /* ------------------------------------------------------------------ */
    /*  Duty gate                                                          */
    /* ------------------------------------------------------------------ */

    public function test_off_duty_staff_cannot_create_order(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $s = $this->staff($r);
        Sanctum::actingAs($s);

        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('duty');
    }

    public function test_on_duty_staff_can_create_order_linked_to_them(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $s = $this->staff($r);
        $service = app(AttendanceService::class);

        $service->clockIn($s, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));

        Sanctum::actingAs($s);
        $resp = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertCreated()->json('order');

        // Order linked to the staff member.
        $this->assertSame($s->id, $resp['staff']['id']);
        $this->assertDatabaseHas('orders', ['id' => $resp['id'], 'staff_id' => $s->id]);
    }

    /* ------------------------------------------------------------------ */
    /*  Subscription lock                                                  */
    /* ------------------------------------------------------------------ */

    public function test_locked_restaurant_cannot_create_order(): void
    {
        $r = $this->restaurant();
        $r->forceFill(['trial_ends_at' => now()->subDay(), 'trial_locked_at' => now()])->save();
        $p = $this->addProduct($r);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('restaurant');
    }

    /* ------------------------------------------------------------------ */
    /*  Dine-in → table session                                            */
    /* ------------------------------------------------------------------ */

    public function test_dine_in_order_auto_opens_and_links_session(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id, 'number' => '5', 'public_token' => RestaurantTable::generateToken(),
        ]);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $resp = $this->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertCreated()->json('order');

        $this->assertSame($table->id, $resp['table']['id']);
        $this->assertNotNull($resp['table_session_id']);

        // Second dine-in order joins the SAME open session.
        $resp2 = $this->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ])->assertCreated()->json('order');

        $this->assertSame($resp['table_session_id'], $resp2['table_session_id']);
        $this->assertDatabaseCount('table_sessions', 1); // only one open session
    }

    public function test_dine_in_without_table_rejected(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('table_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Menu guard + price snapshot                                        */
    /* ------------------------------------------------------------------ */

    public function test_cross_restaurant_product_rejected_and_price_snapshotted(): void
    {
        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        $p2 = $this->addProduct($r2, 'Kopi O', 2.0);

        $o1 = User::factory()->restaurant($r1)->owner()->create();
        Sanctum::actingAs($o1);

        // r1 ordering r2's product -> rejected.
        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p2->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        // Price snapshot: change price after order; order keeps original.
        $p1 = $this->addProduct($r1, 'Nasi Kandar', 11.0);
        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p1->id, 'quantity' => 1]],
        ])->assertCreated();

        $p1->update(['price' => 15.0]);
        $this->assertDatabaseHas('order_items', ['product_id' => $p1->id, 'unit_price' => 11.00]);
    }

    /* ------------------------------------------------------------------ */
    /*  Lifecycle transitions                                              */
    /* ------------------------------------------------------------------ */

    public function test_order_lifecycle_transitions_and_guards(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $order = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->json('order');

        // NEW → PREPARING → READY → SERVED → COMPLETED
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'preparing'])->assertOk();
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'ready'])->assertOk();
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'served'])->assertOk();
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'completed'])->assertOk();

        // Illegal: COMPLETED is terminal.
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'cancelled'])
            ->assertUnprocessable();

        // History logged for every transition.
        $this->assertDatabaseCount('order_status_history', 5); // created + 4
    }

    public function test_cancel_only_from_active_states(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $order = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->json('order');

        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'cancelled'])->assertOk();

        // Cannot cancel again.
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'preparing'])->assertUnprocessable();
    }

    /* ------------------------------------------------------------------ */
    /*  Tenant isolation                                                   */
    /* ------------------------------------------------------------------ */

    public function test_cannot_view_or_modify_other_restaurants_order(): void
    {
        $r1 = $this->restaurant();
        $p = $this->addProduct($r1);
        $o1 = User::factory()->restaurant($r1)->owner()->create();
        Sanctum::actingAs($o1);

        $order = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->json('order');

        $r2 = $this->restaurant();
        $o2 = User::factory()->restaurant($r2)->owner()->create();
        Sanctum::actingAs($o2);

        $this->getJson("/api/v1/orders/{$order['id']}")->assertForbidden();
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'completed'])->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Floor / current bill                                               */
    /* ------------------------------------------------------------------ */

    public function test_current_bill_for_table_sums_open_orders(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Nasi Lemak', 12.5);
        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id, 'number' => '12', 'public_token' => RestaurantTable::generateToken(),
        ]);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/orders', ['type' => 'dine_in', 'table_id' => $table->id, 'items' => [['product_id' => $p->id, 'quantity' => 2]]]);
        $this->postJson('/api/v1/orders', ['type' => 'dine_in', 'table_id' => $table->id, 'items' => [['product_id' => $p->id, 'quantity' => 1]]]);

        $this->getJson("/api/v1/orders/table/{$table->id}/current")
            ->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('bill_total', '37.50');
    }
}
