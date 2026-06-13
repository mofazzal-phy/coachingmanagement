<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|string|exists:students,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'slot_id' => 'nullable|string|exists:routine_periods,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student ID is required.',
            'student_id.exists' => 'Selected student does not exist.',
            'status.required' => 'Attendance status is required.',
            'status.in' => 'Invalid attendance status. Valid values: present, absent, late, leave, half_day.',
            'batch_id.exists' => 'Selected batch does not exist.',
            'subject_id.exists' => 'Selected subject does not exist.',
            'slot_id.exists' => 'Selected time slot does not exist.',
            'session_id.exists' => 'Selected session does not exist.',
            'date.date' => 'Invalid date format.',
        ];
    }
}
