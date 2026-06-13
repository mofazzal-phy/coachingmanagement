<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class EnrollmentActivityLog extends BaseModel
{
    protected $table = 'enrollment_activity_logs';

    protected $fillable = [
        'enrollment_id', 'model_type', 'model_id', 'action', 'description',
        'performed_by', 'old_status', 'new_status',
        'old_values', 'new_values', 'ip_address', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public $timestamps = false; // only created_at

    public function performer()
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
