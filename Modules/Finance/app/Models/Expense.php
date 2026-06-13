<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class Expense extends BaseModel
{
    protected $fillable = [
        'expense_category_id', 'title', 'description', 'amount',
        'expense_date', 'voucher_no', 'payment_method', 'created_by'
    ];

    protected $casts = ['expense_date' => 'date'];

    protected array $searchable = ['title', 'voucher_no'];
    protected array $filterable = ['expense_category_id', 'payment_method', 'expense_date'];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
