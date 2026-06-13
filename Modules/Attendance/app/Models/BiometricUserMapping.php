<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;

class BiometricUserMapping extends BaseModel
{
    protected $table = 'biometric_user_mappings';

    protected $fillable = [
        'device_id',
        'biometric_uid',
        'user_type',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }
}
