<?php

use Illuminate\Support\Facades\Route;
use Modules\Teacher\app\Http\Controllers\Api\V1\TeacherController;
use Modules\Teacher\app\Http\Controllers\Api\V1\TeacherIdCardController;

// Read-only routes - teachers can view other teachers
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    Route::get('teachers', [TeacherController::class, 'index']);
    Route::get('teachers/all', [TeacherController::class, 'listAll']);
    // Static routes MUST come before wildcard {id} route
    Route::get('teachers/by-subject/{subjectId}', [TeacherController::class, 'bySubject']);
    Route::get('teachers/by-class-subject', [TeacherController::class, 'byClassSubject']);
    Route::get('teachers/{id}', [TeacherController::class, 'show']);
    Route::get('teachers/{id}/id-card', [TeacherIdCardController::class, 'show']);
    Route::get('teachers/{id}/id-card/download', [TeacherIdCardController::class, 'download']);
});

// Admin-only write routes
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::post('teachers', [TeacherController::class, 'store']);
    Route::put('teachers/{id}', [TeacherController::class, 'update']);
    Route::delete('teachers/{id}', [TeacherController::class, 'destroy']);
});
