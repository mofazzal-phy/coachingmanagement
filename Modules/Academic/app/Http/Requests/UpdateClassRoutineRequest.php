<?php

namespace Modules\Academic\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRoutineRequest extends FormRequest
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
            'subject_id' => 'sometimes|string|exists:subjects,id',
            'teacher_id' => 'sometimes|string|exists:teachers,id',
            'room_id' => 'nullable|string|exists:rooms,id',
            'day_of_week' => 'sometimes|in:sat,sun,mon,tue,wed,thu,fri',
            'start_time' => 'sometimes|date_format:H:i|before:end_time',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:draft,published,archived',
        ];
    }
}
