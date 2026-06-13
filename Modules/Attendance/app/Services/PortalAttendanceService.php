<?php

namespace Modules\Attendance\app\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\ClassSession;
use Modules\Attendance\app\Models\TeacherClassLedger;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Hr\app\Models\Employee;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;

class PortalAttendanceService
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected AttendanceAnalytics $analytics,
    ) {}

    /**
     * Resolve the authenticated user's attendance profile.
     *
     * @return array{0: string, 1: string}|null [user_type, profile_id]
     */
    public function resolveProfile(?User $user = null): ?array
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return null;
        }

        if ($student = Student::where('user_id', $user->id)->first()) {
            return ['student', $student->id];
        }

        if ($teacher = Teacher::where('user_id', $user->id)->first()) {
            return ['teacher', $teacher->id];
        }

        if ($employee = Employee::where('user_id', $user->id)->first()) {
            return ['employee', $employee->id];
        }

        return null;
    }

    /**
     * Build portal attendance payload for a student, teacher, or employee.
     */
    public function getPortalData(string $userType, string $userId): array
    {
        $logs = AttendanceLog::where('user_type', $userType)
            ->where('user_id', $userId)
            ->with(array_merge($this->relationsFor($userType), ['session.subject:id,name', 'session.batch:id,name']))
            ->orderByDesc('attendance_date')
            ->orderByDesc('updated_at')
            ->get();

        $dailyLogs = $logs->filter(fn (AttendanceLog $log) => ($log->attendance_mode ?? 'daily') === 'daily');
        $sessionLogs = $logs->filter(fn (AttendanceLog $log) => $log->attendance_mode === 'session');

        // Keep only the latest daily record per day (handles legacy duplicate rows).
        $canonicalDailyLogs = $dailyLogs
            ->groupBy(fn (AttendanceLog $log) => $log->attendance_date?->toDateString() ?? 'unknown')
            ->map(fn ($group) => $group->sortByDesc('updated_at')->first())
            ->filter(fn ($log) => $log->attendance_date !== null)
            ->sortByDesc(fn (AttendanceLog $log) => $log->attendance_date?->toDateString())
            ->values();

        $summary = $this->buildSummary($canonicalDailyLogs);

        $today = Carbon::today()->toDateString();
        $todayDailyLog = $canonicalDailyLogs->first(
            fn (AttendanceLog $log) => $log->attendance_date?->toDateString() === $today
        );
        $todaySessionLogs = $sessionLogs->filter(
            fn (AttendanceLog $log) => $log->attendance_date?->toDateString() === $today
        )->values();

        $recentDailyRecords = $canonicalDailyLogs->take(30)->map(
            fn (AttendanceLog $log) => $this->formatRecord($log, $userType)
        )->values();

        $recentSessionRecords = $sessionLogs->take(30)->map(
            fn (AttendanceLog $log) => $this->formatRecord($log, $userType, 'session')
        )->values();

        $subjectSummary = $userType === 'student'
            ? $this->buildStudentSubjectSummary($userId, $sessionLogs)
            : [];

        $eligibilityPercentage = $userType === 'student' && !empty($subjectSummary)
            ? $this->averageSubjectPercentage($subjectSummary)
            : $summary['percentage'];

        if ($userType === 'student') {
            $summary['eligibility'] = $this->engine->calculateEligibility($eligibilityPercentage);
            $summary['session_eligibility_percentage'] = $eligibilityPercentage;
        }

        $teacherClassData = $userType === 'teacher'
            ? $this->buildTeacherClassPortalData($userId)
            : null;

        return [
            'user_type' => $userType,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'half_day' => $summary['half_day'],
            'excused' => $summary['excused'],
            'percentage' => $summary['percentage'],
            'summary' => $summary,
            'daily_summary' => $summary,
            'session_summary' => $this->buildSummary($sessionLogs),
            'subject_summary' => $subjectSummary,
            'today_status' => $todayDailyLog?->attendance_status,
            'today_daily' => $todayDailyLog
                ? $this->formatRecord($todayDailyLog, $userType)
                : null,
            'today_sessions' => $todaySessionLogs->map(
                fn (AttendanceLog $log) => $this->formatRecord($log, $userType, 'session')
            )->values(),
            'today_records' => collect([
                $todayDailyLog ? $this->formatRecord($todayDailyLog, $userType) : null,
            ])->filter()->merge(
                $todaySessionLogs->map(fn (AttendanceLog $log) => $this->formatRecord($log, $userType, 'session'))
            )->values(),
            'recent_records' => $recentDailyRecords,
            'recent_session_records' => $recentSessionRecords,
            'class_ledger' => $teacherClassData,
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Teacher portal: class completion / no-show / pending by subject.
     */
    protected function buildTeacherClassPortalData(string $teacherId): array
    {
        $start = Carbon::now()->subDays(30)->toDateString();
        $end = Carbon::today()->toDateString();

        $sessions = ClassSession::query()
            ->with(['subject:id,name', 'batch:id,name'])
            ->where('teacher_id', $teacherId)
            ->whereBetween('session_date', [$start, $end])
            ->orderByDesc('session_date')
            ->orderBy('start_time')
            ->get();

        $ledgers = TeacherClassLedger::query()
            ->with(['subject:id,name', 'batch:id,name'])
            ->where('teacher_id', $teacherId)
            ->whereBetween('session_date', [$start, $end])
            ->get()
            ->keyBy('class_session_id');

        $items = $sessions->map(function (ClassSession $session) use ($ledgers) {
            $ledger = $ledgers->get($session->id);
            $status = $ledger?->status ?? $this->mapClassSessionToLedgerStatus($session->status);

            return [
                'ledger_id' => $ledger?->id,
                'class_session_id' => $session->id,
                'date' => $session->session_date?->toDateString(),
                'subject_id' => $session->subject_id,
                'subject_name' => $session->subject?->name ?? 'Unknown',
                'batch_id' => $session->batch_id,
                'batch_name' => $session->batch?->name,
                'start_time' => $session->start_time ? substr((string) $session->start_time, 0, 5) : null,
                'end_time' => $session->end_time ? substr((string) $session->end_time, 0, 5) : null,
                'status' => $status,
                'status_label' => $this->classLedgerStatusLabel($status),
                'payable_units' => $ledger ? (float) $ledger->payable_units : null,
                'notes' => $ledger?->notes,
            ];
        })->values();

        // Include ledger rows without a linked class session (edge case).
        $sessionIds = $sessions->pluck('id');
        $orphanLedgers = $ledgers->filter(fn ($ledger) => !$sessionIds->contains($ledger->class_session_id));
        foreach ($orphanLedgers as $ledger) {
            $items->push([
                'ledger_id' => $ledger->id,
                'class_session_id' => $ledger->class_session_id,
                'date' => $ledger->session_date?->toDateString(),
                'subject_id' => $ledger->subject_id,
                'subject_name' => $ledger->subject?->name ?? 'Unknown',
                'batch_id' => $ledger->batch_id,
                'batch_name' => $ledger->batch?->name,
                'start_time' => null,
                'end_time' => null,
                'status' => $ledger->status,
                'status_label' => $this->classLedgerStatusLabel($ledger->status),
                'payable_units' => (float) $ledger->payable_units,
                'notes' => $ledger->notes,
            ]);
        }

        $items = $items->sortByDesc('date')->values();

        $todayClasses = $items->filter(fn ($item) => $item['date'] === $end)->values();
        $recentClasses = $items->take(40)->values();

        $subjectSummary = $items->groupBy('subject_id')->map(function ($group) {
            $first = $group->first();
            $total = $group->count();
            $completed = $group->where('status', 'completed')->count();

            return [
                'subject_id' => $first['subject_id'],
                'subject_name' => $first['subject_name'] ?? 'Unknown',
                'total_classes' => $total,
                'completed' => $completed,
                'no_show' => $group->where('status', 'no_show')->count(),
                'cancelled' => $group->where('status', 'cancelled')->count(),
                'pending' => $group->whereIn('status', ['scheduled'])->count(),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->sortByDesc('total_classes')->values()->all();

        return [
            'summary' => [
                'total_classes' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'no_show' => $items->where('status', 'no_show')->count(),
                'cancelled' => $items->where('status', 'cancelled')->count(),
                'pending' => $items->where('status', 'scheduled')->count(),
                'completion_rate' => $items->count() > 0
                    ? round(($items->where('status', 'completed')->count() / $items->count()) * 100, 1)
                    : 0,
                'period_start' => $start,
                'period_end' => $end,
            ],
            'today_classes' => $todayClasses->all(),
            'recent_classes' => $recentClasses->all(),
            'subject_summary' => $subjectSummary,
        ];
    }

    protected function mapClassSessionToLedgerStatus(string $classStatus): string
    {
        return match ($classStatus) {
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'scheduled',
        };
    }

    protected function classLedgerStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Completed',
            'no_show' => 'No Show',
            'cancelled' => 'Cancelled',
            default => 'Pending',
        };
    }

    protected function buildSummary($logs): array
    {
        $total = $logs->count();
        $present = $logs->where('attendance_status', 'present')->count();
        $absent = $logs->where('attendance_status', 'absent')->count();
        $late = $logs->where('attendance_status', 'late')->count();
        $leave = $logs->where('attendance_status', 'leave')->count();
        $halfDay = $logs->where('attendance_status', 'half_day')->count();
        $excused = $leave + $halfDay;

        $percentage = $total > 0
            ? round((($present + $late) / $total) * 100, 2)
            : 0;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'half_day' => $halfDay,
            'excused' => $excused,
            'percentage' => $percentage,
        ];
    }

    protected function buildStudentSubjectSummary(string $studentId, $sessionLogs): array
    {
        $batchIds = Enrollment::where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return $this->analytics->subjectWiseAttendance($studentId);
        }

        $start = Carbon::now()->subDays(30)->toDateString();
        $end = Carbon::today()->toDateString();

        $expectedBySubject = ClassSession::query()
            ->with('subject:id,name')
            ->whereIn('batch_id', $batchIds)
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy('subject_id');

        $attendedBySubject = $sessionLogs
            ->filter(fn (AttendanceLog $log) => in_array($log->attendance_status, ['present', 'late'], true))
            ->groupBy(fn (AttendanceLog $log) => $log->studentAttendance?->subject_id ?? $log->session?->subject_id);

        $subjectIds = $expectedBySubject->keys()
            ->merge($attendedBySubject->keys())
            ->filter()
            ->unique();

        $summary = [];
        foreach ($subjectIds as $subjectId) {
            $expected = $expectedBySubject->get($subjectId)?->count() ?? 0;
            $attended = $attendedBySubject->get($subjectId)?->count() ?? 0;

            if ($expected === 0 && $attended === 0) {
                continue;
            }

            $subjectName = $expectedBySubject->get($subjectId)?->first()?->subject?->name
                ?? $sessionLogs->first(fn ($log) => ($log->studentAttendance?->subject_id ?? $log->session?->subject_id) === $subjectId)
                    ?->studentAttendance?->subject?->name
                ?? 'Unknown';

            $denominator = max($expected, $attended);
            $summary[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'expected_sessions' => $expected,
                'attended_sessions' => $attended,
                'percentage' => $denominator > 0 ? round(($attended / $denominator) * 100, 2) : 0,
            ];
        }

        if (empty($summary)) {
            return $this->analytics->subjectWiseAttendance($studentId);
        }

        return collect($summary)->sortByDesc('percentage')->values()->all();
    }

    protected function averageSubjectPercentage(array $subjectSummary): float
    {
        if (empty($subjectSummary)) {
            return 0;
        }

        $percentages = collect($subjectSummary)->pluck('percentage')->filter(fn ($p) => $p !== null);
        if ($percentages->isEmpty()) {
            return 0;
        }

        return round($percentages->avg(), 2);
    }

    /**
     * @return array<int, string>
     */
    protected function relationsFor(string $userType): array
    {
        return match ($userType) {
            'student' => ['studentAttendance.subject:id,name', 'studentAttendance.batch:id,name'],
            'teacher' => ['teacherAttendance.subject:id,name'],
            'employee' => ['employeeAttendance.department:id,name'],
            default => [],
        };
    }

    protected function formatRecord(AttendanceLog $log, string $userType, string $mode = 'daily'): array
    {
        $record = [
            'id' => $log->id,
            'date' => $log->attendance_date?->toDateString(),
            'status' => $log->attendance_status,
            'check_in' => $log->check_in?->format('H:i'),
            'check_in_display' => $log->check_in?->format('h:i A'),
            'check_out' => $log->check_out?->format('H:i'),
            'check_out_display' => $log->check_out?->format('h:i A'),
            'late_minutes' => (int) ($log->late_minutes ?? 0),
            'remarks' => $log->remarks,
            'source' => $log->attendance_source,
            'mode' => $log->attendance_mode ?? $mode,
            'updated_at' => $log->updated_at?->toIso8601String(),
        ];

        if ($userType === 'teacher' && $log->teacherAttendance) {
            $record['subject'] = $log->teacherAttendance->subject
                ? ['id' => $log->teacherAttendance->subject->id, 'name' => $log->teacherAttendance->subject->name]
                : $this->inferSubjectLabel($userType, $log->user_id, $log);
        }

        if ($userType === 'student' && $log->studentAttendance) {
            $record['subject'] = $log->studentAttendance->subject
                ? ['id' => $log->studentAttendance->subject->id, 'name' => $log->studentAttendance->subject->name]
                : ($log->session?->subject
                    ? ['id' => $log->session->subject->id, 'name' => $log->session->subject->name]
                    : $this->inferSubjectLabel($userType, $log->user_id, $log));
            $record['batch'] = $log->studentAttendance->batch
                ? ['id' => $log->studentAttendance->batch->id, 'name' => $log->studentAttendance->batch->name]
                : ($log->session?->batch
                    ? ['id' => $log->session->batch->id, 'name' => $log->session->batch->name]
                    : null);
        }

        if ($userType === 'employee' && $log->employeeAttendance) {
            $record['department'] = $log->employeeAttendance->department
                ? ['id' => $log->employeeAttendance->department->id, 'name' => $log->employeeAttendance->department->name]
                : null;
        }

        return $record;
    }

    /**
     * Show routine subject name when attendance row has no subject_id.
     */
    protected function inferSubjectLabel(string $userType, string $userId, AttendanceLog $log): ?array
    {
        if (!$log->attendance_date) {
            return null;
        }

        $when = $log->check_in ?? $log->attendance_date->copy()->startOfDay();
        $dayMap = [
            'Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed',
            'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat',
        ];
        $dayCode = $dayMap[$when->format('D')] ?? null;
        if (!$dayCode) {
            return null;
        }

        $query = ClassRoutine::published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('day_of_week', $dayCode)
            ->where('start_time', '<=', $when->format('H:i:s'))
            ->where('end_time', '>=', $when->format('H:i:s'))
            ->with('subject:id,name');

        if ($userType === 'teacher') {
            $query->where('teacher_id', $userId);
        } else {
            $batchId = $log->studentAttendance?->batch_id;
            if (!$batchId) {
                return null;
            }
            $query->where('batch_id', $batchId);
        }

        $routine = $query->first();
        if (!$routine?->subject) {
            return null;
        }

        return ['id' => $routine->subject->id, 'name' => $routine->subject->name];
    }
}
