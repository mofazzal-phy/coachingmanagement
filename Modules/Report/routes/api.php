<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\app\Http\Controllers\Api\V1\ReportController;

// Reports - accessible by admin, teachers, and employees
Route::middleware(['api.auth', 'role:super-admin,admin,teacher,employee'])->prefix('v1')->group(function () {
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/students', [ReportController::class, 'studentReport']);
    Route::get('reports/financial', [ReportController::class, 'financialReport']);
    Route::get('reports/exam/{examId}', [ReportController::class, 'examReport']);
    Route::get('reports/exam/{examId}/merit', [ReportController::class, 'examMerit']);
    Route::get('reports/exam/{examId}/analytics', [ReportController::class, 'examAnalytics']);
    Route::get('reports/exam/{examId}/subject-analysis', [ReportController::class, 'examSubjectAnalysis']);
    Route::get('reports/exam/{examId}/export', [ReportController::class, 'examExport']);
});
