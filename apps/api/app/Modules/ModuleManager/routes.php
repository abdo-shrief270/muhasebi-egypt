<?php

use App\Modules\ModuleManager\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('modules')->group(function (): void {
    Route::get('/', [ModuleController::class, 'index']);
    Route::post('{key}/trial', [ModuleController::class, 'trial']);
    Route::post('{key}/enable', [ModuleController::class, 'enable']);
    Route::post('{key}/disable', [ModuleController::class, 'disable']);
});
