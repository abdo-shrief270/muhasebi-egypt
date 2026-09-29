<?php

use App\Modules\Suppliers\Http\Controllers\PurchaseController;
use App\Modules\Suppliers\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::pattern('supplier', '[0-9a-fA-F-]{36}');
    Route::pattern('purchase', '[0-9a-fA-F-]{36}');

    Route::prefix('suppliers')->controller(SupplierController::class)->group(function (): void {
        Route::middleware('can:suppliers.view')->group(function (): void {
            Route::get('/', 'index');
            Route::get('payment-methods', 'paymentMethods');
            Route::get('{supplier}', 'show');
            Route::get('{supplier}/statement', 'statement');
        });
        // suppliers.manage is checked by the form requests.
        Route::post('/', 'store');
        Route::patch('{supplier}', 'update');
        Route::post('{supplier}/payments', 'pay')->middleware('branch');
    });

    // Purchases move stock, so they happen in a branch.
    Route::prefix('purchases')->middleware('branch')->controller(PurchaseController::class)->group(function (): void {
        Route::middleware('can:suppliers.view')->group(function (): void {
            Route::get('/', 'index');
            Route::get('variants', 'variants')->middleware('can:suppliers.manage');
            Route::get('{purchase}', 'show');
        });
        Route::post('/', 'store');
        Route::post('{purchase}/returns', 'return');
    });
});
