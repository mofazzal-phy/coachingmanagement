<?php

/**
 * One-off biometric attendance test script.
 * Usage: php scripts/test-biometric-scan.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Models\BiometricLog;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;
use Modules\Hr\app\Models\Employee;

echo "=== Biometric Attendance Test ===\n\n";

// 1. Ensure simulator device exists
$device = BiometricDevice::firstOrCreate(
    ['device_name' => 'Device Simulator'],
    [
        'device_type' => 'fingerprint',
        'ip_address' => '127.0.0.1',
        'port' => 4371,
        'serial_no' => 'SIM-001',
        'driver' => 'simulator',
        'config' => json_encode(['description' => 'Test simulator device']),
        'status' => 'online',
        'is_active' => true,
    ]
);

echo "Device: {$device->device_name} (ID: {$device->id}, driver: {$device->driver})\n\n";

// 2. Find test users
$student = Student::first();
$teacher = Teacher::first();
$employee = Employee::where('status', 'active')->first();

if (!$student && !$teacher && !$employee) {
    echo "ERROR: No student, teacher, or employee found in database.\n";
    echo "Create at least one user first, then re-run this script.\n";
    exit(1);
}

$engine = app(AttendanceEngine::class);
$results = [];

$testCases = [];
if ($student) {
    $testCases[] = [
        'label' => 'Student: ' . trim($student->first_name . ' ' . $student->last_name),
        'user_type' => 'student',
        'user_id' => $student->id,
    ];
}
if ($teacher) {
    $testCases[] = [
        'label' => 'Teacher: ' . trim($teacher->first_name . ' ' . $teacher->last_name),
        'user_type' => 'teacher',
        'user_id' => $teacher->id,
    ];
}
if ($employee) {
    $testCases[] = [
        'label' => 'Employee: ' . trim($employee->first_name . ' ' . $employee->last_name),
        'user_type' => 'employee',
        'user_id' => $employee->id,
    ];
}

foreach ($testCases as $case) {
    echo "Testing scan for {$case['label']}...\n";
    echo "  user_type: {$case['user_type']}\n";
    echo "  user_id:   {$case['user_id']}\n";

    $scanData = [
        'device_id' => $device->id,
        'biometric_uid' => 'TEST-' . strtoupper(substr(md5($case['user_id'] . microtime(true)), 0, 8)),
        'user_type' => $case['user_type'],
        'user_id' => $case['user_id'],
        'scan_time' => now(),
        'raw_payload' => [
            'simulated' => true,
            'source' => 'test-biometric-scan.php',
            'user_type' => $case['user_type'],
            'user_id' => $case['user_id'],
            'mode' => 'fingerprint',
        ],
    ];

    try {
        $log = $engine->processBiometricScan($scanData);
        if ($log) {
            echo "  RESULT: OK — attendance_log ID: {$log->id}, status: {$log->attendance_status}\n";
            $results[] = ['case' => $case['label'], 'ok' => true, 'log_id' => $log->id];
        } else {
            echo "  RESULT: FAILED — processBiometricScan returned null\n";
            $results[] = ['case' => $case['label'], 'ok' => false];
        }
    } catch (Throwable $e) {
        echo "  RESULT: ERROR — {$e->getMessage()}\n";
        $results[] = ['case' => $case['label'], 'ok' => false, 'error' => $e->getMessage()];
    }

    echo "\n";
}

// 3. Summary
$bioCount = BiometricLog::count();
$attCount = AttendanceLog::where('attendance_source', 'biometric')->count();
$passed = count(array_filter($results, fn ($r) => $r['ok']));

echo "=== Summary ===\n";
echo "Tests passed: {$passed}/" . count($results) . "\n";
echo "Total biometric_logs: {$bioCount}\n";
echo "Total biometric attendance_logs: {$attCount}\n\n";

echo "=== Copy these IDs for UI testing (Device Simulator page) ===\n";
if ($student) {
    echo "Student UUID: {$student->id}\n";
    echo "Student display ID: " . ($student->student_id ?? 'N/A') . "\n";
}
if ($teacher) {
    echo "Teacher UUID: {$teacher->id}\n";
    echo "Teacher display ID: " . ($teacher->teacher_id ?? 'N/A') . "\n";
}
if ($employee) {
    echo "Employee UUID: {$employee->id}\n";
    echo "Employee display ID: " . ($employee->employee_id ?? 'N/A') . "\n";
}
echo "Simulator Device ID: {$device->id}\n\n";

echo "UI test URL: /dashboard/attendance/simulator\n";

exit($passed === count($results) ? 0 : 1);
