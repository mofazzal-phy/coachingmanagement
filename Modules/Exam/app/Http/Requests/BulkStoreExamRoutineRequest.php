<?php

namespace Modules\Exam\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreExamRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => 'required|string|exists:exams,id',
            'exam_type_id' => 'nullable|string|exists:exam_types,id',
            'routines' => 'required|array|min:1',
            'routines.*.subject_id' => 'required|string|exists:subjects,id',
            'routines.*.exam_date' => 'required|date',
            'routines.*.start_time' => 'required|date_format:H:i',
            'routines.*.end_time' => 'required|date_format:H:i',
            'routines.*.batch_id' => 'nullable|string|exists:batches,id',
            'routines.*.course_id' => 'nullable|string|exists:courses,id',
            'routines.*.class_id' => 'nullable|string|exists:classes,id',
            'routines.*.room_id' => 'nullable|string|exists:rooms,id',
            'routines.*.teacher_id' => 'nullable|string|exists:teachers,id',
            'routines.*.total_marks' => 'sometimes|integer|min:1',
            'routines.*.pass_marks' => 'sometimes|integer|min:1',
            'routines.*.mark_config' => 'sometimes|nullable|array',
            'routines.*.duration_minutes' => 'sometimes|nullable|integer|min:1|max:600',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $routines = $this->input('routines', []);
            foreach ($routines as $i => $routine) {
                if (!empty($routine['start_time']) && !empty($routine['end_time'])) {
                    // Use strtotime for proper time comparison (handles HH:mm and HH:mm:ss)
                    $startTs = strtotime($routine['start_time']);
                    $endTs = strtotime($routine['end_time']);
                    if ($startTs !== false && $endTs !== false && $endTs <= $startTs) {
                        $validator->errors()->add(
                            "routines.{$i}.end_time",
                            "Row {$i}: End time must be after start time."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'routines.required' => 'At least one routine entry is required.',
            'routines.*.subject_id.required' => 'Each routine must have a subject.',
            'routines.*.exam_date.required' => 'Each routine must have an exam date.',
            'routines.*.start_time.required' => 'Each routine must have a start time.',
            'routines.*.end_time.required' => 'Each routine must have an end time.',
        ];
    }
}
