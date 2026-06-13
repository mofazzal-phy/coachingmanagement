<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\app\Http\Controllers\Api\V1\CourseController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\BatchController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\EnrollmentController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\MonthlyFeeController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\PaymentGatewayController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\EnrollmentSessionController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\EnrollmentReportController;
use Modules\Enrollment\app\Http\Controllers\Api\V1\PublicController;

/*
|--------------------------------------------------------------------------
| Enrollment Module API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no auth required)
Route::prefix('v1/enrollment/public')->group(function () {
    Route::get('courses', [PublicController::class, 'courses']);
    Route::get('courses/{id}', [PublicController::class, 'courseDetails']);
    Route::get('courses/{courseId}/batches', [PublicController::class, 'batches']);

    // Write/abuse-prone endpoints are rate limited against bots/scrapers.
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('calculate-fee', [PublicController::class, 'calculateFee']);
    });
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('apply', [PublicController::class, 'applyOnline']);
    });

    Route::get('track/{enrollmentNo}', [PublicController::class, 'trackStatus']);
    Route::get('{enrollmentNo}/pdf', [PublicController::class, 'enrollmentPdf']);
});

// Authenticated routes
Route::middleware(['api.auth'])->prefix('v1')->group(function () {

    // === Enrollment Session (Wizard) ===
    Route::post('enrollment/initiate', [EnrollmentSessionController::class, 'initiate']);
    Route::get('enrollment/session/{sessionId}', [EnrollmentSessionController::class, 'show']);
    Route::post('enrollment/session/{sessionId}/student-info', [EnrollmentSessionController::class, 'saveStudentInfo']);
    Route::post('enrollment/session/{sessionId}/academic-info', [EnrollmentSessionController::class, 'saveAcademicInfo']);
    Route::post('enrollment/session/{sessionId}/course-batch', [EnrollmentSessionController::class, 'saveCourseBatch']);
    Route::post('enrollment/session/{sessionId}/documents', [EnrollmentSessionController::class, 'uploadDocuments']);
    Route::post('enrollment/session/{sessionId}/review', [EnrollmentSessionController::class, 'markReady']);
    Route::post('enrollment/session/{sessionId}/payment', [EnrollmentSessionController::class, 'finalizePayment']);
    Route::delete('enrollment/session/{sessionId}', [EnrollmentSessionController::class, 'abandon']);

    // Courses
    Route::get('courses/statistics', [CourseController::class, 'statistics']);
    Route::get('courses/export', [CourseController::class, 'export']);
    Route::post('courses/bulk-action', [CourseController::class, 'bulkAction']);
    Route::get('courses/list-all', [CourseController::class, 'listAll']);
    Route::get('courses/by-category/{category}', [CourseController::class, 'byCategory']);
    Route::post('courses/{id}/restore', [CourseController::class, 'restore']);
    Route::post('courses/{id}/duplicate', [CourseController::class, 'duplicate']);
    Route::apiResource('courses', CourseController::class);
    Route::get('courses/{id}/analytics', [CourseController::class, 'analytics']);
    Route::post('courses/{id}/assign-subjects', [CourseController::class, 'assignSubjects']);

    // Batches
    Route::get('batches/statistics', [BatchController::class, 'statistics']);
    Route::get('batches/export', [BatchController::class, 'export']);
    Route::post('batches/bulk-action', [BatchController::class, 'bulkAction']);
    Route::post('batches/{id}/restore', [BatchController::class, 'restore']);
    Route::post('batches/{id}/duplicate', [BatchController::class, 'duplicate']);
    Route::get('batches/by-course/{courseId}', [BatchController::class, 'byCourse']);
    Route::get('batches/{id}/students', [BatchController::class, 'students']);
    Route::apiResource('batches', BatchController::class);

    // Enrollments
    Route::get('enrollments/search-student', [EnrollmentController::class, 'searchStudent']);
    Route::get('enrollments/suggested-courses', [EnrollmentController::class, 'suggestedCourses']);
    Route::get('enrollments/suggested-batches', [EnrollmentController::class, 'suggestedBatches']);
    Route::get('enrollments/pending-payments', [EnrollmentController::class, 'pendingPayments']);
    Route::get('enrollments/sibling-discount/{studentId}', [EnrollmentController::class, 'checkSiblingDiscount']);
    Route::get('enrollments/check-duplicate', [EnrollmentController::class, 'checkDuplicate']);
    Route::get('enrollments/generate-student-id', [EnrollmentController::class, 'generateStudentId']);
    Route::get('enrollments/waiting-list', [EnrollmentController::class, 'waitingList']);
    Route::get('enrollments/waiting-list/stats', [EnrollmentController::class, 'waitingListStats']);
    Route::post('enrollments/waiting-list/add', [EnrollmentController::class, 'addToWaitingList']);
    Route::post('enrollments/waiting-list/{id}/approve', [EnrollmentController::class, 'approveFromWaitingList']);
    Route::post('enrollments/waiting-list/bulk-approve', [EnrollmentController::class, 'bulkApproveWaitingList']);
    Route::get('enrollments/{id}/receipt', [EnrollmentController::class, 'receipt']);
    Route::get('enrollments/{id}/timeline', [EnrollmentController::class, 'timeline']);
    Route::get('audit-logs', [EnrollmentController::class, 'auditLogs']);
    Route::post('enrollments/enroll', [EnrollmentController::class, 'enroll']);
    Route::post('enrollments/renew', [EnrollmentController::class, 'renew']);
    Route::post('enrollments/{id}/confirm', [EnrollmentController::class, 'confirmEnrollment']);
    Route::post('enrollments/{id}/change-batch', [EnrollmentController::class, 'changeBatch']);
    Route::post('enrollments/{id}/payment', [EnrollmentController::class, 'recordPayment']);
    Route::post('enrollments/{id}/refund', [EnrollmentController::class, 'refund']);
    Route::post('enrollments/{id}/verify-payment', [EnrollmentController::class, 'verifyPayment']);
    Route::get('enrollments/{id}/invoice/download', [EnrollmentController::class, 'invoiceDownload']);
    Route::post('enrollments/{id}/dropout', [EnrollmentController::class, 'dropout']);
    Route::post('enrollments/calculate-fee', [EnrollmentController::class, 'calculateFee']);
    Route::get('enrollments/export', [EnrollmentController::class, 'export']);
    Route::apiResource('enrollments', EnrollmentController::class)->only(['index', 'show', 'update', 'destroy']);

    // === Monthly Fee Management ===
    Route::prefix('monthly-fees')->group(function () {
        Route::get('/', [MonthlyFeeController::class, 'index']);
        Route::get('overdue', [MonthlyFeeController::class, 'overdue']);
        Route::get('current-month-pending', [MonthlyFeeController::class, 'currentMonthPending']);
        Route::get('enrollment/{enrollmentId}', [MonthlyFeeController::class, 'enrollmentRecords']);
        Route::get('enrollment/{enrollmentId}/summary', [MonthlyFeeController::class, 'summary']);

        // Payment confirmation workflow
        Route::get('pending-confirmations', [MonthlyFeeController::class, 'pendingConfirmations']);
        Route::get('confirmed-payments', [MonthlyFeeController::class, 'confirmedPayments']);
        Route::post('{paymentId}/confirm', [MonthlyFeeController::class, 'confirmPayment']);
        Route::post('{paymentId}/reject', [MonthlyFeeController::class, 'rejectPayment']);
        Route::get('{paymentId}/invoice/download', [MonthlyFeeController::class, 'downloadInvoice']);

        Route::get('{id}', [MonthlyFeeController::class, 'show']);
        Route::post('{recordId}/pay', [MonthlyFeeController::class, 'recordPayment']);
    });

    // === Payment Gateway ===
    Route::prefix('payment')->group(function () {
        Route::get('gateways', [PaymentGatewayController::class, 'gateways']);
        Route::post('initiate', [PaymentGatewayController::class, 'initiatePayment']);
        Route::post('callback/{gateway}', [PaymentGatewayController::class, 'handleCallback']);
        Route::post('verify', [PaymentGatewayController::class, 'verifyPayment']);
        Route::post('manual', [PaymentGatewayController::class, 'manualPayment']);
    });

    // Reports
    Route::prefix('reports/enrollment')->group(function () {
        Route::get('overview', [EnrollmentReportController::class, 'overview']);
        Route::get('revenue-trend', [EnrollmentReportController::class, 'revenueTrend']);
        Route::get('occupancy', [EnrollmentReportController::class, 'occupancy']);
        Route::get('summary', [EnrollmentReportController::class, 'summary']);
        Route::get('batch-wise', [EnrollmentReportController::class, 'batchWise']);
        Route::get('course-wise', [EnrollmentReportController::class, 'courseWise']);
        Route::get('mode-wise', [EnrollmentReportController::class, 'modeWise']);
        Route::get('daily', [EnrollmentReportController::class, 'dailyEnrollment']);
        Route::get('revenue-projection', [EnrollmentReportController::class, 'revenueProjection']);
        Route::get('teacher-performance', [EnrollmentReportController::class, 'teacherPerformance']);
        Route::get('batch-performance', [EnrollmentReportController::class, 'batchPerformance']);
        Route::get('course-performance', [EnrollmentReportController::class, 'coursePerformance']);
        Route::get('export/pdf', [EnrollmentReportController::class, 'exportPdf']);
        Route::get('export/excel', [EnrollmentReportController::class, 'exportExcel']);
    });
});
