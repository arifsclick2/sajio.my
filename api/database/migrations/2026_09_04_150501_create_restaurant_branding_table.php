<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete()->unique();
            // Logo stored on object storage later; store URL/path now.
            $table->string('logo_url', 500)->nullable();
            $table->string('brand_color', 20)->default('#0d9488'); // teal default
            $table->string('receipt_header', 500)->nullable();
            $table->string('receipt_footer', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_branding');
    }
};
