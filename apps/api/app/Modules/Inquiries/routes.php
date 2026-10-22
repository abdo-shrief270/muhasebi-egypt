<?php

use App\Modules\Inquiries\Http\Controllers\InquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->controller(InquiryController::class)->group(function (): void {
    Route::pattern('inquiry', '[0-9a-fA-F-]{36}');
    Route::get('inquiries', 'index')->middleware('can:inquiries.view');
    Route::post('inquiries', 'store')->middleware('branch');
    Route::patch('inquiries/{inquiry}', 'update');
});
