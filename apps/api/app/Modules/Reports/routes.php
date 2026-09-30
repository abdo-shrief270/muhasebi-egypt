<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'can:reports.view'])->prefix('reports')->controller(ReportController::class)->group(function (): void {
    Route::pattern('report', '[a-z_]+');

    Route::get('/', 'index');
    Route::get('{report}', 'show');
    Route::get('{report}/export', 'export')->middleware('feature:reports.excel_export');
});
