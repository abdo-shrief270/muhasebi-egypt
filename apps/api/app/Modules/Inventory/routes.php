<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\PriceCheckController;
use App\Modules\Inventory\Http\Controllers\SerialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'branch'])->prefix('inventory')->controller(InventoryController::class)->group(function (): void {
    Route::middleware('can:inventory.view')->group(function (): void {
        Route::get('/', 'index');
        Route::get('summary', 'summary');
        Route::get('reasons', 'reasons');
        Route::get('variants/{variant}/movements', 'movements')->whereUuid('variant');
    });

    // inventory.adjust is checked by the form requests.
    Route::post('opening', 'opening');
    Route::post('adjustments', 'adjust');
});

// Quick price check from anywhere in the app (scan or type).
Route::middleware(['auth:sanctum', 'tenant', 'branch', 'can:products.view'])->get('inventory/price-check', PriceCheckController::class);

// Find a unit by IMEI / serial (inventory.view, sales.sell or sales.refund — checked in the controller).
Route::middleware(['auth:sanctum', 'tenant', 'branch'])->get('inventory/serials', SerialController::class);
