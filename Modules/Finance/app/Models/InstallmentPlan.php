<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class InstallmentPlan extends BaseModel
{
    protected $fillable = [
        'name', 'description', 'plan_type', 'total_installments', 'frequency_days',
        'config', 'late_fee_rule_id', 'is_active'
    ];

    protected $casts = [
        'config' => 'json',
        'is_active' => 'boolean',
        'total_installments' => 'integer',
        'frequency_days' => 'integer',
    ];

    protected array $searchable = ['name', 'description'];
    protected array $filterable = ['plan_type', 'is_active'];

    public function lateFeeRule()
    {
        return $this->belongsTo(LateFeeRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function studentFeeAssignments()
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }
}
