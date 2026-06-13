<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class MonthlyFeeRecord extends BaseModel
{
    protected $table = 'monthly_fee_records';

    protected $fillable = [
        'enrollment_id', 'month', 'total_monthly_fee',
        'paid_amount', 'due_amount', 'payment_status',
        'due_date', 'paid_at',
        'fine_amount', 'remarks',
    ];

    protected $casts = [
        'total_monthly_fee' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    protected array $searchable = ['month'];
    protected array $filterable = [
        'enrollment_id', 'payment_status', 'month',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function payments()
    {
        return $this->hasMany(MonthlyFeePayment::class, 'monthly_fee_record_id');
    }

    /**
     * Get only confirmed payments for this record.
     */
    public function confirmedPayments()
    {
        return $this->hasMany(MonthlyFeePayment::class, 'monthly_fee_record_id')
            ->where('payment_status', 'confirmed');
    }

    /**
     * Get payments awaiting confirmation for this record.
     */
    public function unconfirmedPayments()
    {
        return $this->hasMany(MonthlyFeePayment::class, 'monthly_fee_record_id')
            ->where('payment_status', 'awaiting_confirmation');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now());
    }

    public function scopeByMonth($query, string $month)
    {
        return $query->where('month', $month);
    }
}
