<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POS + staff ordering endpoints (all restaurant roles). §17-18.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {
    }

    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    /**
     * Create an order (POS / staff device).
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in([OrderType::DineIn->value, OrderType::Takeaway->value])],
            'table_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', Rule::in(['pos', 'staff', 'customer_qr'])],
        ]);

        $order = $this->orders->createOrder($request->user(), $restaurant, $validated);

        return response()->json([
            'message' => "Order {$order->order_no} diterima.",
            'order' => $this->shape($order),
        ], 201);
    }

    /**
     * List orders (floor view). Filters: status, type, date, q.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $query = Order::query()
            ->forRestaurant($restaurant->id)
            ->with(['table:id,number', 'staff:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->when($request->filled('q'), fn ($q) => $q->where('order_no', 'ilike', '%'.$request->input('q').'%'))
            ->orderByDesc('id');

        $orders = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => $this->shape($o)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($order->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your order.');
        }

        return response()->json([
            'order' => $this->shape($order->load(['items', 'table', 'tableSession', 'staff:id,name', 'statusHistory' => fn ($q) => $q->with('changedBy:id,name')->orderByDesc('id')])),
        ]);
    }

    /**
     * Move an order through its lifecycle (NEW→PREPARING→READY→SERVED→COMPLETED / CANCEL).
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($order->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your order.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, OrderStatus::cases()))],
            'reason' => ['nullable', 'string', 'max:100'],
        ]);

        $updated = $this->orders->transition(
            $order,
            $request->user(),
            OrderStatus::from($validated['status']),
            $validated['reason'] ?? null,
        );

        return response()->json([
            'message' => "Order {$order->order_no} kini: {$updated->status->label()}.",
            'order' => $this->shape($updated),
        ]);
    }

    /**
     * Orders currently in an open state for a table (current bill view).
     */
    public function forTable(Request $request, \App\Models\RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your table.');
        }

        $orders = Order::query()
            ->forRestaurant($restaurant->id)
            ->where('table_id', $table->id)
            ->whereIn('status', ['new', 'preparing', 'ready', 'served'])
            ->with('items')
            ->orderBy('id')
            ->get();

        $total = round($orders->sum(fn (Order $o) => (float) $o->total), 2);

        return response()->json([
            'table' => ['id' => $table->id, 'number' => $table->number],
            'orders' => $orders->map(fn (Order $o) => $this->shape($o)),
            'bill_total' => number_format($total, 2, '.', ''),
        ]);
    }

    private function shape(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type->value,
            'type_label' => $order->type->label(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'source' => $order->source,
            'table' => $order->table ? ['id' => $order->table->id, 'number' => $order->table->number] : null,
            'table_session_id' => $order->table_session_id,
            'staff' => $order->staff ? ['id' => $order->staff->id, 'name' => $order->staff->name] : null,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->name,
                'unit_price' => $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => $i->line_total,
                'note' => $i->note,
            ]) : null,
            'created_at' => $order->created_at?->toISOString(),
        ];
    }
}
