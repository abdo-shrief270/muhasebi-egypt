<?php

use App\Modules\Marketplace\Http\Controllers\MarketSettingsController;
use App\Modules\Marketplace\Http\Controllers\PublicMarketController;
use Illuminate\Support\Facades\Route;

// The shop: how it shows in «سوق محاسبي» (every plan includes it).
Route::middleware(['auth:sanctum', 'tenant', 'can:marketplace.manage'])
    ->prefix('marketplace')->controller(MarketSettingsController::class)->group(function (): void {
        Route::get('settings', 'show');
        Route::put('settings', 'update')->middleware('throttle:30,1');
    });

// The marketplace site (no login).
Route::prefix('public/market')->middleware('throttle:market')->controller(PublicMarketController::class)->group(function (): void {
    Route::get('search', 'search');
    Route::get('options', 'options');
});
