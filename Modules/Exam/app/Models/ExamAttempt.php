<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Student\app\Models\Student;

class ExamAttempt extends BaseModel
{
    protected $fillable = [
        'exam_routine_id', 'student_id', 'is_practice',
        'started_at', 'submitted_at', 'last_saved_at',
        'answers', 'score', 'total_marks', 'status',
    ];

    protected $casts = [
        'is_practice' => 'boolean',
        'answers' => 'array',
        'score' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_saved_at' => 'datetime',
    ];

    protected array $filterable = ['status', 'is_practice', 'exam_routine_id', 'student_id'];

    public function routine()
    {
        return $this->belongsTo(ExamRoutine::class, 'exam_routine_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopePractice($query)
    {
        return $query->where('is_practice', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}
