<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'name',
    'subdomain',
    'currency',
    'timezone',
    'country',
    'status',
    'trial_ends_at',
])]
class Restaurant extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'trial_ends_at' => 'datetime',
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
}
