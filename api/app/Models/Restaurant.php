<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

#[Fillable([
    'name',
    'subdomain',
    'currency',
    'timezone',
    'country',
    'status',
    'trial_ends_at',
    'trial_reminders_sent',
    'trial_locked_at',
    'trial_expired_email_sent_at',
    'last_order_no',
    'stripe_id',
    'pm_type',
    'pm_last_four',
])]
class Restaurant extends Model
{
    use Billable, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'trial_ends_at' => 'datetime',
            'trial_reminders_sent' => 'array',
            'trial_locked_at' => 'datetime',
            'trial_expired_email_sent_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'owner');
    }

    public function subscriptionEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(RestaurantSetting::class);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(RestaurantBranding::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function tableTags(): HasMany
    {
        return $this->hasMany(TableTag::class);
    }

    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('stripe_status', ['trialing', 'active', 'past_due'])
            ->latestOfMany();
    }

    /* ------------------------------------------------------------------ */
    /*  Billing / subscription status                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Effective subscription state of this restaurant (plan §4):
     *   - active Stripe sub  -> ACTIVE
     *   - past_due Stripe    -> PAST_DUE
     *   - cancelled/ended    -> CANCELLED / EXPIRED
     *   - otherwise on trial -> TRIAL
     *   - suspended status   -> SUSPENDED (super-admin)
     */
    public function subscriptionStatus(): SubscriptionStatus
    {
        if ($this->status === RestaurantStatus::Suspended) {
            return SubscriptionStatus::Suspended;
        }

        $sub = $this->activeSubscription;

        if ($sub) {
            return match ($sub->stripe_status) {
                'active', 'trialing' => SubscriptionStatus::Active,
                'past_due' => SubscriptionStatus::PastDue,
                'cancelled' => SubscriptionStatus::Cancelled,
                default => SubscriptionStatus::Expired,
            };
        }

        // No paid subscription yet.
        if ($this->trialEnded()) {
            return SubscriptionStatus::Expired;
        }

        if ($this->isOnTrial()) {
            return SubscriptionStatus::Trial;
        }

        // Owner registered but hasn't verified email (no trial started yet).
        return SubscriptionStatus::Trial;
    }

    /**
     * Can this restaurant create orders / sell right now?
     */
    public function canOperate(): bool
    {
        return $this->subscriptionStatus()->canOperate();
    }

    /**
     * The restaurant is forced to subscribe (trial over, no active plan).
     */
    public function needsSubscription(): bool
    {
        return in_array($this->subscriptionStatus(), [
            SubscriptionStatus::Expired,
            SubscriptionStatus::Suspended,
        ], true);
    }

    public function isSubscribed(): bool
    {
        return $this->subscriptionStatus() === SubscriptionStatus::Active;
    }

    public function currentPackage(): ?Package
    {
        $sub = $this->activeSubscription;

        if (! $sub || ! $sub->stripe_price) {
            return null;
        }

        return Package::where('stripe_price_id', $sub->stripe_price)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Trial helpers                                                      */
    /* ------------------------------------------------------------------ */

    public function isOnTrial(?Carbon $now = null): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture($now ?? now());
    }

    public function trialEnded(?Carbon $now = null): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast($now ?? now());
    }

    public function trialDaysRemaining(?Carbon $now = null): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        $now ??= now();

        if ($this->trial_ends_at->isPast($now)) {
            return 0;
        }

        return max(1, (int) $now->diffInDays($this->trial_ends_at) + 1);
    }

    /**
     * Start the 14-day trial clock (called once the owner's email is verified).
     */
    public function startTrial(int $days = 14): void
    {
        $this->forceFill(['trial_ends_at' => now()->addDays($days)])->save();
    }

    /**
     * Trial over and no subscription — set status to EXPIRED via event log.
     */
    public function markTrialExpired(): void
    {
        SubscriptionEvent::create([
            'restaurant_id' => $this->id,
            'from_status' => SubscriptionStatus::Trial,
            'to_status' => SubscriptionStatus::Expired,
            'reason' => 'trial_expired',
        ]);
    }

    /**
     * The package is stored on the restaurant once subscribed.
     * (We resolve it via the active subscription's Stripe price.)
     */
    protected function package(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->currentPackage(),
        );
    }
}
