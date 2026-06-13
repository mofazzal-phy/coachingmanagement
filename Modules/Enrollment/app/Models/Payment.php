<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class Payment extends BaseModel
{
    protected $table = 'payments';

    protected $fillable = [
        'enrollment_id', 'receipt_no', 'payment_method',
        'amount', 'received_amount', 'transaction_id', 'reference',
        'payment_date', 'payment_note', 'payment_status',
        'verified_by', 'verified_at', 'gateway_response',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'payment_date' => 'datetime',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function verifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }
}
