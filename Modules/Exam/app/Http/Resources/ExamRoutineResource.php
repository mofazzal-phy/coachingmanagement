<?php

namespace Modules\Exam\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamRoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'subject_id' => $this->subject_id,
            'batch_id' => $this->batch_id,
            'course_id' => $this->course_id,
            'class_id' => $this->class_id,
            'group_id' => $this->group_id,
            'exam_date' => $this->exam_date?->toDateString(),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'room_id' => $this->room_id,
            'teacher_id' => $this->teacher_id,
            'total_marks' => $this->total_marks,
            'pass_marks' => $this->pass_marks,
            'mark_config' => $this->mark_config,
            'instructions' => $this->instructions,
            'status' => $this->status,
            'delivery_mode' => $this->delivery_mode ?: 'offline',
            'delivery_channel' => $this->deliveryChannel(),
            'duration_minutes' => $this->duration_minutes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships (when loaded)
            'exam' => $this->whenLoaded('exam', fn() => [
                'id' => $this->exam->id,
                'name' => $this->exam->name,
                'start_date' => $this->exam->start_date?->toDateString(),
                'end_date' => $this->exam->end_date?->toDateString(),
                'status' => $this->exam->status,
            ]),
            'subject' => $this->whenLoaded('subject', fn() => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'batch' => $this->whenLoaded('batch', fn() => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ]),
            'course' => $this->whenLoaded('course', fn() => [
                'id' => $this->course->id,
                'name' => $this->course->name,
            ]),
            'class' => $this->whenLoaded('class', fn() => [
                'id' => $this->class->id,
                'name' => $this->class->name,
            ]),
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'name' => $this->group->name,
            ]),
            'room' => $this->whenLoaded('room', fn() => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'code' => $this->room->code,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn() => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->full_name,
            ]),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
