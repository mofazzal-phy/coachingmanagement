<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;

class BiometricDevice extends BaseModel
{
    protected $table = 'biometric_devices';

    protected $fillable = [
        'device_name', 'device_type', 'ip_address', 'port',
        'serial_no', 'driver', 'config', 'status', 'is_active', 'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'port' => 'integer',
        'last_sync_at' => 'datetime',
    ];

    protected array $searchable = ['device_name', 'serial_no', 'ip_address'];
    protected array $filterable = ['status', 'is_active', 'device_type', 'driver'];

    public function logs()
    {
        return $this->hasMany(BiometricLog::class, 'device_id');
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'device_id');
    }
}
