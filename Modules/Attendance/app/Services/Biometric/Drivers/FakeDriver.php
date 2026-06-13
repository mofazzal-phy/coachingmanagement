<?php

namespace Modules\Attendance\app\Services\Biometric\Drivers;

use Carbon\Carbon;

class FakeDriver extends BaseDriver
{
    public function name(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake Device (Testing)';
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
        // Return simulated attendance data
        return [
            [
                'biometric_uid' => 'FAKE-' . rand(1000, 9999),
                'scan_time' => Carbon::now()->toDateTimeString(),
                'raw_payload' => ['source' => 'fake_driver'],
            ],
        ];
    }

    public function pushUsers(array $users): bool
    {
        return true;
    }

    public function syncUsers(): array
    {
        return ['synced' => 0, 'failed' => 0];
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function getDevices(): array
    {
        return [
            [
                'id' => 'fake-001',
                'name' => 'Fake Device 1',
                'type' => 'fake',
                'status' => 'online',
            ],
        ];
    }
}
