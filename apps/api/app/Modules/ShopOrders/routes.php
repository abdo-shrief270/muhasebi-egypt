<?php

use App\Modules\ShopOrders\Http\Controllers\ConnectionController;
use App\Modules\ShopOrders\Http\Controllers\ShopOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:shop_orders'])->group(function (): void {
    Route::pattern('connection', '[0-9a-fA-F-]{36}');
    Route::pattern('order', '[0-9a-fA-F-]{36}');

    Route::prefix('shop-connections')->group(function (): void {
        Route::get('/', [ConnectionController::class, 'index']);
        Route::post('/', [ConnectionController::class, 'store'])->middleware('throttle:20,1');
        Route::post('{connection}/accept', [ConnectionController::class, 'accept']);
        Route::post('{connection}/decline', [ConnectionController::class, 'decline']);
    });

    Route::prefix('shop-orders')->group(function (): void {
        Route::get('/', [ShopOrderController::class, 'index']);
        Route::post('/', [ShopOrderController::class, 'store']);
        Route::get('{order}', [ShopOrderController::class, 'show']);
        Route::post('{order}/transition', [ShopOrderController::class, 'transition']);
    });
});
