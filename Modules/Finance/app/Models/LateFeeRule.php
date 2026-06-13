<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class LateFeeRule extends BaseModel
{
    protected $fillable = [
        'name', 'description', 'calculation_type', 'flat_rate', 'percentage_rate',
        'tier_config', 'grace_period_days', 'max_cap', 'recurring', 'is_active',
        'applies_to'
    ];

    protected $casts = [
        'tier_config' => 'json',
        'recurring' => 'boolean',
        'is_active' => 'boolean',
        'flat_rate' => 'float',
        'percentage_rate' => 'float',
        'grace_period_days' => 'integer',
        'max_cap' => 'float',
    ];

    protected array $searchable = ['name', 'description'];
    protected array $filterable = ['calculation_type', 'is_active', 'applies_to'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function installmentPlans()
    {
        return $this->hasMany(InstallmentPlan::class);
    }
}
