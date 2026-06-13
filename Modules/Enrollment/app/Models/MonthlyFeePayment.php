<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class MonthlyFeePayment extends BaseModel
{
    protected $table = 'monthly_fee_payments';

    protected $fillable = [
        'monthly_fee_record_id', 'payment_id',
        'amount', 'payment_method', 'transaction_id',
        'sender_number', 'bank_name',
        'reference', 'payment_date', 'note',
        'payment_status', 'confirmed_by', 'confirmed_at',
        'rejection_reason', 'gateway_response', 'invoice_no',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'confirmed_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    protected array $searchable = [
        'transaction_id', 'reference', 'note', 'invoice_no',
        'sender_number',
    ];

    protected array $filterable = [
        'monthly_fee_record_id', 'payment_method', 'payment_status',
    ];

    public function monthlyFeeRecord()
    {
        return $this->belongsTo(MonthlyFeeRecord::class, 'monthly_fee_record_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function confirmer()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmed_by');
    }

    public function invoice()
    {
        return $this->hasOne(PaymentInvoice::class, 'monthly_fee_payment_id');
    }

    // Scopes
    public function scopeAwaitingConfirmation($query)
    {
        return $query->where('payment_status', 'awaiting_confirmation');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('payment_status', 'confirmed');
    }

    public function scopeRejected($query)
    {
        return $query->where('payment_status', 'rejected');
    }
}
