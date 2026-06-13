<?php

namespace Modules\Attendance\app\Services;

use Modules\Attendance\app\Models\BiometricUserMapping;

/**
 * Resolves fingerprint/card UID to a system user (for future device use).
 */
class BiometricMappingService
{
    /**
     * @return array{user_type: string, user_id: string}|null
     */
    public function resolve(?string $deviceId, string $biometricUid): ?array
    {
        $query = BiometricUserMapping::query()
            ->where('biometric_uid', $biometricUid)
            ->where('is_active', true);

        if ($deviceId) {
            $mapping = (clone $query)->where('device_id', $deviceId)->first()
                ?? (clone $query)->whereNull('device_id')->first();
        } else {
            $mapping = $query->whereNull('device_id')->first();
        }

        if (!$mapping) {
            return null;
        }

        return [
            'user_type' => $mapping->user_type,
            'user_id' => $mapping->user_id,
        ];
    }
}
