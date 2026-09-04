<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\EmailOtp;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /*  Registration                                                       */
    /* ------------------------------------------------------------------ */

    public function test_owner_can_register_restaurant_and_receives_otp(): void
    {
        Notification::fake();
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Kedai Kopi Sajio',
            'subdomain' => 'kedai-sajio',
        ]);

        $response->assertCreated()
            ->assertJsonPath('restaurant.subdomain', 'kedai-sajio')
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonStructure(['dev_otp', 'otp_expires_in_minutes']);

        $this->assertDatabaseHas('restaurants', [
            'subdomain' => 'kedai-sajio',
            'trial_ends_at' => null, // clock has NOT started until verification
        ]);

        $user = User::where('email', 'arif@kedai.my')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_otps', ['email' => 'arif@kedai.my', 'used_at' => null]);

        Notification::assertSentTo($user, EmailOtpNotification::class);
    }

    public function test_registration_rejects_reserved_subdomain(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif2@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Admin Cafe',
            'subdomain' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subdomain');
    }

    public function test_registration_rejects_invalid_subdomain(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif3@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Bad Sub',
            'subdomain' => '-bad-.MY',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subdomain');
    }

    public function test_registration_rejects_duplicate_subdomain(): void
    {
        Restaurant::factory()->create(['subdomain' => 'taken-name']);

        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif4@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Taken',
            'subdomain' => 'taken-name',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subdomain');
    }

    public function test_registration_with_valid_coupon_marks_it_used(): void
    {
        Notification::fake();
        $coupon = Coupon::query()->create([
            'code' => 'SAJIO10',
            'name' => '10% off',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 5,
        ]);

        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif5@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Coupon Cafe',
            'subdomain' => 'coupon-cafe',
            'coupon_code' => 'sajio10',
        ])->assertCreated();

        $this->assertDatabaseHas('coupons', ['code' => 'SAJIO10', 'used_count' => 1]);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id]);
    }

    public function test_registration_with_invalid_coupon_is_rejected(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif6@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'No Coupon',
            'subdomain' => 'no-coupon',
            'coupon_code' => 'DOESNOTEXIST',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseMissing('users', ['email' => 'arif6@kedai.my']);
    }

    /* ------------------------------------------------------------------ */
    /*  OTP verification                                                   */
    /* ------------------------------------------------------------------ */

    public function test_verify_otp_starts_trial_and_sends_welcome_emails(): void
    {
        Mail::fake();
        Notification::fake();

        $reg = $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif7@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Verified Kopitiam',
            'subdomain' => 'verified-kopi',
        ]);
        $otp = $reg->json('dev_otp');

        $this->assertNotNull($otp);

        $response = $this->postJson('/api/v1/verify-otp', [
            'email' => 'arif7@kedai.my',
            'code' => $otp,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'restaurant' => ['subdomain', 'trial_ends_at']]);

        $this->assertDatabaseHas('users', ['email' => 'arif7@kedai.my']);

        $restaurant = Restaurant::where('subdomain', 'verified-kopi')->first();
        $this->assertNotNull($restaurant->trial_ends_at);
        $this->assertTrue($restaurant->trial_ends_at->isFuture());
        $this->assertTrue($restaurant->isOnTrial());

        // Welcome email to the owner + notification to the super admin.
        Mail::assertQueued(\App\Mail\RestaurantWelcomeMail::class, fn ($mail) => $mail->hasTo('arif7@kedai.my'));
        Mail::assertQueued(\App\Mail\NewRestaurantNotificationMail::class, fn ($mail) => $mail->hasTo('arif@netmow.com'));

        $this->assertDatabaseHas('subscription_events', [
            'restaurant_id' => $restaurant->id,
            'to_status' => 'trial',
        ]);
    }

    public function test_verify_otp_with_wrong_code_fails_and_does_not_start_trial(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/register', [
            'name' => 'Arif',
            'email' => 'arif8@kedai.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Wrong Code',
            'subdomain' => 'wrong-code',
        ]);

        $this->postJson('/api/v1/verify-otp', [
            'email' => 'arif8@kedai.my',
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseHas('restaurants', ['subdomain' => 'wrong-code', 'trial_ends_at' => null]);
        $this->assertDatabaseHas('users', ['email' => 'arif8@kedai.my', 'email_verified_at' => null]);
    }

    /* ------------------------------------------------------------------ */
    /*  Subdomain availability                                             */
    /* ------------------------------------------------------------------ */

    public function test_subdomain_availability_check(): void
    {
        Restaurant::factory()->create(['subdomain' => 'taken']);

        $this->getJson('/api/v1/check-subdomain?subdomain=available-name')
            ->assertOk()
            ->assertJsonPath('available', true);

        $this->getJson('/api/v1/check-subdomain?subdomain=taken')
            ->assertOk()
            ->assertJsonPath('available', false);

        $this->getJson('/api/v1/check-subdomain?subdomain=admin')
            ->assertUnprocessable();
    }
}
