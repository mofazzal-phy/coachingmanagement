<?php

namespace Modules\Exam\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExamRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => 'sometimes|string|exists:exams,id',
            'subject_id' => 'sometimes|string|exists:subjects,id',
            'exam_type_id' => 'nullable|string|exists:exam_types,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'group_id' => 'nullable|string|exists:academic_groups,id',
            'exam_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'room_id' => 'nullable|string|exists:rooms,id',
            'teacher_id' => 'nullable|string|exists:teachers,id',
            'total_marks' => 'sometimes|integer|min:1',
            'pass_marks' => 'sometimes|integer|min:1',
            'mark_config' => 'sometimes|nullable|array',
            'mark_config.*.enabled' => 'sometimes|boolean',
            'mark_config.*.max_marks' => 'sometimes|numeric|min:0',
            'mark_config.*.pass_marks' => 'sometimes|numeric|min:0',
            'mark_config.*.evaluation' => 'sometimes|in:auto,manual',
            'instructions' => 'sometimes|nullable|string|max:5000',
            'duration_minutes' => 'sometimes|nullable|integer|min:1|max:600',
            'randomize_questions' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,published,completed,cancelled',
            'delivery_mode' => 'sometimes|in:offline,online,hybrid',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->filled('start_time') || !$this->filled('end_time')) {
                return;
            }

            $start = strtotime($this->input('start_time'));
            $end = strtotime($this->input('end_time'));

            if ($end <= $start) {
                $end += 24 * 60 * 60;
            }

            if ($end <= $start) {
                $v->errors()->add('end_time', 'End time must be after start time.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
