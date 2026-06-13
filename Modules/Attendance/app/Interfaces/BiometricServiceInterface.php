<?php

namespace Modules\Attendance\app\Interfaces;

interface BiometricServiceInterface
{
    /**
     * Connect to the biometric device.
     */
    public function connect(): bool;

    /**
     * Disconnect from the biometric device.
     */
    public function disconnect(): bool;

    /**
     * Pull attendance records from the device.
     */
    public function pullAttendance(): array;

    /**
     * Push users to the biometric device.
     */
    public function pushUsers(array $users): bool;

    /**
     * Sync users between system and device.
     */
    public function syncUsers(): array;

    /**
     * Test connection to the device.
     */
    public function testConnection(): bool;

    /**
     * Get device information.
     */
    public function getDevices(): array;
}
