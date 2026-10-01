<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\PushController;
use Illuminate\Support\Facades\Route;

// The user's own bell; each notification carries the permission needed to see it (listed in RouteAuthorizationTest).
Route::middleware(['auth:sanctum', 'tenant'])->prefix('notifications')->controller(NotificationController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('unread', 'unread')->middleware('throttle:120,1');
    Route::post('read-all', 'readAll');
    Route::post('{notification}/read', 'read')->whereUuid('notification');
});

// The user's own push devices and preferences (own-account routes, listed in RouteAuthorizationTest).
Route::middleware(['auth:sanctum', 'tenant'])->controller(PushController::class)->group(function (): void {
    Route::get('notifications/preferences', 'preferences');
    Route::put('notifications/preferences', 'updatePreferences');
    Route::get('push/key', 'key');
    Route::post('push/subscriptions', 'subscribe')->middleware('throttle:20,1');
    Route::delete('push/subscriptions', 'unsubscribe');
    Route::post('push/test', 'test')->middleware('throttle:5,1');
});
