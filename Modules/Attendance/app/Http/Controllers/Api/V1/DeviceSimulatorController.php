<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Support\AttendanceTimeFormatter;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\Biometric\BiometricServiceManager;
use Modules\Attendance\app\Services\Biometric\Drivers\SimulatorDriver;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;
use Modules\Hr\app\Models\Employee;

class DeviceSimulatorController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected BiometricServiceManager $biometricManager
    ) {}

    /**
     * List available simulator devices.
     */
    public function getDevices(): JsonResponse
    {
        $this->ensureSimulatorDevicesExist();

        $devices = BiometricDevice::query()
            ->whereIn('driver', ['simulator', 'fake'])
            ->orderBy('device_name')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'driver' => $device->driver,
                    'status' => $device->status,
                    'is_active' => $device->is_active,
                ];
            });

        return $this->collectionResponse($devices);
    }

    /**
     * List users available for simulator scans.
     */
    public function getUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_type' => 'required|in:student,teacher,employee',
        ]);

        $users = match ($validated['user_type']) {
            'student' => Student::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name', 'student_id']),
            'teacher' => Teacher::query()
                ->orderBy('first_name')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name', 'teacher_id']),
            'employee' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name', 'employee_id']),
        };

        $mapped = $users->map(function ($user) use ($validated) {
            $displayId = match ($validated['user_type']) {
                'student' => $user->student_id,
                'teacher' => $user->teacher_id,
                'employee' => $user->employee_id,
            };

            return [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'display_id' => $displayId,
            ];
        });

        return $this->collectionResponse($mapped);
    }

    /**
     * Ensure at least one simulator-compatible device exists for testing.
     */
    protected function ensureSimulatorDevicesExist(): void
    {
        BiometricDevice::firstOrCreate(
            ['device_name' => 'Device Simulator'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '127.0.0.1',
                'port' => 4371,
                'serial_no' => 'SIM-001',
                'driver' => 'simulator',
                'config' => ['description' => 'Simulator device for UI testing'],
                'status' => 'online',
                'is_active' => true,
            ]
        );

        BiometricDevice::firstOrCreate(
            ['device_name' => 'Fake Test Device'],
            [
                'device_type' => 'fingerprint',
                'ip_address' => '127.0.0.1',
                'port' => 4370,
                'serial_no' => 'FAKE-001',
                'driver' => 'fake',
                'config' => ['description' => 'Fake device for testing without hardware'],
                'status' => 'online',
                'is_active' => true,
            ]
        );
    }

    /**
     * Simulate a fingerprint scan event.
     */
    public function simulateScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|exists:biometric_devices,id',
            'user_type' => 'required|in:student,teacher,employee',
            'user_id' => 'required|string',
            'scan_time' => 'nullable|date',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
        ]);

        $device = BiometricDevice::findOrFail($validated['device_id']);

        // Verify the device uses a simulator-compatible driver
        if (!in_array($device->driver, ['simulator', 'fake'])) {
            return $this->error('This device does not support simulation. Use a simulator or fake driver device.', 400);
        }

        try {
            $resolvedUserId = $this->resolveSimulatorUserId($validated['user_type'], $validated['user_id']);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), 'Invalid user selected for simulation.');
        }

        $scanData = [
            'device_id' => $device->id,
            'biometric_uid' => 'SIM-' . strtoupper(substr(md5($resolvedUserId . time()), 0, 8)),
            'user_type' => $validated['user_type'],
            'user_id' => $resolvedUserId,
            'scan_time' => isset($validated['scan_time'])
                ? AttendanceTimeFormatter::parse($validated['scan_time'])
                : AttendanceTimeFormatter::now(),
            'mode' => $validated['mode'] ?? 'fingerprint',
            'batch_id' => $validated['batch_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
            'raw_payload' => [
                'simulated' => true,
                'source' => 'manual_simulator',
                'timestamp' => AttendanceTimeFormatter::now()->toIso8601String(),
                'user_type' => $validated['user_type'],
                'user_id' => $resolvedUserId,
                'mode' => $validated['mode'] ?? 'fingerprint',
            ],
        ];

        try {
            $log = $this->engine->processBiometricScan($scanData);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        if (!$log) {
            return $this->error('Failed to process simulated scan. User ID may be invalid.', 400);
        }

        return $this->created(
            $this->formatAttendanceLogForApi(
                $log->load(['device:id,device_name', 'studentAttendance.student', 'teacherAttendance.teacher', 'employeeAttendance.employee'])
            ),
            'Simulated biometric scan processed successfully'
        );
    }

    protected function formatAttendanceLogForApi(AttendanceLog $log): array
    {
        $data = $log->toArray();

        return AttendanceTimeFormatter::appendTimeFields($data, $log->check_in, $log->check_out);
    }

    /**
     * Generate random attendance events for testing.
     */
    public function generateRandomEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|exists:biometric_devices,id',
            'count' => 'nullable|integer|min:1|max:100',
            'user_type' => 'nullable|in:student,teacher,employee',
        ]);

        $device = BiometricDevice::findOrFail($validated['device_id']);

        if (!in_array($device->driver, ['simulator', 'fake'])) {
            return $this->error('This device does not support simulation.', 400);
        }

        $driver = $this->biometricManager->driverForDevice($device);

        if (!$driver instanceof SimulatorDriver) {
            return $this->error('Device driver does not support random event generation.', 400);
        }

        $count = $validated['count'] ?? 10;
        $userType = $validated['user_type'] ?? null;
        $events = [];

        $candidateUsers = $this->simulatorUserPool($userType);

        if ($candidateUsers->isEmpty()) {
            return $this->error('No users found in the system for random simulation.', 422);
        }

        for ($i = 0; $i < $count; $i++) {
            $picked = $candidateUsers->random();
            $randomUserType = $userType ?? $picked['user_type'];
            $randomUserId = $picked['id'];

            $scanData = $driver->simulateScan($randomUserType, $randomUserId);
            $scanData['device_id'] = $device->id;

            try {
                $log = $this->engine->processBiometricScan($scanData);
                if ($log) {
                    $events[] = $log;
                }
            } catch (\RuntimeException) {
                continue;
            }
        }

        return $this->created(
            ['processed' => count($events), 'total_requested' => $count],
            "Generated {$count} random attendance events"
        );
    }

    /**
     * Get recent simulated scan logs.
     */
    public function recentScans(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $logs = \Modules\Attendance\app\Models\BiometricLog::with(['device:id,device_name'])
            ->whereHas('device', function ($q) {
                $q->whereIn('driver', ['simulator', 'fake']);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                // Extract user info from raw_payload if available
                $payload = $log->raw_payload ?? [];
                $userType = $payload['user_type'] ?? 'unknown';
                $userId = $payload['user_id'] ?? $log->biometric_uid;
                $mode = $payload['mode'] ?? 'fingerprint';

                // Resolve user name
                $name = $this->resolveUserName($userType, $userId);

                // Resolve attendance status for this scan (same user + scan date)
                $attendanceStatus = null;
                $lateMinutes = 0;
                if ($userType !== 'unknown' && $userId) {
                    $logRow = AttendanceLog::query()
                        ->where('user_type', $userType)
                        ->where('user_id', $userId)
                        ->whereDate('attendance_date', $log->scan_time->toDateString())
                        ->where('attendance_source', 'biometric')
                        ->orderByDesc('updated_at')
                        ->first(['attendance_status', 'late_minutes']);
                    $attendanceStatus = $logRow?->attendance_status;
                    $lateMinutes = (int) ($logRow?->late_minutes ?? 0);
                }

                return [
                    'id' => $log->id,
                    'device_id' => $log->device_id,
                    'device_name' => $log->device?->device_name ?? 'Unknown',
                    'biometric_uid' => $log->biometric_uid,
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'name' => $name,
                    'mode' => $mode,
                    'scan_time' => AttendanceTimeFormatter::formatDateTime($log->scan_time),
                    'scan_time_display' => AttendanceTimeFormatter::formatTime12($log->scan_time),
                    'date' => $log->scan_time->format('Y-m-d'),
                    'status' => $log->sync_status,
                    'attendance_status' => $attendanceStatus,
                    'late_minutes' => $lateMinutes ?? 0,
                ];
            });

        return $this->collectionResponse($logs);
    }

    /**
     * Resolve simulator input to the profile UUID used in attendance tables.
     */
    protected function resolveSimulatorUserId(string $userType, string $userId): string
    {
        $resolvedId = match ($userType) {
            'student' => Student::query()
                ->where('id', $userId)
                ->orWhere('student_id', $userId)
                ->value('id'),
            'teacher' => Teacher::query()
                ->where('id', $userId)
                ->orWhere('teacher_id', $userId)
                ->value('id'),
            'employee' => Employee::query()
                ->where('status', 'active')
                ->where(function ($q) use ($userId) {
                    $q->where('id', $userId)->orWhere('employee_id', $userId);
                })
                ->value('id'),
            default => null,
        };

        if (!$resolvedId) {
            throw ValidationException::withMessages([
                'user_id' => ["No {$userType} found for this ID. Select a user from the list or paste the profile UUID."],
            ]);
        }

        return $resolvedId;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user_type: string, id: string}>
     */
    protected function simulatorUserPool(?string $userType)
    {
        if ($userType === 'student') {
            return Student::where('status', 'active')->inRandomOrder()->limit(50)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'student', 'id' => $id]);
        }

        if ($userType === 'teacher') {
            return Teacher::inRandomOrder()->limit(50)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'teacher', 'id' => $id]);
        }

        if ($userType === 'employee') {
            return Employee::where('status', 'active')->inRandomOrder()->limit(50)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'employee', 'id' => $id]);
        }

        $pool = collect();
        $pool = $pool->merge(
            Student::where('status', 'active')->inRandomOrder()->limit(20)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'student', 'id' => $id])
        );
        $pool = $pool->merge(
            Teacher::inRandomOrder()->limit(20)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'teacher', 'id' => $id])
        );
        $pool = $pool->merge(
            Employee::where('status', 'active')->inRandomOrder()->limit(20)->pluck('id')
                ->map(fn ($id) => ['user_type' => 'employee', 'id' => $id])
        );

        return $pool;
    }

    /**
     * Resolve a user's display name from their user type and ID.
     */
    protected function resolveUserName(string $userType, string $userId): string
    {
        try {
            $name = match ($userType) {
                'student' => ($s = Student::where('id', $userId)->orWhere('student_id', $userId)->first())
                    ? trim($s->first_name . ' ' . $s->last_name)
                    : null,
                'teacher' => ($t = Teacher::where('id', $userId)->orWhere('teacher_id', $userId)->first())
                    ? trim($t->first_name . ' ' . $t->last_name)
                    : null,
                'employee' => ($e = Employee::where('id', $userId)->orWhere('employee_id', $userId)->first())
                    ? trim($e->first_name . ' ' . $e->last_name)
                    : null,
                default => null,
            };

            return $name ?: $userId;
        } catch (\Exception $e) {
            return $userId;
        }
    }
}
