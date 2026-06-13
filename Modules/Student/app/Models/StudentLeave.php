<?php

namespace Modules\Student\app\Models;

use Modules\Core\app\Models\BaseModel;

class StudentLeave extends BaseModel
{
    protected $table = 'student_leaves';

    protected $fillable = [
        'student_id', 'leave_type', 'start_date', 'end_date',
        'total_days', 'reason', 'status', 'approved_by', 'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected array $searchable = ['reason'];
    protected array $filterable = ['status', 'student_id', 'leave_type'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
