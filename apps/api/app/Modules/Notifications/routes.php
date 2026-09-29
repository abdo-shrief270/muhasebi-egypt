<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// The user's own bell; each notification carries the permission needed to see it (listed in RouteAuthorizationTest).
Route::middleware(['auth:sanctum', 'tenant'])->prefix('notifications')->controller(NotificationController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('unread', 'unread')->middleware('throttle:120,1');
    Route::post('read-all', 'readAll');
    Route::post('{notification}/read', 'read')->whereUuid('notification');
});
