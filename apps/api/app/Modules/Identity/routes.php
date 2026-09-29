<?php

use App\Modules\Identity\Http\Controllers\AuditLogController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\BranchController;
use App\Modules\Identity\Http\Controllers\PermissionController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\ShopTypeController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// The kinds of shop, for the registration form.
Route::get('shop-types', [ShopTypeController::class, 'index'])->middleware('throttle:60,1');

Route::prefix('auth')->group(function (): void {
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::pattern('user', '[0-9a-fA-F-]{36}');
    Route::pattern('branch', '[0-9a-fA-F-]{36}');
    Route::pattern('role', '[0-9]+');

    Route::put('shop/types', [ShopTypeController::class, 'update'])->middleware('can:owner');

    Route::middleware('can:users.manage')->group(function (): void {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
    });

    Route::get('permissions', [PermissionController::class, 'index'])->middleware('can:roles.manage');
    Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.manage');
    Route::middleware('can:roles.manage')->group(function (): void {
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    });

    Route::get('branches', [BranchController::class, 'index']);
    Route::middleware('can:branches.manage')->group(function (): void {
        Route::post('branches', [BranchController::class, 'store']);
        Route::patch('branches/{branch}', [BranchController::class, 'update']);
    });

    Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('can:audit.view');
});
