<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->char('currency', 3)->default('MYR');
            $table->string('timezone', 64)->default('Asia/Kuala_Lumpur');
            $table->char('country', 2)->default('MY');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('trial_ends_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
