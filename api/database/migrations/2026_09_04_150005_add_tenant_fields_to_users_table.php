<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('restaurant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // super_admin has no restaurant; restaurant roles: owner/manager/staff.
            $table->string('role', 20)->default('owner')->after('restaurant_id');

            $table->index(['restaurant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'role']);
            $table->dropConstrainedForeignId('restaurant_id');
            $table->dropColumn('role');
        });
    }
};
