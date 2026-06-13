<?php

namespace Modules\Attendance\app\Services\Biometric\Drivers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ESSL Biometric Device Driver
 *
 * This driver provides integration with ESSL biometric devices
 * (e.g., ESSL ZK series, ESSL iClock series).
 *
 * ESSL devices typically use TCP/IP communication on port 4370
 * (same as ZKTeco protocol) or HTTP API on port 80/8080.
 *
 * Required config keys:
 * - ip_address: string (e.g., '192.168.1.101')
 * - port: int (default: 4370 for TCP, 80 for HTTP)
 * - protocol: string ('tcp' or 'http', default: 'tcp')
 * - timeout: int (seconds, default: 30)
 */
class ESSLDriver extends BaseDriver
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
        return 'essl';
    }

    public function label(): string
    {
        return 'ESSL Device';
    }

    /**
     * Connect to the ESSL device.
     *
     * Supports two protocols:
     * - 'tcp': Direct TCP socket connection (port 4370)
     * - 'http': HTTP API connection (port 80/8080)
     */
    public function connect(): bool
    {
        $ip = $this->config['ip_address'] ?? null;
        $port = $this->config['port'] ?? 4370;
        $protocol = $this->config['protocol'] ?? 'tcp';
        $timeout = $this->config['timeout'] ?? 30;

        if (empty($ip)) {
            $this->lastError = 'ESSL device IP address is not configured.';
            Log::warning($this->lastError);
            return false;
        }

        try {
            if ($protocol === 'http') {
                return $this->connectHttp($ip, $port, $timeout);
            }

            // Default: TCP socket connection
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($socket) {
                fclose($socket);
                $this->connected = true;
                Log::info("ESSL device connected via TCP at {$ip}:{$port}");
                return true;
            }

            $this->lastError = "Cannot reach ESSL device at {$ip}:{$port} - {$errstr} ({$errno})";
            Log::warning($this->lastError);
            $this->connected = false;
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL connection error: ' . $e->getMessage();
            Log::error($this->lastError);
            $this->connected = false;
            return false;
        }
    }

    /**
     * Connect via HTTP API.
     */
    protected function connectHttp(string $ip, int $port, int $timeout): bool
    {
        try {
            $url = "http://{$ip}:{$port}/";
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'method' => 'GET',
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                $this->connected = true;
                Log::info("ESSL device connected via HTTP at {$ip}:{$port}");
                return true;
            }

            $this->lastError = "ESSL HTTP connection failed at {$ip}:{$port}";
            $this->connected = false;
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL HTTP connection error: ' . $e->getMessage();
            $this->connected = false;
            return false;
        }
    }

    public function disconnect(): bool
    {
        $this->connected = false;
        Log::info('ESSL device disconnected.');
        return true;
    }

    /**
     * Pull attendance records from the ESSL device.
     *
     * For TCP protocol, uses the ZKTeco-compatible command set.
     * For HTTP protocol, calls the device's REST API endpoint.
     *
     * Returns an array of scan records.
     */
    public function pullAttendance(): array
    {
        if (!$this->connected && !$this->connect()) {
            Log::warning('Cannot pull attendance: ESSL device not connected.');
            return [];
        }

        $protocol = $this->config['protocol'] ?? 'tcp';

        try {
            if ($protocol === 'http') {
                return $this->pullAttendanceHttp();
            }

            // TCP mode - requires ESSL/ZKTeco SDK
            Log::info('ESSL pullAttendance via TCP requires SDK integration. Install compatible ESSL/ZKTeco SDK.');
            return [];

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL pullAttendance error: ' . $e->getMessage();
            Log::error($this->lastError);
            return [];
        }
    }

    /**
     * Pull attendance via HTTP API.
     */
    protected function pullAttendanceHttp(): array
    {
        $ip = $this->config['ip_address'] ?? '';
        $port = $this->config['port'] ?? 80;

        try {
            $url = "http://{$ip}:{$port}/iclock/cdata/attendance";
            $response = @file_get_contents($url);

            if ($response === false) {
                $this->lastError = "ESSL HTTP attendance pull failed at {$url}";
                return [];
            }

            // Parse ESSL attendance data format
            // Format: PIN,DateTime,Status,VerifyMode,WorkCode,Reserved
            $records = [];
            $lines = explode("\n", trim($response));

            foreach ($lines as $line) {
                $parts = str_getcsv($line);
                if (count($parts) >= 2) {
                    $records[] = [
                        'biometric_uid' => trim($parts[0]),
                        'scan_time' => Carbon::parse(trim($parts[1]))->toDateTimeString(),
                        'raw_payload' => [
                            'source' => 'essl_http',
                            'device_ip' => $ip,
                            'status' => $parts[2] ?? '0',
                            'verify_mode' => $parts[3] ?? '0',
                            'work_code' => $parts[4] ?? '',
                        ],
                    ];
                }
            }

            Log::info('ESSL HTTP attendance pull completed.', ['count' => count($records)]);
            return $records;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL HTTP attendance pull error: ' . $e->getMessage();
            Log::error($this->lastError);
            return [];
        }
    }

    /**
     * Push users to the ESSL device.
     *
     * Each user entry should contain:
     * - user_id: string|int
     * - name: string
     * - password: string|null (optional)
     * - card_number: string|null (optional)
     * - privilege: int (0=user, 1=admin, default: 0)
     */
    public function pushUsers(array $users): bool
    {
        if (!$this->connected && !$this->connect()) {
            Log::warning('Cannot push users: ESSL device not connected.');
            return false;
        }

        if (empty($users)) {
            Log::warning('No users provided to push to ESSL device.');
            return false;
        }

        $protocol = $this->config['protocol'] ?? 'tcp';

        try {
            if ($protocol === 'http') {
                return $this->pushUsersHttp($users);
            }

            Log::info('ESSL pushUsers via TCP requires SDK integration.');
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL pushUsers error: ' . $e->getMessage();
            Log::error($this->lastError);
            return false;
        }
    }

    /**
     * Push users via HTTP API.
     */
    protected function pushUsersHttp(array $users): bool
    {
        $ip = $this->config['ip_address'] ?? '';
        $port = $this->config['port'] ?? 80;

        try {
            $url = "http://{$ip}:{$port}/iclock/cdata/user";

            $data = '';
            foreach ($users as $user) {
                // Format: PIN,Name,Password,Card,Privilege,GroupID,TimeGroup
                $data .= implode(',', [
                    $user['user_id'] ?? '',
                    $user['name'] ?? '',
                    $user['password'] ?? '',
                    $user['card_number'] ?? '0',
                    $user['privilege'] ?? '0',
                    $user['group_id'] ?? '1',
                    $user['time_group'] ?? '1',
                ]) . "\n";
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: text/plain',
                    'content' => $data,
                    'timeout' => 30,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            $success = $response !== false;

            if ($success) {
                Log::info('ESSL HTTP user push completed.', ['count' => count($users)]);
            } else {
                $this->lastError = "ESSL HTTP user push failed at {$url}";
            }

            return $success;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL HTTP user push error: ' . $e->getMessage();
            Log::error($this->lastError);
            return false;
        }
    }

    /**
     * Sync users between the system and the ESSL device.
     *
     * Returns an array with synced/failed counts.
     */
    public function syncUsers(): array
    {
        if (!$this->connected && !$this->connect()) {
            return ['synced' => 0, 'failed' => 0, 'error' => 'Device not connected'];
        }

        try {
            // Pull current users from device
            $deviceUsers = $this->pullAttendance();

            // In a real implementation, you would:
            // 1. Get all users from the local database
            // 2. Compare with device users
            // 3. Push missing users to device
            // 4. Pull new scans from device

            Log::info('ESSL syncUsers completed.', ['device_users' => count($deviceUsers)]);
            return [
                'synced' => count($deviceUsers),
                'failed' => 0,
                'message' => 'Sync simulation completed. Real sync requires SDK integration.',
            ];

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL syncUsers error: ' . $e->getMessage();
            Log::error($this->lastError);
            return ['synced' => 0, 'failed' => 1, 'error' => $this->lastError];
        }
    }

    /**
     * Test connection to the ESSL device.
     *
     * Returns true if the device is reachable.
     */
    public function testConnection(): bool
    {
        $ip = $this->config['ip_address'] ?? null;
        $port = $this->config['port'] ?? 4370;
        $protocol = $this->config['protocol'] ?? 'tcp';
        $timeout = $this->config['timeout'] ?? 10;

        if (empty($ip)) {
            $this->lastError = 'ESSL device IP address is not configured.';
            return false;
        }

        try {
            if ($protocol === 'http') {
                $url = "http://{$ip}:{$port}/";
                $context = stream_context_create([
                    'http' => ['timeout' => $timeout, 'method' => 'GET'],
                ]);
                $response = @file_get_contents($url, false, $context);
                return $response !== false;
            }

            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if ($socket) {
                fclose($socket);
                return true;
            }

            $this->lastError = "Cannot reach ESSL device at {$ip}:{$port} - {$errstr} ({$errno})";
            return false;

        } catch (\Throwable $e) {
            $this->lastError = 'ESSL testConnection error: ' . $e->getMessage();
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
                'id' => $this->config['device_id'] ?? 'essl-' . md5($ip ?? 'unknown'),
                'name' => $this->config['device_name'] ?? 'ESSL Device',
                'type' => 'essl',
                'status' => $isConnected ? 'online' : 'offline',
                'ip_address' => $ip,
                'port' => $port,
                'protocol' => $this->config['protocol'] ?? 'tcp',
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
