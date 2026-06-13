<?php

namespace Modules\Hr\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hr\app\Models\Employee;
use Modules\Hr\app\Models\Department;
use Modules\Hr\app\Models\Designation;
use Modules\Hr\app\Models\StaffAttendance;
use Modules\Hr\app\Models\LeaveRequest;
use Modules\Hr\app\Models\LeaveType;
use Modules\Hr\app\Models\Payroll;
use Modules\Core\app\Http\Controllers\BaseApiController;

class EmployeeController extends BaseApiController
{
    // === Departments ===
    public function departments(): JsonResponse
    {
        return $this->collectionResponse(Department::withCount('employees')->get());
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:departments,code',
            'description' => 'nullable|string',
        ]);
        return $this->created(Department::create($validated));
    }

    public function updateDepartment(Request $request, string $id): JsonResponse
    {
        $dept = Department::find($id);
        if (!$dept) return $this->notFound();
        $dept->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:departments,code,' . $id,
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($dept->fresh());
    }

    public function showDepartment(string $id): JsonResponse
    {
        $dept = Department::withCount('employees')->find($id);
        if (!$dept) return $this->notFound();
        return $this->success($dept);
    }

    public function destroyDepartment(string $id): JsonResponse
    {
        $dept = Department::find($id);
        if (!$dept) return $this->notFound();
        $dept->delete();
        return $this->noContent();
    }

    // === Designations ===
    public function designations(): JsonResponse
    {
        return $this->collectionResponse(Designation::withCount('employees')->get());
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:designations,code',
            'description' => 'nullable|string',
        ]);
        return $this->created(Designation::create($validated));
    }

    public function updateDesignation(Request $request, string $id): JsonResponse
    {
        $desig = Designation::find($id);
        if (!$desig) return $this->notFound();
        $desig->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:designations,code,' . $id,
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($desig->fresh());
    }

    public function showDesignation(string $id): JsonResponse
    {
        $desig = Designation::withCount('employees')->find($id);
        if (!$desig) return $this->notFound();
        return $this->success($desig);
    }

    public function destroyDesignation(string $id): JsonResponse
    {
        $desig = Designation::find($id);
        if (!$desig) return $this->notFound();
        $desig->delete();
        return $this->noContent();
    }

    // === Employees ===
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $employees = Employee::with(['department', 'designation', 'user'])
            ->search($request->search)
            ->filter($request->only(['status', 'department_id', 'designation_id', 'employment_type']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'department_id' => 'required|string|exists:departments,id',
            'designation_id' => 'required|string|exists:designations,id',
            'employee_id' => 'required|string|unique:employees,employee_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'required|date',
            'employment_type' => 'sometimes|in:permanent,contract,probation,intern,part_time',
            'salary' => 'sometimes|numeric|min:0',
            'qualification' => 'nullable|string',
        ]);

        return $this->created(Employee::create($validated)->load(['department', 'designation']));
    }

    public function show(string $id): JsonResponse
    {
        $employee = Employee::with(['department', 'designation', 'user', 'attendances', 'leaveRequests', 'payrolls'])->find($id);
        if (!$employee) return $this->notFound();
        return $this->success($employee);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (!$employee) return $this->notFound();

        $validated = $request->validate([
            'department_id' => 'sometimes|string|exists:departments,id',
            'designation_id' => 'sometimes|string|exists:designations,id',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'sometimes|date',
            'date_of_leaving' => 'nullable|date',
            'employment_type' => 'sometimes|in:permanent,contract,probation,intern,part_time',
            'salary' => 'sometimes|numeric|min:0',
            'qualification' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,resigned,terminated',
        ]);

        $employee->update($validated);
        return $this->success($employee->fresh(['department', 'designation']));
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (!$employee) return $this->notFound();
        $employee->delete();
        return $this->noContent();
    }

    // === Staff Attendance ===
    public function staffAttendance(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $attendance = StaffAttendance::with('employee')
            ->filter($request->only(['status', 'date', 'employee_id']))
            ->orderBy('date', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($attendance);
    }

    public function storeStaffAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable',
            'check_out' => 'nullable',
            'status' => 'required|in:present,absent,late,half-day,leave',
            'remarks' => 'nullable|string',
        ]);

        $attendance = StaffAttendance::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            $validated
        );

        return $this->created($attendance->load('employee'));
    }

    public function updateStaffAttendance(Request $request, string $id): JsonResponse
    {
        $attendance = StaffAttendance::find($id);
        if (!$attendance) return $this->notFound();

        $validated = $request->validate([
            'check_in' => 'nullable',
            'check_out' => 'nullable',
            'status' => 'sometimes|in:present,absent,late,half-day,leave',
            'remarks' => 'nullable|string',
        ]);

        $attendance->update($validated);
        return $this->success($attendance->fresh('employee'));
    }

    public function bulkStaffAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.employee_id' => 'required|string|exists:employees,id',
            'records.*.status' => 'required|in:present,absent,late,half-day,leave',
            'records.*.remarks' => 'nullable|string',
        ]);

        $created = [];
        foreach ($validated['records'] as $record) {
            $attendance = StaffAttendance::updateOrCreate(
                ['employee_id' => $record['employee_id'], 'date' => $validated['date']],
                [
                    'employee_id' => $record['employee_id'],
                    'date' => $validated['date'],
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                ]
            );
            $created[] = $attendance;
        }

        return $this->created($created, 'Staff attendance recorded successfully');
    }

    // === Leave Types ===
    public function leaveTypes(): JsonResponse
    {
        return $this->collectionResponse(LeaveType::all());
    }

    public function storeLeaveType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:leave_types,code',
            'max_days_per_year' => 'sometimes|integer|min:1|max:365',
            'description' => 'nullable|string',
        ]);
        return $this->created(LeaveType::create($validated));
    }

    public function updateLeaveType(Request $request, string $id): JsonResponse
    {
        $type = LeaveType::find($id);
        if (!$type) return $this->notFound();
        $type->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:leave_types,code,' . $id,
            'max_days_per_year' => 'sometimes|integer|min:1|max:365',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($type->fresh());
    }

    public function destroyLeaveType(string $id): JsonResponse
    {
        $type = LeaveType::find($id);
        if (!$type) return $this->notFound();
        $type->delete();
        return $this->noContent();
    }

    // === Leave Requests ===
    public function leaveRequests(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->filter($request->only(['status', 'employee_id', 'leave_type_id']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($leaves);
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
            'leave_type_id' => 'required|string|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $validated['total_days'] = now()->parse($validated['start_date'])->diffInDays(now()->parse($validated['end_date'])) + 1;

        return $this->created(LeaveRequest::create($validated)->load(['employee', 'leaveType']));
    }

    public function showLeaveRequest(string $id): JsonResponse
    {
        $leave = LeaveRequest::with(['employee', 'leaveType'])->find($id);
        if (!$leave) return $this->notFound();
        return $this->success($leave);
    }

    public function updateLeaveRequest(Request $request, string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (!$leave) return $this->notFound();

        $validated = $request->validate([
            'leave_type_id' => 'sometimes|string|exists:leave_types,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'sometimes|string',
        ]);

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $validated['total_days'] = now()->parse($validated['start_date'])->diffInDays(now()->parse($validated['end_date'])) + 1;
        }

        $leave->update($validated);
        return $this->success($leave->fresh(['employee', 'leaveType']));
    }

    public function approveLeave(string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (!$leave) return $this->notFound();
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        return $this->success($leave->fresh(['employee', 'leaveType']));
    }

    public function rejectLeave(Request $request, string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (!$leave) return $this->notFound();
        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'rejection_reason' => $request->input('reason', ''),
        ]);
        return $this->success($leave->fresh(['employee', 'leaveType']));
    }

    // === Payroll ===
    public function payrolls(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $payrolls = Payroll::with('employee')
            ->filter($request->only(['status', 'month', 'employee_id']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($payrolls);
    }

    public function storePayroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
            'month' => 'required|string|max:7',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'sometimes|numeric|min:0',
            'deductions' => 'sometimes|numeric|min:0',
            'bonus' => 'sometimes|numeric|min:0',
            'present_days' => 'sometimes|integer|min:0',
            'absent_days' => 'sometimes|integer|min:0',
            'leave_days' => 'sometimes|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        $validated['net_salary'] = $validated['basic_salary'] + ($validated['allowances'] ?? 0) - ($validated['deductions'] ?? 0) + ($validated['bonus'] ?? 0);

        return $this->created(Payroll::create($validated)->load('employee'));
    }

    public function showPayroll(string $id): JsonResponse
    {
        $payroll = Payroll::with('employee')->find($id);
        if (!$payroll) return $this->notFound();
        return $this->success($payroll);
    }

    public function updatePayroll(Request $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (!$payroll) return $this->notFound();

        $validated = $request->validate([
            'basic_salary' => 'sometimes|numeric|min:0',
            'allowances' => 'sometimes|numeric|min:0',
            'deductions' => 'sometimes|numeric|min:0',
            'bonus' => 'sometimes|numeric|min:0',
            'present_days' => 'sometimes|integer|min:0',
            'absent_days' => 'sometimes|integer|min:0',
            'leave_days' => 'sometimes|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        if (isset($validated['basic_salary']) || isset($validated['allowances']) || isset($validated['deductions']) || isset($validated['bonus'])) {
            $validated['net_salary'] = ($validated['basic_salary'] ?? $payroll->basic_salary)
                + ($validated['allowances'] ?? $payroll->allowances ?? 0)
                - ($validated['deductions'] ?? $payroll->deductions ?? 0)
                + ($validated['bonus'] ?? $payroll->bonus ?? 0);
        }

        $payroll->update($validated);
        return $this->success($payroll->fresh('employee'));
    }

    public function destroyPayroll(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (!$payroll) return $this->notFound();
        $payroll->delete();
        return $this->noContent();
    }

    public function processPayroll(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (!$payroll) return $this->notFound();
        $payroll->update(['status' => 'processed']);
        return $this->success($payroll->fresh('employee'));
    }

    public function markPayrollPaid(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (!$payroll) return $this->notFound();
        $payroll->update(['status' => 'paid', 'payment_date' => now()]);
        return $this->success($payroll->fresh('employee'));
    }
}
