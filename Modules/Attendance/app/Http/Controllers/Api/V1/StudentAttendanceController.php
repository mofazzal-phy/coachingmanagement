<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\AttendanceAnalytics;
use Modules\Attendance\app\Services\SessionAttendanceService;
use Modules\Student\app\Models\Student;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Support\AttendanceTimeFormatter;
use Carbon\Carbon;

class StudentAttendanceController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected AttendanceAnalytics $analytics,
        protected SessionAttendanceService $sessionAttendance,
    ) {}

    /**
     * Get students for attendance marking (by single or multiple batches).
     */
    public function getStudents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|string',
            'date' => 'required|date',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'mode' => 'nullable|in:daily,session',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'class_session_id' => 'nullable|string|exists:class_sessions,id',
        ]);

        $date = $validated['date'];
        $mode = $validated['mode'] ?? 'daily';
        $sessionId = $validated['session_id'] ?? null;

        if (!empty($validated['class_session_id'])) {
            $linked = $this->sessionAttendance->ensureFromClassSession($validated['class_session_id']);
            $sessionId = $linked['attendance_session']->id;
            $mode = 'session';
        }

        // Support both single batch_id and multiple batch_ids (comma-separated)
        $batchIds = [];
        if (!empty($validated['batch_ids'])) {
            $batchIds = array_map('trim', explode(',', $validated['batch_ids']));
        } elseif (!empty($validated['batch_id'])) {
            $batchIds = [$validated['batch_id']];
        }

        if (empty($batchIds)) {
            return $this->collectionResponse(collect([]));
        }

        // Get active enrollments for selected batches
        $enrollmentsQuery = Enrollment::with('student')
            ->whereIn('batch_id', $batchIds)
            ->where('status', 'active');

        // Filter by subject if provided (enrollments have a subjects many-to-many relationship)
        if (!empty($validated['subject_id'])) {
            $enrollmentsQuery->whereHas('subjects', function ($q) use ($validated) {
                $q->where('subjects.id', $validated['subject_id']);
            });
        }

        $enrollments = $enrollmentsQuery->get();

        // Get existing attendance logs for this date (latest per student)
        $studentIds = $enrollments->pluck('student_id');
        $logsQuery = AttendanceLog::where('user_type', 'student')
            ->whereIn('user_id', $studentIds)
            ->where('attendance_date', $date);

        if ($mode === 'session' && $sessionId) {
            $logsQuery->where('attendance_session_id', $sessionId);
        } else {
            $logsQuery->where(function ($q) {
                $q->where('attendance_mode', 'daily')
                    ->orWhereNull('attendance_session_id');
            });
        }

        $existingLogs = $logsQuery
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        // Build a map of student_id => batch_id from enrollments
        $studentBatchMap = $enrollments->pluck('batch_id', 'student_id');

        $scheduleContext = null;
        if ($mode === 'daily' && count($batchIds) === 1) {
            $sampleStudentId = $studentIds->first();
            if ($sampleStudentId) {
                $scheduleContext = $this->engine->resolveDailyScheduleContext(
                    'student',
                    $sampleStudentId,
                    Carbon::parse($date),
                    $batchIds[0]
                );
            }
        }

        $students = $enrollments->map(function ($enrollment) use ($existingLogs, $studentBatchMap, $date, $mode) {
            $student = $enrollment->student;
            $log = $existingLogs->get($student->id);
            $batchId = $studentBatchMap->get($student->id);

            $scheduledStart = null;
            if ($mode === 'daily' && $batchId) {
                $schedule = $this->engine->resolveDailyScheduleContext(
                    'student',
                    $student->id,
                    Carbon::parse($date),
                    $batchId
                );
                $scheduledStart = $schedule['scheduled_start']?->format('H:i');
            }

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'roll_no' => $student->roll_no,
                'batch_id' => $batchId,
                'batch_name' => $enrollment->batch?->name ?? '',
                'status' => $log?->attendance_status ?? 'present',
                'check_in' => $this->formatAppTime($log?->check_in),
                'check_in_display' => $this->formatAppTime($log?->check_in, 'h:i A'),
                'check_out' => $this->formatAppTime($log?->check_out),
                'check_out_display' => $this->formatAppTime($log?->check_out, 'h:i A'),
                'late_minutes' => (int) ($log?->late_minutes ?? 0),
                'scheduled_start' => $scheduledStart,
                'scheduled_start_display' => $scheduledStart
                    ? $this->formatWallClockTime12($scheduledStart)
                    : null,
                'remarks' => $log?->remarks ?? '',
                'has_attendance' => $log !== null,
                'attendance_source' => $log?->attendance_source,
            ];
        })->sortBy('roll_no')->values();

        return $this->success([
            'students' => $students,
            'context' => [
                'mode' => $mode,
                'session_id' => $sessionId,
                'date' => $date,
                'scheduled_start' => $scheduleContext
                    ? ($scheduleContext['scheduled_start']?->format('H:i'))
                    : null,
                'scheduled_start_display' => $scheduleContext
                    ? $this->formatWallClockTime12($scheduleContext['start_time'] ?? $scheduleContext['scheduled_start']?->format('H:i'))
                    : null,
                'present_grace_minutes' => (int) config('attendance.biometric.present_grace_minutes', 10),
                'late_grace_minutes' => (int) config('attendance.biometric.late_grace_minutes', 20),
            ],
        ]);
    }

    /**
     * Mark single student attendance.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'slot_id' => 'nullable|string|exists:routine_periods,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $log = $this->engine->markStudentAttendance(
            studentId: $validated['student_id'],
            status: $validated['status'],
            batchId: $validated['batch_id'] ?? null,
            subjectId: $validated['subject_id'] ?? null,
            slotId: $validated['slot_id'] ?? null,
            sessionId: $validated['session_id'] ?? null,
            remarks: $validated['remarks'] ?? null,
            date: isset($validated['date']) ? Carbon::parse($validated['date']) : null
        );

        return $this->created($log->load('studentAttendance.student'));
    }

    /**
     * Bulk mark attendance (optimized - default present).
     * Supports multi-batch via batch_ids array or per-record batch_id.
     */
    public function bulkMarkAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|array',
            'batch_ids.*' => 'string|exists:batches,id',
            'date' => 'required|date',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'class_session_id' => 'nullable|string|exists:class_sessions,id',
            'records' => 'required|array',
            'records.*.student_id' => 'required|string|exists:students,id',
            'records.*.status' => 'required|in:present,absent,late,leave,half_day',
            'records.*.batch_id' => 'nullable|string|exists:batches,id',
            'records.*.check_in' => 'nullable|date_format:H:i',
            'records.*.check_out' => 'nullable|date_format:H:i',
            'records.*.remarks' => 'nullable|string',
        ]);

        // Determine batch IDs: prefer batch_ids array, then single batch_id, then per-record
        $batchIds = $validated['batch_ids'] ?? [];
        if (empty($batchIds) && !empty($validated['batch_id'])) {
            $batchIds = [$validated['batch_id']];
        }

        $sessionId = $validated['session_id'] ?? null;
        if (!empty($validated['class_session_id'])) {
            $linked = $this->sessionAttendance->ensureFromClassSession($validated['class_session_id']);
            $sessionId = $linked['attendance_session']->id;
        }

        $result = $this->engine->bulkMarkStudentAttendance(
            records: $validated['records'],
            date: $validated['date'],
            batchIds: $batchIds,
            batchId: $validated['batch_id'] ?? null,
            subjectId: $validated['subject_id'] ?? null,
            sessionId: $sessionId
        );

        return $this->created($result, 'Attendance saved successfully');
    }

    /**
     * Get student attendance summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $summary = $this->engine->getStudentSummary(
            studentId: $validated['student_id'],
            startDate: isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null,
            endDate: isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null
        );

        return $this->success($summary);
    }

    /**
     * Get subject-wise attendance for a student.
     */
    public function subjectWise(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
        ]);

        $data = $this->analytics->subjectWiseAttendance($validated['student_id']);
        return $this->collectionResponse($data);
    }

    /**
     * Get batch attendance report.
     */
    public function batchReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'required|string|exists:batches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $logs = AttendanceLog::where('user_type', 'student')
            ->where('attendance_date', '>=', $validated['start_date'])
            ->where('attendance_date', '<=', $validated['end_date'])
            ->whereHas('studentAttendance', fn($q) => $q->where('batch_id', $validated['batch_id']))
            ->with('studentAttendance.student:id,first_name,last_name,student_id,roll_no')
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('user_id');

        $report = $logs->map(function ($studentLogs, $userId) {
            $student = $studentLogs->first()->studentAttendance->student;
            $total = $studentLogs->count();
            $present = $studentLogs->whereIn('attendance_status', ['present', 'late'])->count();
            return [
                'student_id' => $userId,
                'name' => $student?->full_name ?? 'Unknown',
                'student_code' => $student?->student_id ?? '',
                'roll_no' => $student?->roll_no ?? '',
                'total' => $total,
                'present' => $present,
                'absent' => $total - $present,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        })->values();

        return $this->collectionResponse($report);
    }

    protected function formatAppTime(?Carbon $value, string $format = 'H:i'): ?string
    {
        if (!$value) {
            return null;
        }

        return AttendanceTimeFormatter::toAppTimezone($value)?->format($format);
    }

    /** Format routine/API wall-clock time (H:i) without datetime timezone drift. */
    protected function formatWallClockTime12(mixed $time): ?string
    {
        if (!$time) {
            return null;
        }

        $normalized = is_string($time)
            ? substr(trim($time), 0, 5)
            : $time->format('H:i');

        [$h24, $m] = array_map('intval', explode(':', $normalized));
        $h12 = $h24 % 12 ?: 12;
        $ampm = $h24 >= 12 ? 'PM' : 'AM';

        return sprintf('%d:%02d %s', $h12, $m, $ampm);
    }
}
