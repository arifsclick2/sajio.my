<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use App\Services\RestaurantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Customer QR ordering (§15) — no login, no app. A guest scans the table
 * QR, browses the public menu, and places an order straight into the
 * kitchen. The restaurant is always resolved from the table's public token
 * (never a client-supplied tenant id).
 *
 * Public menu is served only when the restaurant can operate and the plan
 * allows customer ordering (trial / Premium / Pro — not Basic).
 */
class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly RestaurantProfileService $profile,
    ) {
    }

    /** Resolve an active table by its public token and its restaurant gates. */
    private function resolveTable(Request $request, string $publicToken): RestaurantTable
    {
        $table = RestaurantTable::query()
            ->where('public_token', strtoupper(trim($publicToken)))
            ->active()
            ->with('restaurant')
            ->first();

        if (! $table) {
            abort(404, 'Table not found.');
        }

        $restaurant = $table->restaurant;

        if ($restaurant->needsSubscription() || ! $restaurant->canOperate()) {
            throw ValidationException::withMessages([
                'restaurant' => ['This restaurant is not accepting orders right now.'],
            ]);
        }

        if (! $restaurant->customerQrEnabled()) {
            throw ValidationException::withMessages([
                'restaurant' => ['Customer QR ordering is not available at this restaurant right now.'],
            ]);
        }

        return $table;
    }

    /**
     * GET /api/v1/public/table/{token}/menu
     * Public menu for a scanned table: restaurant identity + active menu.
     */
    public function menu(string $publicToken): JsonResponse
    {
        $table = RestaurantTable::query()
            ->where('public_token', strtoupper(trim($publicToken)))
            ->active()
            ->with('restaurant')
            ->first();

        if (! $table) {
            abort(404, 'Table not found.');
        }

        $restaurant = $table->restaurant;

        if ($restaurant->needsSubscription() || ! $restaurant->canOperate()) {
            abort(403, 'This restaurant is not accepting orders right now.');
        }

        if (! $restaurant->customerQrEnabled()) {
            abort(403, 'Customer QR ordering is not available at this restaurant right now.');
        }

        $branding = $this->profile->branding($restaurant);

        $categories = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with(['products' => fn ($q) => $q
                ->where('is_active', true)
                ->where('available', true)
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'products' => $c->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'price' => $p->price,
                    'image_url' => $p->image_url,
                ]),
            ])
            ->filter(fn ($c) => count($c['products']) > 0)
            ->values();

        return response()->json([
            'table' => ['id' => $table->id, 'number' => $table->number],
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'subdomain' => $restaurant->subdomain,
                'brand_color' => $branding->brand_color,
                'logo_url' => $branding->logo_url,
            ],
            'currency' => 'RM',
            'categories' => $categories,
        ]);
    }

    /**
     * POST /api/v1/public/table/{token}/orders
     * Guest places an order on a table.
     */
    public function placeOrder(Request $request, string $publicToken): JsonResponse
    {
        $table = $this->resolveTable($request, $publicToken);
        $restaurant = $table->restaurant;

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', Rule::in(['customer_qr'])],
        ]);

        $order = $this->orders->createCustomerOrder($restaurant, $table, $validated);

        return response()->json([
            'message' => "Order {$order->order_no} received — please pay at the counter.",
            'order' => $this->shape($order),
        ], 201);
    }

    private function shape(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type->value,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'table' => $order->table ? ['id' => $order->table->id, 'number' => $order->table->number] : null,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit_price' => $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => $i->line_total,
            ]),
        ];
    }
}
