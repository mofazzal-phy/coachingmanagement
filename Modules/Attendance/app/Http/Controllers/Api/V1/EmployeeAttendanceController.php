<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\AttendanceAnalytics;
use Modules\Attendance\app\Services\AttendanceMetricsService;
use Modules\Hr\app\Models\Employee;
use Modules\Attendance\app\Models\AttendanceLog;
use Carbon\Carbon;

class EmployeeAttendanceController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected AttendanceAnalytics $analytics,
        protected AttendanceMetricsService $metrics,
    ) {}

    /**
     * Get all employees for attendance marking.
     */
    public function getEmployees(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'department_id' => 'nullable|string|exists:departments,id',
        ]);

        $date = $request->input('date', Carbon::today()->toDateString());
        $departmentId = $request->input('department_id');

        $employeeQuery = Employee::select(
            'id', 'first_name', 'last_name', 'employee_id', 'email', 'phone',
            'department_id', 'designation_id', 'status', 'employment_type'
        )
            ->with(['department:id,name', 'designation:id,name'])
            ->where('status', 'active')
            ->orderBy('first_name');

        if ($departmentId) {
            $employeeQuery->where('department_id', $departmentId);
        }

        $employees = $employeeQuery->get();

        $existingLogs = AttendanceLog::with('timeMetric')
            ->where('user_type', 'employee')
            ->where('attendance_date', $date)
            ->where(function ($q) {
                $q->where('attendance_mode', 'daily')
                    ->orWhereNull('attendance_session_id');
            })
            ->get()
            ->keyBy('user_id');

        $result = $employees->map(function ($employee) use ($existingLogs) {
            $log = $existingLogs->get($employee->id);

            return [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'email' => $employee->email,
                'phone' => $employee->phone,
                'department' => $employee->department?->name ?? 'N/A',
                'department_id' => $employee->department_id,
                'designation' => $employee->designation?->name ?? 'N/A',
                'employment_type' => $employee->employment_type ?? null,
                'status' => $log?->attendance_status ?? 'present',
                'check_in' => $log?->check_in?->format('H:i'),
                'check_out' => $log?->check_out?->format('H:i'),
                'remarks' => $log?->remarks ?? '',
                'has_attendance' => $log !== null,
                'metrics' => $this->metrics->formatForApi($log?->timeMetric),
            ];
        })->values();

        return $this->collectionResponse($result);
    }

    /**
     * Mark employee attendance.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'department_id' => 'nullable|string|exists:departments,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $log = $this->engine->markEmployeeAttendance(
            employeeId: $validated['employee_id'],
            status: $validated['status'],
            departmentId: $validated['department_id'] ?? null,
            sessionId: $validated['session_id'] ?? null,
            remarks: $validated['remarks'] ?? null,
            date: isset($validated['date']) ? Carbon::parse($validated['date']) : null
        );

        return $this->created($log->load('employeeAttendance.employee'));
    }

    /**
     * Bulk mark employee attendance.
     */
    public function bulkMarkAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'department_id' => 'nullable|string|exists:departments,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'records' => 'required|array',
            'records.*.employee_id' => 'required|string|exists:employees,id',
            'records.*.status' => 'required|in:present,absent,late,leave,half_day',
            'records.*.check_in' => 'nullable|date_format:H:i',
            'records.*.check_out' => 'nullable|date_format:H:i',
            'records.*.remarks' => 'nullable|string',
        ]);

        $logs = $this->engine->bulkMarkEmployeeAttendance(
            records: $validated['records'],
            date: $validated['date'],
            departmentId: $validated['department_id'] ?? null,
            sessionId: $validated['session_id'] ?? null
        );

        return $this->created($logs, 'Employee attendance saved successfully');
    }

    /**
     * Get employee attendance summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
        ]);

        $stats = $this->analytics->employeeStats($validated['employee_id']);
        return $this->success($stats);
    }
}
