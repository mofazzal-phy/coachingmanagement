<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'attendance_log_id' => $this->attendance_log_id,
            'employee_id' => $this->employee_id,
            'department_id' => $this->department_id,
            'shift_id' => $this->shift_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($this->relationLoaded('employee')) {
            $data['employee'] = [
                'id' => $this->employee->id,
                'employee_id' => $this->employee->employee_id,
                'name' => $this->employee->full_name,
            ];
        }

        if ($this->relationLoaded('department')) {
            $data['department'] = [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ];
        }

        if ($this->relationLoaded('log')) {
            $data['log'] = new AttendanceLogResource($this->log);
        }

        return $data;
    }
}
