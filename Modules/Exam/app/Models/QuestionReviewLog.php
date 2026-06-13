<?php

namespace Modules\Exam\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\app\Traits\HasUuid;

class QuestionReviewLog extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'question_id', 'action', 'reviewed_by', 'comment', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}
