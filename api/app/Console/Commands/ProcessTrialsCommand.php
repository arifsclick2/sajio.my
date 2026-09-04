<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\TrialExpiredMail;
use App\Mail\TrialReminderMail;
use App\Models\Restaurant;
use App\Models\SubscriptionEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Runs periodically (every hour). Two jobs:
 *
 *  1. TRIAL REMINDERS — emails the owner when the trial has 7 / 4 / 1
 *     days remaining (day 7 / 10 / 13 from the start). Each bucket is
 *     emailed exactly once (tracked in restaurants.trial_reminders_sent).
 *
 *  2. DAY-14 LOCK — when a trial ends with no paid subscription, mark the
 *     restaurant locked (trial_locked_at), log the EXPIRED transition, and
 *     email the owner once. Ordering is already blocked via canOperate().
 */
class ProcessTrialsCommand extends Command
{
    protected $signature = 'sajio:process-trials {--dry-run : Report what would happen without sending}';

    protected $description = 'Send trial reminders and lock restaurants whose trial expired without a subscription';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $reminded = 0;
        $locked = 0;

        Restaurant::query()
            ->where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->with(['users' => fn ($q) => $q->where('role', 'owner')])
            ->orderBy('id')
            ->chunkById(200, function ($restaurants) use (&$reminded, &$locked, $dry): void {
                foreach ($restaurants as $restaurant) {
                    $remaining = $restaurant->trialDaysRemaining();

                    if ($restaurant->isOnTrial()) {
                        if ($this->shouldSendReminder($restaurant, $remaining)) {
                            $this->sendReminder($restaurant, $remaining, $dry);
                            $reminded++;
                        }
                        continue;
                    }

                    // Trial over.
                    if ($restaurant->trialEnded() && ! $restaurant->isSubscribed()) {
                        $this->lockRestaurant($restaurant, $dry);
                        $locked++;
                    }
                }
            });

        $this->info("ProcessTrials: {$reminded} reminder(s) sent, {$locked} restaurant(s) locked.".($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /*  Reminders                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Milestones to email: 7 days left (≈day 7), 4 days left (≈day 10),
     * 1 day left (≈day 13). Each fires once when remaining crosses the bucket.
     */
    private function shouldSendReminder(Restaurant $restaurant, int $remaining): bool
    {
        $sent = $restaurant->trial_reminders_sent ?? [];

        if ($remaining <= 7 && $remaining > 4 && ! in_array(7, $sent, true)) {
            return true;
        }
        if ($remaining <= 4 && $remaining > 1 && ! in_array(4, $sent, true)) {
            return true;
        }
        if ($remaining <= 1 && ! in_array(1, $sent, true)) {
            return true;
        }

        return false;
    }

    private function sendReminder(Restaurant $restaurant, int $bucket, bool $dry): void
    {
        $owner = $restaurant->users->first();

        if ($owner) {
            if ($dry) {
                $this->line("  [remind] #{$restaurant->id} {$restaurant->subdomain} ({$bucket}d left)");
            } else {
                Mail::to($owner->email)
                    ->queue(new TrialReminderMail($restaurant, $owner->name, $bucket));
            }
        }

        if (! $dry) {
            $sent = $restaurant->trial_reminders_sent ?? [];
            $sent[] = $bucket;
            $restaurant->forceFill(['trial_reminders_sent' => array_values(array_unique($sent))])->save();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Day-14 lock                                                        */
    /* ------------------------------------------------------------------ */

    private function lockRestaurant(Restaurant $restaurant, bool $dry): void
    {
        if ($dry) {
            $this->line("  [lock] #{$restaurant->id} {$restaurant->subdomain}");
            return;
        }

        $owner = $restaurant->users->first();

        // Mark the lock + log EXPIRED once.
        if ($restaurant->trial_locked_at === null) {
            $restaurant->forceFill(['trial_locked_at' => now()])->save();

            SubscriptionEvent::create([
                'restaurant_id' => $restaurant->id,
                'from_status' => SubscriptionStatus::Trial,
                'to_status' => SubscriptionStatus::Expired,
                'reason' => 'trial_expired_lock',
            ]);
        }

        // Email once.
        if ($restaurant->trial_expired_email_sent_at === null && $owner) {
            $restaurant->forceFill(['trial_expired_email_sent_at' => now()])->save();
            Mail::to($owner->email)->queue(new TrialExpiredMail($restaurant, $owner->name));
        }
    }
}
