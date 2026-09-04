<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'restaurant_id', 'table_id', 'table_session_id', 'staff_id', 'order_no',
    'type', 'status', 'source', 'subtotal', 'discount', 'tax', 'total',
    'customer_name', 'customer_phone', 'note', 'completed_at', 'cancelled_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->where('restaurant_id', $restaurantId);
    }
}
