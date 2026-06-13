<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class SmartPaymentInvoice extends BaseModel
{
    protected $table = 'smart_payment_invoices';

    protected $fillable = [
        'payment_transaction_id', 'invoice_no', 'invoice_type',
        'generated_at', 'generated_by', 'file_path', 'metadata'
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'metadata' => 'json',
    ];

    protected array $searchable = ['invoice_no', 'invoice_type'];
    protected array $filterable = ['invoice_type', 'payment_transaction_id', 'generated_by'];

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function generator()
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }

    public function scopeByInvoiceType($query, string $type)
    {
        return $query->where('invoice_type', $type);
    }
}
