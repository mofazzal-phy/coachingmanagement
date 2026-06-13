<?php

namespace Modules\Attendance\app\Services\Biometric\Drivers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco Biometric Device Driver
 *
 * This driver provides integration with ZKTeco biometric devices.
 * When a real ZKTeco device is available on the network, configure
 * the IP address and port in the device settings.
 *
 * Required config keys:
 * - ip_address: string (e.g., '192.168.1.100')
 * - port: int (default: 4370)
 * - timeout: int (seconds, default: 30)
 *
 * @see https://github.com/zaherg/zkteco-php
 */
class ZKTecoDriver extends BaseDriver
{
    /**
     * Connection state tracking.
     */
    protected bool $connected = false;

    /**
     * Last error message from device operations.
     */
    protected ?string $lastError = null;

    public function name(): string
    {
        return 'zkteco';
    }

    public function label(): string
    {
        return 'ZKTeco Device';
    }

    /**
     * Connect to the ZKTeco device via TCP/IP.
     *
     * Uses the zkteco-php SDK if available, otherwise simulates
     * a connection attempt with proper error handling.
     */
    public function connect(): bool
    {
        $ip = $this->config['ip_address'] ?? null;
        $port = $this->config['port'] ?? 4370;
        $timeout = $this->config['timeout'] ?? 30;

        if (empty($ip)) {
            $this->lastError = 'ZKTeco device IP address is not configured.';
            Log::warning($this->lastError);
            return false;
        }

        try {
            // Attempt real connection if SDK is available
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($ip, $port);
                // $this->connected = $zk->connect();
                // return $this->connected;

                // Placeholder: SDK detected but not yet wired
                $this->lastError = 'ZKTeco SDK detected but integration not yet wired. Install zaherg/zkteco-php and uncomment SDK code.';
                Log::warning($this->lastError);
                $this->connected = false;
                return false;
            }

            // Fallback: test TCP socket connectivity
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($socket) {
                fclose($socket);
                $this->connected = true;
                Log::info("ZKTeco device connected at {$ip}:{$port}");
                return true;
            }

            $this->lastError = "Cannot reach ZKTeco device at {$ip}:{$port} - {$errstr} ({$errno})";
            Log::warning($this->lastError);
            $this->connected = false;
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco connection error: ' . $e->getMessage();
            Log::error($this->lastError);
            $this->connected = false;
            return false;
        }
    }

    public function disconnect(): bool
    {
        if (!$this->connected) {
            return true;
        }

        try {
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($this->config['ip_address'], $this->config['port'] ?? 4370);
                // $zk->disconnect();
            }

            $this->connected = false;
            Log::info('ZKTeco device disconnected.');
            return true;

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco disconnect error: ' . $e->getMessage();
            Log::error($this->lastError);
            return false;
        }
    }

    /**
     * Pull attendance records from the ZKTeco device.
     *
     * Returns an array of scan records, each containing:
     * - biometric_uid: string
     * - scan_time: string (datetime)
     * - raw_payload: array with device metadata
     */
    public function pullAttendance(): array
    {
        if (!$this->connected && !$this->connect()) {
            Log::warning('Cannot pull attendance: ZKTeco device not connected.');
            return [];
        }

        try {
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($this->config['ip_address'], $this->config['port'] ?? 4370);
                // $attendance = $zk->getAttendance();
                // return collect($attendance)->map(function ($record) {
                //     return [
                //         'biometric_uid' => (string) $record['id'],
                //         'scan_time' => Carbon::parse($record['timestamp'])->toDateTimeString(),
                //         'raw_payload' => [
                //             'source' => 'zkteco',
                //             'device_ip' => $this->config['ip_address'] ?? null,
                //             'device_name' => $this->config['device_name'] ?? 'ZKTeco',
                //             'status' => $record['status'] ?? 0,
                //         ],
                //     ];
                // })->toArray();

                $this->lastError = 'ZKTeco SDK not yet wired for attendance pull.';
                Log::warning($this->lastError);
                return [];
            }

            // Without SDK, return empty - real device required
            Log::info('ZKTeco pullAttendance called - real device SDK required for actual data.');
            return [];

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco pullAttendance error: ' . $e->getMessage();
            Log::error($this->lastError);
            return [];
        }
    }

    /**
     * Push users to the ZKTeco device.
     *
     * Each user entry should contain:
     * - user_id: string|int
     * - name: string
     * - password: string|null (optional)
     * - card_number: string|null (optional)
     */
    public function pushUsers(array $users): bool
    {
        if (!$this->connected && !$this->connect()) {
            Log::warning('Cannot push users: ZKTeco device not connected.');
            return false;
        }

        if (empty($users)) {
            Log::warning('No users provided to push to ZKTeco device.');
            return false;
        }

        try {
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($this->config['ip_address'], $this->config['port'] ?? 4370);
                // foreach ($users as $user) {
                //     $zk->setUser($user['user_id'], $user['user_id'], $user['name'] ?? '', $user['password'] ?? '', $user['card_number'] ?? 0);
                // }
                // return true;

                $this->lastError = 'ZKTeco SDK not yet wired for user push.';
                Log::warning($this->lastError);
                return false;
            }

            Log::info('ZKTeco pushUsers called - real device SDK required.');
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco pushUsers error: ' . $e->getMessage();
            Log::error($this->lastError);
            return false;
        }
    }

    /**
     * Sync users between the system and the ZKTeco device.
     *
     * Returns an array with synced/failed counts.
     */
    public function syncUsers(): array
    {
        if (!$this->connected && !$this->connect()) {
            Log::warning('Cannot sync users: ZKTeco device not connected.');
            return ['synced' => 0, 'failed' => 0, 'error' => 'Device not connected'];
        }

        try {
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($this->config['ip_address'], $this->config['port'] ?? 4370);
                // $deviceUsers = $zk->getUser();
                // ... sync logic ...
                // return ['synced' => count($deviceUsers), 'failed' => 0];

                $this->lastError = 'ZKTeco SDK not yet wired for user sync.';
                Log::warning($this->lastError);
                return ['synced' => 0, 'failed' => 0, 'error' => $this->lastError];
            }

            Log::info('ZKTeco syncUsers called - real device SDK required.');
            return ['synced' => 0, 'failed' => 0, 'error' => 'SDK not available'];

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco syncUsers error: ' . $e->getMessage();
            Log::error($this->lastError);
            return ['synced' => 0, 'failed' => 1, 'error' => $this->lastError];
        }
    }

    /**
     * Test connection to the ZKTeco device.
     *
     * Returns true if the device is reachable and responds.
     */
    public function testConnection(): bool
    {
        $ip = $this->config['ip_address'] ?? null;
        $port = $this->config['port'] ?? 4370;
        $timeout = $this->config['timeout'] ?? 10;

        if (empty($ip)) {
            $this->lastError = 'ZKTeco device IP address is not configured.';
            return false;
        }

        try {
            if (class_exists('\ZKTeco\ZK')) {
                // $zk = new \ZKTeco\ZK($ip, $port);
                // return $zk->connect();

                $this->lastError = 'ZKTeco SDK detected but not yet wired.';
                return false;
            }

            // Test TCP connectivity
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($socket) {
                fclose($socket);
                return true;
            }

            $this->lastError = "Cannot reach ZKTeco device at {$ip}:{$port} - {$errstr} ({$errno})";
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ZKTeco testConnection error: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Get device information.
     *
     * Returns an array with device details and status.
     */
    public function getDevices(): array
    {
        $ip = $this->config['ip_address'] ?? null;
        $port = $this->config['port'] ?? 4370;
        $isConnected = $this->connected;

        if (!$isConnected && $ip) {
            $isConnected = $this->testConnection();
        }

        return [
            [
                'id' => $this->config['device_id'] ?? 'zkteco-' . md5($ip ?? 'unknown'),
                'name' => $this->config['device_name'] ?? 'ZKTeco Device',
                'type' => 'zkteco',
                'status' => $isConnected ? 'online' : 'offline',
                'ip_address' => $ip,
                'port' => $port,
                'model' => $this->config['model'] ?? 'Unknown',
                'serial_number' => $this->config['serial_number'] ?? null,
                'last_error' => $this->lastError,
                'last_connected_at' => $isConnected ? Carbon::now()->toDateTimeString() : null,
            ],
        ];
    }

    /**
     * Get the last error message.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
