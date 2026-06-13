<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreTeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.teacher_id' => 'required|string|exists:teachers,id',
            'records.*.status' => 'required|in:present,absent,late,leave,half_day',
            'records.*.remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Attendance date is required.',
            'records.required' => 'At least one attendance record is required.',
            'records.*.teacher_id.required' => 'Teacher ID is required for each record.',
            'records.*.teacher_id.exists' => 'Invalid teacher in records.',
            'records.*.status.required' => 'Status is required for each record.',
            'records.*.status.in' => 'Invalid status in records.',
        ];
    }
}
