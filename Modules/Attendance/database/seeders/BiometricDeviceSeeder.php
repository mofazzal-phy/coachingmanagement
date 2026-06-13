<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\app\Models\BiometricDevice;

class BiometricDeviceSeeder extends Seeder
{
    /**
     * Seed biometric devices for testing and development.
     *
     * Creates sample devices for each driver type:
     * - FakeDriver: Always works, no real device needed
     * - SimulatorDriver: Works with the Device Simulator UI
     * - ZKTecoDriver: Requires real ZKTeco device on network
     * - ESSLDriver: Requires real ESSL device on network
     */
    public function run(): void
    {
        // 1. Fake Device - for testing without any hardware
        BiometricDevice::firstOrCreate(
            ['device_name' => 'Fake Test Device'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '127.0.0.1',
                'port' => 4370,
                'serial_no' => 'FAKE-001',
                'driver' => 'fake',
                'config' => json_encode([
                    'timeout' => 5,
                    'description' => 'Fake device for testing attendance without real hardware',
                ]),
                'status' => 'online',
                'is_active' => true,
            ]
        );

        // 2. Simulator Device - for UI-based simulation
        BiometricDevice::firstOrCreate(
            ['device_name' => 'Device Simulator'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '127.0.0.1',
                'port' => 4371,
                'serial_no' => 'SIM-001',
                'driver' => 'simulator',
                'config' => json_encode([
                    'timeout' => 5,
                    'description' => 'Simulator device controlled via the Device Simulator UI page',
                    'simulate_random_events' => true,
                ]),
                'status' => 'online',
                'is_active' => true,
            ]
        );

        // 3. ZKTeco Device (placeholder) - requires real hardware
        BiometricDevice::firstOrCreate(
            ['device_name' => 'ZKTeco Device (Main)'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '192.168.1.100',
                'port' => 4370,
                'serial_no' => 'ZK-UNCONFIGURED',
                'driver' => 'zkteco',
                'config' => json_encode([
                    'timeout' => 30,
                    'model' => 'ZK-Series',
                    'description' => 'REAL HARDWARE REQUIRED - Update IP address to match your ZKTeco device',
                    'sdk_available' => class_exists('\ZKTeco\ZK'),
                ]),
                'status' => 'offline',
                'is_active' => false,
            ]
        );

        // 4. ESSL Device (placeholder) - requires real hardware
        BiometricDevice::firstOrCreate(
            ['device_name' => 'ESSL Device (Backup)'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '192.168.1.101',
                'port' => 4370,
                'serial_no' => 'ES-UNCONFIGURED',
                'driver' => 'essl',
                'config' => json_encode([
                    'timeout' => 30,
                    'protocol' => 'tcp',
                    'model' => 'iClock-Series',
                    'description' => 'REAL HARDWARE REQUIRED - Update IP address to match your ESSL device',
                ]),
                'status' => 'offline',
                'is_active' => false,
            ]
        );

        // 5. ESSL Device (HTTP mode) - alternative protocol
        BiometricDevice::firstOrCreate(
            ['device_name' => 'ESSL Device (HTTP)'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '192.168.1.102',
                'port' => 80,
                'serial_no' => 'ES-HTTP-UNCONFIGURED',
                'driver' => 'essl',
                'config' => json_encode([
                    'timeout' => 30,
                    'protocol' => 'http',
                    'model' => 'iClock-HTTP',
                    'description' => 'REAL HARDWARE REQUIRED - ESSL device with HTTP API enabled',
                ]),
                'status' => 'offline',
                'is_active' => false,
            ]
        );

        $this->command->info('Biometric devices seeded successfully.');
    }
}
