<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class LeaveRequest extends BaseModel
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'total_days', 'reason', 'status', 'approved_by', 'rejection_reason'
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
    protected array $searchable = ['reason'];
    protected array $filterable = ['status', 'employee_id', 'leave_type_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
