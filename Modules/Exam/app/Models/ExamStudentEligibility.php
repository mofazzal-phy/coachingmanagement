<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Student\app\Models\Student;

class ExamStudentEligibility extends BaseModel
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_WARNING = 'warning';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_OVERRIDDEN = 'overridden';

    protected $table = 'exam_student_eligibility';

    protected $fillable = [
        'exam_id',
        'student_id',
        'status',
        'attendance_percent',
        'is_override',
        'override_reason',
        'overridden_by',
        'overridden_at',
        'computed_at',
    ];

    protected $casts = [
        'attendance_percent' => 'float',
        'is_override' => 'boolean',
        'overridden_at' => 'datetime',
        'computed_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
