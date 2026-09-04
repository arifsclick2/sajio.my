<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'restaurant_id',
    'user_id',
    'shift_id',
    'work_date',
    'clock_in_at',
    'clock_out_at',
    'clock_in_method',
    'clock_out_method',
    'worked_minutes',
    'note',
])]
class Attendance extends Model
{
    use HasFactory;

    /** Derived statuses */
    public const ON_DUTY = 'on_duty';

    public const COMPLETED = 'completed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'worked_minutes' => 'integer',
        ];
    }

    public function scopeOnDuty(Builder $query): Builder
    {
        return $query->whereNull('clock_out_at')->whereNotNull('clock_in_at');
    }

    public function scopeForWorkDate(Builder $query, string $date): Builder
    {
        return $query->where('work_date', $date);
    }

    public function isOnDuty(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }

    public function isCompleted(): bool
    {
        return $this->clock_out_at !== null;
    }

    public function status(): string
    {
        return $this->isCompleted() ? self::COMPLETED : self::ON_DUTY;
    }

    /**
     * Compute worked minutes in the restaurant's local timezone.
     */
    public function computeWorkedMinutes(?string $timezone = null): void
    {
        $tz = $timezone ?? 'Asia/Kuala_Lumpur';

        if ($this->clock_in_at === null) {
            $this->worked_minutes = null;

            return;
        }

        $in = $this->clock_in_at->copy()->timezone($tz);
        $out = ($this->clock_out_at?->copy()->timezone($tz)) ?? now($tz);

        // diffInMinutes is signed in this Carbon version — use abs.
        $this->worked_minutes = max(0, abs((int) $out->diffInMinutes($in)));
    }
}
