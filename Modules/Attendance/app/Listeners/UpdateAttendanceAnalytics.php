<?php

namespace Modules\Attendance\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Attendance\Events\AttendanceMarked;

class UpdateAttendanceAnalytics
{
    /**
     * Handle the event.
     *
     * After attendance is marked, clear relevant analytics caches
     * so the next dashboard/report request fetches fresh data.
     */
    public function handle(AttendanceMarked $event): void
    {
        $tags = [
            'attendance:analytics',
            "attendance:user:{$event->userType}:{$event->userId}",
        ];

        if ($event->userType === 'student') {
            $tags[] = 'attendance:student:*';
        } elseif ($event->userType === 'teacher') {
            $tags[] = 'attendance:teacher:*';
        } elseif ($event->userType === 'employee') {
            $tags[] = 'attendance:employee:*';
        }

        // Clear cached analytics for this user type
        foreach ($tags as $tag) {
            Cache::forget($tag);
        }

        // Also clear the today-stats cache since a new log was created
        Cache::forget('attendance:today-stats');

        // Log the analytics update (optional, for debugging)
        logger()->info('Attendance analytics cache cleared', [
            'user_type' => $event->userType,
            'user_id' => $event->userId,
            'status' => $event->status,
            'is_biometric' => $event->isBiometric,
        ]);
    }
}
