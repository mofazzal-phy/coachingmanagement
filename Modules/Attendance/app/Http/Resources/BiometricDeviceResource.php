<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'serial_no' => $this->serial_no,
            'driver' => $this->driver,
            'config' => $this->config,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'last_sync_at' => $this->last_sync_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($this->logs_count !== null) {
            $data['logs_count'] = $this->logs_count;
        }

        if ($this->attendance_logs_count !== null) {
            $data['attendance_logs_count'] = $this->attendance_logs_count;
        }

        return $data;
    }
}
