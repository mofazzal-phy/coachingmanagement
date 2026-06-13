<?php

use Illuminate\Support\Facades\Route;
use Modules\Hr\app\Http\Controllers\Api\V1\EmployeeController;

// Read-only routes - employees can view HR data
Route::middleware(['api.auth'])->prefix('v1')->group(function () {

    // Departments - view
    Route::get('departments', [EmployeeController::class, 'departments']);
    Route::get('departments/{id}', [EmployeeController::class, 'showDepartment']);

    // Designations - view
    Route::get('designations', [EmployeeController::class, 'designations']);
    Route::get('designations/{id}', [EmployeeController::class, 'showDesignation']);

    // Employees - view
    Route::get('employees', [EmployeeController::class, 'index']);
    Route::get('employees/{id}', [EmployeeController::class, 'show']);

    // Staff Attendance - view
    Route::get('staff-attendance', [EmployeeController::class, 'staffAttendance']);

    // Leave Types - view
    Route::get('leave-types', [EmployeeController::class, 'leaveTypes']);

    // Leave Requests - view
    Route::get('leave-requests', [EmployeeController::class, 'leaveRequests']);
    Route::get('leave-requests/{id}', [EmployeeController::class, 'showLeaveRequest']);

    // Payroll - view
    Route::get('payrolls', [EmployeeController::class, 'payrolls']);
    Route::get('payrolls/{id}', [EmployeeController::class, 'showPayroll']);
});

// Admin/Employee write routes
Route::middleware(['api.auth', 'role:super-admin,admin,employee'])->prefix('v1')->group(function () {

    // Departments - CRUD
    Route::post('departments', [EmployeeController::class, 'storeDepartment']);
    Route::put('departments/{id}', [EmployeeController::class, 'updateDepartment']);
    Route::delete('departments/{id}', [EmployeeController::class, 'destroyDepartment']);

    // Designations - CRUD
    Route::post('designations', [EmployeeController::class, 'storeDesignation']);
    Route::put('designations/{id}', [EmployeeController::class, 'updateDesignation']);
    Route::delete('designations/{id}', [EmployeeController::class, 'destroyDesignation']);

    // Employees - CRUD
    Route::post('employees', [EmployeeController::class, 'store']);
    Route::put('employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('employees/{id}', [EmployeeController::class, 'destroy']);

    // Staff Attendance - CRUD
    Route::post('staff-attendance', [EmployeeController::class, 'storeStaffAttendance']);
    Route::put('staff-attendance/{id}', [EmployeeController::class, 'updateStaffAttendance']);
    Route::post('staff-attendance/bulk', [EmployeeController::class, 'bulkStaffAttendance']);

    // Leave Types - CRUD
    Route::post('leave-types', [EmployeeController::class, 'storeLeaveType']);
    Route::put('leave-types/{id}', [EmployeeController::class, 'updateLeaveType']);
    Route::delete('leave-types/{id}', [EmployeeController::class, 'destroyLeaveType']);

    // Leave Requests - CRUD
    Route::post('leave-requests', [EmployeeController::class, 'storeLeaveRequest']);
    Route::put('leave-requests/{id}', [EmployeeController::class, 'updateLeaveRequest']);
    Route::post('leave-requests/{id}/approve', [EmployeeController::class, 'approveLeave']);
    Route::post('leave-requests/{id}/reject', [EmployeeController::class, 'rejectLeave']);

    // Payroll - CRUD
    Route::post('payrolls', [EmployeeController::class, 'storePayroll']);
    Route::put('payrolls/{id}', [EmployeeController::class, 'updatePayroll']);
    Route::delete('payrolls/{id}', [EmployeeController::class, 'destroyPayroll']);
    Route::post('payrolls/{id}/process', [EmployeeController::class, 'processPayroll']);
    Route::post('payrolls/{id}/paid', [EmployeeController::class, 'markPayrollPaid']);
});
