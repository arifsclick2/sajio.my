<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording (§17) — record-only; Sajio is not a payment processor.
 *  - POST /orders/{order}/pay       → single (takeaway) order settlement
 *  - POST /sessions/{session}/close → dine-in session bill settlement
 *    (handled by TableSessionController, same service)
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * Record a payment and complete a single order (takeaway / counter).
     */
    public function payOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant || $order->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your order.');
        }

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->payments->payOrder(
            $order,
            $user,
            PaymentMethod::from($validated['method']),
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            $validated['reference'] ?? null,
            $validated['note'] ?? null,
        );

        return response()->json([
            'payment' => $this->shapePayment($payment),
            'order' => $this->shapeOrder($order->fresh(['items'])),
        ], 201);
    }

    /* ------------------------------------------------------------------ */
    /*  Shapes                                                             */
    /* ------------------------------------------------------------------ */

    private function shapePayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'table_session_id' => $payment->table_session_id,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'note' => $payment->note,
            'received_by' => $payment->received_by,
            'paid_at' => $payment->paid_at?->toISOString(),
        ];
    }

    private function shapeOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type->value,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'completed_at' => $order->completed_at?->toISOString(),
        ];
    }
}
