<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Teacher\app\Models\Teacher;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\Classes;

class TeacherAttendance extends BaseModel
{
    protected $table = 'teacher_attendances';

    protected $fillable = [
        'attendance_log_id', 'teacher_id', 'subject_id', 'class_id',
    ];

    protected array $filterable = ['teacher_id', 'subject_id', 'class_id'];

    public function log()
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }
}
