<?php

namespace Modules\Attendance\Listeners;

use Modules\Attendance\Events\AttendanceMarked;

class SendAttendanceNotification
{
    /**
     * Log absent/late marks — wire SMS/email here when needed.
     */
    public function handle(AttendanceMarked $event): void
    {
        if ($event->isBiometric) {
            return;
        }

        if (!in_array($event->status, ['absent', 'late'], true)) {
            return;
        }

        logger()->info('Attendance notification would be sent', [
            'user_type' => $event->userType,
            'user_id' => $event->userId,
            'status' => $event->status,
        ]);
    }
}
