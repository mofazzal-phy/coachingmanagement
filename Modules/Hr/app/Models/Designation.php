<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class Designation extends BaseModel
{
    protected $fillable = ['name', 'code', 'description', 'status'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
