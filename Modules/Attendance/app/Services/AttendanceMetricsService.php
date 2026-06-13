<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\AttendanceTimeMetric;
use Modules\Hr\app\Models\Employee;

class AttendanceMetricsService
{
    protected int $lateGraceMinutes = 15;

    protected string $defaultStart = '09:00';

    protected string $defaultEnd = '17:00';

    public function computeForLog(AttendanceLog $log, ?Employee $employee = null): AttendanceTimeMetric
    {
        $date = Carbon::parse($log->attendance_date);
        $scheduledStart = Carbon::parse($date->format('Y-m-d') . ' ' . $this->defaultStart);
        $scheduledEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $this->defaultEnd);

        $checkIn = $log->check_in ? Carbon::parse($log->check_in) : null;
        $checkOut = $log->check_out ? Carbon::parse($log->check_out) : null;

        $workedMinutes = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $workedMinutes = (int) $checkIn->diffInMinutes($checkOut);
        }

        $graceEnd = $scheduledStart->copy()->addMinutes($this->lateGraceMinutes);
        if ($checkIn && $checkIn->gt($graceEnd)) {
            $lateMinutes = (int) $graceEnd->diffInMinutes($checkIn);
        }

        if ($checkOut && $checkOut->lt($scheduledEnd) && in_array($log->attendance_status, ['present', 'late', 'half_day'], true)) {
            $earlyLeaveMinutes = (int) $checkOut->diffInMinutes($scheduledEnd);
        }

        $scheduledMinutes = (int) $scheduledStart->diffInMinutes($scheduledEnd);
        if ($workedMinutes > $scheduledMinutes) {
            $overtimeMinutes = $workedMinutes - $scheduledMinutes;
        }

        if ($employee === null && $log->user_type === 'employee') {
            $employee = Employee::find($log->user_id);
        }

        return AttendanceTimeMetric::updateOrCreate(
            ['attendance_log_id' => $log->id],
            [
                'scheduled_start' => $scheduledStart,
                'scheduled_end' => $scheduledEnd,
                'actual_check_in' => $checkIn,
                'actual_check_out' => $checkOut,
                'worked_minutes' => $workedMinutes,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'employment_context' => [
                    'employment_type' => $employee?->employment_type,
                    'user_type' => $log->user_type,
                ],
                'computed_at' => now(),
            ]
        );
    }

    public function formatForApi(?AttendanceTimeMetric $metric): ?array
    {
        if (!$metric) {
            return null;
        }

        return [
            'worked_minutes' => $metric->worked_minutes,
            'worked_hours' => round($metric->worked_minutes / 60, 2),
            'late_minutes' => $metric->late_minutes,
            'early_leave_minutes' => $metric->early_leave_minutes,
            'overtime_minutes' => $metric->overtime_minutes,
            'scheduled_start' => $metric->scheduled_start?->format('H:i'),
            'scheduled_end' => $metric->scheduled_end?->format('H:i'),
        ];
    }
}
