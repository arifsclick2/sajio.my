<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'restaurant_id', 'table_id', 'tag_code', 'public_token', 'tag_type', 'status',
])]
class TableTag extends Model
{
    use HasFactory;

    public const TYPES = ['qr', 'nfc', 'qr_nfc'];

    public const STATUSES = ['active', 'disabled', 'damaged'];

    protected function casts(): array
    {
        return [
            'table_id' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::upper(Str::random(8));
        } while (self::query()->where('public_token', $token)->exists());

        return $token;
    }

    public static function nextTagCode(int $restaurantId): string
    {
        $count = self::query()->where('restaurant_id', $restaurantId)->count();

        do {
            $count++;
            $code = 'TAG-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        } while (self::query()->where('restaurant_id', $restaurantId)->where('tag_code', $code)->exists());

        return $code;
    }
}
