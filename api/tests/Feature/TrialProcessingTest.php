<?php

namespace Tests\Feature;

use App\Mail\TrialExpiredMail;
use App\Mail\TrialReminderMail;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrialProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithRestaurant(array $overrides = []): array
    {
        $restaurant = Restaurant::factory()->create(array_merge([
            'trial_ends_at' => now()->addDays(14),
        ], $overrides));
        $owner = User::factory()->restaurant($restaurant)->owner()->create();

        return [$owner, $restaurant];
    }

    /* ------------------------------------------------------------------ */
    /*  Reminders                                                          */
    /* ------------------------------------------------------------------ */

    public function test_sends_reminder_at_7_days_remaining(): void
    {
        Mail::fake();
        [$owner, $restaurant] = $this->ownerWithRestaurant();
        // 7 days from the end = trial started 7 days ago.
        $restaurant->forceFill(['trial_ends_at' => now()->addDays(7)])->save();

        $this->artisan('sajio:process-trials')->assertSuccessful();

        Mail::assertQueued(TrialReminderMail::class, fn ($mail) => $mail->restaurant->id === $restaurant->id && $mail->daysRemaining === 7);
        $restaurant->refresh();
        $this->assertContains(7, $restaurant->trial_reminders_sent);
    }

    public function test_reminders_fire_once_per_bucket_and_skip_early_trials(): void
    {
        Mail::fake();
        [$owner, $restaurant] = $this->ownerWithRestaurant(); // 14 days left

        $this->artisan('sajio:process-trials')->assertSuccessful();
        Mail::assertNothingQueued(); // too early

        // Jump to 4 days remaining → fires day-10 reminder (bucket 4).
        $restaurant->forceFill(['trial_ends_at' => now()->addDays(4)])->save();
        $this->artisan('sajio:process-trials')->assertSuccessful();
        Mail::assertQueued(TrialReminderMail::class, 1);
        $restaurant->refresh();
        $this->assertContains(4, $restaurant->trial_reminders_sent);

        // Re-run at 4 days → no duplicate.
        $this->artisan('sajio:process-trials')->assertSuccessful();
        Mail::assertQueued(TrialReminderMail::class, 1);
    }

    /* ------------------------------------------------------------------ */
    /*  Day-14 lock                                                        */
    /* ------------------------------------------------------------------ */

    public function test_locks_restaurant_when_trial_expires_without_subscription(): void
    {
        Mail::fake();
        [$owner, $restaurant] = $this->ownerWithRestaurant([
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($restaurant->trialEnded());

        $this->artisan('sajio:process-trials')->assertSuccessful();

        $restaurant->refresh();
        $this->assertNotNull($restaurant->trial_locked_at);
        $this->assertTrue($restaurant->needsSubscription());
        $this->assertDatabaseHas('subscription_events', [
            'restaurant_id' => $restaurant->id,
            'to_status' => 'expired',
            'reason' => 'trial_expired_lock',
        ]);

        Mail::assertQueued(TrialExpiredMail::class, fn ($mail) => $mail->restaurant->id === $restaurant->id);

        // Re-run → no duplicate lock/email.
        $this->artisan('sajio:process-trials')->assertSuccessful();
        Mail::assertQueued(TrialExpiredMail::class, 1);
        $this->assertDatabaseCount('subscription_events', 1);
    }

    public function test_subscribed_restaurant_is_not_locked_after_trial(): void
    {
        Mail::fake();
        [$owner, $restaurant] = $this->ownerWithRestaurant([
            'trial_ends_at' => now()->subDay(),
        ]);

        // Simulate an active subscription.
        \App\Models\Subscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'type' => 'main',
            'stripe_id' => 'sub_test_ok',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_123',
        ]);

        $this->artisan('sajio:process-trials')->assertSuccessful();

        $restaurant->refresh();
        $this->assertNull($restaurant->trial_locked_at);
        $this->assertFalse($restaurant->needsSubscription());
        Mail::assertNothingQueued();
    }
}
