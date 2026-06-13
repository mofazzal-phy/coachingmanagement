<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class FeeStructure extends BaseModel
{
    protected $fillable = [
        'academic_session_id', 'class_id', 'fee_type_id', 'exam_id', 'course_id',
        'amount', 'description', 'due_day', 'due_date', 'event_date', 'status',
    ];
    protected array $filterable = ['status', 'class_id', 'academic_session_id', 'fee_type_id'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'event_date' => 'date:Y-m-d',
    ];

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }

    public function class()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class);
    }
}
