<?php

use App\Modules\Catalog\Http\Controllers\CatalogListsController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::pattern('product', '[0-9a-fA-F-]{36}');
    Route::pattern('category', '[0-9]+');
    Route::pattern('brand', '[0-9]+');
    Route::pattern('deviceModel', '[0-9]+');

    Route::prefix('products')->group(function (): void {
        Route::middleware('can:products.view')->group(function (): void {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('barcode/{code}', [ProductController::class, 'barcode'])->where('code', '[^/]{1,64}');
            Route::get('{product}', [ProductController::class, 'show']);
        });
        // products.manage is checked by SaveProductRequest.
        Route::post('/', [ProductController::class, 'store']);
        Route::patch('{product}', [ProductController::class, 'update']);
    });

    Route::prefix('catalog')->name('catalog.')->controller(CatalogListsController::class)->group(function (): void {
        Route::middleware('can:products.view')->group(function (): void {
            Route::get('categories', 'categories');
            Route::get('brands', 'brands');
            Route::get('device-models', 'models');
        });

        // products.manage is checked by SaveCatalogEntryRequest; deletes check it here.
        Route::post('categories', 'storeCategory')->name('categories.store');
        Route::patch('categories/{category}', 'updateCategory')->name('categories.update');
        Route::delete('categories/{category}', 'destroyCategory')->middleware('can:products.manage');

        Route::post('brands', 'storeBrand')->name('brands.store');
        Route::patch('brands/{brand}', 'updateBrand')->name('brands.update');
        Route::delete('brands/{brand}', 'destroyBrand')->middleware('can:products.manage');

        Route::post('brands/{brand}/models', 'storeModel')->name('models.store');
        Route::patch('device-models/{deviceModel}', 'updateModel')->name('models.update');
        Route::delete('device-models/{deviceModel}', 'destroyModel')->middleware('can:products.manage');
    });
});
