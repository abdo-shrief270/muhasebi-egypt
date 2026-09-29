<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
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
