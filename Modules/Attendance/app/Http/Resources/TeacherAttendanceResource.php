<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'attendance_log_id' => $this->attendance_log_id,
            'teacher_id' => $this->teacher_id,
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($this->relationLoaded('teacher')) {
            $data['teacher'] = [
                'id' => $this->teacher->id,
                'teacher_id' => $this->teacher->teacher_id,
                'name' => $this->teacher->first_name . ' ' . $this->teacher->last_name,
            ];
        }

        if ($this->relationLoaded('subject')) {
            $data['subject'] = [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ];
        }

        if ($this->relationLoaded('class')) {
            $data['class'] = [
                'id' => $this->class->id,
                'name' => $this->class->name,
            ];
        }

        if ($this->relationLoaded('log')) {
            $data['log'] = new AttendanceLogResource($this->log);
        }

        return $data;
    }
}
