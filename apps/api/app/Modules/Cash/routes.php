<?php

use App\Modules\Cash\Http\Controllers\CashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('cash')->controller(CashController::class)->group(function (): void {
    Route::pattern('shift', '[0-9a-fA-F-]{36}');

    Route::get('summary', 'summary')->middleware('can:cash.manage');
    Route::get('options', 'options')->middleware('can:cash.shift');
    Route::get('shifts/{shift}', 'show')->middleware('can:cash.shift');
    // Anyone's for cash.manage, your own for cash.shift: checked by the form request.
    Route::post('shifts/{shift}/close', 'close');

    // A drawer lives in a branch.
    Route::middleware('branch')->group(function (): void {
        Route::middleware('can:cash.shift')->group(function (): void {
            Route::get('current', 'current');
            Route::get('shifts', 'index');
        });
        // cash.shift / cash.expenses / cash.manage are checked by the form requests.
        Route::post('shifts', 'open');
        Route::post('movements', 'movement');
    });
});
