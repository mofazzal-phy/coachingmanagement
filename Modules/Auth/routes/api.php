<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Controllers\Api\V1\AuthController;
use Modules\Auth\app\Http\Controllers\Api\V1\UserController;
use Modules\Auth\app\Http\Controllers\Api\V1\RoleController;
use Modules\Auth\app\Http\Controllers\Api\V1\PermissionController;

/*
|--------------------------------------------------------------------------
| Auth Module API Routes
|--------------------------------------------------------------------------
*/

// Public auth routes
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Protected auth routes
    Route::middleware(['api.auth'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Protected admin routes
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {

    // User management
    Route::get('users/stats', [UserController::class, 'stats']);
    Route::get('users/role/{role}', [UserController::class, 'byRole']);
    Route::apiResource('users', UserController::class)->except(['edit', 'create']);

    // Role management
    Route::post('roles/assign', [RoleController::class, 'assignToUser']);
    Route::apiResource('roles', RoleController::class)->except(['edit', 'create']);

    // Permission management
    Route::post('permissions/sync-role', [PermissionController::class, 'syncRolePermissions']);
    Route::apiResource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
});
