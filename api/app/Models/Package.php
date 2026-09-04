<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'slug',
    'description',
    'price_monthly',
    'is_active',
    'sort_order',
])]
class Package extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function limits(): HasOne
    {
        return $this->hasOne(PackageLimit::class);
    }

    /**
     * Monthly price in sen (Stripe minor units) for this package.
     */
    public function priceMonthlyInSen(): int
    {
        return (int) round(((float) $this->price_monthly) * 100);
    }
}
