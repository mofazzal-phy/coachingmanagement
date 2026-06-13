<?php

use Illuminate\Support\Facades\Route;
use Modules\Admission\app\Http\Controllers\Api\V1\AdmissionController;

// Read-only routes - teachers can view admissions
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    Route::get('admissions', [AdmissionController::class, 'index']);
    Route::get('admissions/{id}', [AdmissionController::class, 'show']);
});

// Admin-only write routes (including approve/reject/enroll)
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::post('admissions', [AdmissionController::class, 'store']);
    Route::put('admissions/{id}', [AdmissionController::class, 'update']);
    Route::delete('admissions/{id}', [AdmissionController::class, 'destroy']);
    Route::post('admissions/{id}/approve', [AdmissionController::class, 'approve']);
    Route::post('admissions/{id}/reject', [AdmissionController::class, 'reject']);
    Route::post('admissions/{id}/enroll', [AdmissionController::class, 'enroll']);
});
