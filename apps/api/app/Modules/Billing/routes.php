<?php

use App\Modules\Billing\Http\Controllers\Admin\AdminAuthController;
use App\Modules\Billing\Http\Controllers\Admin\AdminPaymentController;
use App\Modules\Billing\Http\Controllers\Admin\AdminShopController;
use App\Modules\Billing\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

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
        Route::get('invoices/{invoice}', 'invoice');
    });
    // Owner only, checked in the controller (multipart upload).
    Route::post('requests', 'requestPayment')->middleware('throttle:10,1');
});

// Platform admins (super admin): a separate sign-in, no shop.
Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'can:platform-admin'])->group(function (): void {
        Route::pattern('tenant', '[0-9a-fA-F-]{36}');
        Route::pattern('paymentRequest', '[0-9a-fA-F-]{36}');

        Route::get('auth/me', [AdminAuthController::class, 'me']);
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);

        Route::controller(AdminShopController::class)->group(function (): void {
            Route::get('overview', 'overview');
            Route::get('shops', 'index');
            Route::get('shops/{tenant}', 'show');
            Route::post('shops/{tenant}/activate', 'activate');
            Route::post('shops/{tenant}/trial', 'extendTrial');
            Route::post('shops/{tenant}/suspend', 'suspend');
            Route::post('shops/{tenant}/unsuspend', 'unsuspend');
        });

        Route::controller(AdminPaymentController::class)->group(function (): void {
            Route::get('payments', 'index');
            Route::get('payments/{paymentRequest}/proof', 'proof');
            Route::post('payments/{paymentRequest}/approve', 'approve');
            Route::post('payments/{paymentRequest}/reject', 'reject');
        });
    });
});
