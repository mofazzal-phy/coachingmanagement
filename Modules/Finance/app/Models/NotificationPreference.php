<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class NotificationPreference extends BaseModel
{
    protected $fillable = [
        'student_id', 'sms_enabled', 'email_enabled',
        'due_reminder', 'overdue_alert', 'payment_confirmation',
        'payment_rejection', 'installment_reminder',
        'sms_phone', 'email_address'
    ];

    protected $casts = [
        'sms_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'due_reminder' => 'boolean',
        'overdue_alert' => 'boolean',
        'payment_confirmation' => 'boolean',
        'payment_rejection' => 'boolean',
        'installment_reminder' => 'boolean',
    ];

    protected array $searchable = ['sms_phone', 'email_address'];
    protected array $filterable = ['student_id', 'sms_enabled', 'email_enabled'];

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class);
    }
}
