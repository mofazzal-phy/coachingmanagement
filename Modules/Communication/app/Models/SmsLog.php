<?php

namespace Modules\Communication\app\Models;

use Modules\Core\app\Models\BaseModel;

class SmsLog extends BaseModel
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'recipient', 'message', 'gateway', 'status', 'response',
    ];

    protected array $searchable = ['recipient', 'message'];
    protected array $filterable = ['status', 'gateway'];
}
