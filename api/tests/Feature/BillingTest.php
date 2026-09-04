<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->onTrial()->create();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();

        return [$owner, $restaurant];
    }

    /* ------------------------------------------------------------------ */
    /*  Packages listing (public)                                          */
    /* ------------------------------------------------------------------ */

    public function test_packages_list_returns_seeded_packages(): void
    {
        $this->seed(PackageSeeder::class);

        $response = $this->getJson('/api/v1/billing/packages');

        $response->assertOk()
            ->assertJsonCount(3, 'packages');

        $names = collect($response->json('packages'))->pluck('name');
        $this->assertTrue($names->contains('Basic'));
        $this->assertTrue($names->contains('Premium'));
        $this->assertTrue($names->contains('Pro'));
    }

    /* ------------------------------------------------------------------ */
    /*  Checkout (owner-only; Stripe service mocked)                       */
    /* ------------------------------------------------------------------ */

    public function test_owner_can_start_checkout_for_package(): void
    {
        $this->seed(PackageSeeder::class);
        [$owner, $restaurant] = $this->ownerWithRestaurant();

        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn([
                    'url' => 'https://checkout.stripe.com/c/pay_test_123',
                    'session_id' => 'cs_test_123',
                ]);
        });

        Sanctum::actingAs($owner);

        $package = Package::where('slug', 'premium')->first();

        $response = $this->postJson('/api/v1/billing/checkout', [
            'package_id' => $package->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/c/pay_test_123')
            ->assertJsonPath('session_id', 'cs_test_123');
    }

    public function test_non_owner_cannot_checkout(): void
    {
        $this->seed(PackageSeeder::class);
        $restaurant = Restaurant::factory()->onTrial()->create();
        $staff = User::factory()->restaurant($restaurant)->role(\App\Enums\UserRole::Staff)->create();

        Sanctum::actingAs($staff);

        $package = Package::where('slug', 'basic')->first();

        $this->postJson('/api/v1/billing/checkout', ['package_id' => $package->id])
            ->assertUnprocessable();
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->seed(PackageSeeder::class);
        $package = Package::where('slug', 'basic')->first();

        $this->postJson('/api/v1/billing/checkout', ['package_id' => $package->id])
            ->assertUnauthorized();
    }

    /* ------------------------------------------------------------------ */
    /*  Billing status                                                     */
    /* ------------------------------------------------------------------ */

    public function test_billing_status_shows_trial(): void
    {
        [$owner, $restaurant] = $this->ownerWithRestaurant();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/billing/status');

        $response->assertOk()
            ->assertJsonPath('status', 'trial')
            ->assertJsonPath('is_subscribed', false)
            ->assertJsonPath('needs_subscription', false)
            ->assertJsonPath('trial.is_on_trial', true);
    }

    public function test_billing_status_shows_active_when_subscribed(): void
    {
        [$owner, $restaurant] = $this->ownerWithRestaurant();

        // Simulate an active Cashier subscription (restaurant-scoped).
        \App\Models\Subscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'type' => 'main',
            'stripe_id' => 'sub_test_123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_123',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/billing/status');

        $response->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('is_subscribed', true);
    }

    /* ------------------------------------------------------------------ */
    /*  Webhook state transitions (SubscriptionService)                    */
    /* ------------------------------------------------------------------ */

    public function test_checkout_completed_records_subscription(): void
    {
        [$owner, $restaurant] = $this->ownerWithRestaurant();
        $this->assertTrue($restaurant->isOnTrial());

        $service = app(SubscriptionService::class);
        $service->handleSubscriptionPayload([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'metadata' => ['restaurant_id' => (string) $restaurant->id],
                    'subscription' => 'sub_test_checkout',
                ],
            ],
        ]);

        // The subscription row is recorded; the follow-up
        // customer.subscription.created/updated clears the trial.
        $this->assertDatabaseHas('subscriptions', [
            'restaurant_id' => $restaurant->id,
            'stripe_id' => 'sub_test_checkout',
        ]);

        // A completed checkout that carries a sub id transitions to active.
        $restaurant->refresh();
        $this->assertSame(SubscriptionStatus::Active, $restaurant->subscriptionStatus());

        // The active sub (trialing/active) clears the trial on the
        // customer.subscription.updated event.
        $service->handleSubscriptionPayload([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_checkout',
                    'metadata' => ['restaurant_id' => (string) $restaurant->id],
                    'status' => 'active',
                ],
            ],
        ]);
        $restaurant->refresh();
        $this->assertNull($restaurant->trial_ends_at);

        $this->assertDatabaseHas('subscription_events', [
            'restaurant_id' => $restaurant->id,
            'to_status' => 'active',
        ]);
    }

    public function test_subscription_updated_past_due_sets_past_due(): void
    {
        [$owner, $restaurant] = $this->ownerWithRestaurant();
        $restaurant->forceFill(['trial_ends_at' => null])->save();

        $service = app(SubscriptionService::class);
        $service->handleSubscriptionPayload([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_pastdue',
                    'metadata' => ['restaurant_id' => (string) $restaurant->id],
                    'status' => 'past_due',
                ],
            ],
        ]);

        $restaurant->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $restaurant->subscriptionStatus());
        $this->assertTrue($restaurant->canOperate()); // grace period
        $this->assertDatabaseHas('subscriptions', [
            'restaurant_id' => $restaurant->id,
            'stripe_id' => 'sub_test_pastdue',
            'stripe_status' => 'past_due',
        ]);
    }

    public function test_subscription_deleted_sets_cancelled(): void
    {
        [$owner, $restaurant] = $this->ownerWithRestaurant();

        // Pre-existing active sub.
        \App\Models\Subscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'type' => 'main',
            'stripe_id' => 'sub_test_del',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_123',
        ]);

        $service = app(SubscriptionService::class);
        $service->handleSubscriptionPayload([
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test_del',
                    'metadata' => ['restaurant_id' => (string) $restaurant->id],
                ],
            ],
        ]);

        $restaurant->refresh();
        $this->assertDatabaseMissing('subscriptions', ['stripe_id' => 'sub_test_del']);
        $this->assertDatabaseHas('subscription_events', [
            'restaurant_id' => $restaurant->id,
            'to_status' => 'cancelled',
            'reason' => 'subscription_deleted',
        ]);
    }

    public function test_unknown_restaurant_payload_is_ignored(): void
    {
        $service = app(SubscriptionService::class);
        $service->handleSubscriptionPayload([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['metadata' => ['restaurant_id' => '999999']]],
        ]);

        $this->assertDatabaseCount('subscription_events', 0);
    }

    public function test_webhook_requires_valid_signature(): void
    {
        // Without a valid Stripe signature, the endpoint must be rejected.
        $this->postJson('/stripe/webhook', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => []]],
        ], ['Stripe-Signature' => 'bad_signature'])
            ->assertStatus(403);
    }
}
