<?php

use App\Modules\Feedback\Http\Controllers\ClientErrorController;
use App\Modules\Feedback\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

// Any signed-in user can talk to the platform team and report what broke (listed in RouteAuthorizationTest).
// Rate-limited per user; feedback also per shop per day, client errors are capped per shop.
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('feedback', [FeedbackController::class, 'store'])->middleware('throttle:feedback');
    Route::post('client-errors', [ClientErrorController::class, 'store'])->middleware('throttle:client-errors');
});
