<?php

return [
    'name' => 'Attendance',

    'biometric' => [
        // Minutes after scheduled start to count as present (early arrival included).
        'present_grace_minutes' => (int) env('ATTENDANCE_BIOMETRIC_PRESENT_GRACE', 10),
        // Minutes after scheduled start to count as late (beyond this → absent).
        'late_grace_minutes' => (int) env('ATTENDANCE_BIOMETRIC_LATE_GRACE', 20),
        // Fallback office start when no class routine applies (employees, no schedule).
        'default_office_start' => env('ATTENDANCE_DEFAULT_OFFICE_START', '09:00'),
    ],
];
