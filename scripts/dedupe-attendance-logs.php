<?php

/**
 * Remove duplicate attendance_logs for the same user/day/session (keeps latest updated).
 * Usage: php scripts/dedupe-attendance-logs.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Attendance\app\Models\AttendanceLog;

$logs = AttendanceLog::orderByDesc('updated_at')->get();

$groups = $logs->groupBy(function (AttendanceLog $log) {
    $date = $log->attendance_date?->toDateString() ?? 'unknown';
    $session = $log->attendance_session_id ?? 'null';
    return implode('|', [$log->user_type, $log->user_id, $date, $session]);
});

$removed = 0;

foreach ($groups as $group) {
    if ($group->count() <= 1) {
        continue;
    }

    $keep = $group->sortByDesc(fn (AttendanceLog $log) => $log->updated_at)->first();

    $group->reject(fn (AttendanceLog $log) => $log->id === $keep->id)->each(function (AttendanceLog $duplicate) use (&$removed) {
        $duplicate->studentAttendance?->delete();
        $duplicate->teacherAttendance?->delete();
        $duplicate->employeeAttendance?->delete();
        $duplicate->delete();
        $removed++;
    });
}

echo "Removed {$removed} duplicate attendance log(s).\n";
