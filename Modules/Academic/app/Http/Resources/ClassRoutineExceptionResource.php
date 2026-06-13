<?php

namespace Modules\Academic\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRoutineExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_routine_id' => $this->class_routine_id,
            'exception_date' => $this->exception_date,
            'exception_type' => $this->exception_type,
            'original_subject_id' => $this->original_subject_id,
            'new_period_id' => $this->new_period_id,
            'substitute_teacher_id' => $this->substitute_teacher_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
