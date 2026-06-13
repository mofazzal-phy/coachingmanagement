<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;

class ExamRoutineQuestion extends BaseModel
{
    protected $fillable = [
        'exam_routine_id', 'question_id', 'sort_order', 'marks_override',
    ];

    protected $casts = [
        'marks_override' => 'decimal:2',
    ];

    public function routine()
    {
        return $this->belongsTo(ExamRoutine::class, 'exam_routine_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
