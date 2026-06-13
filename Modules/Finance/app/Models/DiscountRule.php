<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class DiscountRule extends BaseModel
{
    protected $fillable = [
        'name', 'condition_type', 'condition_config',
        'discount_type', 'discount_value', 'max_cap', 'priority', 'stackable',
        'status', 'created_by'
    ];

    protected $casts = [
        'condition_config' => 'json',
        'stackable' => 'boolean',
        'max_cap' => 'float',
        'discount_value' => 'float',
        'priority' => 'integer',
    ];

    protected array $searchable = ['name'];
    protected array $filterable = ['condition_type', 'discount_type', 'status'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByConditionType($query, string $type)
    {
        return $query->where('condition_type', $type);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
