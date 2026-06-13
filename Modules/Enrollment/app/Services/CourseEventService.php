<?php

namespace Modules\Enrollment\app\Services;

use Modules\Enrollment\app\Models\Course;

class CourseEventService
{
    public function __construct(protected ActivityLogService $logService, protected NotificationService $notifyService) {}

    public function onCreated(Course $course): void
    {
        $this->logService->logForModel(null, 'course', $course->id, 'created',
            "Course '{$course->name}' created",
            null, $course->toArray());
    }

    public function onUpdated(Course $course, array $oldValues): void
    {
        $changes = array_diff_assoc($course->toArray(), $oldValues);
        if (empty($changes)) return;

        $this->logService->logForModel(null, 'course', $course->id, 'updated',
            "Course '{$course->name}' updated: ".implode(', ', array_keys($changes)),
            $oldValues, $course->toArray());

        $this->notifyService->notifyAdmins('course_updated', "Course '{$course->name}' has been updated.");
    }

    public function onDeleted(Course $course): void
    {
        $this->logService->logForModel(null, 'course', $course->id, 'deleted',
            "Course '{$course->name}' deleted", $course->toArray());
    }

    public function onRestored(Course $course): void
    {
        $this->logService->logForModel(null, 'course', $course->id, 'restored',
            "Course '{$course->name}' restored");
    }
}
