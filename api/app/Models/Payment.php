<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id', 'table_session_id', 'order_id', 'method',
    'amount', 'reference', 'note', 'received_by', 'paid_at',
])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->where('restaurant_id', $restaurantId);
    }
}
