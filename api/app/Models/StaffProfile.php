<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id',
    'user_id',
    'staff_code',
    'position',
    'phone',
    'joined_at',
    'is_active',
])]
class StaffProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate the next staff code for a restaurant (S001, S002, ...).
     */
    public static function nextStaffCode(int $restaurantId): string
    {
        $count = self::query()->where('restaurant_id', $restaurantId)->count();

        do {
            $count++;
            $code = 'S'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        } while (self::query()->where('restaurant_id', $restaurantId)->where('staff_code', $code)->exists());

        return $code;
    }
}
