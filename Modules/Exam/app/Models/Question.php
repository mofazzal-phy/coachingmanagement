<?php

namespace Modules\Exam\app\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\app\Models\BaseModel;

class Question extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'question_set_id', 'set_title', 'sort_order',
        'created_by', 'class_id', 'subject_id', 'course_id', 'batch_id',
        'chapter', 'topic', 'question_type', 'difficulty', 'marks',
        'content', 'stimulus', 'options', 'parts', 'correct_answer', 'attachment_path',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'options' => 'array',
        'parts' => 'array',
        'correct_answer' => 'array',
        'marks' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected array $searchable = ['content', 'chapter', 'topic'];
    protected array $filterable = [
        'status', 'question_type', 'difficulty', 'subject_id',
        'class_id', 'course_id', 'batch_id', 'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function subject()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Subject::class);
    }

    public function class()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class, 'class_id');
    }

    public function course()
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Batch::class);
    }

    public function reviewLogs()
    {
        return $this->hasMany(QuestionReviewLog::class)->orderByDesc('created_at');
    }

    public function isEditableByCreator(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
