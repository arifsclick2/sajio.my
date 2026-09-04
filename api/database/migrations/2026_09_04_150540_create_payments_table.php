<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payments are RECORD-ONLY (§17): Sajio records the method — it is not
        // a payment processor. A payment settles either a single order
        // (takeaway) or a table session's bill (dine-in).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->nullable()->constrained('table_sessions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('method', 10);              // cash | card | qr | other (§17)
            $table->decimal('amount', 12, 2);          // amount applied to the bill
            $table->string('reference', 120)->nullable(); // card last4 / TNG ref / etc.
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'paid_at']);
            $table->index(['order_id']);
            $table->index(['table_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
