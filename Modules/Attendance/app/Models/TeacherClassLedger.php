<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Teacher\app\Models\Teacher;
use Modules\Academic\app\Models\Subject;
use Modules\Enrollment\app\Models\Batch;

class TeacherClassLedger extends BaseModel
{
    protected $table = 'teacher_class_ledger';

    protected $fillable = [
        'teacher_id', 'class_session_id', 'attendance_log_id',
        'teacher_type', 'batch_id', 'subject_id', 'session_date',
        'status', 'payable_units', 'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'payable_units' => 'decimal:2',
    ];

    protected array $filterable = [
        'teacher_id', 'class_session_id', 'batch_id', 'subject_id',
        'session_date', 'status', 'teacher_type',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
