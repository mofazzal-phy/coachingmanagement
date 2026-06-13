<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'course_id' => $this->course_id,
            'batch_id' => $this->batch_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'room_id' => $this->room_id,
            'slot_id' => $this->slot_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'session_type' => $this->session_type,
            'source' => $this->source,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'logs_count' => $this->when($this->logs_count !== null, $this->logs_count),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        // Include relationships when loaded
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

        if ($this->relationLoaded('teacher')) {
            $data['teacher'] = [
                'id' => $this->teacher->id,
                'name' => $this->teacher->first_name . ' ' . $this->teacher->last_name,
            ];
        }

        if ($this->relationLoaded('room')) {
            $data['room'] = [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ];
        }

        if ($this->relationLoaded('class')) {
            $data['class'] = [
                'id' => $this->class->id,
                'name' => $this->class->name,
            ];
        }

        if ($this->relationLoaded('course')) {
            $data['course'] = [
                'id' => $this->course->id,
                'name' => $this->course->name,
            ];
        }

        if ($this->relationLoaded('creator')) {
            $data['creator'] = [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ];
        }

        if ($this->relationLoaded('logs')) {
            $data['logs'] = AttendanceLogResource::collection($this->logs);
        }

        return $data;
    }
}
