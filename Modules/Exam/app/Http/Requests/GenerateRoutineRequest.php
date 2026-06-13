<?php

namespace Modules\Exam\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class GenerateRoutineRequest extends FormRequest
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
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'required|string|exists:subjects,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'slot_duration' => 'required|integer|min:1|max:1440', // minutes (up to 24 hours)
            'gap_minutes' => 'required|integer|min:0|max:120',
            'start_time' => 'required|date_format:H:i',
            'end_time_limit' => 'nullable|date_format:H:i',
            'exclude_days' => 'sometimes|array',
            'exclude_days.*' => 'string', // Day names like 'Friday', 'Saturday'
            'auto_assign_rooms' => 'boolean',
            'auto_assign_teachers' => 'boolean',
            'apply' => 'boolean',
            'delivery_mode' => 'sometimes|in:offline,online,hybrid',
            /** append = keep existing routines, add only new subjects; replace = reschedule selected subjects */
            'merge_mode' => 'sometimes|in:append,replace',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->filled('start_time') || !$this->filled('end_time_limit')) {
                return;
            }

            $start = strtotime($this->input('start_time'));
            $end = strtotime($this->input('end_time_limit'));

            if ($end <= $start) {
                $end += 24 * 60 * 60;
            }

            if (($end - $start) < 15 * 60) {
                $v->errors()->add(
                    'end_time_limit',
                    'Daily end limit must allow at least one exam slot (15+ minutes after start).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'subject_ids.required' => 'Please select at least one subject.',
            'start_date.required' => 'Please select a start date.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'slot_duration.required' => 'Please specify exam duration in minutes.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('[GenerateRoutineRequest] Validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['_token']),
        ]);

        parent::failedValidation($validator);
    }
}
