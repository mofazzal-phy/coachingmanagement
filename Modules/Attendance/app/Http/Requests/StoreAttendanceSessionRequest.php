<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'nullable|string|exists:classes,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'teacher_id' => 'nullable|string|exists:teachers,id',
            'room_id' => 'nullable|string|exists:rooms,id',
            'slot_id' => 'nullable|string|exists:routine_periods,id',
            'attendance_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'session_type' => 'nullable|in:student,teacher,employee',
            'source' => 'nullable|in:manual,routine,biometric,simulator',
            'status' => 'nullable|in:active,completed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_date.required' => 'Attendance date is required.',
            'attendance_date.date' => 'Invalid date format.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'session_type.in' => 'Invalid session type. Valid: student, teacher, employee.',
            'source.in' => 'Invalid source. Valid: manual, routine, biometric, simulator.',
            'status.in' => 'Invalid status. Valid: active, completed, cancelled.',
            'batch_id.exists' => 'Selected batch does not exist.',
            'subject_id.exists' => 'Selected subject does not exist.',
            'teacher_id.exists' => 'Selected teacher does not exist.',
            'room_id.exists' => 'Selected room does not exist.',
        ];
    }
}
