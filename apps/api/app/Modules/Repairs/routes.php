<?php

use App\Modules\Repairs\Http\Controllers\FaultCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:repairs'])->prefix('repairs')->group(function (): void {
    Route::get('fault-categories', [FaultCategoryController::class, 'index']);
});
