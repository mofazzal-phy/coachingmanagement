<?php

namespace Modules\Attendance\app\Models;

use App\Models\User;
use Modules\Core\app\Models\BaseModel;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\Room;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Teacher\app\Models\Teacher;

class AttendanceSession extends BaseModel
{
    protected $table = 'attendance_sessions';

    protected $fillable = [
        'class_id', 'course_id', 'batch_id', 'subject_id', 'teacher_id',
        'room_id', 'slot_id', 'routine_id', 'class_session_id',
        'attendance_date', 'start_time', 'end_time',
        'session_type', 'source', 'status', 'scheduled_status',
        'rescheduled_from_id', 'cancel_reason', 'expected_headcount',
        'created_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    protected array $filterable = [
        'batch_id', 'subject_id', 'teacher_id', 'status', 'scheduled_status',
        'session_type', 'source', 'attendance_date', 'class_session_id', 'routine_id',
    ];

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(AttendanceLog::class, 'attendance_session_id');
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function routine()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\ClassRoutine::class, 'routine_id');
    }
}
