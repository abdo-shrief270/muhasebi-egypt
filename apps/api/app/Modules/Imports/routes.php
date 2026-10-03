<?php

use App\Modules\Imports\Http\Controllers\ContactController;
use App\Modules\Imports\Http\Controllers\PaymentController;
use App\Modules\Imports\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:imports'])->prefix('imports')->group(function (): void {
    // Looking.
    Route::middleware('can:imports.view')->group(function (): void {
        Route::get('summary', [ShipmentController::class, 'summary']);
        Route::get('contacts', [ContactController::class, 'index']);
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->whereUuid('contact');
        Route::get('shipments', [ShipmentController::class, 'index']);
        Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->whereUuid('shipment');
        Route::get('shipments/{shipment}/attachments/{attachment}', [ShipmentController::class, 'attachment'])->whereUuid(['shipment', 'attachment']);
        Route::get('payments/{payment}/proof', [PaymentController::class, 'proof'])->whereUuid('payment');
    });

    // Doing.
    Route::middleware('can:imports.manage')->group(function (): void {
        Route::get('variants', [ShipmentController::class, 'variants']);
        Route::post('contacts', [ContactController::class, 'store']);
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->whereUuid('contact');
        Route::controller(ShipmentController::class)->prefix('shipments')->group(function (): void {
            Route::post('/', 'store');
            Route::put('{shipment}', 'update')->whereUuid('shipment');
            Route::post('{shipment}/status', 'move')->whereUuid('shipment');
            Route::post('{shipment}/costs', 'addCost')->whereUuid('shipment');
            Route::delete('{shipment}/costs/{cost}', 'removeCost')->whereUuid(['shipment', 'cost']);
            Route::post('{shipment}/receive', 'receive')->whereUuid('shipment');
            Route::post('{shipment}/cancel', 'cancel')->whereUuid('shipment');
            Route::post('{shipment}/attachments', 'upload')->whereUuid('shipment')->middleware('throttle:30,1');
            Route::delete('{shipment}/attachments/{attachment}', 'removeAttachment')->whereUuid(['shipment', 'attachment']);
        });
        Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:30,1');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->whereUuid('payment');
    });
});
