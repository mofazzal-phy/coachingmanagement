<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'attendance_log_id' => $this->attendance_log_id,
            'student_id' => $this->student_id,
            'batch_id' => $this->batch_id,
            'subject_id' => $this->subject_id,
            'slot_id' => $this->slot_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($this->relationLoaded('student')) {
            $data['student'] = [
                'id' => $this->student->id,
                'student_id' => $this->student->student_id,
                'name' => $this->student->full_name,
                'roll_no' => $this->student->roll_no,
            ];
        }

        if ($this->relationLoaded('batch')) {
            $data['batch'] = [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ];
        }

        if ($this->relationLoaded('subject')) {
            $data['subject'] = [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ];
        }

        if ($this->relationLoaded('log')) {
            $data['log'] = new AttendanceLogResource($this->log);
        }

        return $data;
    }
}
