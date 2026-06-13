<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Student\app\Models\Student;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\AcademicGroup;
use Modules\Academic\app\Models\Subject;
use Modules\Teacher\app\Models\Teacher;

class Enrollment extends BaseModel
{
    protected $table = 'enrollments';

    protected $fillable = [
        'enrollment_no', 'student_id', 'batch_id', 'academic_session_id',
        'enrollment_type', 'source', 'previous_enrollment_id',
        'enrolled_class_id', 'enrolled_group_id',
        'mode',
        'total_fee', 'discount_percent', 'discount_reason',
        'payable_fee', 'paid_amount', 'due_amount', 'payment_status',
        'fee_type',
        'enrollment_fee', 'enrollment_fee_paid',
        'total_months', 'paid_months',
        'status', 'enrolled_at', 'start_date', 'end_date',
        'guardian_phone', 'guardian_email',
        'payment_method', 'payment_reference', 'payment_transaction_id',
        'payment_date', 'invoice_no',
        'waiting_position', 'priority',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_fee' => 'decimal:2',
        'payable_fee' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'enrollment_fee' => 'decimal:2',
        'enrollment_fee_paid' => 'decimal:2',
    ];

    /**
     * Dynamic payable amount — never negative.
     * For one-time: max(0, payable_fee - paid_amount)
     * For monthly: current month's remaining payable
     */
    public function getPayableAmountAttribute(): float
    {
        $remaining = (float) max(0, ($this->payable_fee ?? 0) - ($this->paid_amount ?? 0));
        return round($remaining, 2);
    }

    /**
     * Alias for frontend consistency — same as payable_amount, never negative.
     */
    public function getDueAmountAttribute($value): float
    {
        return round((float) max(0, $value ?? 0), 2);
    }

    /**
     * True if nothing is owed for the current period.
     */
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->payable_amount <= 0;
    }

    protected array $searchable = ['enrollment_no', 'guardian_phone', 'guardian_email'];
    protected array $filterable = [
        'status', 'payment_status', 'enrollment_type', 'mode',
        'student_id', 'batch_id', 'academic_session_id',
        'enrolled_class_id', 'enrolled_group_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function enrolledClass()
    {
        return $this->belongsTo(Classes::class, 'enrolled_class_id');
    }

    public function enrolledGroup()
    {
        return $this->belongsTo(AcademicGroup::class, 'enrolled_group_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'enrollment_subject', 'enrollment_id', 'subject_id')
            ->withPivot(['subject_fee', 'teacher_id', 'batch_id'])
            ->withTimestamps();
    }

    public function previousEnrollment()
    {
        return $this->belongsTo(Enrollment::class, 'previous_enrollment_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'enrollment_id');
    }

    public function monthlyFeeRecords()
    {
        return $this->hasMany(MonthlyFeeRecord::class, 'enrollment_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'enrollment_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(EnrollmentActivityLog::class, 'enrollment_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function getCourseAttribute()
    {
        return $this->batch?->course;
    }
}
