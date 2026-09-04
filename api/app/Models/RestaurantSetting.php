<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id', 'phone', 'email', 'address', 'city', 'state',
    'postcode', 'country', 'opening_hours',
])]
class RestaurantSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
