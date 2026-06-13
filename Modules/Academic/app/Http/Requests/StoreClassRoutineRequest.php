<?php

namespace Modules\Academic\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => 'nullable|string|exists:batches,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            // academic_groups.id is BIGINT UNSIGNED (auto-increment), not UUID
            'group_id' => 'nullable|exists:academic_groups,id',
            'subject_id' => 'required|string|exists:subjects,id',
            'teacher_id' => 'required|string|exists:teachers,id',
            'room_id' => 'nullable|string|exists:rooms,id',
            'day_of_week' => 'required|in:sat,sun,mon,tue,wed,thu,fri',
            'start_time' => 'required|date_format:H:i|before:end_time',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:draft,published,archived',
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Subject is required',
            'teacher_id.required' => 'Teacher is required',
            'day_of_week.required' => 'Day of week is required',
            'day_of_week.in' => 'Day must be sat, sun, mon, tue, wed, thu, or fri',
            'start_time.required' => 'Start time is required',
            'start_time.before' => 'Start time must be before end time',
            'end_time.required' => 'End time is required',
            'end_time.after' => 'End time must be after start time',
        ];
    }
}
