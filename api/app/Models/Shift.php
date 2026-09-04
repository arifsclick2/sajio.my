<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'restaurant_id',
    'name',
    'start_time',
    'end_time',
    'crosses_midnight',
    'is_active',
    'sort_order',
])]
class Shift extends Model
{
    use HasFactory;

    public const EARLY_GRACE_MINUTES = 15; // allowed to clock in a bit early

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Does this shift cover the given moment? Times are restaurant-local.
     */
    public function covers(CarbonInterface $localTime): bool
    {
        $minutes = $localTime->hour * 60 + $localTime->minute;

        $start = $this->startMinutes();
        $end = $this->endMinutes();

        // Allow clocking in a little before the shift starts (grace).
        $effectiveStart = max(0, $start - self::EARLY_GRACE_MINUTES);

        if ($this->crosses_midnight) {
            // e.g. 19:00 -> 07:00 covers 19:00..23:59 and 00:00..07:00.
            return $minutes >= $effectiveStart || $minutes < $end;
        }

        return $minutes >= $effectiveStart && $minutes < $end;
    }

    public function startMinutes(): int
    {
        return $this->minutesOf($this->start_time);
    }

    public function endMinutes(): int
    {
        return $this->minutesOf($this->end_time);
    }

    private function minutesOf(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
