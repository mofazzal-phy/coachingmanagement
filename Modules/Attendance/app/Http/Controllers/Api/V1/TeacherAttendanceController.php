<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\AttendanceAnalytics;
use Modules\Attendance\app\Services\TeacherClassLedgerService;
use Modules\Attendance\app\Services\ClassSessionService;
use Modules\Teacher\app\Models\Teacher;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\TeacherClassLedger;
use Modules\Academic\app\Models\ClassRoutine;
use Carbon\Carbon;

class TeacherAttendanceController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected AttendanceAnalytics $analytics,
        protected TeacherClassLedgerService $classLedger,
        protected ClassSessionService $classSessionService,
    ) {}

    /**
     * Get all teachers for attendance marking.
     */
    public function getTeachers(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'mode' => 'nullable|in:office,all',
        ]);

        $date = $request->input('date', Carbon::today()->toDateString());
        $batchId = $request->input('batch_id');
        $subjectId = $request->input('subject_id');
        $mode = $request->input('mode', 'all');
        $dateCarbon = Carbon::parse($date);
        $dayCode = $this->classSessionService->dayCodeFromDate($dateCarbon);

        if ($mode === 'office') {
            $teacherQuery = Teacher::select('id', 'first_name', 'last_name', 'teacher_id', 'email', 'phone', 'teacher_type')
                ->where('status', 'active')
                ->orderBy('first_name');

            if ($batchId || $subjectId) {
                $routineQuery = $this->baseRoutineQuery($dayCode);

                if ($batchId) {
                    $routineQuery->where('batch_id', $batchId);
                }

                if ($subjectId) {
                    $routineQuery->where('subject_id', $subjectId);
                }

                $teacherIds = (clone $routineQuery)->distinct()->pluck('teacher_id');

                if ($teacherIds->isEmpty()) {
                    return $this->collectionResponse(collect([]));
                }

                $teacherQuery->whereIn('id', $teacherIds);
            }

            $teachers = $teacherQuery->get();
        } else {
            $routineQuery = $this->baseRoutineQuery($dayCode);

            if ($batchId) {
                $routineQuery->where('batch_id', $batchId);
            }

            if ($subjectId) {
                $routineQuery->where('subject_id', $subjectId);
            }

            $teacherIds = (clone $routineQuery)->distinct()->pluck('teacher_id');

            if ($teacherIds->isEmpty()) {
                return $this->collectionResponse(collect([]));
            }

            $teachers = Teacher::select('id', 'first_name', 'last_name', 'teacher_id', 'email', 'phone', 'teacher_type')
                ->whereIn('id', $teacherIds)
                ->orderBy('first_name')
                ->get();
        }

        $existingLogsQuery = AttendanceLog::where('user_type', 'teacher')
            ->where('attendance_date', $date)
            ->where(function ($q) {
                $q->where('attendance_mode', 'daily')
                    ->orWhereNull('attendance_session_id');
            });

        if ($subjectId) {
            $existingLogsQuery->whereHas(
                'teacherAttendance',
                fn ($q) => $q->where('subject_id', $subjectId)
            );
        }

        $existingLogs = $existingLogsQuery->get()->keyBy('user_id');

        $result = $teachers->map(function ($teacher) use ($existingLogs, $batchId, $subjectId, $dayCode) {
            $log = $existingLogs->get($teacher->id);
            $routineMeta = $this->buildTeacherRoutineMeta($teacher->id, $batchId, $subjectId, $dayCode);

            return [
                'id' => $teacher->id,
                'teacher_id' => $teacher->teacher_id,
                'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'teacher_type' => $teacher->teacher_type ?? 'permanent',
                'status' => $log?->attendance_status ?? 'present',
                'check_in' => $log?->check_in?->format('H:i'),
                'check_out' => $log?->check_out?->format('H:i'),
                'remarks' => $log?->remarks ?? '',
                'has_attendance' => $log !== null,
                'subject_id' => $routineMeta['subject_id'] ?? null,
                'batch_id' => $routineMeta['batch_id'] ?? null,
                'routine' => $routineMeta,
            ];
        })->values();

        return $this->collectionResponse($result);
    }

    /**
     * Build routine metadata for a teacher based on optional batch/subject filters.
     */
    protected function buildTeacherRoutineMeta(
        string $teacherId,
        ?string $batchId,
        ?string $subjectId,
        ?string $dayCode = null
    ): ?array {
        $routineQuery = ClassRoutine::published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('teacher_id', $teacherId)
            ->with('subject:id,name', 'batch:id,name');

        if ($dayCode) {
            $routineQuery->where('day_of_week', $dayCode);
        }

        if ($batchId) {
            $routineQuery->where('batch_id', $batchId);
        }

        if ($subjectId) {
            $routineQuery->where('subject_id', $subjectId);
        }

        $routines = $routineQuery->get();

        if ($routines->isEmpty()) {
            return null;
        }

        $first = $routines->first();
        $batchNames = $routines->pluck('batch.name')->filter()->unique()->values();
        $subjectNames = $routines->pluck('subject.name')->filter()->unique()->values();

        return [
            'slot_time' => $first->start_time && $first->end_time
                ? substr($first->start_time, 0, 5) . ' - ' . substr($first->end_time, 0, 5)
                : null,
            'batch_name' => $batchNames->join(', '),
            'subject_name' => $subjectNames->join(', '),
            'batch_id' => $first->batch_id,
            'subject_id' => $first->subject_id,
        ];
    }

    /**
     * Mark teacher attendance.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|string|exists:teachers,id',
            'status' => 'required|in:present,absent,late,leave,half_day',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $log = $this->engine->markTeacherAttendance(
            teacherId: $validated['teacher_id'],
            status: $validated['status'],
            subjectId: $validated['subject_id'] ?? null,
            classId: $validated['class_id'] ?? null,
            sessionId: $validated['session_id'] ?? null,
            remarks: $validated['remarks'] ?? null,
            date: isset($validated['date']) ? Carbon::parse($validated['date']) : null
        );

        return $this->created($log->load('teacherAttendance.teacher'));
    }

    /**
     * Bulk mark teacher attendance.
     */
    public function bulkMarkAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'session_id' => 'nullable|string|exists:attendance_sessions,id',
            'records' => 'required|array',
            'records.*.teacher_id' => 'required|string|exists:teachers,id',
            'records.*.status' => 'required|in:present,absent,late,leave,half_day',
            'records.*.subject_id' => 'nullable|string|exists:subjects,id',
            'records.*.check_in' => 'nullable|date_format:H:i',
            'records.*.check_out' => 'nullable|date_format:H:i',
            'records.*.remarks' => 'nullable|string',
        ]);

        $logs = $this->engine->bulkMarkTeacherAttendance(
            records: $validated['records'],
            date: $validated['date'],
            subjectId: $validated['subject_id'] ?? null,
            classId: $validated['class_id'] ?? null,
            sessionId: $validated['session_id'] ?? null
        );

        return $this->created($logs, 'Teacher attendance saved successfully');
    }

    /**
     * Get teacher class ledger for per-class payroll prep.
     */
    public function getClassLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'teacher_id' => 'nullable|string|exists:teachers,id',
        ]);

        $date = Carbon::parse($validated['date']);
        $batchIds = !empty($validated['batch_id']) ? [$validated['batch_id']] : null;
        $sync = $request->boolean('sync', true);

        if ($sync) {
            $this->classLedger->syncForDate($date, $batchIds);
        }

        $query = TeacherClassLedger::with([
            'teacher:id,first_name,last_name,teacher_id,teacher_type',
            'classSession:id,start_time,end_time,status',
            'batch:id,name',
            'subject:id,name',
            'attendanceLog:id,attendance_status,check_in,check_out',
        ])->where('session_date', $date->toDateString());

        if ($batchIds) {
            $query->whereIn('batch_id', $batchIds);
        }

        if (!empty($validated['subject_id'])) {
            $query->where('subject_id', $validated['subject_id']);
        }

        if (!empty($validated['teacher_id'])) {
            $query->where('teacher_id', $validated['teacher_id']);
        }

        $entries = $query->get()->sortBy(function ($entry) {
            return ($entry->classSession?->start_time ?? '') . ($entry->teacher?->first_name ?? '');
        })->values()->map(function ($entry) {
            $classSession = $entry->classSession;
            $log = $entry->attendanceLog;

            return [
                'id' => $entry->id,
                'class_session_id' => $entry->class_session_id,
                'teacher_id' => $entry->teacher_id,
                'teacher_name' => $entry->teacher?->full_name ?? trim(($entry->teacher?->first_name ?? '') . ' ' . ($entry->teacher?->last_name ?? '')),
                'teacher_code' => $entry->teacher?->teacher_id,
                'teacher_type' => $entry->teacher_type,
                'batch_id' => $entry->batch_id,
                'batch_name' => $entry->batch?->name,
                'subject_id' => $entry->subject_id,
                'subject_name' => $entry->subject?->name,
                'session_date' => $entry->session_date?->toDateString(),
                'start_time' => $classSession?->start_time ? substr((string) $classSession->start_time, 0, 5) : null,
                'end_time' => $classSession?->end_time ? substr((string) $classSession->end_time, 0, 5) : null,
                'status' => $entry->status,
                'payable_units' => (float) $entry->payable_units,
                'notes' => $entry->notes,
                'attendance_status' => $log?->attendance_status,
                'check_in' => $log?->check_in?->format('H:i'),
                'check_out' => $log?->check_out?->format('H:i'),
            ];
        })->values();

        return $this->success([
            'entries' => $entries,
            'context' => [
                'date' => $date->toDateString(),
                'total' => $entries->count(),
            ],
        ]);
    }

    /**
     * Bulk mark teacher class ledger entries.
     */
    public function bulkMarkClassLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.ledger_id' => 'nullable|string|exists:teacher_class_ledger,id',
            'records.*.class_session_id' => 'nullable|string|exists:class_sessions,id',
            'records.*.status' => 'required|in:scheduled,completed,cancelled,no_show',
            'records.*.attendance_status' => 'nullable|in:present,absent,late,leave,half_day',
            'records.*.check_in' => 'nullable|date_format:H:i',
            'records.*.check_out' => 'nullable|date_format:H:i',
            'records.*.notes' => 'nullable|string',
            'records.*.payable_units' => 'nullable|numeric|min:0',
        ]);

        $result = $this->classLedger->bulkMark($validated['records'], $validated['date']);

        return $this->created($result, 'Class ledger updated successfully');
    }

    /**
     * Get teacher attendance summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|string|exists:teachers,id',
        ]);

        $stats = $this->analytics->teacherStats($validated['teacher_id']);
        return $this->success($stats);
    }

    protected function baseRoutineQuery(?string $dayCode)
    {
        $query = ClassRoutine::query()
            ->published()
            ->notLunchBreak()
            ->notOffDay()
            ->whereNotNull('teacher_id');

        if ($dayCode) {
            $query->where('day_of_week', $dayCode);
        }

        return $query;
    }
}
