<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class EnrollmentSession extends BaseModel
{
    protected $table = 'enrollment_sessions';

    protected $fillable = [
        'current_step', 'enrollment_type', 'student_id',
        'step_data', 'status', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'step_data' => 'array',
        'expires_at' => 'datetime',
        'current_step' => 'integer',
    ];

    protected array $searchable = [];
    protected array $filterable = ['status', 'enrollment_type', 'created_by'];

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class, 'student_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
