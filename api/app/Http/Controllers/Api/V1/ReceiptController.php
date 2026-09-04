<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\TableSession;
use App\Services\RestaurantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Receipt data for browser printing (§22). Two kinds:
 *  - order receipt:   takeaway / counter (single order + its payments)
 *  - session receipt: dine-in bill (closed session + completed orders +
 *                     the session payment) — the cashier's printout.
 *
 * V1 receipts are rendered client-side from this JSON (restaurant branding,
 * items, discounts/tax, payment method, footer).
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly RestaurantProfileService $profile,
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

    /* ------------------------------------------------------------------ */
    /*  Order receipt                                                      */
    /* ------------------------------------------------------------------ */

    public function orderReceipt(Request $request, Order $order): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($order->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your order.');
        }

        $order->load(['items', 'table:id,number', 'payments']);

        $payments = $order->payments->map(fn (Payment $p) => $this->paymentBlock($p));

        return response()->json([
            'receipt' => [
                'type' => 'order',
                'restaurant' => $this->restaurantBlock($restaurant),
                'order' => $this->orderBlock($order),
                'table' => $order->table ? ['id' => $order->table->id, 'number' => $order->table->number] : null,
                'payments' => $payments,
                'total_paid' => number_format((float) $order->payments->sum('amount'), 2, '.', ''),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Session (dine-in bill) receipt                                     */
    /* ------------------------------------------------------------------ */

    public function sessionReceipt(Request $request, TableSession $session): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($session->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your session.');
        }

        // Receipt lists what the customer actually paid for: completed orders.
        $orders = $session->orders()
            ->where('status', 'completed')
            ->with('items')
            ->orderBy('id')
            ->get();

        $payments = $session->payments()->get();

        return response()->json([
            'receipt' => [
                'type' => 'session',
                'restaurant' => $this->restaurantBlock($restaurant),
                'session' => [
                    'id' => $session->id,
                    'table' => $session->table ? ['id' => $session->table->id, 'number' => $session->table->number] : null,
                    'opened_at' => $session->opened_at?->toISOString(),
                    'closed_at' => $session->closed_at?->toISOString(),
                    'status' => $session->status,
                    'total_amount' => $session->total_amount,
                ],
                'orders' => $orders->map(fn (Order $o) => $this->orderBlock($o)),
                'payments' => $payments->map(fn (Payment $p) => $this->paymentBlock($p)),
                'total_paid' => number_format((float) $payments->sum('amount'), 2, '.', ''),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Blocks                                                             */
    /* ------------------------------------------------------------------ */

    private function restaurantBlock(Restaurant $restaurant): array
    {
        $settings = $this->profile->settings($restaurant);
        $branding = $this->profile->branding($restaurant);

        return [
            'name' => $restaurant->name,
            'subdomain' => $restaurant->subdomain,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'address' => $settings->address,
            'city' => $settings->city,
            'state' => $settings->state,
            'postcode' => $settings->postcode,
            'brand_color' => $branding->brand_color,
            'logo_url' => $branding->logo_url,
            'receipt_header' => $branding->receipt_header,
            'receipt_footer' => $branding->receipt_footer,
        ];
    }

    private function orderBlock(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type->value,
            'type_label' => $order->type->label(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'created_at' => $order->created_at?->toISOString(),
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->name,
                'unit_price' => $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => $i->line_total,
                'note' => $i->note,
            ]),
        ];
    }

    private function paymentBlock(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'paid_at' => $payment->paid_at?->toISOString(),
            'received_by' => $payment->received_by,
        ];
    }
}
