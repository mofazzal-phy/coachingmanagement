<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|string|exists:employees,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'department_id' => 'nullable|string|exists:departments,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee ID is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'status.required' => 'Attendance status is required.',
            'status.in' => 'Invalid attendance status. Valid values: present, absent, late, leave, half_day.',
            'department_id.exists' => 'Selected department does not exist.',
            'session_id.exists' => 'Selected session does not exist.',
        ];
    }
}
