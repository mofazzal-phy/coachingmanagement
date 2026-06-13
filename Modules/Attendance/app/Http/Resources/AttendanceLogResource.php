<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'user_type' => $this->user_type,
            'user_id' => $this->user_id,
            'attendance_source' => $this->attendance_source,
            'attendance_status' => $this->attendance_status,
            'check_in' => $this->check_in?->toDateTimeString(),
            'check_out' => $this->check_out?->toDateTimeString(),
            'attendance_date' => $this->attendance_date?->toDateString(),
            'device_id' => $this->device_id,
            'attendance_session_id' => $this->attendance_session_id,
            'remarks' => $this->remarks,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        // Include relationships when loaded
        if ($this->relationLoaded('session')) {
            $data['session'] = new AttendanceSessionResource($this->session);
        }

        if ($this->relationLoaded('device')) {
            $data['device'] = new BiometricDeviceResource($this->device);
        }

        if ($this->relationLoaded('creator')) {
            $data['creator'] = [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ];
        }

        // Include type-specific attendance data
        if ($this->relationLoaded('studentAttendance') && $this->studentAttendance) {
            $data['student_attendance'] = new StudentAttendanceResource($this->studentAttendance);
        }

        if ($this->relationLoaded('teacherAttendance') && $this->teacherAttendance) {
            $data['teacher_attendance'] = new TeacherAttendanceResource($this->teacherAttendance);
        }

        if ($this->relationLoaded('employeeAttendance') && $this->employeeAttendance) {
            $data['employee_attendance'] = new EmployeeAttendanceResource($this->employeeAttendance);
        }

        return $data;
    }
}
