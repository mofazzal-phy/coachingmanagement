<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Student\app\Models\Student;
use Modules\Enrollment\app\Models\Enrollment;

class StudentFeeNotification extends BaseModel
{
    protected $table = 'student_fee_notifications';

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'fee_structure_id',
        'title',
        'message',
        'amount',
        'due_date',
        'status',
        'type',
        'meta',
        'read_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_date' => 'date',
        'meta' => 'json',
        'read_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['unread', 'read']);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
