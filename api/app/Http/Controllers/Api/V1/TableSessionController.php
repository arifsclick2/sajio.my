<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\PaymentService;
use App\Services\TableSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Table sessions + tag scanning (staff/cashier workflow, §13).
 */
class TableSessionController extends Controller
{
    public function __construct(
        private readonly TableSessionService $sessions,
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
     * Open a session for a table.
     */
    public function open(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $table = RestaurantTable::find($validated['table_id']);
        if ($table->restaurant_id !== $restaurant->id) {
            throw ValidationException::withMessages(['table_id' => ['Invalid table.']]);
        }

        $session = $this->sessions->openSession($table, $user);

        return response()->json(['session' => $this->shape($session)], 201);
    }

    /**
     * Current open session for a table (or null).
     */
    public function forTable(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your table.');
        }

        $session = $this->sessions->openForTable($table);

        return response()->json(['session' => $session ? $this->shape($session) : null]);
    }

    /**
     * Resolve a scanned tag token -> table + open session (cashier flow).
     */
    public function scanTag(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:20'],
        ]);

        $table = $this->sessions->tableFromTagToken($validated['token']);

        if (! $table || $table->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'Tag not found or not assigned.'], 404);
        }

        $session = $this->sessions->openForTable($table);

        return response()->json([
            'table' => ['id' => $table->id, 'number' => $table->number],
            'session' => $session ? $this->shape($session) : null,
        ]);
    }

    /**
     * Settle a session's bill (§13): records the payment, completes the
     * orders and closes the session — table becomes available.
     */
    public function close(Request $request, TableSession $session): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($session->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your session.');
        }

        $validated = $request->validate([
            'method' => ['sometimes', 'string', 'in:'.implode(',', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $result = $this->payments->settleSession(
            $session,
            $request->user(),
            isset($validated['method']) ? PaymentMethod::from($validated['method']) : null,
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            $validated['reference'] ?? null,
            $validated['note'] ?? null,
        );

        return response()->json([
            'session' => $this->shape($result['session']),
            'payment' => $result['payment'] ? $this->shapePayment($result['payment']) : null,
            'orders' => array_map(fn (Order $order) => $this->shapeOrder($order), $result['orders']),
        ]);
    }

    /**
     * Open sessions across the restaurant (dashboard / floor view).
     */
    public function openSessions(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $sessions = $restaurant->tableSessions()
            ->open()
            ->with('table:id,number')
            ->latest('opened_at')
            ->get();

        return response()->json(['sessions' => $sessions->map(fn (TableSession $s) => $this->shape($s))]);
    }

    private function shape(TableSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'table' => $session->table ? ['id' => $session->table->id, 'number' => $session->table->number] : null,
            'opened_at' => $session->opened_at?->toISOString(),
            'closed_at' => $session->closed_at?->toISOString(),
            'opened_by' => $session->opened_by,
            'closed_by' => $session->closed_by,
            'total_amount' => $session->total_amount,
        ];
    }

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
            'table_id' => $order->table_id,
            'table_session_id' => $order->table_session_id,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'completed_at' => $order->completed_at?->toISOString(),
        ];
    }
}
