<?php

namespace Tests\Feature;

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

class PaymentsTest extends TestCase
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

    private function table(Restaurant $r, string $number = '8'): RestaurantTable
    {
        return RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => $number,
            'public_token' => RestaurantTable::generateToken(),
        ]);
    }

    private function staff(Restaurant $r): User
    {
        $u = User::factory()->restaurant($r)->role(UserRole::Staff)->create();
        StaffProfile::query()->create([
            'restaurant_id' => $r->id,
            'user_id' => $u->id,
            'staff_code' => StaffProfile::nextStaffCode($r->id),
            'is_active' => true,
        ]);

        return $u;
    }

    private function clockIn(User $u): void
    {
        app(AttendanceService::class)->clockIn($u, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));
    }

    /** Create a takeaway order as the acting user; returns order id. */
    private function takeaway(Restaurant $r, Product $p, int $qty = 1, array $extra = []): array
    {
        return $this->postJson('/api/v1/orders', array_merge([
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => $qty]],
        ], $extra))->assertCreated()->json('order');
    }

    /** Create a dine-in order on $tableId as acting user. */
    private function dineIn(Restaurant $r, Product $p, int $tableId, int $qty = 1): array
    {
        return $this->postJson('/api/v1/orders', [
            'type' => 'dine_in',
            'table_id' => $tableId,
            'items' => [['product_id' => $p->id, 'quantity' => $qty]],
        ])->assertCreated()->json('order');
    }

    /* ------------------------------------------------------------------ */
    /*  Single-order payment (takeaway)                                    */
    /* ------------------------------------------------------------------ */

    public function test_takeaway_order_paid_with_cash_is_completed(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Nasi Lemak', 12.5);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $order = $this->takeaway($r, $p, 2); // RM25.00

        $resp = $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'cash'])
            ->assertCreated()
            ->json();

        $this->assertSame('25.00', $resp['payment']['amount']);
        $this->assertSame('cash', $resp['payment']['method']);
        $this->assertSame('Tunai', $resp['payment']['method_label']);
        $this->assertSame($order['id'], $resp['payment']['order_id']);
        $this->assertSame('completed', $resp['order']['status']);
        $this->assertNotNull($resp['order']['completed_at']);

        // Completed + history row for the payment.
        $this->assertDatabaseHas('payments', ['restaurant_id' => $r->id, 'amount' => 25.00, 'received_by' => $owner->id]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order['id'],
            'to_status' => 'completed',
            'reason' => 'order_paid',
        ]);
    }

    public function test_order_payment_must_match_total_and_never_double_pays(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Teh Tarik', 2.5);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $order = $this->takeaway($r, $p); // RM2.50

        // Wrong amount rejected.
        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'cash', 'amount' => 10.00])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        // Valid payment.
        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'qr', 'reference' => 'TNG-12345'])
            ->assertCreated();

        // Double pay rejected.
        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'card'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_order_payment_requires_valid_method_and_duty(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r);
        $owner = User::factory()->restaurant($r)->owner()->create();

        // Invalid method -> 422.
        Sanctum::actingAs($owner);
        $order = $this->takeaway($r, $p);
        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'crypto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('method');

        // Off-duty staff cannot take payment (duty gate).
        $staff = $this->staff($r);
        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('duty');
    }

    public function test_on_duty_staff_can_record_payment(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Roti Canai', 1.8);
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);
        $order = $this->takeaway($r, $p);

        $staff = $this->staff($r);
        $this->clockIn($staff);
        Sanctum::actingAs($staff);

        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'card', 'reference' => 'Visa 4242'])
            ->assertCreated()
            ->assertJsonPath('payment.received_by', $staff->id);
    }

    /* ------------------------------------------------------------------ */
    /*  Dine-in session settle                                             */
    /* ------------------------------------------------------------------ */

    public function test_dine_in_session_settle_records_payment_and_closes(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Nasi Lemak', 12.5);
        $t = $this->table($r, '12');
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $o1 = $this->dineIn($r, $p, $t->id, 2); // 25.00
        $o2 = $this->dineIn($r, $p, $t->id, 1); // 12.50

        // Serve the first order along the normal lifecycle.
        $this->postJson("/api/v1/orders/{$o1['id']}/status", ['status' => 'preparing'])->assertOk();
        $this->postJson("/api/v1/orders/{$o1['id']}/status", ['status' => 'ready'])->assertOk();
        $this->postJson("/api/v1/orders/{$o1['id']}/status", ['status' => 'served'])->assertOk();

        $sessionId = $o1['table_session_id'];
        $this->assertNotNull($sessionId);
        $this->assertSame($sessionId, $o2['table_session_id']);

        $resp = $this->postJson("/api/v1/sessions/{$sessionId}/close", ['method' => 'card'])
            ->assertOk()
            ->json();

        $this->assertSame('37.50', $resp['payment']['amount']);
        $this->assertSame('card', $resp['payment']['method']);
        $this->assertSame($sessionId, $resp['payment']['table_session_id']);
        $this->assertSame('closed', $resp['session']['status']);
        $this->assertSame('37.50', $resp['session']['total_amount']);
        $this->assertCount(2, $resp['orders']);

        // Both orders completed; table freed.
        $this->assertSame('completed', $resp['orders'][0]['status']);
        $this->assertSame('completed', $resp['orders'][1]['status']);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $o2['id'],
            'to_status' => 'completed',
            'reason' => 'session_settled',
        ]);
        $this->getJson('/api/v1/sessions/open-sessions')->assertOk()->assertJsonCount(0, 'sessions');

        // A closed session cannot be settled twice.
        $this->postJson("/api/v1/sessions/{$sessionId}/close", ['method' => 'qr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
    }

    public function test_session_bill_excludes_cancelled_orders(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Kopi O', 2.0);
        $t = $this->table($r, '5');
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $oA = $this->dineIn($r, $p, $t->id, 3); // 6.00 — will be cancelled
        $oB = $this->dineIn($r, $p, $t->id, 2); // 4.00 — will be paid

        $this->postJson("/api/v1/orders/{$oA['id']}/status", ['status' => 'cancelled'])->assertOk();

        $resp = $this->postJson("/api/v1/sessions/{$oB['table_session_id']}/close", ['method' => 'cash'])
            ->assertOk()
            ->json();

        $this->assertSame('4.00', $resp['payment']['amount']);
        $this->assertCount(1, $resp['orders']); // only the billable one
        $this->assertSame('cancelled', $this->getJson("/api/v1/orders/{$oA['id']}")->json('order.status'));
    }

    public function test_session_settle_amount_must_match_bill(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Mee Goreng', 8.0);
        $t = $this->table($r, '9');
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $o = $this->dineIn($r, $p, $t->id);

        $this->postJson("/api/v1/sessions/{$o['table_session_id']}/close", ['method' => 'cash', 'amount' => 5.00])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_locked_restaurant_can_still_settle_existing_session(): void
    {
        $r = $this->restaurant();
        $p = $this->addProduct($r, 'Bihun Sup', 9.0);
        $t = $this->table($r, '21');
        $owner = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($owner);

        $o = $this->dineIn($r, $p, $t->id);

        // Lock the restaurant (subscription ended) — new orders blocked...
        $r->forceFill(['trial_ends_at' => now()->subDay(), 'trial_locked_at' => now()])->save();
        // actingAs keeps returning the same user instance, whose restaurant
        // relation was cached during the dine-in request — drop it so the
        // next request sees the freshly-locked row.
        $owner->unsetRelation('restaurant');
        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        // ...but paying the existing bill must still work (no stranded guests).
        $this->postJson("/api/v1/sessions/{$o['table_session_id']}/close", ['method' => 'qr'])
            ->assertOk()
            ->assertJsonPath('session.status', 'closed')
            ->assertJsonPath('payment.amount', '9.00');
    }

    /* ------------------------------------------------------------------ */
    /*  Tenant isolation                                                   */
    /* ------------------------------------------------------------------ */

    public function test_cannot_pay_or_settle_other_restaurants_orders(): void
    {
        $r1 = $this->restaurant();
        $p1 = $this->addProduct($r1);
        $t1 = $this->table($r1, '1');
        $o1 = User::factory()->restaurant($r1)->owner()->create();
        Sanctum::actingAs($o1);

        $order = $this->dineIn($r1, $p1, $t1->id);
        $sessionId = $order['table_session_id'];

        $r2 = $this->restaurant();
        $o2 = User::factory()->restaurant($r2)->owner()->create();
        Sanctum::actingAs($o2);

        $this->postJson("/api/v1/orders/{$order['id']}/pay", ['method' => 'cash'])
            ->assertForbidden();
        $this->postJson("/api/v1/sessions/{$sessionId}/close", ['method' => 'cash'])
            ->assertForbidden();
    }
}
