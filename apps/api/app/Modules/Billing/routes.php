<?php

use App\Modules\Billing\Http\Controllers\Admin\AdminAuthController;
use App\Modules\Billing\Http\Controllers\Admin\AdminCouponController;
use App\Modules\Billing\Http\Controllers\Admin\AdminFeedbackController;
use App\Modules\Billing\Http\Controllers\Admin\AdminMonitoringController;
use App\Modules\Billing\Http\Controllers\Admin\AdminPaymentController;
use App\Modules\Billing\Http\Controllers\Admin\AdminShopController;
use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\PublicPlansController;
use App\Modules\Billing\Http\Middleware\AdminGate;
use Illuminate\Support\Facades\Route;

// Plans and prices for the public website (no sign-in).
Route::get('public/plans', PublicPlansController::class)->middleware('throttle:60,1');

// The shop's subscription.
Route::middleware(['auth:sanctum', 'tenant'])->prefix('billing')->controller(BillingController::class)->group(function (): void {
    Route::pattern('paymentRequest', '[0-9a-fA-F-]{36}');
    Route::pattern('invoice', '[0-9a-fA-F-]{36}');

    // Every screen shows the status (listed in RouteAuthorizationTest).
    Route::get('status', 'status');
    Route::middleware('can:owner')->group(function (): void {
        Route::get('/', 'show');
        Route::post('quote', 'quote');
        Route::post('requests/{paymentRequest}/cancel', 'cancelRequest');
        Route::post('pay-with-credit', 'payWithCredit')->middleware('throttle:10,1');
        Route::post('coupon', 'redeem')->middleware('throttle:10,1');
        Route::post('points/convert', 'convertPoints')->middleware('throttle:20,1');
        Route::get('invoices/{invoice}', 'invoice');
    });
    // Owner only, checked in the controller (multipart upload).
    Route::post('requests', 'requestPayment')->middleware('throttle:10,1');
});

// Platform admins (super admin): a separate sign-in, no shop; only on the admin domain / IPs.
Route::prefix('admin')->middleware(AdminGate::class)->group(function (): void {
    Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'can:platform-admin'])->group(function (): void {
        Route::pattern('tenant', '[0-9a-fA-F-]{36}');
        Route::pattern('paymentRequest', '[0-9a-fA-F-]{36}');

        Route::get('auth/me', [AdminAuthController::class, 'me']);
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('activity', [AdminAuthController::class, 'activity']);

        Route::controller(AdminShopController::class)->group(function (): void {
            Route::get('overview', 'overview');
            Route::get('shops', 'index');
            Route::get('shops/{tenant}', 'show');
            Route::post('shops/{tenant}/activate', 'activate');
            Route::post('shops/{tenant}/trial', 'extendTrial');
            Route::post('shops/{tenant}/beta', 'grantBeta');
            Route::post('shops/{tenant}/suspend', 'suspend');
            Route::post('shops/{tenant}/unsuspend', 'unsuspend');
        });

        Route::controller(AdminCouponController::class)->group(function (): void {
            Route::get('coupons', 'index');
            Route::post('coupons', 'store');
            Route::patch('coupons/{coupon}', 'update')->whereUuid('coupon');
            Route::post('shops/{tenant}/wallet', 'grant');
        });

        Route::controller(AdminMonitoringController::class)->group(function (): void {
            Route::get('health', 'health');
            Route::get('server-errors', 'errors');
            Route::post('server-errors/{error}/resolve', 'resolve')->whereUuid('error');
        });

        Route::controller(AdminFeedbackController::class)->group(function (): void {
            Route::pattern('feedback', '[0-9a-fA-F-]{36}');
            Route::get('feedback', 'index');
            Route::patch('feedback/{feedback}', 'update');
            Route::get('feedback/{feedback}/screenshot', 'screenshot');
            Route::get('client-errors', 'errors');
            Route::post('client-errors/{fingerprint}/resolve', 'resolve')->where('fingerprint', '[0-9a-f]{40}');
        });

        Route::controller(AdminPaymentController::class)->group(function (): void {
            Route::get('payments', 'index');
            Route::get('payments/{paymentRequest}/proof', 'proof');
            Route::post('payments/{paymentRequest}/approve', 'approve');
            Route::post('payments/{paymentRequest}/reject', 'reject');
        });
    });
});
