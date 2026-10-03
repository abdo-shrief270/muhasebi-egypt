<?php

use App\Modules\OnlineStore\Http\Controllers\PublicStoreController;
use App\Modules\OnlineStore\Http\Controllers\StoreSettingsController;
use App\Modules\OnlineStore\Http\Middleware\ResolveMediaShop;
use App\Modules\OnlineStore\Http\Middleware\ResolveStore;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:online_store', 'can:online_store.manage'])
    ->prefix('online-store')->controller(StoreSettingsController::class)->group(function (): void {
        Route::get('settings', 'show');
        Route::put('settings', 'update')->middleware('throttle:30,1');
        Route::post('media/{kind}', 'upload')->whereIn('kind', ['logo', 'cover'])->middleware('throttle:20,1');
        Route::delete('media/{kind}', 'removeMedia')->whereIn('kind', ['logo', 'cover']);
    });

// Caddy's on-demand TLS check for {slug}.<STORE_HOST> (?domain=…): 200 only for an open store.
Route::get('public/stores-tls', [PublicStoreController::class, 'tlsCheck'])->middleware([ResolveStore::class, 'module:online_store', 'throttle:store']);

// The public store (no login). The shop comes from the slug, then the module must be usable.
Route::get('public/media/stores/{path}', [PublicStoreController::class, 'media'])
    ->where('path', '[0-9a-f-]{36}/(logo|cover)-[a-z0-9]{16}-[0-9]+\.webp')
    ->middleware([ResolveMediaShop::class, 'module:online_store']);
Route::prefix('public/stores/{slug}')->where(['slug' => '[a-z0-9-]{3,40}'])
    ->middleware([ResolveStore::class, 'module:online_store', 'throttle:store'])
    ->controller(PublicStoreController::class)->group(function (): void {
        Route::get('/', 'show');
        Route::get('products', 'products');
        Route::get('products/{product}', 'product')->whereUuid('product');
        Route::get('index', 'index');
    });
