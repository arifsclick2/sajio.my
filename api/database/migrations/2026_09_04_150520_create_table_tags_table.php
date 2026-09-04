<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table Tag / Table Token — Pro feature (§11). Physical tag != table.
        Schema::create('table_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->string('tag_code', 30);               // human label on the card
            $table->string('public_token', 20)->unique(); // scanned value
            $table->string('tag_type', 10)->default('qr'); // qr | nfc | qr_nfc
            $table->string('status', 20)->default('active'); // active | disabled | damaged
            $table->timestamps();

            $table->unique(['restaurant_id', 'tag_code']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_tags');
    }
};
