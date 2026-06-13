<?php

namespace Modules\Attendance\app\Models;

use App\Models\User;
use Modules\Core\app\Models\BaseModel;

class AttendanceLog extends BaseModel
{
    protected $table = 'attendance_logs';

    protected $fillable = [
        'user_type', 'user_id', 'attendance_source', 'attendance_mode', 'attendance_status',
        'check_in', 'check_out', 'late_minutes', 'attendance_date', 'device_id',
        'attendance_session_id', 'session_key', 'remarks', 'created_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    protected array $filterable = [
        'user_type', 'attendance_status', 'attendance_source', 'attendance_mode',
        'attendance_date', 'device_id', 'attendance_session_id',
    ];

    protected array $searchable = ['remarks'];

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function studentAttendance()
    {
        return $this->hasOne(StudentAttendance::class, 'attendance_log_id');
    }

    public function teacherAttendance()
    {
        return $this->hasOne(TeacherAttendance::class, 'attendance_log_id');
    }

    public function employeeAttendance()
    {
        return $this->hasOne(EmployeeAttendance::class, 'attendance_log_id');
    }

    public function timeMetric()
    {
        return $this->hasOne(AttendanceTimeMetric::class, 'attendance_log_id');
    }
}
