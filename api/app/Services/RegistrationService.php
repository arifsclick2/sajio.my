<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Mail\NewRestaurantNotificationMail;
use App\Mail\RestaurantWelcomeMail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\EmailOtp;
use App\Models\Restaurant;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the Sajio registration flow:
 *
 *   1. register()        — create owner (unverified) + restaurant in a DB
 *                          transaction; validate/attach an optional coupon;
 *                          issue and email the OTP.
 *   2. verify()          — confirm the OTP, mark email verified, start the
 *                          14-day trial, send Welcome + Super Admin emails,
 *                          and issue an API token (auto-login).
 *   3. resendOtp()       — issue a fresh OTP for a registration in progress.
 */
class RegistrationService
{
    /**
     * @param  array{name: string, email: string, password: string, restaurant_name: string, subdomain: string, coupon_code?: ?string}  $data
     * @return array{user: User, restaurant: Restaurant, otp_code: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null);

            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Owner,
            ]);

            $restaurant = Restaurant::create([
                'name' => $data['restaurant_name'],
                'subdomain' => strtolower($data['subdomain']),
                'currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur',
                'country' => 'MY',
                // Trial clock does NOT start until the owner verifies their email.
                'trial_ends_at' => null,
            ]);

            $user->forceFill(['restaurant_id' => $restaurant->id])->save();

            if ($coupon !== null) {
                $this->applyRegistrationCoupon($coupon, $restaurant);
            }

            $otpCode = EmailOtp::issue($user->email, 'verify_email');

            // Queue the OTP email (mailer is Mailgun via the queue).
            $user->notify(new EmailOtpNotification($otpCode, 'verify your email'));

            return ['user' => $user, 'restaurant' => $restaurant, 'otp_code' => $otpCode];
        });
    }

    /**
     * Verify the OTP, start the trial, notify, and return the owner with a fresh token.
     *
     * @return array{user: User, restaurant: Restaurant, token: string}
     */
    public function verify(string $email, string $code): array
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || $user->role !== UserRole::Owner) {
            throw ValidationException::withMessages([
                'email' => ['No pending registration found for this email.'],
            ]);
        }

        if ($user->email_verified_at !== null) {
            // Already verified — the OTP is redundant; just return the session.
            return $this->sessionFor($user);
        }

        if (! EmailOtp::verify($user->email, $code, 'verify_email')) {
            throw ValidationException::withMessages([
                'code' => ['That code is invalid or has expired. Please request a new one.'],
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $restaurant = $user->restaurant;
        $restaurant->startTrial(14);

        SubscriptionEvent::create([
            'restaurant_id' => $restaurant->id,
            'from_status' => null,
            'to_status' => SubscriptionStatus::Trial,
            'reason' => 'email_verified_trial_started',
        ]);

        // Welcome email to the owner.
        Mail::to($user->email)
            ->send(new RestaurantWelcomeMail($restaurant, $user->name));

        // Notify the Super Admin about the new registration.
        $superAdminEmail = config('app.super_admin_email');
        if ($superAdminEmail) {
            Mail::to($superAdminEmail)
                ->send(new NewRestaurantNotificationMail($restaurant, $user->name, $user->email));
        }

        return $this->sessionFor($user);
    }

    /**
     * @return array{user: User, restaurant: Restaurant, token: string}
     */
    public function sessionFor(User $user): array
    {
        return [
            'user' => $user->load('restaurant'),
            'restaurant' => $user->restaurant,
            'token' => $user->createToken('auth')->plainTextToken,
        ];
    }

    public function resendOtp(string $email): string
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || $user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['No pending verification found for this email.'],
            ]);
        }

        $otpCode = EmailOtp::issue($user->email, 'verify_email');
        $user->notify(new EmailOtpNotification($otpCode, 'verify your email'));

        return $otpCode;
    }

    /* ------------------------------------------------------------------ */
    /*  Coupon helpers                                                     */
    /* ------------------------------------------------------------------ */

    private function resolveCoupon(?string $code): ?Coupon
    {
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['That coupon code is invalid or has expired.'],
            ]);
        }

        return $coupon;
    }

    /**
     * Record that this restaurant registered using the coupon. The coupon is
     * applied to the FIRST subscription invoice (user decision).
     */
    private function applyRegistrationCoupon(Coupon $coupon, Restaurant $restaurant): void
    {
        $coupon->increment('used_count');

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'restaurant_id' => $restaurant->id,
            'used_at' => now(),
        ]);
    }
}
