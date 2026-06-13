<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Student\app\Models\Student;
use Modules\Enrollment\app\Models\Batch;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\RoutinePeriod;

class StudentAttendance extends BaseModel
{
    protected $table = 'student_attendances';

    protected $fillable = [
        'attendance_log_id', 'student_id', 'batch_id', 'subject_id', 'slot_id',
    ];

    protected array $filterable = ['student_id', 'batch_id', 'subject_id'];

    public function log()
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function slot()
    {
        return $this->belongsTo(RoutinePeriod::class, 'slot_id');
    }
}
