<?php

use App\Modules\Marketplace\Http\Controllers\MarketLinksController;
use App\Modules\Marketplace\Http\Controllers\MarketSettingsController;
use App\Modules\Marketplace\Http\Controllers\PublicMarketController;
use Illuminate\Support\Facades\Route;

// The shop: how it shows in «سوق محاسبي» (every plan includes it).
Route::middleware(['auth:sanctum', 'tenant', 'can:marketplace.manage'])
    ->prefix('marketplace')->controller(MarketSettingsController::class)->group(function (): void {
        Route::get('settings', 'show');
        Route::put('settings', 'update')->middleware('throttle:30,1');
    });

// The shop's phones ↔ the platform's phone catalog («اربط موبايلاتك»).
Route::middleware(['auth:sanctum', 'tenant', 'can:marketplace.manage'])
    ->prefix('marketplace/phones')->controller(MarketLinksController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('catalog', 'catalog');
        Route::post('confirm', 'confirm')->middleware('throttle:30,1');
        Route::put('{variant}', 'update')->whereUuid('variant')->middleware('throttle:120,1');
        Route::delete('{variant}', 'destroy')->whereUuid('variant')->middleware('throttle:120,1');
    });

// The marketplace site (no login).
Route::prefix('public/market')->middleware('throttle:market')->controller(PublicMarketController::class)->group(function (): void {
    Route::get('search', 'search');
    Route::get('options', 'options');
    Route::get('shops', 'shops');
    Route::get('shops/{slug}', 'shop')->where('slug', '[a-z0-9]{3,16}');
    Route::get('offers/{id}', 'offer')->where('id', '[A-Za-z0-9:_-]{1,120}');
    Route::get('phones', 'phones');
    Route::get('phones/{slug}', 'phone')->where('slug', '[a-z0-9-]{1,140}');
});
