<?php

namespace Modules\Academic\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateClassRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => 'required|in:batch,course,class',
            'level_id' => 'required|string',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'string|exists:subjects,id',
            'constraints' => 'nullable|array',
            'constraints.days' => 'nullable|array',
            'constraints.days.*' => 'in:sat,sun,mon,tue,wed,thu,fri',
            'constraints.max_per_day' => 'nullable|integer|min:1|max:12',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'apply' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'level.required' => 'Level is required (batch, course, or class)',
            'level_id.required' => 'Level ID is required',
            'subject_ids.required' => 'At least one subject must be selected',
            'subject_ids.min' => 'At least one subject must be selected',
        ];
    }
}
