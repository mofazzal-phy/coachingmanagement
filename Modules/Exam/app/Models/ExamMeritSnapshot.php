<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;

class ExamMeritSnapshot extends BaseModel
{
    protected $fillable = [
        'exam_id',
        'delivery_channel',
        'scope_type',
        'scope_id',
        'channel_published_at',
        'total_students',
        'ranking_rule',
        'scope_meta',
        'merit_list',
        'subject_toppers',
        'computed_at',
    ];

    protected $casts = [
        'channel_published_at' => 'datetime',
        'computed_at' => 'datetime',
        'scope_meta' => 'array',
        'merit_list' => 'array',
        'subject_toppers' => 'array',
        'total_students' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
