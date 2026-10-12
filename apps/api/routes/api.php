<?php

// API routes live in each module: app/Modules/{Module}/routes.php (prefixed with /api/v1).

use App\Support\Monitoring\Diagnostics;
use App\Support\Monitoring\Health;
use Illuminate\Support\Facades\Route;

// For the uptime monitor / monitor.sh: 200 when the database, cache, queue, scheduler and disk are fine, else 503.
Route::get('v1/health', function (Health $health) {
    $result = $health->check();

    return response()->json($result, $result['ok'] ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:60,1');

// For testing a monitor from outside (DIAGNOSTICS_TOKEN set, else 404): a 5xx, a slow answer, no answer.
Route::prefix('v1/diagnostics')->middleware('throttle:10,1')->group(function (): void {
    Route::get('error', [Diagnostics::class, 'error']);
    Route::get('slow', [Diagnostics::class, 'slow']);
    Route::get('hang', [Diagnostics::class, 'hang']);
});
