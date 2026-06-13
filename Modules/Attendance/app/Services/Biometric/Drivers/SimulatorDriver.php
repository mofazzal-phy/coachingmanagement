<?php

namespace Modules\Attendance\app\Services\Biometric\Drivers;

use Carbon\Carbon;
use Modules\Attendance\app\Support\AttendanceTimeFormatter;

class SimulatorDriver extends BaseDriver
{
    public function name(): string
    {
        return 'simulator';
    }

    public function label(): string
    {
        return 'Device Simulator';
    }

    public function connect(): bool
    {
        return true;
    }

    public function disconnect(): bool
    {
        return true;
    }

    public function pullAttendance(): array
    {
        // Simulator returns whatever was simulated via the UI
        // This is handled by the SimulatorController directly
        return [];
    }

    public function pushUsers(array $users): bool
    {
        return true;
    }

    public function syncUsers(): array
    {
        return ['synced' => count($this->config['users'] ?? []), 'failed' => 0];
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function getDevices(): array
    {
        return [
            [
                'id' => 'sim-001',
                'name' => 'Simulator Device',
                'type' => 'simulator',
                'status' => 'online',
            ],
        ];
    }

    /**
     * Simulate a fingerprint scan event.
     */
    public function simulateScan(string $userType, string $userId, ?Carbon $scanTime = null): array
    {
        $scanTime = $scanTime ?? AttendanceTimeFormatter::now();

        return [
            'biometric_uid' => 'SIM-' . strtoupper(substr(md5($userId . $scanTime->timestamp), 0, 8)),
            'user_type' => $userType,
            'user_id' => $userId,
            'scan_time' => AttendanceTimeFormatter::formatDateTime($scanTime),
            'device_id' => $this->config['device_id'] ?? null,
            'mode' => 'fingerprint',
            'raw_payload' => [
                'source' => 'simulator',
                'simulated' => true,
                'device_name' => $this->config['device_name'] ?? 'Simulator',
                'user_type' => $userType,
                'user_id' => $userId,
                'mode' => 'fingerprint',
            ],
        ];
    }
}
