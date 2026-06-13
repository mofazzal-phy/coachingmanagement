<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class ExpenseCategory extends BaseModel
{
    protected $fillable = ['name', 'code', 'description', 'status'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
