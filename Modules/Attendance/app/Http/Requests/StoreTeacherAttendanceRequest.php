<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => 'required|string|exists:teachers,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Teacher ID is required.',
            'teacher_id.exists' => 'Selected teacher does not exist.',
            'status.required' => 'Attendance status is required.',
            'status.in' => 'Invalid attendance status. Valid values: present, absent, late, leave, half_day.',
            'subject_id.exists' => 'Selected subject does not exist.',
            'class_id.exists' => 'Selected class does not exist.',
            'session_id.exists' => 'Selected session does not exist.',
        ];
    }
}
