<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBiometricDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deviceId = $this->route('id'); // For update operations

        return [
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'port' => 'nullable|integer|min:1|max:65535',
            'serial_no' => 'nullable|string|max:255|unique:biometric_devices,serial_no' . ($deviceId ? ',' . $deviceId : ''),
            'driver' => 'required|string|in:fake,simulator,zkteco,essl',
            'config' => 'nullable|array',
            'status' => 'nullable|in:online,offline,error',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'device_name.required' => 'Device name is required.',
            'device_name.max' => 'Device name must not exceed 255 characters.',
            'device_type.required' => 'Device type is required.',
            'driver.required' => 'Driver type is required.',
            'driver.in' => 'Invalid driver. Valid: fake, simulator, zkteco, essl.',
            'serial_no.unique' => 'This serial number is already registered.',
            'port.integer' => 'Port must be a valid integer.',
            'port.min' => 'Port must be between 1 and 65535.',
            'port.max' => 'Port must be between 1 and 65535.',
            'status.in' => 'Invalid status. Valid: online, offline, error.',
        ];
    }
}
