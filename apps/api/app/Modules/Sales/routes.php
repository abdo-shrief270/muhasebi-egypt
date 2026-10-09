<?php

use App\Modules\Sales\Http\Controllers\PosController;
use App\Modules\Sales\Http\Controllers\PublicReceiptController;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Sales\Http\Controllers\SalesStatsController;
use Illuminate\Support\Facades\Route;

// The customer's receipt behind the QR code: no login, a random 32-character token.
Route::get('public/receipts/{token}', [PublicReceiptController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::pattern('sale', '[0-9a-fA-F-]{36}');

    Route::get('sales/stats', SalesStatsController::class)->middleware('can:reports.view');

    Route::middleware('branch')->group(function (): void {
        Route::prefix('pos')->controller(PosController::class)->middleware('can:sales.sell')->group(function (): void {
            Route::get('items', 'items');
            Route::get('options', 'options');
            Route::get('catalog', 'catalog');
            Route::get('last-prices', 'lastPrices');
        });

        Route::prefix('sales')->controller(SaleController::class)->group(function (): void {
            Route::middleware('can:sales.view')->group(function (): void {
                Route::get('/', 'index');
                Route::get('{sale}', 'show');
            });
            // sales.sell / sales.refund are checked by the form requests.
            Route::post('/', 'store');
            Route::post('{sale}/returns', 'return')->middleware('feature:sales.returns');
        });
    });
});
