<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class PaymentTransaction extends BaseModel
{
    protected $fillable = [
        'enrollment_id', 'student_id', 'transaction_no', 'amount',
        'payment_method', 'gateway_trx_id', 'reference_no', 'status',
        'confirmed_by', 'confirmed_at', 'rejection_reason', 'remarks',
        'is_manual'
    ];

    protected $casts = [
        'amount' => 'float',
        'confirmed_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    protected array $searchable = ['transaction_no', 'gateway_trx_id', 'reference_no', 'remarks'];
    protected array $filterable = ['status', 'payment_method', 'enrollment_id', 'student_id', 'is_manual'];

    public function enrollment()
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Enrollment::class);
    }

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmed_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'transaction_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeManual($query)
    {
        return $query->where('is_manual', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_manual', false);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }
}
