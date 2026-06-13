<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class FeeCollection extends BaseModel
{
    protected $fillable = [
        'student_id', 'academic_session_id', 'fee_type_id', 'invoice_no',
        'amount', 'discount', 'paid_amount', 'due_amount',
        'due_date', 'paid_date', 'payment_method', 'transaction_id',
        'remarks', 'status', 'collected_by'
    ];

    protected $casts = ['due_date' => 'date', 'paid_date' => 'date'];

    protected array $searchable = ['invoice_no', 'transaction_id', 'remarks'];
    protected array $filterable = ['status', 'payment_method', 'student_id', 'fee_type_id'];

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class);
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
