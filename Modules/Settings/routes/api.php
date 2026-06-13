<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\app\Http\Controllers\Api\V1\SettingController;

// Read-only routes - all authenticated users can view settings
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    Route::get('settings', [SettingController::class, 'index']);
    Route::get('settings/grading-rules', [SettingController::class, 'gradingRules']);
    Route::get('settings/{key}', [SettingController::class, 'get']);
    Route::get('settings-groups', [SettingController::class, 'groups']);
});

// Admin-only write routes
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::put('settings', [SettingController::class, 'update']);
    Route::put('settings/grading-rules', [SettingController::class, 'updateGradingRules']);
});
