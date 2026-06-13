<?php

namespace Modules\Hr\app\Models;

use Modules\Core\app\Models\BaseModel;

class Employee extends BaseModel
{
    protected $fillable = [
        'user_id', 'department_id', 'designation_id', 'employee_id',
        'first_name', 'last_name', 'email', 'phone', 'address',
        'gender', 'date_of_birth', 'date_of_joining', 'date_of_leaving',
        'employment_type', 'salary', 'qualification', 'photo', 'status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_joining' => 'date',
        'date_of_leaving' => 'date',
        'salary' => 'decimal:2',
    ];

    protected array $searchable = ['employee_id', 'first_name', 'last_name', 'email', 'phone'];
    protected array $filterable = ['status', 'department_id', 'designation_id', 'employment_type'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
