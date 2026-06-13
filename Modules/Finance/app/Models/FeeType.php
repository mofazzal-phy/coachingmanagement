<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class FeeType extends BaseModel
{
    protected $fillable = ['name', 'code', 'description', 'category', 'status'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status', 'category'];

    public function isOneTime(): bool
    {
        return $this->category === 'one_time';
    }

    public function isMonthly(): bool
    {
        return $this->category === 'monthly';
    }

    public function isEventBased(): bool
    {
        return $this->category === 'event_based';
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'one_time' => 'One Time',
            'monthly' => 'Monthly',
            'event_based' => 'Event Based',
            default => ucfirst(str_replace('_', ' ', $this->category ?? 'monthly')),
        };
    }
}
