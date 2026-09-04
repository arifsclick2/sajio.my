<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\TableSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 6 — Money & reports (§19-21): expenses (+categories), money
 * dashboard, summary and daily/weekly/monthly/product/category/staff/hourly
 * reports. All money = NUMERIC strings; sales = recorded payments; reports
 * are tenant-isolated and timezone-corrected to the restaurant's tz.
 */
class MoneyReportsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->onTrial()->create(['timezone' => 'UTC']);
    }

    private function owner(Restaurant $r): User
    {
        $u = User::factory()->restaurant($r)->owner()->create();
        Sanctum::actingAs($u);

        return $u;
    }

    private function staff(Restaurant $r): User
    {
        return User::factory()->restaurant($r)->role(\App\Enums\UserRole::Staff)->create();
    }

    private function makeOrder(
        Restaurant $r,
        string $status,
        float $total,
        float $subtotal = 0,
        float $discount = 0,
        float $tax = 0,
        ?string $when = null,
        ?User $staff = null,
        ?RestaurantTable $table = null,
    ): Order {
        $this->seq++;
        $order = Order::query()->create([
            'restaurant_id' => $r->id,
            'table_id' => $table?->id,
            'staff_id' => $staff?->id ?? $r->users()->first()?->id ?? User::factory()->restaurant($r)->owner()->create()->id,
            'order_no' => '#M'.(1000 + $this->seq),
            'type' => OrderType::DineIn,
            'status' => $status,
            'source' => 'pos',
            'subtotal' => $subtotal !== 0.0 ? $subtotal : $total,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ]);

        if ($when) {
            $at = Carbon::parse($when, 'UTC');
            $order->forceFill(['created_at' => $at]);
            if ($status === 'completed') {
                $order->forceFill(['completed_at' => $at]);
            }
            if ($status === 'cancelled') {
                $order->forceFill(['cancelled_at' => $at]);
            }
            $order->save();
        }

        return $order;
    }

    private function addItem(Order $order, Product $product, int $qty, float $unitPrice): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
        ]);
    }

    private function pay(Restaurant $r, float $amount, string $method = 'cash', ?string $when = null, ?Order $order = null, ?TableSession $session = null): Payment
    {
        return Payment::query()->create([
            'restaurant_id' => $r->id,
            'order_id' => $order?->id,
            'table_session_id' => $session?->id,
            'method' => $method,
            'amount' => $amount,
            'paid_at' => Carbon::parse($when ?? now(), 'UTC'),
        ]);
    }

    private function product(Restaurant $r, string $name, float $price, ?Category $cat = null): Product
    {
        $cat ??= Category::query()->create(['restaurant_id' => $r->id, 'name' => 'Makanan']);

        return Product::query()->create([
            'restaurant_id' => $r->id,
            'category_id' => $cat->id,
            'name' => $name,
            'price' => $price,
        ]);
    }

    private function category(Restaurant $r, string $name = 'Belanja', float $amount = 88.0, string $date = '2026-09-03'): Expense
    {
        $cat = ExpenseCategory::query()->create(['restaurant_id' => $r->id, 'name' => $name]);

        return Expense::query()->create([
            'restaurant_id' => $r->id,
            'category_id' => $cat->id,
            'description' => 'Test '.$name,
            'amount' => $amount,
            'expense_date' => $date,
            'payment_method' => 'cash',
        ]);
    }

    private function subscribeTo(Restaurant $r, string $slug): void
    {
        $package = Package::query()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'description' => 'Test',
            'price_monthly' => 100.0,
            'stripe_price_id' => 'price_'.$slug,
            'is_active' => true,
        ]);
        if ($slug === 'basic') {
            $package->limits()->create(['advanced_reports' => false]);
        }
        if ($slug === 'premium') {
            $package->limits()->create(['advanced_reports' => true]);
        }
        Subscription::query()->create([
            'restaurant_id' => $r->id,
            'type' => 'main',
            'stripe_id' => 'sub_'.$slug,
            'stripe_status' => 'active',
            'stripe_price' => $package->stripe_price_id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Expenses + categories (§19)                                        */
    /* ------------------------------------------------------------------ */

    public function test_owner_creates_and_lists_expenses_with_summary(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $cat = $this->postJson('/api/v1/expense-categories', ['name' => 'Ingredients'])
            ->assertCreated()->json('category');
        $this->assertSame('Ingredients', $cat['name']);

        $this->postJson('/api/v1/expenses', [
            'category_id' => $cat['id'],
            'description' => 'Chicken 10kg',
            'amount' => 88.50,
            'expense_date' => '2026-09-03',
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->postJson('/api/v1/expenses', [
            'description' => 'Tablecloth (no category)',
            'amount' => 12.00,
            'expense_date' => '2026-09-04',
            'payment_method' => 'qr',
        ])->assertCreated();

        $resp = $this->getJson('/api/v1/expenses?from=2026-09-03&to=2026-09-04')
            ->assertOk()->json();

        $this->assertSame(2, $resp['summary']['count']);
        $this->assertSame('100.50', $resp['summary']['total_amount']);
        $byCat = collect($resp['summary']['by_category'])->keyBy('category');
        $this->assertSame('88.50', $byCat['Ingredients']['amount']);
        $this->assertSame('12.00', $byCat['Uncategorised']['amount']);
        $this->assertSame(2, count($resp['data']));
    }

    public function test_staff_cannot_manage_expenses(): void
    {
        $r = $this->restaurant();
        $s = $this->staff($r);
        Sanctum::actingAs($s);

        $this->postJson('/api/v1/expenses', [
            'description' => 'nope', 'amount' => 5, 'expense_date' => '2026-09-04',
        ])->assertForbidden();
        $this->getJson('/api/v1/expense-categories')->assertForbidden();
    }

    public function test_expenses_are_tenant_isolated_and_soft_deleted(): void
    {
        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        $o1 = $this->owner($r1);
        $expense = $this->category($r2, 'Other Shop', 500.0, '2026-09-02');

        // r1 cannot touch r2's expense.
        $this->putJson("/api/v1/expenses/{$expense->id}", ['description' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertForbidden();

        // r1's own expense soft-deletes and disappears from listing.
        $own = $this->category($r1, 'Mine', 30.0, '2026-09-02');
        $this->deleteJson("/api/v1/expenses/{$own->id}")->assertOk();
        $this->assertNotNull(Expense::withTrashed()->find($own->id));
        $this->assertSame(0, $this->getJson('/api/v1/expenses')->json('summary')['count']);
    }

    /* ------------------------------------------------------------------ */
    /*  20. Money dashboard                                                */
    /* ------------------------------------------------------------------ */

    public function test_dashboard_shows_today_money_live_and_trend(): void
    {
        $r = $this->restaurant();
        $this->owner($r);
        $table = RestaurantTable::query()->create([
            'restaurant_id' => $r->id,
            'number' => '1',
            'capacity' => 4,
            'public_token' => RestaurantTable::generateToken(),
        ]);
        TableSession::query()->create([
            'restaurant_id' => $r->id,
            'table_id' => $table->id,
            'status' => TableSession::OPEN,
            'opened_at' => Carbon::parse('2026-09-04 08:00', 'UTC'),
        ]);

        $done = $this->makeOrder($r, 'completed', 15.0, when: '2026-09-04 10:15');
        $this->pay($r, 15.0, 'cash', '2026-09-04 10:15', $done);
        $this->pay($r, 4.5, 'qr', '2026-09-04 11:00');
        $this->makeOrder($r, 'new', 12.0, when: '2026-09-04 11:30');      // pending
        $this->pay($r, 20.0, 'card', '2026-09-03 12:00');                 // yesterday
        $this->category($r, 'Rent', 10.0, '2026-09-04');

        $resp = $this->getJson('/api/v1/reports/dashboard?date=2026-09-04')->assertOk()->json();

        $this->assertSame('19.50', $resp['today']['sales']);
        $this->assertSame(2, $resp['today']['payments_count']);
        $this->assertSame('10.00', $resp['today']['expenses']);
        $this->assertSame('9.50', $resp['net_position']);
        $this->assertSame(1, $resp['live']['active_tables']);
        $this->assertSame(1, $resp['live']['pending_orders']);
        $this->assertCount(7, $resp['trend']);
        $this->assertSame('20.00', collect($resp['trend'])->firstWhere('date', '2026-09-03')['sales']);
        // method breakdown for today only
        $methods = collect($resp['payment_breakdown'])->keyBy('method');
        $this->assertSame('15.00', $methods['cash']['amount']);
        $this->assertSame('4.50', $methods['qr']['amount']);
        $this->assertArrayNotHasKey('card', $methods->all());
        // recent orders present
        $this->assertGreaterThanOrEqual(2, count($resp['recent_orders']));
    }

    /* ------------------------------------------------------------------ */
    /*  19 + 21. Summary & reports                                         */
    /* ------------------------------------------------------------------ */

    public function test_summary_computes_sales_orders_expenses_net_position(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $o1 = $this->makeOrder($r, 'completed', 27.5, subtotal: 30.0, discount: 2.5, tax: 0.0, when: '2026-09-04 10:00');
        $this->pay($r, 27.5, 'cash', '2026-09-04 10:00', $o1);
        $this->makeOrder($r, 'cancelled', 99.0, when: '2026-09-04 10:30');
        $this->category($r, 'Ingredients', 10.0, '2026-09-04');
        $this->category($r, 'Utilities', 5.5, '2026-09-05');
        // outside range
        $this->pay($r, 100.0, 'card', '2026-08-01');

        $resp = $this->getJson('/api/v1/reports/summary?from=2026-09-01&to=2026-09-30')->assertOk()->json();

        $this->assertSame('27.50', $resp['sales']['total']);
        $this->assertSame(1, $resp['sales']['payments_count']);
        $this->assertSame('27.50', $resp['sales']['by_method'][0]['amount']);
        $this->assertSame(1, $resp['orders']['completed_count']);
        $this->assertSame('30.00', $resp['orders']['gross']);
        $this->assertSame('2.50', $resp['orders']['discounts']);
        $this->assertSame('27.50', $resp['orders']['net']);
        $this->assertSame('15.50', $resp['expenses']['total']);
        $this->assertSame('12.00', $resp['net_position']);
    }

    public function test_daily_sales_series_buckets_by_day(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $this->pay($r, 10.0, 'cash', '2026-09-01 09:00');
        $this->pay($r, 5.5, 'qr', '2026-09-01 14:00');
        $this->pay($r, 20.0, 'cash', '2026-09-03 09:00');

        $resp = $this->getJson('/api/v1/reports/sales?period=daily&from=2026-09-01&to=2026-09-03')->assertOk()->json();
        $series = collect($resp['series']);
        $this->assertCount(3, $series);
        $this->assertSame('15.50', $series[0]['sales']);
        $this->assertSame(2, $series[0]['payments']);
        $this->assertSame('0.00', $series[1]['sales']);
        $this->assertSame('20.00', $series[2]['sales']);
    }

    public function test_monthly_sales_series_rolls_up_months(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $this->pay($r, 100.0, 'cash', '2026-08-05 09:00');
        $this->pay($r, 50.0, 'cash', '2026-09-05 09:00');

        $resp = $this->getJson('/api/v1/reports/sales?period=monthly&from=2026-08-01&to=2026-09-30')->assertOk()->json();
        $series = collect($resp['series']);
        $this->assertCount(2, $series);
        $this->assertSame('Aug 2026', $series[0]['label']);
        $this->assertSame('100.00', $series[0]['sales']);
        $this->assertSame('Sep 2026', $series[1]['label']);
        $this->assertSame('50.00', $series[1]['sales']);
    }

    public function test_product_and_category_reports_use_completed_orders_only(): void
    {
        $r = $this->restaurant();
        $this->owner($r);
        $catNasi = Category::query()->create(['restaurant_id' => $r->id, 'name' => 'Nasi']);
        $nasi = $this->product($r, 'Nasi Lemak', 12.5, $catNasi);
        $teh = $this->product($r, 'Teh Tarik', 2.5);

        $done = $this->makeOrder($r, 'completed', 27.5, subtotal: 27.5, when: '2026-09-04 10:00');
        $this->addItem($done, $nasi, 2, 12.5);
        $this->addItem($done, $teh, 1, 2.5);

        $cancelled = $this->makeOrder($r, 'cancelled', 12.5, subtotal: 12.5, when: '2026-09-04 10:30');
        $this->addItem($cancelled, $nasi, 1, 12.5);

        $products = collect($this->getJson('/api/v1/reports/products?from=2026-09-01&to=2026-09-30')->json('products'));
        $this->assertSame('Nasi Lemak', $products->first()['name']);
        $this->assertSame(2, $products->first()['quantity']);
        $this->assertSame('25.00', $products->first()['revenue']);

        $cats = collect($this->getJson('/api/v1/reports/categories?from=2026-09-01&to=2026-09-30')->json('categories'))
            ->keyBy('category');
        $this->assertSame('25.00', $cats['Nasi']['revenue']);
        $this->assertSame('2.50', $cats['Makanan']['revenue']);
    }

    public function test_staff_report_lists_staff_and_anonymous_qr_orders(): void
    {
        $r = $this->restaurant();
        $waiter = $this->staff($r);
        $this->owner($r);

        $w = $this->makeOrder($r, 'completed', 15.0, when: '2026-09-04 10:00', staff: $waiter);
        $this->pay($r, 15.0, 'cash', '2026-09-04 10:00', $w);

        // anonymous (customer QR) completed order
        $anon = $this->makeOrder($r, 'completed', 8.0, when: '2026-09-04 11:00');
        $anon->forceFill(['staff_id' => null])->save();
        $this->pay($r, 8.0, 'qr', '2026-09-04 11:00', $anon);

        $staff = collect($this->getJson('/api/v1/reports/staff?from=2026-09-01&to=2026-09-30')->json('staff'))
            ->keyBy('name');
        $this->assertSame('15.00', $staff[$waiter->name]['total']);
        $this->assertSame(1, $staff[$waiter->name]['orders']);
        $this->assertSame('8.00', $staff['Walk-in / QR']['total']);
    }

    public function test_advanced_reports_gated_to_premium_pro_and_trial(): void
    {
        // Trial (no package) → enabled.
        $r = $this->restaurant();
        $this->owner($r);
        $this->getJson('/api/v1/reports/staff')->assertOk();
        $this->getJson('/api/v1/reports/hours')->assertOk();
        $this->getJson('/api/v1/reports/advanced')->assertOk();

        // Basic → blocked for advanced; basic reports still fine.
        $r2 = $this->restaurant();
        $this->subscribeTo($r2, 'basic');
        $this->owner($r2);
        $this->getJson('/api/v1/reports/staff')->assertForbidden();
        $this->getJson('/api/v1/reports/hours')->assertForbidden();
        $this->getJson('/api/v1/reports/advanced')->assertForbidden();
        $this->getJson('/api/v1/reports/summary')->assertOk();
        $this->getJson('/api/v1/reports/products')->assertOk();

        // Premium → enabled.
        $r3 = $this->restaurant();
        $this->subscribeTo($r3, 'premium');
        $this->owner($r3);
        $this->getJson('/api/v1/reports/staff')->assertOk();
    }

    public function test_hours_report_buckets_payments_by_hour(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $this->pay($r, 10.0, 'cash', '2026-09-04 09:15');
        $this->pay($r, 4.0, 'cash', '2026-09-04 09:45');
        $this->pay($r, 20.0, 'qr', '2026-09-04 21:05');

        $hours = collect($this->getJson('/api/v1/reports/hours?from=2026-09-04&to=2026-09-04')->json('hours'));
        $this->assertCount(24, $hours);
        $this->assertSame('14.00', $hours->firstWhere('hour', 9)['sales']);
        $this->assertSame(2, $hours->firstWhere('hour', 9)['count']);
        $this->assertSame('20.00', $hours->firstWhere('hour', 21)['sales']);
    }

    public function test_advanced_report_has_discount_void_and_profit(): void
    {
        $r = $this->restaurant();
        $this->owner($r);

        $disc = $this->makeOrder($r, 'completed', 20.0, subtotal: 22.0, discount: 2.0, when: '2026-09-04 10:00');
        $this->pay($r, 20.0, 'cash', '2026-09-04 10:00', $disc);
        $this->makeOrder($r, 'voided', 9.9, subtotal: 9.9, when: '2026-09-04 11:00');
        $this->category($r, 'Rent', 5.0, '2026-09-04');

        $resp = $this->getJson('/api/v1/reports/advanced?from=2026-09-01&to=2026-09-30')->assertOk()->json();

        $this->assertSame('2.00', $resp['discounts']['total_discount']);
        $this->assertSame(1, $resp['discounts']['discounted_orders']);
        $this->assertSame(1, $resp['void_refunds']['count']);
        $this->assertSame('9.90', $resp['void_refunds']['amount']);
        $this->assertSame('20.00', $resp['net_sales']);
        $this->assertSame('5.00', $resp['total_expenses']);
        $this->assertSame('15.00', $resp['profit']);
    }
}
