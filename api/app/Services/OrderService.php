<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order creation + lifecycle (§18). Enforces:
 *  - restaurant must be able to operate (trial/active; not locked)
 *  - staff must be ON DUTY to create orders (owners exempt)
 *  - items must be this restaurant's sellable products
 *  - dine-in attaches to (and auto-opens) a table session
 *  - status transitions follow the allowed map and are logged
 */
class OrderService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly TableSessionService $sessions,
    ) {
    }

    /**
     * @param  array{items: array<int, array{product_id:int,quantity:int,note?:string}>, type:string, table_id?:?int, discount?:?float, tax?:?float, customer_name?:?string, customer_phone?:?string, note?:?string, source?:?string}  $payload
     */
    public function createOrder(User $user, Restaurant $restaurant, array $payload): Order
    {
        // Subscription gate: locked restaurants can't sell.
        if ($restaurant->needsSubscription()) {
            throw ValidationException::withMessages([
                'restaurant' => ['Your subscription has ended. Please subscribe to continue.'],
            ]);
        }

        // Duty gate: only on-duty staff (or owners) may take orders.
        $this->attendance->assertCanCreateOrders($user);

        $type = OrderType::from($payload['type']);
        $table = null;
        $session = null;

        if ($type === OrderType::DineIn) {
            $tableId = $payload['table_id'] ?? null;
            if (! $tableId) {
                throw ValidationException::withMessages(['table_id' => ['Select a table for dine-in.']]);
            }

            $table = RestaurantTable::find($tableId);
            if (! $table || $table->restaurant_id !== $restaurant->id) {
                throw ValidationException::withMessages(['table_id' => ['Invalid table.']]);
            }

            $session = $this->sessions->openForTable($table) ?? $this->sessions->openSession($table, $user);
        }

        $items = $this->resolveItems($restaurant, $payload['items']);
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => ['Order must contain at least one item.']]);
        }

        $subtotal = round(array_sum(array_map(fn ($i) => $i['line_total'], $items)), 2);
        $discount = round((float) ($payload['discount'] ?? 0), 2);
        $tax = round((float) ($payload['tax'] ?? 0), 2);
        $total = round($subtotal - $discount + $tax, 2);

        if ($total < 0) {
            throw ValidationException::withMessages(['discount' => ['Discount cannot exceed the subtotal.']]);
        }

        return DB::transaction(function () use ($user, $restaurant, $payload, $type, $table, $session, $items, $subtotal, $discount, $tax, $total): Order {
            // Sequential human order number (per restaurant).
            $restaurant = Restaurant::query()->whereKey($restaurant->id)->lockForUpdate()->first();
            $restaurant->increment('last_order_no');
            $orderNo = '#'.(1000 + $restaurant->last_order_no);

            $order = Order::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table?->id,
                'table_session_id' => $session?->id,
                'staff_id' => $user->id,
                'order_no' => $orderNo,
                'type' => $type,
                'status' => OrderStatus::New,
                'source' => $payload['source'] ?? 'pos',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'note' => $payload['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                    'note' => $item['note'] ?? null,
                ]);
            }

            $this->logStatus($order, null, OrderStatus::New, $user, 'order_created');

            return $order->load(['items', 'table', 'tableSession', 'staff:id,name']);
        });
    }

    /**
     * Transition an order status along the allowed map; logs history.
     */
    public function transition(Order $order, User $user, OrderStatus $to, ?string $reason = null): Order
    {
        $from = $order->status;

        if (! in_array($to, $from->allowedNext(), true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot move an order from {$from->label()} to {$to->label()}."],
            ]);
        }

        $data = ['status' => $to];

        if ($to === OrderStatus::Completed) {
            $data['completed_at'] = now();
        }
        if ($to === OrderStatus::Cancelled) {
            $data['cancelled_at'] = now();
        }

        $order->update($data);
        $this->logStatus($order, $from, $to, $user, $reason ?? ('order_'.$to->value));

        return $order->fresh();
    }

    public function logStatus(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $user, ?string $reason): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $user?->id,
            'reason' => $reason,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Internals                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array{product_id:int,name:string,unit_price:string,quantity:int,line_total:float,note?:?string}>
     */
    private function resolveItems(Restaurant $restaurant, array $rawItems): array
    {
        $productIds = collect($rawItems)->pluck('product_id')->unique()->all();

        $products = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', $productIds)
            ->sellable()
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages([
                'items' => ['One or more items are not available on your menu.'],
            ]);
        }

        $items = [];
        foreach ($rawItems as $raw) {
            $qty = max(1, (int) ($raw['quantity'] ?? 1));
            $product = $products->get($raw['product_id']);
            $unit = (float) $product->price;

            $items[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'line_total' => round($unit * $qty, 2),
                'note' => $raw['note'] ?? null,
            ];
        }

        return $items;
    }
}
