<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\app\Http\Controllers\Api\V1\AttendanceController;
use Modules\Attendance\app\Http\Controllers\Api\V1\StudentAttendanceController;
use Modules\Attendance\app\Http\Controllers\Api\V1\TeacherAttendanceController;
use Modules\Attendance\app\Http\Controllers\Api\V1\EmployeeAttendanceController;
use Modules\Attendance\app\Http\Controllers\Api\V1\BiometricDeviceController;
use Modules\Attendance\app\Http\Controllers\Api\V1\AttendanceSessionController;
use Modules\Attendance\app\Http\Controllers\Api\V1\ClassSessionController;
use Modules\Attendance\app\Http\Controllers\Api\V1\AttendanceDashboardController;
use Modules\Attendance\app\Http\Controllers\Api\V1\AttendanceReportController;
use Modules\Attendance\app\Http\Controllers\Api\V1\DeviceSimulatorController;
use Modules\Attendance\app\Http\Controllers\Api\V1\BiometricMappingController;
use Modules\Attendance\app\Http\Controllers\Api\V1\MyAttendanceController;

/*
|--------------------------------------------------------------------------
| Attendance Module API Routes
|--------------------------------------------------------------------------
|
| Enterprise Hybrid Attendance Management System
| Supports: Manual, Biometric, Student/Teacher/Employee, Multi-Batch,
| Slot-Based, Realtime, Analytics, Eligibility Engine
|
*/

// =========================================================================
// READ-ONLY ROUTES - All authenticated users can view attendance
// =========================================================================
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    // Legacy attendance endpoints (backward compatibility)
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::get('attendance/by-class', [AttendanceController::class, 'byClass']);
    Route::get('attendance/summary', [AttendanceController::class, 'summary']);

    // Own attendance (student / teacher / employee portal)
    Route::get('attendance/my', [MyAttendanceController::class, 'show']);

    // Dashboard endpoints
    Route::get('attendance/dashboard/today', [AttendanceDashboardController::class, 'todayOverview']);
    Route::get('attendance/dashboard/realtime', [AttendanceDashboardController::class, 'realtimeData']);
    Route::get('attendance/dashboard/monthly-trend', [AttendanceDashboardController::class, 'monthlyTrend']);
    Route::get('attendance/dashboard/low-attendance-alerts', [AttendanceDashboardController::class, 'lowAttendanceAlerts']);
    Route::get('attendance/dashboard/consecutive-absent-alerts', [AttendanceDashboardController::class, 'consecutiveAbsentAlerts']);
    Route::get('attendance/dashboard/device-status', [AttendanceDashboardController::class, 'deviceStatus']);

    // Student attendance read-only
    Route::get('attendance/students', [StudentAttendanceController::class, 'getStudents']);
    Route::get('attendance/students/summary', [StudentAttendanceController::class, 'summary']);
    Route::get('attendance/students/subject-wise', [StudentAttendanceController::class, 'subjectWise']);
    Route::get('attendance/students/batch-report', [StudentAttendanceController::class, 'batchReport']);

    // Teacher attendance read-only
    Route::get('attendance/teachers', [TeacherAttendanceController::class, 'getTeachers']);
    Route::get('attendance/teachers/class-ledger', [TeacherAttendanceController::class, 'getClassLedger']);
    Route::get('attendance/teachers/summary', [TeacherAttendanceController::class, 'summary']);

    // Employee attendance read-only
    Route::get('attendance/employees', [EmployeeAttendanceController::class, 'getEmployees']);
    Route::get('attendance/employees/summary', [EmployeeAttendanceController::class, 'summary']);

    // Attendance sessions read-only
    Route::get('attendance/sessions', [AttendanceSessionController::class, 'index']);
    Route::get('attendance/sessions/{id}', [AttendanceSessionController::class, 'show']);
    Route::get('attendance/sessions/detect/current', [AttendanceSessionController::class, 'detectCurrentSession']);
    Route::get('attendance/sessions/from-class/{classSessionId}', [AttendanceSessionController::class, 'fromClassSession']);

    // Class sessions (scheduled classes from routine)
    Route::get('attendance/class-sessions', [ClassSessionController::class, 'index']);
    Route::get('attendance/class-sessions/{id}', [ClassSessionController::class, 'show']);

    // Biometric devices read-only
    Route::get('attendance/devices', [BiometricDeviceController::class, 'index']);
    Route::get('attendance/devices/{id}', [BiometricDeviceController::class, 'show']);
    Route::get('attendance/devices/drivers/list', [BiometricDeviceController::class, 'drivers']);
    Route::get('attendance/biometric-mappings', [BiometricMappingController::class, 'index']);

    // Reports read-only
    Route::get('attendance/reports/daily', [AttendanceReportController::class, 'dailyReport']);
    Route::get('attendance/reports/monthly', [AttendanceReportController::class, 'monthlyReport']);
    Route::get('attendance/reports/batch', [AttendanceReportController::class, 'batchReport']);
    Route::get('attendance/reports/subject', [AttendanceReportController::class, 'subjectReport']);
    Route::get('attendance/reports/teacher', [AttendanceReportController::class, 'teacherReport']);
    Route::get('attendance/reports/employee', [AttendanceReportController::class, 'employeeReport']);
    Route::get('attendance/reports/session', [AttendanceReportController::class, 'sessionReport']);

    // Device simulator read-only
    Route::get('attendance/simulator/devices', [DeviceSimulatorController::class, 'getDevices']);
    Route::get('attendance/simulator/users', [DeviceSimulatorController::class, 'getUsers']);
    Route::get('attendance/simulator/recent-scans', [DeviceSimulatorController::class, 'recentScans']);

    // Wildcard route — must be defined LAST to avoid catching specific routes like `attendance/students`
    Route::get('attendance/{id}', [AttendanceController::class, 'show']);
});

// =========================================================================
// WRITE ROUTES - Teacher/Admin/Staff can manage attendance
// =========================================================================
Route::middleware(['api.auth', 'role:super-admin,admin,teacher'])->prefix('v1')->group(function () {
    // Legacy attendance write endpoints
    Route::post('attendance', [AttendanceController::class, 'store']);
    Route::post('attendance/bulk', [AttendanceController::class, 'bulkStore']);
    Route::put('attendance/{id}', [AttendanceController::class, 'update']);
    Route::delete('attendance/{id}', [AttendanceController::class, 'destroy']);

    // Student attendance management
    Route::post('attendance/students/mark', [StudentAttendanceController::class, 'markAttendance']);
    Route::post('attendance/students/bulk-mark', [StudentAttendanceController::class, 'bulkMarkAttendance']);

    // Teacher attendance management
    Route::post('attendance/teachers/mark', [TeacherAttendanceController::class, 'markAttendance']);
    Route::post('attendance/teachers/bulk-mark', [TeacherAttendanceController::class, 'bulkMarkAttendance']);
    Route::post('attendance/teachers/class-ledger/mark', [TeacherAttendanceController::class, 'bulkMarkClassLedger']);

    // Employee attendance management
    Route::post('attendance/employees/mark', [EmployeeAttendanceController::class, 'markAttendance']);
    Route::post('attendance/employees/bulk-mark', [EmployeeAttendanceController::class, 'bulkMarkAttendance']);

    // Attendance sessions management
    Route::post('attendance/sessions', [AttendanceSessionController::class, 'store']);
    Route::put('attendance/sessions/{id}', [AttendanceSessionController::class, 'update']);
    Route::post('attendance/sessions/{id}/close', [AttendanceSessionController::class, 'close']);
    Route::post('attendance/sessions/{id}/cancel', [AttendanceSessionController::class, 'cancel']);
    Route::post('attendance/sessions/from-routine', [AttendanceSessionController::class, 'fromRoutine']);

    // Class sessions sync & status
    Route::post('attendance/class-sessions/sync', [ClassSessionController::class, 'sync']);
    Route::patch('attendance/class-sessions/{id}/status', [ClassSessionController::class, 'updateStatus']);
});

// =========================================================================
// ADMIN-ONLY ROUTES - Device management, simulator
// =========================================================================
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    // Biometric device CRUD
    Route::post('attendance/devices', [BiometricDeviceController::class, 'store']);
    Route::put('attendance/devices/{id}', [BiometricDeviceController::class, 'update']);
    Route::delete('attendance/devices/{id}', [BiometricDeviceController::class, 'destroy']);
    Route::post('attendance/devices/{id}/test-connection', [BiometricDeviceController::class, 'testConnection']);
    Route::post('attendance/devices/{id}/pull-attendance', [BiometricDeviceController::class, 'pullAttendance']);
    Route::post('attendance/biometric-mappings', [BiometricMappingController::class, 'store']);
    Route::delete('attendance/biometric-mappings/{id}', [BiometricMappingController::class, 'destroy']);

    // Device simulator
    Route::post('attendance/simulator/scan', [DeviceSimulatorController::class, 'simulateScan']);
    Route::post('attendance/simulator/generate-random', [DeviceSimulatorController::class, 'generateRandomEvents']);
});
