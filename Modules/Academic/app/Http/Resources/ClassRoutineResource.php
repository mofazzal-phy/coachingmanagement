<?php

namespace Modules\Academic\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch' => $this->whenLoaded('batch', fn() => [
                'id' => $this->batch?->id,
                'name' => $this->batch?->name,
            ]),
            'course' => $this->whenLoaded('course', fn() => [
                'id' => $this->course?->id,
                'name' => $this->course?->name,
            ]),
            'class' => $this->whenLoaded('class', fn() => [
                'id' => $this->class?->id,
                'name' => $this->class?->name,
            ]),
            'section' => $this->whenLoaded('section', fn() => [
                'id' => $this->section?->id,
                'name' => $this->section?->name,
            ]),
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group?->id,
                'name' => $this->group?->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn() => [
                'id' => $this->subject?->id,
                'name' => $this->subject?->name,
                'code' => $this->subject?->code,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn() => [
                'id' => $this->teacher?->id,
                'name' => $this->teacher?->user?->name
                    ?? ($this->teacher?->first_name && $this->teacher?->last_name
                        ? $this->teacher->first_name . ' ' . $this->teacher->last_name
                        : ($this->teacher?->first_name ?? $this->teacher?->last_name ?? 'Unknown')),
                'first_name' => $this->teacher?->first_name,
                'last_name' => $this->teacher?->last_name,
            ]),
            'room' => $this->whenLoaded('room', fn() => [
                'id' => $this->room?->id,
                'name' => $this->room?->name,
                'code' => $this->room?->code,
            ]),
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->day_name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start_time_formatted' => $this->start_time_formatted,
            'end_time_formatted' => $this->end_time_formatted,
            'time_formatted' => $this->time_formatted,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'version' => $this->version,
            'status' => $this->status,
            'is_live' => $this->is_live_now,
            'duration' => $this->duration,
            'color' => $this->subject_color,
            'created_by' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
