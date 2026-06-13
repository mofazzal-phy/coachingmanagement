<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;

class AttendanceTimeMetric extends BaseModel
{
    protected $table = 'attendance_time_metrics';

    protected $fillable = [
        'attendance_log_id',
        'scheduled_start', 'scheduled_end',
        'actual_check_in', 'actual_check_out',
        'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes',
        'employment_context', 'computed_at',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'employment_context' => 'array',
        'computed_at' => 'datetime',
    ];

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }
}
