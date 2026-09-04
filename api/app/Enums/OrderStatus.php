<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Preparing => 'Memasak',
            self::Ready => 'Sedia',
            self::Served => 'Dihidang',
            self::Completed => 'Selesai',
            self::Cancelled => 'Batal',
            self::Voided => 'Void',
        };
    }

    /**
     * Allowed forward transitions (§18) + cancellations.
     *
     * @return array<int, OrderStatus>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Served],
            self::Served => [self::Completed],
            self::Completed => [],
            self::Cancelled => [],
            self::Voided => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Voided], true);
    }
}
