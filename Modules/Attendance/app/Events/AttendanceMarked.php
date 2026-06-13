<?php

namespace Modules\Attendance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\app\Models\AttendanceLog;

class AttendanceMarked
{
    use Dispatchable, SerializesModels;

    /**
     * The attendance log that was created/updated.
     */
    public AttendanceLog $attendanceLog;

    /**
     * The user type (student, teacher, employee).
     */
    public string $userType;

    /**
     * The user ID.
     */
    public string $userId;

    /**
     * The attendance status.
     */
    public string $status;

    /**
     * Whether this was a biometric scan.
     */
    public bool $isBiometric;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AttendanceLog $attendanceLog,
        string $userType,
        string $userId,
        string $status,
        bool $isBiometric = false
    ) {
        $this->attendanceLog = $attendanceLog;
        $this->userType = $userType;
        $this->userId = $userId;
        $this->status = $status;
        $this->isBiometric = $isBiometric;
    }
}
