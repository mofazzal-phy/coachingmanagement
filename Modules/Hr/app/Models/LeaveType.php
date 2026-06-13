<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class LeaveType extends BaseModel
{
    protected $fillable = ['name', 'code', 'max_days_per_year', 'description', 'status'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status'];
}
