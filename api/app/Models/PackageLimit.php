<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'package_id',
    'staff_count',
    'pos_devices',
    'table_count',
    'menu_items',
    'customer_qr_ordering',
    'advanced_reports',
    'table_card_tag_system',
    'fast_table_scan_at_pos',
    'nfc_tag_support',
    'table_card_printing',
])]
class PackageLimit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'staff_count' => 'integer',
            'pos_devices' => 'integer',
            'table_count' => 'integer',
            'menu_items' => 'integer',
            'customer_qr_ordering' => 'boolean',
            'advanced_reports' => 'boolean',
            'table_card_tag_system' => 'boolean',
            'fast_table_scan_at_pos' => 'boolean',
            'nfc_tag_support' => 'boolean',
            'table_card_printing' => 'boolean',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
