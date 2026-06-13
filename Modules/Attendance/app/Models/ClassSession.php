<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\Room;
use Modules\Academic\app\Models\Subject;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Teacher\app\Models\Teacher;

class ClassSession extends BaseModel
{
    protected $table = 'class_sessions';

    protected $fillable = [
        'routine_id', 'batch_id', 'course_id', 'class_id', 'subject_id',
        'teacher_id', 'room_id', 'slot_id',
        'session_date', 'start_time', 'end_time',
        'status', 'source', 'attendance_session_id',
        'cancelled_at', 'cancel_reason', 'rescheduled_from_id',
    ];

    protected $casts = [
        'session_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    protected array $filterable = [
        'batch_id', 'subject_id', 'teacher_id', 'status',
        'session_date', 'source', 'routine_id',
    ];

    public function routine()
    {
        return $this->belongsTo(ClassRoutine::class, 'routine_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }
}
