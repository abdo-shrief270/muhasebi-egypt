<?php

use App\Modules\ShopOrders\Http\Controllers\ConnectionController;
use App\Modules\ShopOrders\Http\Controllers\ShopOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:shop_orders'])->group(function (): void {
    Route::pattern('connection', '[0-9a-fA-F-]{36}');
    Route::pattern('order', '[0-9a-fA-F-]{36}');

    Route::prefix('shop-connections')->group(function (): void {
        Route::get('/', [ConnectionController::class, 'index'])->middleware('can:shop_orders.view');
        Route::middleware('can:shop_orders.partners')->group(function (): void {
            Route::post('/', [ConnectionController::class, 'store'])->middleware('throttle:20,1');
            Route::post('{connection}/accept', [ConnectionController::class, 'accept']);
            Route::post('{connection}/decline', [ConnectionController::class, 'decline']);
        });
    });

    Route::prefix('shop-orders')->group(function (): void {
        Route::get('/', [ShopOrderController::class, 'index'])->middleware('can:shop_orders.view');
        Route::post('/', [ShopOrderController::class, 'store'])->middleware('can:shop_orders.place');
        Route::get('{order}', [ShopOrderController::class, 'show'])->middleware('can:shop_orders.view');
        // The permission depends on which side of the order the shop is on; see TransitionOrderRequest.
        Route::post('{order}/transition', [ShopOrderController::class, 'transition']);
    });
});
