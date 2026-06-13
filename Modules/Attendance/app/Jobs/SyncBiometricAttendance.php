<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\Biometric\BiometricServiceManager;

class SyncBiometricAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(protected BiometricDevice $device) {}

    public function handle(BiometricServiceManager $biometricManager, AttendanceEngine $engine): void
    {
        try {
            $driver = $biometricManager->driverForDevice($this->device);

            if (!$driver->testConnection()) {
                Log::warning('Biometric device connection failed during sync', [
                    'device_id' => $this->device->id,
                    'device_name' => $this->device->device_name,
                ]);
                $this->fail(new \RuntimeException(
                    "Could not connect to device '{$this->device->device_name}'"
                ));
                return;
            }

            $records = $driver->pullAttendance();

            $processed = 0;
            foreach ($records as $record) {
                $scanData = array_merge($record, [
                    'device_id' => $this->device->id,
                    'raw_payload' => array_merge($record['raw_payload'] ?? [], ['synced_by' => 'job']),
                ]);

                if ($engine->processBiometricScan($scanData)) {
                    $processed++;
                }
            }

            $this->device->update(['last_sync_at' => now()]);

            Log::info('Biometric device sync completed', [
                'device_id' => $this->device->id,
                'device_name' => $this->device->device_name,
                'total_records' => count($records),
                'processed' => $processed,
            ]);
        } catch (\Exception $e) {
            Log::error('Biometric device sync failed', [
                'device_id' => $this->device->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    public function tags(): array
    {
        return ['biometric', 'sync', 'device:' . ($this->device->id ?? 'unknown')];
    }
}
