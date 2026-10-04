<?php

// API routes live in each module: app/Modules/{Module}/routes.php (prefixed with /api/v1).

use App\Support\Monitoring\Health;
use Illuminate\Support\Facades\Route;

// For the uptime monitor / monitor.sh: 200 when the database, cache, queue, scheduler and disk are fine, else 503.
Route::get('v1/health', function (Health $health) {
    $result = $health->check();

    return response()->json($result, $result['ok'] ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:60,1');
