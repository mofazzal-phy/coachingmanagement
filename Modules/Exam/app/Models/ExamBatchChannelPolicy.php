<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Enrollment\app\Models\Batch;

class ExamBatchChannelPolicy extends BaseModel
{
    protected $fillable = [
        'exam_id',
        'batch_id',
        'delivery_channel',
        'eligibility_check_enabled',
        'min_attendance_percent',
        'exam_fee_applicable',
    ];

    protected $casts = [
        'eligibility_check_enabled' => 'boolean',
        'exam_fee_applicable' => 'boolean',
        'min_attendance_percent' => 'float',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return array{check_enabled: bool, min_percent: ?float, fee_applicable: bool}
     */
    public function rulesArray(): array
    {
        return [
            'check_enabled' => (bool) $this->eligibility_check_enabled,
            'min_percent' => $this->min_attendance_percent,
            'fee_applicable' => (bool) $this->exam_fee_applicable,
        ];
    }
}
