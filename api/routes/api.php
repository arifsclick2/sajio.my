<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RestaurantTableController;
use App\Http\Controllers\Api\V1\TableSessionController;
use App\Http\Controllers\Api\V1\TableTagController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\Admin\CouponAdminController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\PackageAdminController;
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

        // Attendance — self service (staff / manager)
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);

        // Attendance + shifts + staff — owner / manager
        Route::middleware('role:owner,manager')->group(function (): void {
            Route::get('/attendance', [AttendanceController::class, 'index']);
            Route::get('/attendance/on-duty', [AttendanceController::class, 'onDuty']);

            Route::get('/shifts', [ShiftController::class, 'index']);
            Route::post('/shifts', [ShiftController::class, 'store']);
            Route::put('/shifts/{shift}', [ShiftController::class, 'update']);
            Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
        });

        // Table sessions — all restaurant roles (staff/cashier/manager/owner)
        Route::post('/sessions/open', [TableSessionController::class, 'open']);
        Route::post('/sessions/scan-tag', [TableSessionController::class, 'scanTag']);
        Route::get('/sessions/open-sessions', [TableSessionController::class, 'openSessions']);
        Route::get('/sessions/table/{table}', [TableSessionController::class, 'forTable']);
        Route::post('/sessions/{session}/close', [TableSessionController::class, 'close']);

        // Menu + Tables (owner/manager)
        Route::middleware('role:owner,manager')->group(function (): void {
            Route::get('/menu/categories', [MenuController::class, 'categories']);
            Route::post('/menu/categories', [MenuController::class, 'storeCategory']);
            Route::put('/menu/categories/{category}', [MenuController::class, 'updateCategory']);
            Route::delete('/menu/categories/{category}', [MenuController::class, 'destroyCategory']);

            Route::get('/menu/products', [MenuController::class, 'products']);
            Route::post('/menu/products', [MenuController::class, 'storeProduct']);
            Route::put('/menu/products/{product}', [MenuController::class, 'updateProduct']);
            Route::delete('/menu/products/{product}', [MenuController::class, 'destroyProduct']);

            Route::get('/tables', [RestaurantTableController::class, 'index']);
            Route::post('/tables', [RestaurantTableController::class, 'store']);
            Route::post('/tables/bulk', [RestaurantTableController::class, 'bulkStore']);
            Route::put('/tables/{table}', [RestaurantTableController::class, 'update']);
            Route::post('/tables/{table}/regenerate-token', [RestaurantTableController::class, 'regenerateToken']);
            Route::delete('/tables/{table}', [RestaurantTableController::class, 'destroy']);

            // Table Tags (owner/manager; Pro feature gated client-side)
            Route::get('/table-tags', [TableTagController::class, 'index']);
            Route::post('/table-tags', [TableTagController::class, 'store']);
            Route::put('/table-tags/{tableTag}', [TableTagController::class, 'update']);
            Route::post('/table-tags/{tableTag}/assign', [TableTagController::class, 'assign']);
            Route::post('/table-tags/{tableTag}/unassign', [TableTagController::class, 'unassign']);
            Route::post('/table-tags/{tableTag}/regenerate-token', [TableTagController::class, 'regenerateToken']);
            Route::delete('/table-tags/{tableTag}', [TableTagController::class, 'destroy']);
        });

        // Profile & branding (owner/manager)
        Route::middleware('role:owner,manager')->group(function (): void {
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile/settings', [ProfileController::class, 'updateSettings']);
            Route::put('/profile/branding', [ProfileController::class, 'updateBranding']);
        });

        // ---- Super Admin ----
        Route::middleware('role:super_admin')->prefix('admin')->group(function (): void {
            Route::get('/stats', [OverviewController::class, 'stats']);
            Route::get('/restaurants', [OverviewController::class, 'restaurants']);
            Route::put('/restaurants/{restaurant}/status', [OverviewController::class, 'setRestaurantStatus']);
            Route::post('/packages/sync-stripe', [OverviewController::class, 'syncPackagesToStripe']);

            Route::get('/packages', [PackageAdminController::class, 'index']);
            Route::post('/packages', [PackageAdminController::class, 'store']);
            Route::put('/packages/{package}', [PackageAdminController::class, 'update']);

            Route::get('/coupons', [CouponAdminController::class, 'index']);
            Route::post('/coupons', [CouponAdminController::class, 'store']);
            Route::put('/coupons/{coupon}', [CouponAdminController::class, 'update']);
            Route::delete('/coupons/{coupon}', [CouponAdminController::class, 'destroy']);
        });
    });
});
