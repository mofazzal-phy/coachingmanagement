<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class StaffAttendance extends BaseModel
{
    protected $table = 'staff_attendance';

    protected $fillable = ['employee_id', 'date', 'check_in', 'check_out', 'status', 'remarks'];
    protected $casts = ['date' => 'date', 'check_in' => 'datetime', 'check_out' => 'datetime'];
    protected array $filterable = ['status', 'date', 'employee_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
