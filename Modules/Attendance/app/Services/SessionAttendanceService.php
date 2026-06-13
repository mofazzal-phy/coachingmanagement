<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Attendance\app\Models\AttendanceSession;
use Modules\Attendance\app\Models\ClassSession;

class SessionAttendanceService
{
    public function __construct(
        protected ClassSessionService $classSessionService
    ) {}

    /**
     * Create or resolve an attendance session from a routine context.
     *
     * @return array{class_session: ClassSession, attendance_session: AttendanceSession, routine: ClassRoutine}
     */
    public function ensureFromRoutine(
        string $batchId,
        ?Carbon $at = null,
        ?string $routineId = null
    ): array {
        $at = $at ?? now();
        $date = $at->copy()->startOfDay();

        $routine = $routineId
            ? ClassRoutine::findOrFail($routineId)
            : $this->classSessionService->findRoutineAtTime($batchId, $at);

        if (!$routine) {
            throw new \RuntimeException('No published routine slot found for this batch at the given time.');
        }

        if ($routine->batch_id !== $batchId) {
            throw new \InvalidArgumentException('Routine does not belong to the specified batch.');
        }

        $classSession = $this->classSessionService->ensureFromRoutine($routine, $date);
        $attendanceSession = $this->ensureAttendanceSession($classSession, $routine, $date);

        if (!$classSession->attendance_session_id) {
            $classSession->update(['attendance_session_id' => $attendanceSession->id]);
        }

        return [
            'class_session' => $classSession->fresh(['batch', 'subject', 'teacher', 'room']),
            'attendance_session' => $attendanceSession->fresh(['batch', 'subject', 'teacher']),
            'routine' => $routine->load(['subject:id,name', 'teacher:id,first_name,last_name', 'batch:id,name', 'room:id,name']),
        ];
    }

    /**
     * Resolve attendance session for an existing class session.
     */
    public function ensureFromClassSession(string $classSessionId): array
    {
        $classSession = ClassSession::with('routine')->findOrFail($classSessionId);
        $routine = $classSession->routine;
        $date = Carbon::parse($classSession->session_date);

        $attendanceSession = $this->ensureAttendanceSession(
            $classSession,
            $routine,
            $date
        );

        if (!$classSession->attendance_session_id) {
            $classSession->update(['attendance_session_id' => $attendanceSession->id]);
        }

        return [
            'class_session' => $classSession->fresh(['batch', 'subject', 'teacher', 'room']),
            'attendance_session' => $attendanceSession->fresh(['batch', 'subject', 'teacher']),
            'routine' => $routine,
        ];
    }

    protected function ensureAttendanceSession(
        ClassSession $classSession,
        ?ClassRoutine $routine,
        Carbon $date
    ): AttendanceSession {
        if ($classSession->attendance_session_id) {
            $existing = AttendanceSession::find($classSession->attendance_session_id);
            if ($existing) {
                return $existing;
            }
        }

        return AttendanceSession::updateOrCreate(
            ['class_session_id' => $classSession->id],
            [
                'routine_id' => $classSession->routine_id,
                'class_id' => $classSession->class_id,
                'course_id' => $classSession->course_id,
                'batch_id' => $classSession->batch_id,
                'subject_id' => $classSession->subject_id,
                'teacher_id' => $classSession->teacher_id,
                'room_id' => $classSession->room_id,
                'slot_id' => $classSession->slot_id,
                'attendance_date' => $date->toDateString(),
                'start_time' => $classSession->start_time,
                'end_time' => $classSession->end_time,
                'session_type' => 'student',
                'source' => $classSession->source === 'routine' ? 'routine' : 'manual',
                'status' => 'active',
                'scheduled_status' => $this->mapClassStatusToScheduled($classSession->status),
                'created_by' => auth()->id(),
            ]
        );
    }

    protected function mapClassStatusToScheduled(string $classStatus): string
    {
        return match ($classStatus) {
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'rescheduled' => 'rescheduled',
            default => 'scheduled',
        };
    }
}
