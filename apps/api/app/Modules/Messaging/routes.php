<?php

use App\Modules\Messaging\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('messages')->controller(MessageController::class)->group(function (): void {
    Route::pattern('key', '[a-z_]{3,40}');

    // Every WhatsApp button reads the wording (listed in RouteAuthorizationTest).
    Route::get('templates', 'templates');
    Route::middleware('can:messages.templates')->group(function (): void {
        Route::put('templates/{key}', 'update');
        Route::delete('templates/{key}', 'reset');
    });

    Route::middleware('can:messages.send')->group(function (): void {
        Route::post('log', 'log')->middleware('throttle:120,1');
        Route::get('log', 'history');
    });
});
