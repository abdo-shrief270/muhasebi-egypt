<?php

use App\Modules\Installments\Http\Controllers\InstallmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:installments'])->prefix('installments')->group(function (): void {
    Route::pattern('plan', '[0-9a-fA-F-]{36}');

    Route::middleware('can:installments.collect')->group(function (): void {
        Route::get('/', [InstallmentController::class, 'index'])->name('installments.index');
        Route::get('due', [InstallmentController::class, 'due']);
        Route::get('{plan}', [InstallmentController::class, 'show'])->name('installments.show');
    });
    Route::get('available', [InstallmentController::class, 'available'])->middleware('can:installments.manage');
    Route::post('{plan}/cancel', [InstallmentController::class, 'cancel'])->middleware('can:installments.manage');

    // The form requests check installments.manage / installments.collect.
    Route::middleware('branch')->group(function (): void {
        Route::post('/', [InstallmentController::class, 'store']);
        Route::post('{plan}/payments', [InstallmentController::class, 'pay']);
    });
});
