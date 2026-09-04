<?php

namespace App\Services;

use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableTag;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Table Session workflow (§12-13): open on first order, close on payment.
 * Tags are scanned to resolve the table/session (cashier + staff flow).
 */
class TableSessionService
{
    /**
     * Open a session for a table (rejects if already open).
     */
    public function openSession(RestaurantTable $table, User $user): TableSession
    {
        $open = $this->openForTable($table);

        if ($open) {
            throw ValidationException::withMessages([
                'table' => ['This table already has an open session.'],
            ]);
        }

        return TableSession::query()->create([
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
            'opened_by' => $user->id,
            'status' => TableSession::OPEN,
            'opened_at' => now(),
        ]);
    }

    public function openForTable(RestaurantTable $table): ?TableSession
    {
        return TableSession::query()
            ->where('table_id', $table->id)
            ->open()
            ->latest()
            ->first();
    }

    /**
     * Open a session with no operator (customer QR ordering, §15).
     * The cashier later identifies it by scanning the table.
     */
    public function openSessionAnonymous(RestaurantTable $table): TableSession
    {
        $open = $this->openForTable($table);

        if ($open) {
            return $open;
        }

        return TableSession::query()->create([
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
            'opened_by' => null,
            'status' => TableSession::OPEN,
            'opened_at' => now(),
        ]);
    }

    /**
     * Resolve a table from a scanned tag's public token (active tags only).
     */
    public function tableFromTagToken(string $token): ?RestaurantTable
    {
        $tag = TableTag::query()
            ->active()
            ->where('public_token', strtoupper(trim($token)))
            ->first();

        return $tag?->table;
    }

    /**
     * Close a session (payment taken). Rejects already-closed.
     */
    public function closeSession(TableSession $session, User $user, float $amount): TableSession
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['This session is already closed.'],
            ]);
        }

        $session->forceFill([
            'status' => TableSession::CLOSED,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'total_amount' => round($amount, 2),
        ])->save();

        return $session;
    }
}
