<?php

use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stripe webhook (signature-verified by the controller's middleware).
// CSRF is bypassed: Stripe does not send a CSRF token; the Stripe signature
// is the authentication. Cashier's own route is disabled (Cashier::ignoreRoutes)
// because we need restaurant-scoped handling.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
