<?php

use App\Modules\UsedDevices\Http\Controllers\SellerController;
use App\Modules\UsedDevices\Http\Controllers\UsedDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:used_devices'])->prefix('used-devices')->group(function (): void {
    Route::pattern('device', '[0-9a-fA-F-]{36}');
    Route::pattern('seller', '[0-9a-fA-F-]{36}');

    // Who sold what: national ID, phone and card photos (all branches).
    Route::prefix('sellers')->controller(SellerController::class)->group(function (): void {
        Route::middleware('can:used_devices.view_seller')->group(function (): void {
            Route::get('/', 'index');
            Route::get('{seller}', 'show');
        });
        Route::post('{seller}/erase', 'erase')->middleware('can:owner');
    });
    Route::middleware('can:owner')->controller(SellerController::class)->group(function (): void {
        Route::get('settings', 'settings');
        Route::put('settings', 'updateSettings');
    });

    Route::middleware('can:used_devices.manage')->controller(UsedDeviceController::class)->group(function (): void {
        Route::get('options', 'options');
        Route::get('device-models', 'deviceModels');
        Route::get('imei-check', 'imeiCheck');
        Route::get('national-id-check', 'nationalIdCheck')->middleware('throttle:60,1');
    });

    // Devices are bought into a branch's stock and drawer.
    Route::middleware('branch')->controller(UsedDeviceController::class)->group(function (): void {
        Route::middleware('can:used_devices.manage')->group(function (): void {
            Route::get('/', 'index');
            Route::get('{device}', 'show');
        });
        // used_devices.manage is checked by BuyDeviceRequest / in the controller; card photos also need used_devices.view_seller.
        Route::post('/', 'store')->middleware('throttle:30,1');
        Route::patch('{device}', 'update');
        Route::get('{device}/photos/{photo}', 'photo')->whereNumber('photo');
    });
});
