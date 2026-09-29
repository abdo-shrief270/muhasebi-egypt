<?php

use App\Modules\Repairs\Http\Controllers\FaultCategoryController;
use App\Modules\Repairs\Http\Controllers\PublicTicketController;
use App\Modules\Repairs\Http\Controllers\TicketController;
use App\Modules\Repairs\Http\Middleware\ResolveTicketShop;
use Illuminate\Support\Facades\Route;

// The tracking link on the intake receipt: no login.
Route::middleware(['throttle:60,1', ResolveTicketShop::class, 'module:repairs'])
    ->get('public/repairs/{token}', [PublicTicketController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{16,40}');

Route::middleware(['auth:sanctum', 'tenant', 'module:repairs'])->prefix('repairs')->group(function (): void {
    Route::pattern('ticket', '[0-9a-fA-F-]{36}');

    Route::get('fault-categories', [FaultCategoryController::class, 'index'])->middleware('can:repairs.view');
    Route::middleware('can:repairs.settings')->group(function (): void {
        Route::post('fault-categories', [FaultCategoryController::class, 'storeCategory']);
        Route::patch('fault-categories/{category}', [FaultCategoryController::class, 'updateCategory'])->whereNumber('category');
        Route::post('fault-categories/{category}/types', [FaultCategoryController::class, 'storeType'])->whereNumber('category');
        Route::patch('fault-types/{type}', [FaultCategoryController::class, 'updateType'])->whereNumber('type');
    });

    // Tickets live in a branch.
    Route::middleware('branch')->controller(TicketController::class)->group(function (): void {
        Route::middleware('can:repairs.view')->group(function (): void {
            Route::get('tickets', 'index');
            Route::get('summary', 'summary');
            Route::get('options', 'options');
            Route::get('tickets/{ticket}', 'show');
        });
        // repairs.create / update_status / deliver are checked by the form requests or the actions.
        Route::post('tickets', 'store');
        Route::patch('tickets/{ticket}', 'update');
        Route::post('tickets/{ticket}/status', 'status');
        Route::post('tickets/{ticket}/parts', 'addPart');
        Route::delete('tickets/{ticket}/parts/{part}', 'removePart')->whereNumber('part');
        Route::post('tickets/{ticket}/deliver', 'deliver');
        Route::post('tickets/{ticket}/warranty', 'warranty');
    });
});
