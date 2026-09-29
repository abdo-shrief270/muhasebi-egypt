<?php

use App\Modules\Customers\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('customers')->controller(CustomerController::class)->group(function (): void {
    Route::pattern('customer', '[0-9a-fA-F-]{36}');

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
