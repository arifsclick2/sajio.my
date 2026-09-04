<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer QR orders (Phase 5) have no staff member taking them —
        // the customer sends the order straight from their phone.
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'staff_id')) {
            DB::statement('ALTER TABLE orders ALTER COLUMN staff_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // No-op: existing rows may legitimately have NULL staff_id.
    }
};
