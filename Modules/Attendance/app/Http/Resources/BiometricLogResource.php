<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'biometric_uid' => $this->biometric_uid,
            'scan_time' => $this->scan_time?->toDateTimeString(),
            'sync_status' => $this->sync_status,
            'raw_payload' => $this->raw_payload,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($this->relationLoaded('device')) {
            $data['device'] = new BiometricDeviceResource($this->device);
        }

        return $data;
    }
}
