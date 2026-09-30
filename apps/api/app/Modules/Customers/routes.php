<?php

use App\Modules\Customers\Http\Controllers\CustomerController;
use App\Modules\Customers\Http\Controllers\CustomerPrivacyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('customers')->group(function (): void {
    Route::pattern('customer', '[0-9a-fA-F-]{36}');

    Route::controller(CustomerController::class)->group(function (): void {
        Route::middleware('can:customers.view')->group(function (): void {
            Route::get('/', 'index');
            Route::get('{customer}', 'show');
            Route::get('{customer}/statement', 'statement');
        });
        // customers.manage / customers.credit are checked by the form requests.
        Route::post('/', 'store');
        Route::patch('{customer}', 'update');
        Route::post('{customer}/payments', 'pay')->middleware('branch');
    });

    // Personal data (Law 151/2020): export with customers.export (owner and managers); erasure and retention for the owner.
    Route::controller(CustomerPrivacyController::class)->group(function (): void {
        Route::get('{customer}/export', 'export')->middleware('can:customers.export');
        Route::post('{customer}/erase', 'erase')->middleware('can:owner');
        Route::middleware('can:owner')->group(function (): void {
            Route::get('privacy-settings', 'settings');
            Route::put('privacy-settings', 'updateSettings');
        });
    });
});
