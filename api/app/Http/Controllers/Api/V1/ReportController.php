<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\TableSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Money dashboard + reports (§19–21).
 *
 * Money basis: a sale is recorded the moment a payment is taken (record-only,
 * §17) and the order is marked completed. So:
 *   - "Sales" = payments (paid_at, in the restaurant's local timezone)
 *   - Completed orders give gross / discount / tax / net + counts
 *   - Expenses are money-out rows; `Sales - Expenses = Net Position`
 *
 * Everything is tenant-isolated via restaurant_id and timezone-corrected to
 * the restaurant's own local day (Carbon, portable across DBs).
 */
class ReportController extends Controller
{
    /** Order statuses that still need to be served/paid. */
    private const PENDING = ['new', 'preparing', 'ready', 'served'];

    private const TERMINAL = ['completed', 'cancelled', 'voided'];

    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    private function tz(Restaurant $restaurant): string
    {
        return $restaurant->timezone ?: 'UTC';
    }

    /** Parse a Y-m-d into start-of-day in the restaurant's timezone. */
    private function dayStart(string $date, string $tz): Carbon
    {
        return Carbon::parse($date, $tz)->startOfDay();
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /* ------------------------------------------------------------------ */
    /*  20. Dashboard — today at a glance                                  */
    /* ------------------------------------------------------------------ */

    public function dashboard(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $tz = $this->tz($restaurant);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? Carbon::now($tz)->format('Y-m-d');

        $dayStart = $this->dayStart($date, $tz);
        $dayEnd = $dayStart->copy()->addDay();
        $from = $dayStart->copy()->utc();
        $to = $dayEnd->copy()->utc();

        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'method']);

        $orders = Order::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->get(['status', 'total', 'subtotal', 'discount', 'tax']);

        $soldOrders = $orders->filter(fn (Order $o) => ! in_array($o->status->value, ['cancelled', 'voided'], true));

        $todaySales = (float) $payments->sum('amount');

        $byMethod = collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $m) => [
                'method' => $m->value,
                'method_label' => $m->label(),
                'count' => $payments->where('method', $m->value)->count(),
                'amount' => $this->money((float) $payments->where('method', $m->value)->sum('amount')),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();

        // Active tables = currently open sessions.
        $activeTables = TableSession::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', TableSession::OPEN)
            ->count();

        $pendingOrders = Order::query()
            ->forRestaurant($restaurant->id)
            ->whereIn('status', self::PENDING)
            ->count();

        $recentOrders = Order::query()
            ->forRestaurant($restaurant->id)
            ->with(['table:id,number', 'staff:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'type' => $o->type->value,
                'table' => $o->table ? ['id' => $o->table->id, 'number' => $o->table->number] : null,
                'staff' => $o->staff ? ['id' => $o->staff->id, 'name' => $o->staff->name] : null,
                'total' => $o->total,
                'created_at' => $o->created_at?->toISOString(),
            ])
            ->all();

        // Sales trend: last 7 local days ending at the requested day.
        $trend = $this->dailyTrend(
            $restaurant,
            $dayStart,
            $dayStart->copy()->subDays(6)->utc(),
            $dayEnd->copy()->utc(),
        );

        $todayExpenses = Expense::query()
            ->forRestaurant($restaurant->id)
            ->whereDate('expense_date', $date)
            ->get(['amount']);

        return response()->json([
            'date' => $date,
            'today' => [
                'sales' => $this->money($todaySales),
                'payments_count' => $payments->count(),
                'orders_count' => $soldOrders->count(),
                'completed_count' => $soldOrders->filter(fn (Order $o) => $o->status->value === 'completed')->count(),
                'expenses' => $this->money((float) $todayExpenses->sum('amount')),
                'expenses_count' => $todayExpenses->count(),
            ],
            'net_position' => $this->money($todaySales - (float) $todayExpenses->sum('amount')),
            'live' => [
                'active_tables' => $activeTables,
                'pending_orders' => $pendingOrders,
            ],
            'payment_breakdown' => $byMethod,
            'recent_orders' => $recentOrders,
            'trend' => $trend,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  19 + 21. Money summary & reports (date ranges)                     */
    /* ------------------------------------------------------------------ */

    /**
     * Basic money summary for a range (default: last 7 days):
     * sales (payments), order stats from completed orders, payment
     * breakdown, expenses and Net Position = Sales - Expenses (§19).
     */
    public function summary(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        [$from, $to, $fromLocal, $toLocalExcl] = $this->range($request, $restaurant);

        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'method']);

        $completed = Order::query()
            ->forRestaurant($restaurant->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->get(['subtotal', 'discount', 'tax', 'total']);

        $expenses = Expense::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('expense_date', [$fromLocal->format('Y-m-d'), $toLocalExcl->copy()->subDay()->format('Y-m-d')])
            ->get(['amount']);

        $sales = (float) $payments->sum('amount');
        $expensesTotal = (float) $expenses->sum('amount');

        return response()->json([
            'range' => ['from' => $fromLocal->format('Y-m-d'), 'to' => $toLocalExcl->copy()->subDay()->format('Y-m-d')],
            'sales' => [
                'total' => $this->money($sales),
                'payments_count' => $payments->count(),
                'by_method' => $this->methodBreakdown($payments),
            ],
            'orders' => [
                'completed_count' => $completed->count(),
                'gross' => $this->money((float) $completed->sum('subtotal')),
                'discounts' => $this->money((float) $completed->sum('discount')),
                'tax' => $this->money((float) $completed->sum('tax')),
                'net' => $this->money((float) $completed->sum('total')),
            ],
            'expenses' => [
                'total' => $this->money($expensesTotal),
                'count' => $expenses->count(),
            ],
            'net_position' => $this->money($sales - $expensesTotal),
        ]);
    }

    /**
     * Sales series for daily / weekly / monthly periods (§21).
     */
    public function sales(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $validated = $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $period = $validated['period'] ?? 'daily';

        [$from, $to, $fromLocal, $toLocalExcl] = $this->range($request, $restaurant);
        $buckets = $this->buckets($period, $fromLocal, $toLocalExcl);

        // Fetch rows once, group in PHP by local-day bucket.
        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['paid_at', 'amount']);

        $orders = Order::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'status']);

        $paymentByBucket = $this->groupRows($payments, 'paid_at', $buckets, $this->tz($restaurant));
        $orderByBucket = $this->groupRows(
            $orders->filter(fn (Order $o) => ! in_array($o->status->value, ['cancelled', 'voided'], true)),
            'created_at',
            $buckets,
            $this->tz($restaurant),
        );

        $series = [];
        foreach ($buckets as $bucket) {
            $key = $bucket['key'];
            $series[] = [
                'label' => $bucket['label'],
                'sales' => $this->money($paymentByBucket[$key]['sales'] ?? 0.0),
                'payments' => $paymentByBucket[$key]['count'] ?? 0,
                'orders' => $orderByBucket[$key]['count'] ?? 0,
            ];
        }

        return response()->json([
            'period' => $period,
            'range' => ['from' => $fromLocal->format('Y-m-d'), 'to' => $toLocalExcl->copy()->subDay()->format('Y-m-d')],
            'series' => $series,
        ]);
    }

    /**
     * Product sales (§21): revenue + quantity from completed order items.
     */
    public function products(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        [$from, $to] = $this->range($request, $restaurant);

        $items = $this->completedItems($restaurant, $from, $to);

        $grouped = $items->groupBy(function ($row) {
            $key = $row['product_id'] ?? 'deleted';
            $name = $row['name'];

            return $key === 'deleted' ? "deleted:{$name}" : "product:{$key}";
        });

        $products = $grouped->map(function (Collection $rows) {
            $row = $rows->first();

            return [
                'name' => $row['name'],
                'product_id' => $row['product_id'],
                'quantity' => $rows->sum('quantity'),
                'revenue' => $this->money((float) $rows->sum('line_total')),
            ];
        })
            ->sortByDesc(fn (array $p) => (float) $p['revenue'])
            ->values()
            ->take(50)
            ->all();

        return response()->json(['products' => array_values($products)]);
    }

    /**
     * Category sales (§21): revenue grouped by the product's current category.
     */
    public function categories(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        [$from, $to] = $this->range($request, $restaurant);

        $items = $this->completedItems($restaurant, $from, $to);

        // Resolve each item's category through its product (current mapping).
        $productIds = $items->pluck('product_id')->filter()->unique()->values()->all();
        $categoryOf = [];
        if ($productIds) {
            $categoryOf = \App\Models\Product::query()
                ->whereIn('id', $productIds)
                ->with('category:id,name')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->category?->name ?? 'Uncategorised'])
                ->all();
        }

        $withCat = $items->map(function (array $row) use ($categoryOf) {
            $row['category'] = $row['product_id'] !== null ? ($categoryOf[$row['product_id']] ?? 'Uncategorised') : 'Uncategorised';

            return $row;
        });

        $grouped = $withCat->groupBy('category');
        $categories = $grouped->map(fn (Collection $rows, string $name) => [
            'category' => $name,
            'quantity' => $rows->sum('quantity'),
            'revenue' => $this->money((float) $rows->sum('line_total')),
        ])
            ->sortByDesc(fn (array $c) => (float) $c['revenue'])
            ->values()
            ->all();

        return response()->json(['categories' => $categories]);
    }

    /**
     * Staff sales (§21 — advanced). Orders completed per staff member.
     */
    public function staff(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->assertAdvanced($restaurant);
        [$from, $to] = $this->range($request, $restaurant);

        $orders = Order::query()
            ->forRestaurant($restaurant->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->get(['staff_id', 'total']);

        $staffNames = [];
        $staffIds = $orders->pluck('staff_id')->filter()->unique()->values()->all();
        if ($staffIds) {
            $staffNames = \App\Models\User::query()->whereIn('id', $staffIds)->get(['id', 'name'])
                ->pluck('name', 'id')
                ->all();
        }

        $grouped = $orders->groupBy(fn (Order $o) => $o->staff_id ?? 'anonymous');
        $staff = $grouped->map(function (Collection $rows, string $key) use ($staffNames) {
            $id = $key === 'anonymous' ? null : (int) $key;
            $name = $id !== null ? ($staffNames[$id] ?? "Staff #{$id}") : 'Walk-in / QR';

            return [
                'staff_id' => $id,
                'name' => $name,
                'orders' => $rows->count(),
                'total' => $this->money((float) $rows->sum('total')),
            ];
        })
            ->sortByDesc(fn (array $s) => (float) $s['total'])
            ->values()
            ->all();

        return response()->json(['staff' => $staff]);
    }

    /**
     * Hourly sales (§21 — advanced): payments grouped by hour of the day.
     */
    public function hours(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->assertAdvanced($restaurant);
        $tz = $this->tz($restaurant);
        [$from, $to] = $this->range($request, $restaurant);

        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['paid_at', 'amount']);

        $hours = [];
        foreach (range(0, 23) as $hour) {
            $hours[$hour] = ['hour' => $hour, 'label' => sprintf('%02d:00', $hour), 'sales' => $this->money(0.0), 'count' => 0];
        }

        foreach ($payments as $payment) {
            $hour = $payment->paid_at->copy()->tz($tz)->hour;
            $hours[$hour]['sales'] = $this->money((float) $hours[$hour]['sales'] + (float) $payment->amount);
            $hours[$hour]['count']++;
        }

        return response()->json(['hours' => array_values($hours)]);
    }

    /**
     * Advanced summaries (§21): discount + void/refund + profit position.
     */
    public function advanced(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->assertAdvanced($restaurant);
        [$from, $to, $fromLocal, $toLocalExcl] = $this->range($request, $restaurant);

        $ordersInRange = Order::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['completed', 'cancelled', 'voided'])
            ->get(['status', 'discount', 'total']);

        $completed = $ordersInRange->filter(fn (Order $o) => $o->status->value === 'completed');
        $voided = $ordersInRange->filter(fn (Order $o) => in_array($o->status->value, ['cancelled', 'voided'], true));

        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount']);

        $expenses = Expense::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('expense_date', [$fromLocal->format('Y-m-d'), $toLocalExcl->copy()->subDay()->format('Y-m-d')])
            ->get(['amount']);

        $sales = (float) $payments->sum('amount');
        $expensesTotal = (float) $expenses->sum('amount');

        return response()->json([
            'range' => ['from' => $fromLocal->format('Y-m-d'), 'to' => $toLocalExcl->copy()->subDay()->format('Y-m-d')],
            'discounts' => [
                'total_discount' => $this->money((float) $ordersInRange->sum('discount')),
                'discounted_orders' => $completed
                    ->filter(fn (Order $o) => (float) $o->discount > 0)
                    ->count(),
            ],
            'void_refunds' => [
                'count' => $voided->count(),
                'amount' => $this->money((float) $voided->sum('total')),
            ],
            'completed_orders' => $completed->count(),
            'net_sales' => $this->money($sales),
            'total_expenses' => $this->money($expensesTotal),
            'profit' => $this->money($sales - $expensesTotal),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function assertAdvanced(Restaurant $restaurant): void
    {
        if (! $restaurant->advancedReportsEnabled()) {
            abort(403, 'Advanced reports are available on Premium and Pro plans.');
        }
    }

    /**
     * Resolve the from/to UTC window (plus local labels). Defaults to the
     * last 7 local days; capped at 93 days so reports stay snappy.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    private function range(Request $request, Restaurant $restaurant): array
    {
        $tz = $this->tz($restaurant);
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = Carbon::now($tz);
        $fromLocal = isset($validated['from'])
            ? $this->dayStart($validated['from'], $tz)
            : $today->copy()->startOfDay()->subDays(6);
        $toLocalExcl = isset($validated['to'])
            ? $this->dayStart($validated['to'], $tz)->addDay()
            : $today->copy()->startOfDay()->addDay();

        if ($toLocalExcl->lte($fromLocal)) {
            throw ValidationException::withMessages(['to' => ['"to" must be on or after "from".']]);
        }

        if ($fromLocal->diffInDays($toLocalExcl) > 94) {
            throw ValidationException::withMessages(['from' => ['Reports cover at most 93 days at a time.']]);
        }

        return [
            $fromLocal->copy()->utc(),
            $toLocalExcl->copy()->utc(),
            $fromLocal,
            $toLocalExcl,
        ];
    }

    /**
     * Build ordered day/week/month buckets between two local boundaries.
     *
     * @return array<int, array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    private function buckets(string $period, Carbon $fromLocal, Carbon $toLocalExcl): array
    {
        $groups = [];
        $cursor = $fromLocal->copy();
        while ($cursor->lt($toLocalExcl)) {
            $start = match ($period) {
                'weekly' => $cursor->copy()->startOfWeek(),
                'monthly' => $cursor->copy()->startOfMonth(),
                default => $cursor->copy(),
            };
            $key = $start->format('Y-m-d');
            if (! isset($groups[$key])) {
                $groups[$key] = ['start' => $start];
            }
            $cursor->addDay();
        }

        $result = [];
        foreach ($groups as $key => $group) {
            /** @var Carbon $start */
            $start = $group['start'];
            $end = match ($period) {
                'weekly' => $start->copy()->addWeek(),
                'monthly' => $start->copy()->addMonth(),
                default => $start->copy()->addDay(),
            };
            // Clamp to the requested range (partial first/last buckets).
            $bucketFrom = $start->lt($fromLocal) ? $fromLocal->copy() : $start->copy();
            $bucketTo = $end->gt($toLocalExcl) ? $toLocalExcl->copy() : $end->copy();

            $result[] = [
                'key' => $key,
                'label' => $this->bucketLabel($period, $bucketFrom, $bucketTo->copy()->subDay()),
                'start' => $bucketFrom,
                'end' => $bucketTo,
            ];
        }

        return $result;
    }

    private function bucketLabel(string $period, Carbon $from, Carbon $to): string
    {
        $fmt = 'j M';
        if ($period === 'monthly') {
            return $from->format('M Y');
        }
        if ($period === 'weekly') {
            return $from->format($fmt).' – '.$to->format($fmt);
        }

        return $from->format($fmt);
    }

    /**
     * Group rows by the local-day bucket they fall into (keyed by bucket key).
     *
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @param  array<int, array{key: string, label: string, start: Carbon, end: Carbon}>  $buckets
     * @return array<string, array{sales: float, count: int}>
     */
    private function groupRows(Collection $rows, string $column, array $buckets, string $zone): array
    {
        // Precompute index of buckets: day-keys across the full range.
        $dayKeyToBucket = [];
        foreach ($buckets as $bucket) {
            $cursor = $bucket['start']->copy()->startOfDay();
            $endDay = $bucket['end']->copy()->startOfDay();
            while ($cursor->lt($endDay)) {
                $dayKeyToBucket[$cursor->format('Y-m-d')] = $bucket['key'];
                $cursor->addDay();
            }
        }

        $out = [];
        foreach ($buckets as $bucket) {
            $out[$bucket['key']] = ['sales' => 0.0, 'count' => 0];
        }

        foreach ($rows as $row) {
            $date = $row->{$column};
            if (! $date) {
                continue;
            }
            $day = $date->copy()->tz($zone)->format('Y-m-d');
            $key = $dayKeyToBucket[$day] ?? null;
            if ($key === null) {
                continue;
            }
            $out[$key]['sales'] += (float) $row->amount;
            $out[$key]['count']++;
        }

        return $out;
    }

    /** Completed order line items within a UTC window (product + category context). */
    private function completedItems(Restaurant $restaurant, Carbon $from, Carbon $to): Collection
    {
        $rows = \Illuminate\Support\Facades\DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.restaurant_id', $restaurant->id)
            ->where('o.status', 'completed')
            ->whereBetween('o.completed_at', [$from, $to])
            ->select('oi.product_id', 'oi.name', 'oi.quantity', 'oi.line_total')
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => $r->product_id !== null ? (int) $r->product_id : null,
            'name' => $r->name,
            'quantity' => (int) $r->quantity,
            'line_total' => (float) $r->line_total,
        ]);
    }

    private function methodBreakdown(Collection $payments): array
    {
        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $m) => [
                'method' => $m->value,
                'method_label' => $m->label(),
                'count' => $payments->where('method', $m->value)->count(),
                'amount' => $this->money((float) $payments->where('method', $m->value)->sum('amount')),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /** Last 7 local days (ending at $lastDay) of payment sales. */
    private function dailyTrend(Restaurant $restaurant, Carbon $lastDay, Carbon $fromUtc, Carbon $toUtc): array
    {
        $payments = Payment::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->get(['paid_at', 'amount']);

        $orders = Order::query()
            ->forRestaurant($restaurant->id)
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->get(['created_at', 'status']);

        $tz = $this->tz($restaurant);
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $lastDay->copy()->subDays($i);
            $key = $day->format('Y-m-d');
            $trend[] = [
                'date' => $key,
                'label' => $day->format('D'),
                'sales' => $this->money(0.0),
                'orders' => 0,
            ];
        }
        $idx = collect($trend)->pluck('date')->flip();

        foreach ($payments as $payment) {
            $key = $payment->paid_at->copy()->tz($tz)->format('Y-m-d');
            $pos = $idx[$key] ?? null;
            if ($pos !== null) {
                $trend[$pos]['sales'] = $this->money((float) $trend[$pos]['sales'] + (float) $payment->amount);
            }
        }
        foreach ($orders->filter(fn (Order $o) => ! in_array($o->status->value, ['cancelled', 'voided'], true)) as $order) {
            $key = $order->created_at->copy()->tz($tz)->format('Y-m-d');
            $pos = $idx[$key] ?? null;
            if ($pos !== null) {
                $trend[$pos]['orders']++;
            }
        }

        return $trend;
    }
}
