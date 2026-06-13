<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class PaymentAllocation extends BaseModel
{
    protected $fillable = [
        'transaction_id', 'fee_assignment_id', 'amount'
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    protected array $filterable = ['transaction_id', 'fee_assignment_id'];

    public function transaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function feeAssignment()
    {
        return $this->belongsTo(StudentFeeAssignment::class);
    }
}
