<?php

use App\Modules\OwnerApp\Http\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:owner_app'])->prefix('approvals')->controller(ApprovalController::class)->group(function (): void {
    Route::pattern('approval', '[0-9a-fA-F-]{36}');

    // Whoever works a drawer and was refused with approval_required may ask (the signed token says what for, and for whom).
    Route::post('/', 'store')->middleware(['can:cash.shift', 'throttle:20,1']);
    Route::post('pin', 'pin')->middleware(['can:cash.shift', 'throttle:6,1']);
    Route::get('{approval}', 'show')->middleware('can:cash.shift');

    Route::middleware('can:owner_app.approve')->group(function (): void {
        Route::get('/', 'index');
        Route::post('{approval}/approve', 'approve');
        Route::post('{approval}/deny', 'deny');
    });
});
