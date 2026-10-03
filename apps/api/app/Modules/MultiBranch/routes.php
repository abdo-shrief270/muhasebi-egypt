<?php

use App\Modules\MultiBranch\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:multi_branch', 'can:transfers.manage'])
    ->prefix('transfers')->controller(TransferController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('variants', 'variants');
        Route::post('/', 'store')->middleware('throttle:60,1');
        Route::get('{transfer}', 'show')->whereUuid('transfer');
        Route::post('{transfer}/ship', 'ship')->whereUuid('transfer');
        Route::post('{transfer}/receive', 'receive')->whereUuid('transfer');
        Route::post('{transfer}/cancel', 'cancel')->whereUuid('transfer');
    });
