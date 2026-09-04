<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->onTrial()->create(['timezone' => 'UTC', 'name' => 'Kedai Kopi Sajio']);
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

    private function table(Restaurant $r, string $number = '8'): RestaurantTable
    {
        return RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => $number,
            'public_token' => RestaurantTable::generateToken(),
        ]);
    }

    private function owner(Restaurant $r): User
    {
        return User::factory()->restaurant($r)->owner()->create();
    }

    /* ------------------------------------------------------------------ */
    /*  Order (takeaway) receipt                                           */
    /* ------------------------------------------------------------------ */

    public function test_order_receipt_contains_items_payment_and_branding(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Teh Tarik', 2.5);
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $order = $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ])->assertCreated()->json('order');

        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'cash'])->assertCreated();

        $receipt = $this->getJson("/api/v1/orders/{$order['id']}/receipt")
            ->assertOk()
            ->json('receipt');

        $this->assertSame('order', $receipt['type']);
        $this->assertSame('Kedai Kopi Sajio', $receipt['restaurant']['name']);
        $this->assertArrayHasKey('receipt_footer', $receipt['restaurant']);
        $this->assertSame($order['order_no'], $receipt['order']['order_no']);
        $this->assertCount(1, $receipt['order']['items']);
        $this->assertSame('Teh Tarik', $receipt['order']['items'][0]['name']);
        $this->assertSame('5.00', $receipt['order']['total']);
        $this->assertCount(1, $receipt['payments']);
        $this->assertSame('cash', $receipt['payments'][0]['method']);
        $this->assertSame('Tunai', $receipt['payments'][0]['method_label']);
        $this->assertSame('5.00', $receipt['total_paid']);
    }

    /* ------------------------------------------------------------------ */
    /*  Session (dine-in) receipt                                          */
    /* ------------------------------------------------------------------ */

    public function test_session_receipt_lists_completed_orders_and_payment(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Nasi Lemak', 12.5);
        $t = $this->table($r, '12');
        $owner = $this->owner($r);
        Sanctum::actingAs($owner);

        $o1 = $this->postJson('/api/v1/orders', ['type' => 'dine_in', 'table_id' => $t->id, 'items' => [['product_id' => $p->id, 'quantity' => 2]]])->json('order');
        $o2 = $this->postJson('/api/v1/orders', ['type' => 'dine_in', 'table_id' => $t->id, 'items' => [['product_id' => $p->id, 'quantity' => 1]]])->json('order');
        // Cancel o1 — it must NOT appear on the bill receipt.
        $this->postJson("/api/v1/orders/{$o1['id']}/status", ['status' => 'cancelled'])->assertOk();

        $sessionId = $o2['table_session_id'];
        $this->postJson("/api/v1/sessions/{$sessionId}/close", ['method' => 'qr', 'reference' => 'DuitNow-999'])
            ->assertOk();

        $receipt = $this->getJson("/api/v1/sessions/{$sessionId}/receipt")
            ->assertOk()
            ->json('receipt');

        $this->assertSame('session', $receipt['type']);
        $this->assertSame('12', $receipt['session']['table']['number']);
        $this->assertSame('closed', $receipt['session']['status']);
        $this->assertCount(1, $receipt['orders']);          // cancelled excluded
        $this->assertSame($o2['order_no'], $receipt['orders'][0]['order_no']);
        $this->assertCount(1, $receipt['payments']);
        $this->assertSame('qr', $receipt['payments'][0]['method']);
        $this->assertSame('DuitNow-999', $receipt['payments'][0]['reference']);
        $this->assertSame('12.50', $receipt['total_paid']);
    }

    /* ------------------------------------------------------------------ */
    /*  Tenant isolation                                                   */
    /* ------------------------------------------------------------------ */

    public function test_receipts_are_tenant_isolated(): void
    {
        $r1 = $this->restaurant();
        $p1 = $this->addProduct($r1);
        $t1 = $this->table($r1, '1');
        $o1 = $this->owner($r1);
        Sanctum::actingAs($o1);

        $order = $this->postJson('/api/v1/orders', ['type' => 'dine_in', 'table_id' => $t1->id, 'items' => [['product_id' => $p1->id, 'quantity' => 1]]])->json('order');
        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'cash'])->assertCreated();
        $sessionId = $order['table_session_id'];

        $r2 = $this->restaurant();
        Sanctum::actingAs($this->owner($r2));

        $this->getJson("/api/v1/orders/{$order['id']}/receipt")->assertForbidden();
        $this->getJson("/api/v1/sessions/{$sessionId}/receipt")->assertForbidden();
    }
}
