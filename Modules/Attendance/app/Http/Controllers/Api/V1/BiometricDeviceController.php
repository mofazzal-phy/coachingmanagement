<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\Biometric\BiometricServiceManager;

class BiometricDeviceController extends BaseApiController
{
    public function __construct(
        protected BiometricServiceManager $biometricManager,
        protected AttendanceEngine $engine,
    ) {}

    /**
     * List all biometric devices.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BiometricDevice::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $devices = $query->orderBy('created_at', 'desc')
            ->paginate($this->getPerPage($request));

        return $this->paginatedResponse($devices);
    }

    /**
     * Get available driver options.
     */
    public function drivers(): JsonResponse
    {
        $drivers = $this->biometricManager->getDriverOptions();
        return $this->collectionResponse(collect($drivers));
    }

    /**
     * Store a new biometric device.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'port' => 'nullable|integer|min:1|max:65535',
            'serial_no' => 'nullable|string|max:255|unique:biometric_devices,serial_no',
            'driver' => 'required|string|in:fake,simulator,zkteco,essl',
            'config' => 'nullable|array',
            'status' => 'nullable|in:online,offline,error',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['status'] ??= 'offline';
        $validated['is_active'] ??= true;

        $device = BiometricDevice::create($validated);

        return $this->created($device, 'Biometric device created successfully');
    }

    /**
     * Show a biometric device.
     */
    public function show(string $id): JsonResponse
    {
        $device = BiometricDevice::withCount(['logs', 'attendanceLogs'])->findOrFail($id);
        return $this->success($device);
    }

    /**
     * Update a biometric device.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);

        $validated = $request->validate([
            'device_name' => 'sometimes|string|max:255',
            'device_type' => 'sometimes|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'port' => 'nullable|integer|min:1|max:65535',
            'serial_no' => 'nullable|string|max:255|unique:biometric_devices,serial_no,' . $id,
            'driver' => 'sometimes|string|in:fake,simulator,zkteco,essl',
            'config' => 'nullable|array',
            'status' => 'nullable|in:online,offline,error',
            'is_active' => 'nullable|boolean',
        ]);

        $device->update($validated);

        return $this->success($device, 'Biometric device updated successfully');
    }

    /**
     * Delete a biometric device.
     */
    public function destroy(string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);
        $device->delete();

        return $this->noContent('Biometric device deleted successfully');
    }

    /**
     * Test connection to a biometric device.
     */
    public function testConnection(string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);

        try {
            $connected = $this->biometricManager->testDevice($device);

            if ($connected) {
                $device->update(['status' => 'online']);
                return $this->success(['connected' => true], 'Device connection successful');
            }

            $device->update(['status' => 'error']);
            return $this->error('Device connection failed', 400);
        } catch (\Exception $e) {
            $device->update(['status' => 'error']);
            return $this->error('Connection error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Pull attendance records from a device.
     */
    public function pullAttendance(string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);

        try {
            $records = $this->biometricManager->pullFromDevice($device);
            $processed = 0;
            $skipped = 0;

            foreach ($records as $record) {
                $scanData = array_merge($record, [
                    'device_id' => $device->id,
                    'raw_payload' => array_merge($record['raw_payload'] ?? [], ['synced_by' => 'pull']),
                ]);

                if ($this->engine->processBiometricScan($scanData)) {
                    $processed++;
                } else {
                    $skipped++;
                }
            }

            $device->update(['last_sync_at' => now()]);

            return $this->success([
                'device_id' => $device->id,
                'records_count' => count($records),
                'processed' => $processed,
                'skipped' => $skipped,
            ], 'Attendance pull completed');
        } catch (\Exception $e) {
            return $this->error('Failed to pull attendance: ' . $e->getMessage(), 500);
        }
    }
}
