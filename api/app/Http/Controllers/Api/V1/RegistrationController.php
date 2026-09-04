<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Rules\SubdomainRule;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
    ) {
    }

    /**
     * One-step registration: owner details + restaurant + subdomain (+ optional coupon).
     * Creates the owner (unverified) + restaurant, then emails an OTP.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'restaurant_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:63', new SubdomainRule, 'unique:restaurants,subdomain'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        // Throttle registration by email + IP to reduce OTP abuse.
        $throttleKey = 'register:'.strtolower($validated['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Too many attempts. Please wait a few minutes and try again.'],
            ]);
        }
        RateLimiter::hit($throttleKey, 600);

        $result = $this->registration->register($validated);

        return response()->json([
            'message' => 'Registration received. Please verify your email with the code we sent.',
            'user' => $result['user']->only('id', 'name', 'email', 'role'),
            'restaurant' => $result['restaurant']->only('id', 'name', 'subdomain'),
            // In production the OTP goes by email only; exposing it here helps
            // the V1 frontend flow + tests. Remove before launch.
            'dev_otp' => app()->environment('local', 'testing') ? $result['otp_code'] : null,
            'otp_expires_in_minutes' => \App\Models\EmailOtp::TTL_MINUTES,
        ], 201);
    }

    /**
     * Confirm the emailed OTP → starts the 14-day trial and auto-logs-in.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $result = $this->registration->verify($validated['email'], $validated['code']);

        return response()->json([
            'message' => 'Email verified. Your 14-day free trial has started. Welcome to Sajio!',
            'user' => $result['user'],
            'restaurant' => $result['restaurant'],
            'token' => $result['token'],
        ]);
    }

    /**
     * Send a fresh OTP for a registration that is still pending verification.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $code = $this->registration->resendOtp($validated['email']);

        return response()->json([
            'message' => 'A new verification code has been sent.',
            'dev_otp' => app()->environment('local', 'testing') ? $code : null,
        ]);
    }

    /**
     * Public helper: is a subdomain available? Used by the frontend as-you-type.
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $request->validate([
            'subdomain' => ['required', 'string', 'max:63', new SubdomainRule],
        ]);

        $taken = Restaurant::where('subdomain', strtolower($request->input('subdomain')))->exists();

        return response()->json([
            'subdomain' => strtolower($request->input('subdomain')),
            'available' => ! $taken,
        ]);
    }
}
