<?php

use App\Modules\Services\Http\Controllers\ServiceAccountController;
use App\Modules\Services\Http\Controllers\ServiceTransactionController;
use Illuminate\Support\Facades\Route;

// Wallets and airtime lines live in a branch, and their cash goes through the user's drawer there.
Route::middleware(['auth:sanctum', 'tenant', 'module:services', 'branch'])->prefix('services')->group(function (): void {
    Route::pattern('account', '[0-9a-fA-F-]{36}');
    Route::pattern('transaction', '[0-9a-fA-F-]{36}');

    Route::middleware('can:services.manage')->group(function (): void {
        Route::get('options', [ServiceAccountController::class, 'options']);
        Route::get('accounts', [ServiceAccountController::class, 'index']);
        Route::get('accounts/{account}', [ServiceAccountController::class, 'show']);
        Route::get('transactions', [ServiceTransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [ServiceTransactionController::class, 'show']);
    });

    // services.manage / fees / fund / settings are checked by the form requests.
    Route::post('accounts', [ServiceAccountController::class, 'store']);
    Route::patch('accounts/{account}', [ServiceAccountController::class, 'update']);
    Route::post('accounts/{account}/fund', [ServiceAccountController::class, 'fund']);
    Route::post('accounts/{account}/cash-out', [ServiceAccountController::class, 'cashOut']);
    Route::post('transactions', [ServiceTransactionController::class, 'store']);
    Route::post('transactions/{transaction}/reverse', [ServiceTransactionController::class, 'reverse']);
});
