<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording (§17) — Sajio RECORDS payment methods; it is not a
 * payment processor. Two settlement paths:
 *
 *  - payOrder():      a single order's bill (takeaway / counter).
 *  - settleSession(): a dine-in table session's full bill (cashier flow
 *                     `Current Bill → Payment → Close Session`, §13).
 *
 * A payment always matches the amount due exactly; the order(s) settle to
 * COMPLETED and (for dine-in) the session closes so the table is free.
 */
class PaymentService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly TableSessionService $sessions,
        private readonly OrderService $orders,
    ) {
    }

    /**
     * Record payment for a single order and complete it (takeaway/counter).
     */
    public function payOrder(
        Order $order,
        User $user,
        PaymentMethod $method,
        ?float $amount = null,
        ?string $reference = null,
        ?string $note = null,
    ): Payment {
        $this->attendance->assertCanCreateOrders($user);

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Voided], true)) {
            throw ValidationException::withMessages([
                'order' => ['A cancelled or voided order cannot be paid.'],
            ]);
        }

        return DB::transaction(function () use ($order, $user, $method, $amount, $reference, $note): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            $alreadyPaid = round((float) $order->payments()->sum('amount'), 2);
            $due = round((float) $order->total - $alreadyPaid, 2);

            if ($due <= 0) {
                throw ValidationException::withMessages([
                    'order' => ['This order has already been fully paid.'],
                ]);
            }

            $this->assertAmountMatches($due, $amount, 'order');

            $payment = Payment::query()->create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'method' => $method,
                'amount' => $due,
                'reference' => $reference,
                'note' => $note,
                'received_by' => $user->id,
                'paid_at' => now(),
            ]);

            $this->orders->markPaid($order, $user, 'order_paid');

            return $payment->fresh();
        });
    }

    /**
     * Settle a dine-in session: record the payment, complete every billable
     * order and close the session. Works even on subscription-locked
     * restaurants — paying an existing bill must never strand a customer.
     *
     * @return array{session: TableSession, payment: ?Payment, orders: array<int, Order>}
     */
    public function settleSession(
        TableSession $session,
        User $user,
        ?PaymentMethod $method = null,
        ?float $amount = null,
        ?string $reference = null,
        ?string $note = null,
    ): array {
        $this->attendance->assertCanCreateOrders($user);

        return DB::transaction(function () use ($session, $user, $method, $amount, $reference, $note): array {
            $session = TableSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if (! $session || ! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => ['This session is already closed.'],
                ]);
            }

            $orders = $session->orders()
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Voided->value])
                ->lockForUpdate()
                ->get();

            $due = round((float) $orders->sum('total'), 2);

            if ($due > 0) {
                if (! $method) {
                    throw ValidationException::withMessages([
                        'method' => ['Select a payment method.'],
                    ]);
                }
                $this->assertAmountMatches($due, $amount, 'amount');
            }

            $payment = null;
            if ($due > 0) {
                $payment = Payment::query()->create([
                    'restaurant_id' => $session->restaurant_id,
                    'table_session_id' => $session->id,
                    'method' => $method,
                    'amount' => $due,
                    'reference' => $reference,
                    'note' => $note,
                    'received_by' => $user->id,
                    'paid_at' => now(),
                ]);
            }

            foreach ($orders as $order) {
                if (! $order->status->isTerminal()) {
                    $this->orders->markPaid($order, $user, 'session_settled');
                }
            }

            $closed = $this->sessions->closeSession($session, $user, $due);

            return [
                'session' => $closed->load('table:id,number'),
                'payment' => $payment,
                'orders' => $orders->map(fn (Order $o) => $o->fresh(['items']))->all(),
            ];
        });
    }

    /**
     * A recorded payment must equal the amount due (no partial/overpay rows).
     */
    private function assertAmountMatches(float $due, ?float $amount, string $field): void
    {
        if ($amount !== null && abs(round($amount, 2) - $due) > 0.005) {
            throw ValidationException::withMessages([
                $field => ['Amount must match the bill total of RM '.number_format($due, 2).'.'],
            ]);
        }
    }
}
