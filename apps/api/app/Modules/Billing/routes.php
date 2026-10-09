<?php

use App\Modules\Billing\Http\Controllers\Admin\AdminAffiliateController;
use App\Modules\Billing\Http\Controllers\Admin\AdminAuthController;
use App\Modules\Billing\Http\Controllers\Admin\AdminCouponController;
use App\Modules\Billing\Http\Controllers\Admin\AdminFeedbackController;
use App\Modules\Billing\Http\Controllers\Admin\AdminModuleController;
use App\Modules\Billing\Http\Controllers\Admin\AdminMonitoringController;
use App\Modules\Billing\Http\Controllers\Admin\AdminPaymentController;
use App\Modules\Billing\Http\Controllers\Admin\AdminShopController;
use App\Modules\Billing\Http\Controllers\Affiliate\AffiliateAuthController;
use App\Modules\Billing\Http\Controllers\Affiliate\AffiliateController;
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
            Route::get('acquisition', 'acquisition');
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

        Route::controller(AdminModuleController::class)->group(function (): void {
            Route::get('modules', 'index');
            Route::patch('modules/{key}', 'update')->where('key', '[a-z_]+');
            Route::patch('features/{key}', 'feature')->where('key', '[a-z_]+\\.[a-z_]+');
            Route::get('shops/{tenant}/modules', 'shop');
            Route::post('shops/{tenant}/modules/{key}', 'setShop')->where('key', '[a-z_]+');
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

        Route::controller(AdminAffiliateController::class)->group(function (): void {
            Route::pattern('affiliate', '[0-9a-fA-F-]{36}');
            Route::pattern('payout', '[0-9a-fA-F-]{36}');
            Route::pattern('commission', '[0-9a-fA-F-]{36}');
            Route::get('affiliates', 'index');
            Route::get('affiliates/{affiliate}', 'show');
            Route::patch('affiliates/{affiliate}', 'update');
            Route::get('affiliate-payouts', 'payouts');
            Route::post('affiliate-payouts/{payout}/paid', 'markPaid');
            Route::post('affiliate-payouts/{payout}/reject', 'reject');
            Route::post('affiliate-commissions/{commission}/void', 'voidCommission');
        });

        Route::controller(AdminPaymentController::class)->group(function (): void {
            Route::get('payments', 'index');
            Route::get('payments/{paymentRequest}/proof', 'proof');
            Route::post('payments/{paymentRequest}/approve', 'approve');
            Route::post('payments/{paymentRequest}/reject', 'reject');
        });
    });
});

// Partners (برنامج الشركاء): people, not shops. Their own sign-in; the website counts their link's visits.
Route::prefix('affiliates')->group(function (): void {
    Route::post('register', [AffiliateAuthController::class, 'register'])->middleware('throttle:10,60');
    Route::post('login', [AffiliateAuthController::class, 'login'])->middleware('throttle:20,1');
    Route::middleware(['auth:sanctum', 'can:affiliate'])->group(function (): void {
        Route::post('logout', [AffiliateAuthController::class, 'logout']);
        Route::get('me', [AffiliateController::class, 'me']);
        Route::put('me', [AffiliateController::class, 'update'])->middleware('throttle:30,1');
        Route::post('payouts', [AffiliateController::class, 'requestPayout'])->middleware('throttle:10,1');
    });
});
Route::get('public/affiliates/program', [AffiliateController::class, 'program'])->middleware('throttle:60,1');
Route::post('public/affiliates/{code}/click', [AffiliateController::class, 'click'])->where('code', '[A-Za-z0-9]{4,20}')->middleware('throttle:60,1');
