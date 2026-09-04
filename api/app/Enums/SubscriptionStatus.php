<?php

namespace App\Enums;

/**
 * Subscription lifecycle states — Sajio plan §4.
 *
 * TRIAL        — 14-day trial running (no paid subscription yet).
 * ACTIVE       — paid subscription active.
 * PAST_DUE     — payment failed; grace period running (3 days).
 * EXPIRED      — trial ended with no subscription.
 * CANCELLED    — owner/Stripe cancelled; access until period end.
 * SUSPENDED    — grace exhausted / super-admin suspension. Locked.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * States where the restaurant can keep selling (POS / orders allowed).
     */
    public function canOperate(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue, self::Cancelled], true);
    }
}
