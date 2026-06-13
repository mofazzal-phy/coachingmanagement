<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Hr\app\Models\Employee;
use Modules\Hr\app\Models\Department;

class EmployeeAttendance extends BaseModel
{
    protected $table = 'employee_attendances';

    protected $fillable = [
        'attendance_log_id', 'employee_id', 'department_id', 'shift_id',
    ];

    protected array $filterable = ['employee_id', 'department_id'];

    public function log()
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
