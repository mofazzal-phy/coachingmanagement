<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\app\Http\Controllers\Api\V1\StudentController;
use Modules\Student\app\Http\Controllers\Api\V1\StudentIdCardController;
use Modules\Student\app\Http\Controllers\Api\V1\StudentPortalController;

// Read-only routes - teachers can view students
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    Route::get('students', [StudentController::class, 'index']);
    Route::get('students/{id}', [StudentController::class, 'show']);
    Route::get('students/{id}/guardian', [StudentController::class, 'getGuardian']);
    Route::get('students/{id}/fee-summary', [StudentController::class, 'feeSummary']);
    Route::get('students/{id}/id-card', [StudentIdCardController::class, 'show']);
    Route::get('students/{id}/id-card/download', [StudentIdCardController::class, 'download']);

    // ========== Student Portal Routes (self-service for logged-in students) ==========
    Route::prefix('student-portal')->group(function () {
        // Profile
        Route::get('profile', [StudentPortalController::class, 'profile']);

        // Dashboard
        Route::get('dashboard', [StudentPortalController::class, 'dashboard']);

        // Enrollments
        Route::get('enrollments', [StudentPortalController::class, 'enrollments']);

        // Fee Management
        Route::get('fee-dashboard', [StudentPortalController::class, 'feeDashboard']);
        Route::get('fee-ledger/{enrollmentId}', [StudentPortalController::class, 'feeLedger']);
        Route::get('fee-records/{enrollmentId}', [StudentPortalController::class, 'feeRecords']);

        // Exams
        Route::get('exams', [StudentPortalController::class, 'exams']);
        Route::get('exam-routines', [StudentPortalController::class, 'examRoutines']);
        Route::get('exam-results', [StudentPortalController::class, 'examResults']);

        // Class Routines
        Route::get('class-routines', [StudentPortalController::class, 'classRoutines']);

        // Notices
        Route::get('notices', [StudentPortalController::class, 'notices']);

        // Leave Management
        Route::get('leave-applications', [StudentPortalController::class, 'leaveApplications']);
        Route::post('apply-leave', [StudentPortalController::class, 'applyLeave']);

        // Attendance
        Route::get('attendance', [StudentPortalController::class, 'attendance']);

        // Enrollment Details (subjects, teachers)
        Route::get('enrollment-details/{enrollmentId}', [StudentPortalController::class, 'enrollmentDetails']);

        // Study Materials & Downloads
        Route::get('study-materials', [StudentPortalController::class, 'studyMaterials']);
        Route::get('downloads', [StudentPortalController::class, 'downloads']);
    });
});

// Admin/Teacher write routes
Route::middleware(['api.auth', 'role:super-admin,admin,teacher'])->prefix('v1')->group(function () {
    Route::post('students', [StudentController::class, 'store']);
    Route::post('students/{studentId}/guardian', [StudentController::class, 'storeGuardian']);
    Route::put('students/{id}', [StudentController::class, 'update']);
    Route::delete('students/{id}', [StudentController::class, 'destroy']);
    Route::post('students/{id}/pay', [StudentController::class, 'recordPayment']);
});

// Admin-only routes for student leave management
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::get('student-leaves', [StudentPortalController::class, 'adminLeaveList']);
    Route::post('student-leaves/{id}/approve', [StudentPortalController::class, 'adminApproveLeave']);
    Route::post('student-leaves/{id}/reject', [StudentPortalController::class, 'adminRejectLeave']);
});
