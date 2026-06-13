<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => 'required|string|exists:batches,id',
            'date' => 'required|date',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|string|exists:students,id',
            'records.*.status' => 'required|in:present,absent,late,leave,half_day',
            'records.*.remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'batch_id.required' => 'Batch ID is required.',
            'batch_id.exists' => 'Selected batch does not exist.',
            'date.required' => 'Attendance date is required.',
            'date.date' => 'Invalid date format.',
            'records.required' => 'At least one attendance record is required.',
            'records.array' => 'Records must be an array.',
            'records.min' => 'At least one attendance record is required.',
            'records.*.student_id.required' => 'Student ID is required for each record.',
            'records.*.student_id.exists' => 'Invalid student in records.',
            'records.*.status.required' => 'Status is required for each record.',
            'records.*.status.in' => 'Invalid status in records. Valid: present, absent, late, leave, half_day.',
        ];
    }
}
