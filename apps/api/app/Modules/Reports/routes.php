<?php

use App\Modules\Reports\Http\Controllers\OwnerController;
use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'can:reports.view'])->prefix('reports')->controller(ReportController::class)->group(function (): void {
    Route::pattern('report', '[a-z_]+');

    Route::get('/', 'index');
    Route::get('{report}', 'show');
    Route::get('{report}/export', 'export')->middleware('feature:reports.excel_export');
});

// The owner app's screens: today so far and the live feed (owner_app.alerts: owner and managers).
Route::middleware(['auth:sanctum', 'tenant', 'module:owner_app', 'can:owner_app.alerts'])->prefix('owner')->controller(OwnerController::class)->group(function (): void {
    Route::get('today', 'today')->middleware('throttle:60,1');
    Route::get('feed', 'feed')->middleware('throttle:120,1');
});
