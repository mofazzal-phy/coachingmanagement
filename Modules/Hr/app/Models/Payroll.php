<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class Payroll extends BaseModel
{
    protected $fillable = [
        'employee_id', 'month', 'basic_salary', 'allowances', 'deductions',
        'net_salary', 'bonus', 'present_days', 'absent_days', 'leave_days',
        'status', 'payment_date', 'remarks'
    ];

    protected $casts = ['payment_date' => 'date'];
    protected array $filterable = ['status', 'month', 'employee_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
