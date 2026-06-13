<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\AttendanceSession;
use Modules\Attendance\app\Models\StudentAttendance;
use Modules\Attendance\app\Models\TeacherAttendance;
use Modules\Attendance\app\Models\EmployeeAttendance;
use Modules\Attendance\app\Models\BiometricLog;
use Modules\Attendance\app\Support\AttendanceTimeFormatter;
use Modules\Attendance\app\Services\BiometricMappingService;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\Events\AttendanceMarked;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Settings\app\Models\Setting;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;
use Modules\Hr\app\Models\Employee;
use Modules\Enrollment\app\Models\Batch;

class AttendanceEngine
{
    /**
     * Valid attendance statuses.
     */
    protected array $validStatuses = ['present', 'absent', 'late', 'leave', 'half_day', 'holiday'];

    /**
     * Validate that a date is not in the future.
     */
    protected function validateDate(Carbon $date): void
    {
        if ($date->isAfter(Carbon::today())) {
            throw new \InvalidArgumentException('Attendance cannot be marked for future dates.');
        }
    }

    /**
     * Validate that a date is not too far in the past (e.g., > 1 year).
     */
    protected function validateDateRange(Carbon $date): void
    {
        if ($date->isBefore(Carbon::today()->subYear())) {
            throw new \InvalidArgumentException('Attendance date is too far in the past (max 1 year).');
        }
    }

    /**
     * Validate attendance status.
     */
    protected function validateStatus(string $status): void
    {
        if (!in_array($status, $this->validStatuses)) {
            throw new \InvalidArgumentException(
                "Invalid attendance status '{$status}'. Valid statuses: " . implode(', ', $this->validStatuses)
            );
        }
    }

    /**
     * Check if a student exists and is active.
     */
    protected function findStudentOrFail(string $studentId): Student
    {
        $student = Student::where('id', $studentId)->where('status', 'active')->first();
        if (!$student) {
            throw new \RuntimeException("Student not found or inactive: {$studentId}");
        }
        return $student;
    }

    /**
     * Check if a teacher exists.
     */
    protected function findTeacherOrFail(string $teacherId): Teacher
    {
        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            throw new \RuntimeException("Teacher not found: {$teacherId}");
        }
        return $teacher;
    }

    /**
     * Check if an employee exists and is active.
     */
    protected function findEmployeeOrFail(string $employeeId): Employee
    {
        $employee = Employee::where('id', $employeeId)->where('status', 'active')->first();
        if (!$employee) {
            throw new \RuntimeException("Employee not found or inactive: {$employeeId}");
        }
        return $employee;
    }

    /**
     * Check if a batch exists.
     */
    protected function findBatchOrFail(?string $batchId): void
    {
        if ($batchId) {
            $batch = Batch::find($batchId);
            if (!$batch) {
                throw new \RuntimeException("Batch not found: {$batchId}");
            }
        }
    }

    /**
     * Check for duplicate attendance on the same date.
     */
    protected function checkDuplicateAttendance(string $userType, string $userId, Carbon $date, ?string $sessionId = null): void
    {
        $query = AttendanceLog::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('attendance_date', $date);

        if ($sessionId) {
            $query->where('attendance_session_id', $sessionId);
        }

        if ($query->exists()) {
            $msg = $sessionId
                ? "Attendance already marked for {$userType} {$userId} on {$date->toDateString()} in session {$sessionId}"
                : "Attendance already marked for {$userType} {$userId} on {$date->toDateString()}";
            throw new \RuntimeException($msg);
        }
    }

    /**
     * Sanitize remarks field.
     */
    protected function sanitizeRemarks(?string $remarks): ?string
    {
        if ($remarks === null) {
            return null;
        }
        $cleaned = strip_tags($remarks);
        $cleaned = substr($cleaned, 0, 500);
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Create an attendance session for a class/batch.
     */
    public function createSession(array $data): AttendanceSession
    {
        if (empty($data['batch_id'])) {
            throw new \InvalidArgumentException('Batch ID is required to create an attendance session.');
        }

        $this->findBatchOrFail($data['batch_id']);

        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $start = Carbon::parse($data['start_time']);
            $end = Carbon::parse($data['end_time']);
            if ($end->lessThanOrEqualTo($start)) {
                throw new \InvalidArgumentException('End time must be after start time.');
            }
        }

        return AttendanceSession::create(array_merge($data, [
            'created_by' => auth()->id(),
        ]));
    }

    /**
     * Mark student attendance (manual).
     */
    public function markStudentAttendance(
        string $studentId,
        string $status,
        ?string $batchId = null,
        ?string $subjectId = null,
        ?string $slotId = null,
        ?string $sessionId = null,
        ?string $remarks = null,
        ?Carbon $date = null
    ): AttendanceLog {
        $date = $date ?? Carbon::today();
        $status = strtolower(trim($status));

        // Validate
        $this->validateDate($date);
        $this->validateDateRange($date);
        $this->validateStatus($status);
        $this->findStudentOrFail($studentId);
        $this->findBatchOrFail($batchId);
        $this->checkDuplicateAttendance('student', $studentId, $date, $sessionId);

        $remarks = $this->sanitizeRemarks($remarks);

        $log = DB::transaction(function () use ($studentId, $status, $batchId, $subjectId, $slotId, $sessionId, $remarks, $date) {
            $log = AttendanceLog::create([
                'user_type' => 'student',
                'user_id' => $studentId,
                'attendance_source' => 'manual',
                'attendance_mode' => $sessionId ? 'session' : 'daily',
                'attendance_status' => $status,
                'attendance_date' => $date,
                'attendance_session_id' => $sessionId,
                'session_key' => $this->sessionKey($sessionId),
                'remarks' => $remarks,
                'created_by' => auth()->id(),
                'check_in' => in_array($status, ['present', 'late']) ? Carbon::now() : null,
            ]);

            StudentAttendance::create([
                'attendance_log_id' => $log->id,
                'student_id' => $studentId,
                'batch_id' => $batchId,
                'subject_id' => $subjectId,
                'slot_id' => $slotId,
            ]);

            return $log;
        });

        $this->fireMarkedEvent($log, false);

        return $log;
    }

    /**
     * Bulk mark student attendance (optimized for speed).
     * Default all to present, teacher only changes absent/late/leave.
     * Supports multi-batch: each record can have its own batch_id,
     * or a single batchId/batchIds can be used for all records.
     */
    public function bulkMarkStudentAttendance(
        array $records,
        string $date,
        ?string $batchId = null,
        ?string $subjectId = null,
        ?string $sessionId = null,
        array $batchIds = []
    ): array {
        if (empty($records)) {
            throw new \InvalidArgumentException('No attendance records provided.');
        }

        $dateObj = Carbon::parse($date);
        $this->validateDate($dateObj);
        $this->validateDateRange($dateObj);

        // Validate at least one batch reference exists
        if (empty($batchIds) && !$batchId) {
            // Check if records have per-record batch_id
            $hasPerRecordBatch = collect($records)->contains(fn($r) => !empty($r['batch_id']));
            if (!$hasPerRecordBatch) {
                throw new \InvalidArgumentException('At least one batch_id, batch_ids, or per-record batch_id is required.');
            }
        }

        $userId = auth()->id();
        $logs = [];
        $errors = [];

        DB::transaction(function () use ($records, $date, $dateObj, $batchId, $batchIds, $subjectId, $sessionId, $userId, &$logs, &$errors) {
            foreach ($records as $index => $record) {
                $studentId = $record['student_id'] ?? null;
                $status = strtolower(trim($record['status'] ?? 'present'));

                // Determine batch_id for this record: prefer per-record, then batchIds[0], then batchId
                $recordBatchId = $record['batch_id'] ?? (!empty($batchIds) ? $batchIds[0] : $batchId);

                // Validate student exists
                if (!$studentId) {
                    $errors[] = "Row {$index}: Missing student_id";
                    continue;
                }

                try {
                    $this->findStudentOrFail($studentId);
                } catch (\RuntimeException $e) {
                    $errors[] = "Row {$index}: {$e->getMessage()}";
                    continue;
                }

                // Validate status
                try {
                    $this->validateStatus($status);
                } catch (\InvalidArgumentException $e) {
                    $errors[] = "Row {$index}: {$e->getMessage()}";
                    continue;
                }

                $schedule = null;
                if (!$sessionId) {
                    $schedule = $this->resolveDailyScheduleContext('student', $studentId, $dateObj, $recordBatchId);
                }

                $defaultCheckIn = $schedule['scheduled_start'] ?? null;
                $checkIn = in_array($status, ['present', 'late'])
                    ? ($this->parseTimeOnDate($dateObj, $record['check_in'] ?? null) ?? $defaultCheckIn ?? Carbon::now())
                    : null;
                $checkOut = in_array($status, ['present', 'late'])
                    ? $this->parseTimeOnDate($dateObj, $record['check_out'] ?? null)
                    : null;

                $lateMinutes = 0;
                if ($status === 'late' && $checkIn) {
                    if (!$sessionId && !empty($schedule['scheduled_start'])) {
                        $computed = $this->resolveStatusFromCheckIn($checkIn, $schedule['scheduled_start']);
                        $lateMinutes = $computed['late_minutes'];
                    } elseif ($recordBatchId) {
                        $schedule = $schedule ?? $this->resolveDailyScheduleContext('student', $studentId, $dateObj, $recordBatchId);
                        if (!empty($schedule['scheduled_start'])) {
                            $lateMinutes = max(0, (int) $schedule['scheduled_start']->diffInMinutes($checkIn, false));
                        }
                    }
                }

                $log = $this->upsertDailyAttendanceLog(
                    [
                        'user_type' => 'student',
                        'user_id' => $studentId,
                        'attendance_date' => $date,
                        'attendance_session_id' => $sessionId,
                    ],
                    [
                        'attendance_source' => 'manual',
                        'attendance_status' => $status,
                        'remarks' => $this->sanitizeRemarks($record['remarks'] ?? null),
                        'created_by' => $userId,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'late_minutes' => $lateMinutes,
                    ]
                );

                StudentAttendance::updateOrCreate(
                    [
                        'attendance_log_id' => $log->id,
                    ],
                    [
                        'student_id' => $studentId,
                        'batch_id' => $recordBatchId,
                        'subject_id' => $subjectId,
                    ]
                );

                $logs[] = $log;
                $this->fireMarkedEvent($log, false);
            }
        });

        // If there were errors but some succeeded, log the errors
        if (!empty($errors)) {
            Log::warning('Bulk student attendance had errors', [
                'errors' => $errors,
                'success_count' => count($logs),
                'total' => count($records),
            ]);
        }

        return [
            'success' => $logs,
            'errors' => $errors,
            'success_count' => count($logs),
            'error_count' => count($errors),
        ];
    }

    /**
     * Mark teacher attendance.
     */
    public function markTeacherAttendance(
        string $teacherId,
        string $status,
        ?string $subjectId = null,
        ?string $classId = null,
        ?string $sessionId = null,
        ?string $remarks = null,
        ?Carbon $date = null
    ): AttendanceLog {
        $date = $date ?? Carbon::today();
        $status = strtolower(trim($status));

        // Validate
        $this->validateDate($date);
        $this->validateDateRange($date);
        $this->validateStatus($status);
        $this->findTeacherOrFail($teacherId);
        $this->checkDuplicateAttendance('teacher', $teacherId, $date, $sessionId);

        $remarks = $this->sanitizeRemarks($remarks);

        $log = DB::transaction(function () use ($teacherId, $status, $subjectId, $classId, $sessionId, $remarks, $date) {
            $log = AttendanceLog::create([
                'user_type' => 'teacher',
                'user_id' => $teacherId,
                'attendance_source' => 'manual',
                'attendance_mode' => $sessionId ? 'session' : 'daily',
                'attendance_status' => $status,
                'attendance_date' => $date,
                'attendance_session_id' => $sessionId,
                'session_key' => $this->sessionKey($sessionId),
                'remarks' => $remarks,
                'created_by' => auth()->id(),
                'check_in' => in_array($status, ['present', 'late']) ? Carbon::now() : null,
            ]);

            TeacherAttendance::create([
                'attendance_log_id' => $log->id,
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
            ]);

            return $log;
        });

        $this->fireMarkedEvent($log, false);

        return $log;
    }

    /**
     * Bulk mark teacher attendance (upsert — safe to save again on the same date).
     */
    public function bulkMarkTeacherAttendance(
        array $records,
        string $date,
        ?string $subjectId = null,
        ?string $classId = null,
        ?string $sessionId = null
    ): array {
        if (empty($records)) {
            throw new \InvalidArgumentException('No attendance records provided.');
        }

        $dateObj = Carbon::parse($date);
        $this->validateDate($dateObj);
        $this->validateDateRange($dateObj);

        $userId = auth()->id();
        $logs = [];

        DB::transaction(function () use ($records, $date, $dateObj, $subjectId, $classId, $sessionId, $userId, &$logs) {
            foreach ($records as $record) {
                $teacherId = $record['teacher_id'] ?? null;
                $status = strtolower(trim($record['status'] ?? 'present'));

                if (!$teacherId) {
                    continue;
                }

                $this->findTeacherOrFail($teacherId);
                $this->validateStatus($status);

                $checkIn = in_array($status, ['present', 'late'])
                    ? ($this->parseTimeOnDate($dateObj, $record['check_in'] ?? null) ?? Carbon::now())
                    : null;
                $checkOut = in_array($status, ['present', 'late'])
                    ? $this->parseTimeOnDate($dateObj, $record['check_out'] ?? null)
                    : null;

                $log = $this->upsertDailyAttendanceLog(
                    [
                        'user_type' => 'teacher',
                        'user_id' => $teacherId,
                        'attendance_date' => $date,
                        'attendance_session_id' => $sessionId,
                    ],
                    [
                        'attendance_source' => 'manual',
                        'attendance_status' => $status,
                        'remarks' => $this->sanitizeRemarks($record['remarks'] ?? null),
                        'created_by' => $userId,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                    ]
                );

                TeacherAttendance::updateOrCreate(
                    ['attendance_log_id' => $log->id],
                    [
                        'teacher_id' => $teacherId,
                        'subject_id' => $record['subject_id'] ?? $subjectId,
                        'class_id' => $record['class_id'] ?? $classId,
                    ]
                );

                $logs[] = $log;
                $this->fireMarkedEvent($log, false);
            }
        });

        return $logs;
    }

    /**
     * Bulk mark employee attendance (upsert — safe to save again on the same date).
     */
    public function bulkMarkEmployeeAttendance(
        array $records,
        string $date,
        ?string $departmentId = null,
        ?string $sessionId = null
    ): array {
        if (empty($records)) {
            throw new \InvalidArgumentException('No attendance records provided.');
        }

        $dateObj = Carbon::parse($date);
        $this->validateDate($dateObj);
        $this->validateDateRange($dateObj);

        $userId = auth()->id();
        $logs = [];

        DB::transaction(function () use ($records, $date, $dateObj, $departmentId, $sessionId, $userId, &$logs) {
            foreach ($records as $record) {
                $employeeId = $record['employee_id'] ?? null;
                $status = strtolower(trim($record['status'] ?? 'present'));

                if (!$employeeId) {
                    continue;
                }

                $this->findEmployeeOrFail($employeeId);
                $this->validateStatus($status);

                $checkIn = in_array($status, ['present', 'late'])
                    ? ($this->parseTimeOnDate($dateObj, $record['check_in'] ?? null) ?? Carbon::now())
                    : null;
                $checkOut = in_array($status, ['present', 'late'])
                    ? $this->parseTimeOnDate($dateObj, $record['check_out'] ?? null)
                    : null;

                $log = $this->upsertDailyAttendanceLog(
                    [
                        'user_type' => 'employee',
                        'user_id' => $employeeId,
                        'attendance_date' => $date,
                        'attendance_session_id' => $sessionId,
                    ],
                    [
                        'attendance_source' => 'manual',
                        'attendance_status' => $status,
                        'remarks' => $this->sanitizeRemarks($record['remarks'] ?? null),
                        'created_by' => $userId,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                    ]
                );

                EmployeeAttendance::updateOrCreate(
                    ['attendance_log_id' => $log->id],
                    [
                        'employee_id' => $employeeId,
                        'department_id' => $record['department_id'] ?? $departmentId,
                    ]
                );

                $logs[] = $log;
                $this->fireMarkedEvent($log, false);
                $this->computeEmployeeMetrics($log);
            }
        });

        return $logs;
    }

    protected function parseTimeOnDate(Carbon $date, ?string $time): ?Carbon
    {
        if (!$time) {
            return null;
        }

        return Carbon::parse(
            $date->format('Y-m-d') . ' ' . $time,
            AttendanceTimeFormatter::timezone()
        );
    }

    /**
     * Upsert a daily attendance log and remove legacy duplicate rows for the same day.
     */
    protected function upsertDailyAttendanceLog(array $keys, array $values): AttendanceLog
    {
        $keys['session_key'] = $this->sessionKey($keys['attendance_session_id'] ?? null);
        $values['attendance_mode'] = !empty($keys['attendance_session_id']) ? 'session' : 'daily';

        $query = AttendanceLog::query();

        foreach ($keys as $field => $value) {
            $query->where($field, $value);
        }

        $existing = $query->orderByDesc('updated_at')->get();
        $log = $existing->first();

        if ($existing->count() > 1) {
            $existing->slice(1)->each(function (AttendanceLog $duplicate) {
                $duplicate->studentAttendance?->delete();
                $duplicate->teacherAttendance?->delete();
                $duplicate->employeeAttendance?->delete();
                $duplicate->delete();
            });
        }

        if ($log) {
            $log->update($values);
            return $log->fresh();
        }

        return AttendanceLog::create(array_merge($keys, $values));
    }

    protected function sessionKey(?string $sessionId): string
    {
        return $sessionId ?? '00000000-0000-0000-0000-000000000000';
    }

    protected function fireMarkedEvent(AttendanceLog $log, bool $isBiometric = false): void
    {
        AttendanceMarked::dispatch(
            $log,
            $log->user_type,
            $log->user_id,
            $log->attendance_status,
            $isBiometric
        );
    }

    /**
     * Match class routine at scan time for subject/batch context.
     */
    protected function detectRoutineContext(string $userType, string $userId, Carbon $scanTime, ?string $batchId = null): ?array
    {
        $dayCode = $this->dayCodeFromDate($scanTime);
        if (!$dayCode) {
            return null;
        }

        $query = $this->routineQueryForUser($userType, $userId, $dayCode, $batchId);
        if (!$query) {
            return null;
        }

        $currentTime = $scanTime->format('H:i:s');
        $routine = $query
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->first();

        if (!$routine) {
            return null;
        }

        return $this->routineToScheduleContext($routine, $scanTime);
    }

    /**
     * Resolve scheduled start and routine context for a biometric scan.
     * Daily attendance baseline = first class of the day for the batch/teacher.
     */
    protected function resolveBiometricScheduleContext(string $userType, string $userId, Carbon $scanTime, ?string $batchId = null): array
    {
        return $this->resolveDailyScheduleContext($userType, $userId, $scanTime, $batchId);
    }

    /**
     * Resolve daily schedule baseline (first class start of the day).
     */
    public function resolveDailyScheduleContext(string $userType, string $userId, Carbon $date, ?string $batchId = null): array
    {
        $fallback = $this->defaultOfficeScheduleContext($date);

        if (!in_array($userType, ['student', 'teacher'], true)) {
            return $fallback;
        }

        $dayCode = $this->dayCodeFromDate($date);
        if (!$dayCode) {
            return $fallback;
        }

        $query = $this->routineQueryForUser($userType, $userId, $dayCode, $batchId);
        if (!$query) {
            return $fallback;
        }

        $routines = $query->orderBy('start_time')->get();
        if ($routines->isEmpty()) {
            return $fallback;
        }

        $firstRoutine = $routines->first();
        $context = $this->routineToScheduleContext($firstRoutine, $date);

        if ($date->isSameDay(now())) {
            $active = $this->detectRoutineContext($userType, $userId, now(), $batchId);
            if ($active) {
                $context['active_subject_id'] = $active['subject_id'];
                $context['active_subject_name'] = $active['subject_name'];
            }
        }

        return $context;
    }

    /**
     * Derive attendance status and late minutes from check-in vs scheduled start.
     *
     * @return array{status: string, late_minutes: int, minutes_after_start: int}
     */
    public function resolveStatusFromCheckIn(Carbon $checkIn, ?Carbon $scheduledStart): array
    {
        if (!$scheduledStart) {
            return [
                'status' => 'present',
                'late_minutes' => 0,
                'minutes_after_start' => 0,
            ];
        }

        $presentGrace = max(0, (int) config('attendance.biometric.present_grace_minutes', 10));
        $lateGrace = max($presentGrace, (int) config('attendance.biometric.late_grace_minutes', 20));

        $minutesAfterStart = (int) $scheduledStart->diffInMinutes($checkIn, false);
        $lateMinutes = max(0, $minutesAfterStart);

        if ($minutesAfterStart <= $presentGrace) {
            return [
                'status' => 'present',
                'late_minutes' => 0,
                'minutes_after_start' => $minutesAfterStart,
            ];
        }

        if ($minutesAfterStart <= $lateGrace) {
            return [
                'status' => 'late',
                'late_minutes' => $lateMinutes,
                'minutes_after_start' => $minutesAfterStart,
            ];
        }

        return [
            'status' => 'absent',
            'late_minutes' => $lateMinutes,
            'minutes_after_start' => $minutesAfterStart,
        ];
    }

    protected function buildBiometricRemarks(string $status, int $lateMinutes, array $schedule): string
    {
        $startLabel = $schedule['scheduled_start']?->format('h:i A') ?? '';

        return match ($status) {
            'late' => "Biometric scan — {$lateMinutes} min late (class start {$startLabel})",
            'absent' => $lateMinutes > 0
                ? "Biometric scan — absent ({$lateMinutes} min after class start {$startLabel})"
                : "Biometric scan — absent (class start {$startLabel})",
            default => "Biometric scan processed (class start {$startLabel})",
        };
    }

    protected function routineQueryForUser(string $userType, string $userId, string $dayCode, ?string $batchId = null)
    {
        $query = ClassRoutine::published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('day_of_week', $dayCode)
            ->with('subject:id,name');

        if ($userType === 'teacher') {
            return $query->where('teacher_id', $userId);
        }

        if ($userType === 'student') {
            $batchId = $batchId ?: Enrollment::where('student_id', $userId)
                ->where('status', 'active')
                ->value('batch_id');

            if (!$batchId) {
                return null;
            }

            return $query->where('batch_id', $batchId);
        }

        return null;
    }

    protected function routineToScheduleContext(ClassRoutine $routine, Carbon $scanTime): array
    {
        return [
            'batch_id' => $routine->batch_id,
            'subject_id' => $routine->subject_id,
            'class_id' => $routine->class_id,
            'teacher_id' => $routine->teacher_id,
            'subject_name' => $routine->subject?->name,
            'start_time' => $routine->start_time,
            'end_time' => $routine->end_time,
            'scheduled_start' => $this->parseTimeOnDate($scanTime, $routine->start_time),
        ];
    }

    protected function defaultOfficeScheduleContext(Carbon $scanTime): array
    {
        $defaultStart = config('attendance.biometric.default_office_start', '09:00');

        return [
            'batch_id' => null,
            'subject_id' => null,
            'class_id' => null,
            'teacher_id' => null,
            'subject_name' => null,
            'start_time' => $defaultStart,
            'end_time' => null,
            'scheduled_start' => $this->parseTimeOnDate($scanTime, $defaultStart),
        ];
    }

    protected function dayCodeFromDate(Carbon $date): ?string
    {
        $map = [
            'Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed',
            'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat',
        ];

        return $map[$date->format('D')] ?? null;
    }

    /**
     * Mark employee attendance.
     */
    public function markEmployeeAttendance(
        string $employeeId,
        string $status,
        ?string $departmentId = null,
        ?string $sessionId = null,
        ?string $remarks = null,
        ?Carbon $date = null
    ): AttendanceLog {
        $date = $date ?? Carbon::today();
        $status = strtolower(trim($status));

        // Validate
        $this->validateDate($date);
        $this->validateDateRange($date);
        $this->validateStatus($status);
        $this->findEmployeeOrFail($employeeId);
        $this->checkDuplicateAttendance('employee', $employeeId, $date, $sessionId);

        $remarks = $this->sanitizeRemarks($remarks);

        $log = DB::transaction(function () use ($employeeId, $status, $departmentId, $sessionId, $remarks, $date) {
            $log = AttendanceLog::create([
                'user_type' => 'employee',
                'user_id' => $employeeId,
                'attendance_source' => 'manual',
                'attendance_mode' => $sessionId ? 'session' : 'daily',
                'attendance_status' => $status,
                'attendance_date' => $date,
                'attendance_session_id' => $sessionId,
                'session_key' => $this->sessionKey($sessionId),
                'remarks' => $remarks,
                'created_by' => auth()->id(),
                'check_in' => in_array($status, ['present', 'late']) ? Carbon::now() : null,
            ]);

            EmployeeAttendance::create([
                'attendance_log_id' => $log->id,
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
            ]);

            return $log;
        });

        $this->fireMarkedEvent($log, false);
        $this->computeEmployeeMetrics($log);

        return $log;
    }

    protected function computeEmployeeMetrics(AttendanceLog $log): void
    {
        if ($log->user_type !== 'employee') {
            return;
        }

        $employee = Employee::find($log->user_id);
        app(AttendanceMetricsService::class)->computeForLog($log->fresh(), $employee);
    }

    /**
     * Process a biometric scan event.
     */
    public function processBiometricScan(array $scanData): ?AttendanceLog
    {
        $deviceId = $scanData['device_id'] ?? null;
        $biometricUid = $scanData['biometric_uid'] ?? null;
        $scanTime = isset($scanData['scan_time'])
            ? AttendanceTimeFormatter::parse($scanData['scan_time'])
            : AttendanceTimeFormatter::now();

        // Validate device
        if ($deviceId) {
            $device = BiometricDevice::find($deviceId);
            if (!$device) {
                Log::warning('Biometric scan from unknown device', ['device_id' => $deviceId]);
                return null;
            }
        }

        // Resolve user from mapping table, biometric UID, or explicit user_id
        $userType = $scanData['user_type'] ?? null;
        $userId = $scanData['user_id'] ?? null;

        if (!$userId && $biometricUid) {
            $mapped = app(BiometricMappingService::class)->resolve($deviceId, $biometricUid);
            if ($mapped) {
                $userType = $userType ?? $mapped['user_type'];
                $userId = $mapped['user_id'];
            }
        }

        if (!$userId) {
            Log::info('Biometric scan without user mapping', [
                'biometric_uid' => $biometricUid,
                'device_id' => $deviceId,
            ]);
            return null;
        }

        if (!in_array($userType, ['student', 'teacher', 'employee'], true)) {
            Log::warning('Invalid user type in biometric scan', ['user_type' => $userType]);
            return null;
        }

        // Validate user exists before writing any attendance rows.
        match ($userType) {
            'student' => $this->findStudentOrFail($userId),
            'teacher' => $this->findTeacherOrFail($userId),
            'employee' => $this->findEmployeeOrFail($userId),
            default => throw new \InvalidArgumentException("Invalid user type: {$userType}"),
        };

        $date = $scanTime->copy()->startOfDay();
        $schedule = $this->resolveBiometricScheduleContext(
            $userType,
            $userId,
            $scanTime,
            $scanData['batch_id'] ?? null
        );
        $resolved = $this->resolveStatusFromCheckIn($scanTime, $schedule['scheduled_start']);
        $status = $resolved['status'];
        $lateMinutes = $resolved['late_minutes'];
        $batchId = $scanData['batch_id'] ?? $schedule['batch_id'] ?? null;
        $subjectId = $scanData['subject_id']
            ?? $schedule['active_subject_id']
            ?? $schedule['subject_id']
            ?? null;
        $remarks = $this->buildBiometricRemarks($status, $lateMinutes, $schedule);

        $log = DB::transaction(function () use ($userType, $userId, $status, $lateMinutes, $date, $deviceId, $scanData, $scanTime, $biometricUid, $batchId, $subjectId, $schedule, $remarks) {
            BiometricLog::create([
                'device_id' => $deviceId,
                'biometric_uid' => $biometricUid,
                'scan_time' => $scanTime,
                'sync_status' => 'synced',
                'raw_payload' => $scanData['raw_payload'] ?? null,
            ]);

            $log = $this->upsertDailyAttendanceLog(
                [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'attendance_date' => $date,
                    'attendance_session_id' => $scanData['session_id'] ?? null,
                ],
                [
                    'attendance_source' => 'biometric',
                    'attendance_mode' => !empty($scanData['session_id']) ? 'session' : 'daily',
                    'attendance_status' => $status,
                    'check_in' => $scanTime,
                    'late_minutes' => $lateMinutes,
                    'device_id' => $deviceId,
                    'remarks' => $remarks,
                    'created_by' => null,
                ]
            );

            switch ($userType) {
                case 'student':
                    StudentAttendance::updateOrCreate(
                        ['attendance_log_id' => $log->id],
                        [
                            'student_id' => $userId,
                            'batch_id' => $batchId,
                            'subject_id' => $subjectId,
                        ]
                    );
                    break;
                case 'teacher':
                    TeacherAttendance::updateOrCreate(
                        ['attendance_log_id' => $log->id],
                        [
                            'teacher_id' => $userId,
                            'subject_id' => $subjectId,
                            'class_id' => $schedule['class_id'] ?? null,
                        ]
                    );
                    break;
                case 'employee':
                    EmployeeAttendance::updateOrCreate(
                        ['attendance_log_id' => $log->id],
                        [
                            'employee_id' => $userId,
                            'department_id' => $scanData['department_id'] ?? null,
                        ]
                    );
                    break;
            }

            return $log;
        });

        $this->fireMarkedEvent($log, true);
        $this->computeEmployeeMetrics($log);

        return $log;
    }

    /**
     * @deprecated Use resolveStatusFromCheckIn() instead.
     */
    protected function determineBiometricStatus(Carbon $scanTime, array $schedule): string
    {
        return $this->resolveStatusFromCheckIn(
            $scanTime,
            $schedule['scheduled_start'] ?? $this->parseTimeOnDate(
                $scanTime,
                config('attendance.biometric.default_office_start', '09:00')
            )
        )['status'];
    }

    /**
     * Get current routine-based session for a batch.
     */
    public function detectCurrentSession(string $batchId): ?array
    {
        $this->findBatchOrFail($batchId);

        $now = Carbon::now();
        $dayCode = $this->dayCodeFromDate($now);
        $currentTime = $now->format('H:i:s');

        $routine = ClassRoutine::with(['subject', 'teacher', 'room'])
            ->published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('batch_id', $batchId)
            ->where('day_of_week', $dayCode)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->first();

        if (!$routine) {
            return null;
        }

        return [
            'routine' => $routine,
            'batch_id' => $batchId,
            'subject_id' => $routine->subject_id,
            'teacher_id' => $routine->teacher_id,
            'room_id' => $routine->room_id,
            'start_time' => $routine->start_time,
            'end_time' => $routine->end_time,
        ];
    }

    /**
     * Get attendance summary for a student.
     */
    public function getStudentSummary(string $studentId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $this->findStudentOrFail($studentId);

        $startDate = $startDate ?? Carbon::now()->subMonth();
        $endDate = $endDate ?? Carbon::now();

        if ($startDate->greaterThan($endDate)) {
            throw new \InvalidArgumentException('Start date must be before end date.');
        }

        $logs = AttendanceLog::where('user_type', 'student')
            ->where('user_id', $studentId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $total = $logs->count();
        $present = $logs->where('attendance_status', 'present')->count();
        $absent = $logs->where('attendance_status', 'absent')->count();
        $late = $logs->where('attendance_status', 'late')->count();
        $leave = $logs->where('attendance_status', 'leave')->count();
        $halfDay = $logs->where('attendance_status', 'half_day')->count();

        $percentage = $total > 0 ? round((($present + $late) / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'half_day' => $halfDay,
            'percentage' => $percentage,
            'eligibility' => $this->calculateEligibility($percentage),
        ];
    }

    /**
     * @return array{eligible_min: float, warning_min: float}
     */
    public static function eligibilityThresholds(): array
    {
        return [
            'eligible_min' => (float) (Setting::where('key', 'attendance_eligibility_eligible_min')->value('value') ?? 75),
            'warning_min' => (float) (Setting::where('key', 'attendance_eligibility_warning_min')->value('value') ?? 60),
        ];
    }

    /**
     * Calculate attendance eligibility.
     */
    public function calculateEligibility(float $percentage): array
    {
        $thresholds = self::eligibilityThresholds();
        $eligibleMin = $thresholds['eligible_min'];
        $warningMin = $thresholds['warning_min'];

        if ($percentage >= $eligibleMin) {
            return ['status' => 'eligible', 'label' => 'Eligible', 'eligible' => true];
        }

        if ($percentage >= $warningMin) {
            return ['status' => 'warning', 'label' => 'Warning', 'eligible' => true];
        }

        return ['status' => 'blocked', 'label' => 'Exam Blocked', 'eligible' => false];
    }

    /**
     * Get today's attendance stats for dashboard.
     */
    public function getTodayStats(): array
    {
        $today = Carbon::today();

        $totalStudents = Student::where('status', 'active')->count();
        $totalTeachers = Teacher::count();
        $totalEmployees = Employee::where('status', 'active')->count();

        $studentLogs = AttendanceLog::where('user_type', 'student')->where('attendance_date', $today);
        $teacherLogs = AttendanceLog::where('user_type', 'teacher')->where('attendance_date', $today);
        $employeeLogs = AttendanceLog::where('user_type', 'employee')->where('attendance_date', $today);

        $studentPresent = (clone $studentLogs)->whereIn('attendance_status', ['present', 'late'])->count();
        $studentAbsent = (clone $studentLogs)->where('attendance_status', 'absent')->count();
        $studentLate = (clone $studentLogs)->where('attendance_status', 'late')->count();

        $teacherPresent = (clone $teacherLogs)->whereIn('attendance_status', ['present', 'late'])->count();
        $teacherAbsent = (clone $teacherLogs)->where('attendance_status', 'absent')->count();
        $teacherLate = (clone $teacherLogs)->where('attendance_status', 'late')->count();

        $employeePresent = (clone $employeeLogs)->whereIn('attendance_status', ['present', 'late'])->count();
        $employeeAbsent = (clone $employeeLogs)->where('attendance_status', 'absent')->count();
        $employeeLate = (clone $employeeLogs)->where('attendance_status', 'late')->count();

        $lateToday = AttendanceLog::where('attendance_date', $today)
            ->where('attendance_status', 'late')
            ->count();

        $buildStats = function (int $total, int $present, int $absent, int $late) {
            return [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        };

        return [
            'students' => $buildStats($totalStudents, $studentPresent, $studentAbsent, $studentLate),
            'teachers' => $buildStats($totalTeachers, $teacherPresent, $teacherAbsent, $teacherLate),
            'employees' => $buildStats($totalEmployees, $employeePresent, $employeeAbsent, $employeeLate),
            'late_today' => $lateToday,
            'total_students' => $totalStudents,
            'student_present' => $studentPresent,
            'student_absent' => $studentAbsent,
            'student_percentage' => $totalStudents > 0 ? round(($studentPresent / $totalStudents) * 100, 1) : 0,
            'total_teachers' => $totalTeachers,
            'teacher_present' => $teacherPresent,
            'teacher_absent' => $teacherAbsent,
            'total_employees' => $totalEmployees,
            'employee_present' => $employeePresent,
            'employee_absent' => $employeeAbsent,
        ];
    }
}
