<?php

use App\Modules\SupplierReturns\Http\Controllers\BinController;
use App\Modules\SupplierReturns\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

// The returns bin and return notes live in a branch (like its stock).
Route::middleware(['auth:sanctum', 'tenant', 'branch', 'module:supplier_returns'])->prefix('supplier-returns')->group(function (): void {
    Route::pattern('item', '[0-9a-fA-F-]{36}');
    Route::pattern('note', '[0-9a-fA-F-]{36}');

    Route::middleware('can:supplier_returns.view')->group(function (): void {
        Route::get('bin', [BinController::class, 'index']);
        Route::get('notes', [NoteController::class, 'index']);
        Route::get('notes/{note}', [NoteController::class, 'show']);
    });
    Route::get('sources', [BinController::class, 'sources'])->middleware('can:supplier_returns.manage');

    // supplier_returns.manage is checked by the form requests / controllers.
    Route::post('bin', [BinController::class, 'store']);
    Route::patch('bin/{item}', [BinController::class, 'update']);
    Route::post('bin/{item}/restock', [BinController::class, 'restock']);
    Route::post('bin/{item}/write-off', [BinController::class, 'writeOff']);
    Route::post('notes', [NoteController::class, 'store']);
    Route::post('notes/{note}/send', [NoteController::class, 'send']);
    Route::post('notes/{note}/cancel', [NoteController::class, 'cancel']);
    Route::post('notes/{note}/settle', [NoteController::class, 'settle']);
});
