<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;

class BiometricLog extends BaseModel
{
    protected $table = 'biometric_logs';

    protected $fillable = [
        'device_id', 'biometric_uid', 'scan_time', 'sync_status', 'raw_payload',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    protected array $filterable = ['device_id', 'sync_status'];

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }
}
