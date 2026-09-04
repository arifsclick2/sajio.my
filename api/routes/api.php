<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::prefix('v1')->group(function (): void {
    // Registration + email OTP flow
    Route::post('/register', [RegistrationController::class, 'register']);
    Route::post('/verify-otp', [RegistrationController::class, 'verifyOtp']);
    Route::post('/resend-otp', [RegistrationController::class, 'resendOtp']);
    Route::get('/check-subdomain', [RegistrationController::class, 'checkSubdomain']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public package list for the pricing/billing page
    Route::get('/billing/packages', [BillingController::class, 'packages']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Billing (owner-only internally)
        Route::post('/billing/checkout', [BillingController::class, 'checkout']);
        Route::get('/billing/status', [BillingController::class, 'status']);
        Route::post('/billing/portal', [BillingController::class, 'portal']);
    });
});
