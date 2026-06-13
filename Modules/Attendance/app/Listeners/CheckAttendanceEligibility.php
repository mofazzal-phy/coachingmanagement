<?php

namespace Modules\Attendance\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Attendance\Events\AttendanceMarked;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Services\AttendanceEngine;

class CheckAttendanceEligibility
{
    /**
     * Number of days to look back for eligibility calculation.
     */
    protected int $lookbackDays = 30;

    /**
     * Handle the event.
     *
     * After attendance is marked, check if the student's attendance
     * percentage falls below the minimum threshold and flag them.
     */
    public function handle(AttendanceMarked $event): void
    {
        // Only check eligibility for students
        if ($event->userType !== 'student') {
            return;
        }

        $studentId = $event->userId;

        // Calculate attendance percentage for the lookback period
        $startDate = now()->subDays($this->lookbackDays);
        $totalDays = AttendanceLog::where('user_type', 'student')
            ->where('user_id', $studentId)
            ->where('attendance_date', '>=', $startDate)
            ->count();

        $presentDays = AttendanceLog::where('user_type', 'student')
            ->where('user_id', $studentId)
            ->where('attendance_date', '>=', $startDate)
            ->whereIn('attendance_status', ['present', 'late'])
            ->count();

        if ($totalDays === 0) {
            return;
        }

        $percentage = ($presentDays / $totalDays) * 100;
        $thresholds = AttendanceEngine::eligibilityThresholds();
        $eligibleMin = $thresholds['eligible_min'];
        $warningMin = $thresholds['warning_min'];

        // Cache the eligibility result
        $eligibility = match (true) {
            $percentage >= $eligibleMin => [
                'eligible' => true,
                'percentage' => round($percentage, 2),
                'message' => 'Attendance is above minimum threshold.',
            ],
            $percentage >= $warningMin => [
                'eligible' => true,
                'warning' => true,
                'percentage' => round($percentage, 2),
                'message' => "Attendance is below {$eligibleMin}%. Please improve attendance.",
            ],
            default => [
                'eligible' => false,
                'percentage' => round($percentage, 2),
                'message' => "Attendance is critically low. May affect exam eligibility.",
            ],
        };

        Cache::put(
            "attendance:eligibility:student:{$studentId}",
            $eligibility,
            now()->addHours(6)
        );

        // Log eligibility check
        logger()->info('Attendance eligibility checked', [
            'student_id' => $studentId,
            'percentage' => round($percentage, 2),
            'eligible' => $eligibility['eligible'],
            'total_days' => $totalDays,
            'present_days' => $presentDays,
        ]);
    }
}
