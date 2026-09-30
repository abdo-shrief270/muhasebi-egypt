<?php

use App\Modules\ModuleManager\Http\Controllers\FeatureController;
use App\Modules\ModuleManager\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('modules')->group(function (): void {
    Route::get('/', [ModuleController::class, 'index'])->middleware('can:owner');
    Route::post('{key}/trial', [ModuleController::class, 'trial']);
    Route::post('{key}/enable', [ModuleController::class, 'enable']);
    Route::post('{key}/disable', [ModuleController::class, 'disable']);
});

// Small switches inside the modules (owner).
Route::middleware(['auth:sanctum', 'tenant', 'can:owner'])->prefix('features')->group(function (): void {
    Route::get('/', [FeatureController::class, 'index']);
    Route::put('{key}', [FeatureController::class, 'update'])->where('key', '[a-z_]+\\.[a-z_]+');
});
