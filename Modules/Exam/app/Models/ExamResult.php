<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;

class ExamResult extends BaseModel
{
    protected $fillable = [
        'exam_id', 'exam_routine_id', 'student_id', 'exam_attempt_id', 'subject_id',
        'marks_obtained', 'marks_breakdown', 'total_marks', 'grade', 'grade_point',
        'remarks', 'status', 'evaluation_status', 'entered_by'
    ];

    protected $casts = [
        'marks_breakdown' => 'array',
    ];

    protected array $searchable = ['remarks'];
    protected array $filterable = ['status', 'evaluation_status', 'exam_id', 'student_id', 'subject_id'];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function routine()
    {
        return $this->belongsTo(ExamRoutine::class, 'exam_routine_id');
    }

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class);
    }

    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function subject()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Subject::class);
    }
}
