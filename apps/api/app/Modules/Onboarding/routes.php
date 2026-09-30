<?php

use App\Modules\Onboarding\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

// The user's own «ابدأ من هنا» card: only the steps they have the permission for (listed in RouteAuthorizationTest).
Route::middleware(['auth:sanctum', 'tenant'])->prefix('onboarding')->controller(OnboardingController::class)->group(function (): void {
    Route::get('/', 'show');
    Route::patch('/', 'update')->middleware('throttle:30,1');
});
