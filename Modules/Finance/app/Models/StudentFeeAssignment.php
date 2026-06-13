<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class StudentFeeAssignment extends BaseModel
{
    protected $fillable = [
        'enrollment_id', 'fee_structure_id', 'original_amount', 'discounted_amount',
        'final_amount', 'due_date', 'period_month', 'status', 'late_fee_applied', 'paid_amount',
        'balance', 'installment_plan_id', 'installment_number', 'remarks'
    ];

    protected $casts = [
        'original_amount' => 'float',
        'discounted_amount' => 'float',
        'final_amount' => 'float',
        'paid_amount' => 'float',
        'balance' => 'float',
        'late_fee_applied' => 'float',
        'due_date' => 'date',
        'installment_number' => 'integer',
    ];

    protected array $searchable = ['remarks'];
    protected array $filterable = ['status', 'enrollment_id', 'fee_structure_id', 'installment_plan_id'];

    public function enrollment()
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Enrollment::class);
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'fee_assignment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '>', now());
    }
}
