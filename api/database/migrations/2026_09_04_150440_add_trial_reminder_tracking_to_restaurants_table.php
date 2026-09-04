<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Milestones (days-remaining buckets) for which a trial reminder
            // has already been emailed — e.g. [7,4,1]. Prevents duplicates.
            $table->json('trial_reminders_sent')->nullable()->after('trial_ends_at');
            // Set when the trial has ended with no subscription (day-14 lock).
            $table->timestamp('trial_locked_at')->nullable()->after('trial_reminders_sent');
            // Tracks the day-14 lock email so it is only sent once.
            $table->timestamp('trial_expired_email_sent_at')->nullable()->after('trial_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['trial_reminders_sent', 'trial_locked_at', 'trial_expired_email_sent_at']);
        });
    }
};
