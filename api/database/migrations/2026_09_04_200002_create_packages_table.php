<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Monthly price in MYR. Money is NUMERIC(12,2) — never float (§26).
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('package_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            // NULL = unlimited. Limits are NOT enforced during trial (§ user decision).
            $table->unsignedInteger('staff_count')->nullable();
            $table->unsignedInteger('pos_devices')->nullable();
            $table->unsignedInteger('table_count')->nullable();
            $table->unsignedInteger('menu_items')->nullable();
            // Differentiating features (§3 matrix). Always-on basics are not stored.
            $table->boolean('customer_qr_ordering')->default(false);
            $table->boolean('advanced_reports')->default(false);
            $table->boolean('table_card_tag_system')->default(false);
            $table->boolean('fast_table_scan_at_pos')->default(false);
            $table->boolean('nfc_tag_support')->default(false);
            $table->boolean('table_card_printing')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_limits');
        Schema::dropIfExists('packages');
    }
};
