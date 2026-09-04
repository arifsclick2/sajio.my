<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');                 // restaurant-local date of clock-in
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_in_method', 20)->default('web'); // web | machine (future)
            $table->string('clock_out_method', 20)->default('web');
            // Derived worked minutes (restaurant-local).
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // One active attendance per staff per day.
            $table->unique(['user_id', 'work_date']);
            $table->index(['restaurant_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
