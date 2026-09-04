<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording + sales history (§17, §19) — record-only; Sajio is not
 * a payment processor.
 *  - POST /orders/{order}/pay       → single (takeaway) order settlement
 *  - GET  /payments                 → recorded payments (POS sales history)
 *  - POST /sessions/{session}/close → dine-in session bill settlement
 *    (handled by TableSessionController, same service)
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
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
     * Recorded payments (sales history). Filters: date, method, q.
     * Returns a summary (total + per-method breakdown) for the filter,
     * so the POS can show today's money at a glance (§19).
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'method' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = Payment::query()
            ->forRestaurant($restaurant->id)
            ->when(! empty($validated['date']), fn ($q) => $q->whereDate('paid_at', $validated['date']))
            ->when(! empty($validated['method']), fn ($q) => $q->where('method', $validated['method']))
            ->when(! empty($validated['q']), fn ($q) => $q->where('reference', 'like', '%'.$validated['q'].'%'))
            ->orderByDesc('paid_at');

        $payments = $base->with('receivedBy:id,name')->paginate($validated['per_page'] ?? 30);

        $matched = clone $base;
        $rows = $matched->get();
        $summary = [
            'count' => $rows->count(),
            'total_amount' => number_format((float) $rows->sum('amount'), 2, '.', ''),
            'by_method' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
                'method' => $m->value,
                'method_label' => $m->label(),
                'count' => $rows->where('method', $m)->count(),
                'amount' => number_format((float) $rows->where('method', $m)->sum('amount'), 2, '.', ''),
            ])->filter(fn ($row) => $row['count'] > 0)->values(),
        ];

        return response()->json([
            'data' => collect($payments->items())->map(fn (Payment $p) => $this->shapePayment($p)),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Record a payment and complete a single order (takeaway / counter).
     */
    public function payOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->restaurantOf($request);

        if ($order->restaurant_id !== $restaurant->id) {
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
