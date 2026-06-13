<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class PaymentInvoice extends BaseModel
{
    protected $table = 'payment_invoices';

    protected $fillable = [
        'monthly_fee_payment_id', 'invoice_no', 'invoice_type',
        'generated_at', 'generated_by', 'file_path', 'metadata',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected array $searchable = ['invoice_no'];

    protected array $filterable = [
        'monthly_fee_payment_id', 'invoice_type',
    ];

    public function monthlyFeePayment()
    {
        return $this->belongsTo(MonthlyFeePayment::class, 'monthly_fee_payment_id');
    }

    public function generator()
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }
}
