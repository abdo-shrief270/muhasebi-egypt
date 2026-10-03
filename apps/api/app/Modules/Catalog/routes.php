<?php

use App\Modules\Catalog\Http\Controllers\BarcodeController;
use App\Modules\Catalog\Http\Controllers\CatalogListsController;
use App\Modules\Catalog\Http\Controllers\PriceController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductImageController;
use App\Modules\Catalog\Http\Controllers\ProductImportController;
use Illuminate\Support\Facades\Route;

// Product photos are public (online store, shared links): no shop or login needed to read them.
Route::get('public/media/{path}', [ProductImageController::class, 'media'])->where('path', 'products/[0-9a-f/-]+-[0-9]+\\.webp');

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::pattern('product', '[0-9a-fA-F-]{36}');
    Route::pattern('image', '[0-9a-fA-F-]{36}');
    Route::pattern('category', '[0-9]+');
    Route::pattern('brand', '[0-9]+');
    Route::pattern('deviceModel', '[0-9]+');

    // The quantity column is opening stock for the branch in X-Branch-Id.
    Route::prefix('products/import')->middleware(['branch', 'feature:catalog.excel_import'])->controller(ProductImportController::class)->group(function (): void {
        Route::get('template', 'template')->middleware('can:products.manage');
        // products.manage is checked by ImportProductsRequest.
        Route::post('preview', 'preview')->middleware('throttle:30,1');
        Route::post('/', 'store')->middleware('throttle:10,1');
    });

    Route::get('products/labels', [BarcodeController::class, 'variants'])->middleware(['can:products.view', 'feature:catalog.labels']);
    Route::post('products/barcodes', [BarcodeController::class, 'generate'])->middleware('can:products.manage');

    // products.manage (and products.view_cost to start from the cost) is checked by BulkPriceRequest.
    Route::prefix('products/prices')->middleware(['branch', 'feature:catalog.bulk_prices'])->controller(PriceController::class)->group(function (): void {
        Route::post('preview', 'preview')->middleware('throttle:60,1');
        Route::post('/', 'apply')->middleware('throttle:20,1');
    });
    Route::get('products/{product}/prices', [PriceController::class, 'history'])->middleware('can:products.manage');

    Route::prefix('products')->group(function (): void {
        Route::middleware('can:products.view')->group(function (): void {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('barcode/{code}', [ProductController::class, 'barcode'])->where('code', '[^/]{1,64}');
            Route::get('{product}', [ProductController::class, 'show']);
        });
        // products.manage is checked by SaveProductRequest.
        Route::post('/', [ProductController::class, 'store']);
        Route::patch('{product}', [ProductController::class, 'update']);
        // products.manage is checked in the controller.
        Route::middleware('throttle:60,1')->group(function (): void {
            Route::post('{product}/images', [ProductImageController::class, 'store']);
            Route::put('{product}/images/order', [ProductImageController::class, 'order']);
            Route::delete('{product}/images/{image}', [ProductImageController::class, 'destroy']);
        });
        Route::patch('online', [ProductController::class, 'online'])->middleware('can:products.manage');
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
