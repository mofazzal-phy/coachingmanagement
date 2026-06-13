<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;

class AttendanceReportController extends BaseApiController
{
    /**
     * Daily attendance report.
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'user_type' => 'nullable|in:student,teacher,employee',
        ]);

        $date = $request->input('date') ?? $request->input('start_date') ?? now()->format('Y-m-d');

        $query = AttendanceLog::with([
            'studentAttendance.student:id,first_name,last_name,student_id',
            'teacherAttendance.teacher:id,first_name,last_name,teacher_id',
            'employeeAttendance.employee:id,first_name,last_name,employee_id',
        ])->where('attendance_date', $date)
            ->where(function ($q) {
                $q->where('attendance_mode', 'daily')
                    ->orWhereNull('attendance_session_id');
            });

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }

        $records = $query->orderBy('user_type')->orderBy('check_in')->get();

        $summary = $this->buildSummary($records);

        return $this->success([
            'date' => $date,
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $records->map(fn ($log) => $this->formatDailyRecord($log))->values(),
        ]);
    }

    /**
     * Monthly attendance report.
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2020|max:2099',
            'month' => 'nullable',
            'user_type' => 'nullable|in:student,teacher,employee',
        ]);

        $year = $request->input('year');
        $monthVal = $request->input('month');
        if (is_string($monthVal) && str_contains($monthVal, '-')) {
            [$year, $monthVal] = array_map('intval', explode('-', $monthVal));
        }
        $year = $year ?: now()->year;
        $monthVal = $monthVal ?: now()->month;

        $startDate = Carbon::create($year, $monthVal, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = AttendanceLog::with([
            'studentAttendance.student:id,first_name,last_name,student_id',
            'teacherAttendance.teacher:id,first_name,last_name,teacher_id',
            'employeeAttendance.employee:id,first_name,last_name,employee_id',
        ])->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }

        $records = $query->get();

        $userStats = $records->groupBy(fn ($log) => $log->user_type . ':' . $log->user_id)->map(function ($userRecords) {
            $log = $userRecords->first();
            $total = $userRecords->count();
            $present = $userRecords->where('attendance_status', 'present')->count();
            $absent = $userRecords->where('attendance_status', 'absent')->count();
            $late = $userRecords->where('attendance_status', 'late')->count();
            $leave = $userRecords->where('attendance_status', 'leave')->count();

            return [
                'user_id' => $log->user_id,
                'name' => $this->resolveUserName($log),
                'type' => $log->user_type,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'leave' => $leave,
                'total' => $total,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        })->values();

        $dailyBreakdown = $records->groupBy(fn ($log) => $log->attendance_date->format('Y-m-d'))->map(function ($dayRecords, $date) {
            return [
                'date' => $date,
                'total' => $dayRecords->count(),
                'present' => $dayRecords->where('attendance_status', 'present')->count(),
                'absent' => $dayRecords->where('attendance_status', 'absent')->count(),
                'late' => $dayRecords->where('attendance_status', 'late')->count(),
                'leave' => $dayRecords->where('attendance_status', 'leave')->count(),
            ];
        })->values();

        $summary = $this->buildSummary($records);
        $summary['total_days'] = $dailyBreakdown->count();

        return $this->success([
            'year' => $year,
            'month' => $monthVal,
            'month_name' => $startDate->format('F'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'daily_breakdown' => $dailyBreakdown,
            'records' => $userStats,
        ]);
    }

    /**
     * Batch attendance report.
     */
    public function batchReport(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id' => 'required|string|exists:batches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $enrollments = Enrollment::with('student')
            ->where('batch_id', $request->input('batch_id'))
            ->where('status', 'active')
            ->get();

        $studentIds = $enrollments->pluck('student_id')->filter();

        $records = AttendanceLog::where('user_type', 'student')
            ->whereIn('user_id', $studentIds)
            ->whereBetween('attendance_date', [$request->input('start_date'), $request->input('end_date')])
            ->get()
            ->groupBy('user_id');

        $report = $enrollments->map(function ($enrollment) use ($records) {
            $student = $enrollment->student;
            $studentRecords = $records->get($student->id, collect());
            $total = $studentRecords->count();
            $present = $studentRecords->where('attendance_status', 'present')->count();

            return [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'student_name' => trim($student->first_name . ' ' . ($student->last_name ?? '')),
                'name' => trim($student->first_name . ' ' . ($student->last_name ?? '')),
                'roll_no' => $student->roll_no ?? '',
                'total' => $total,
                'present' => $present,
                'absent' => $studentRecords->where('attendance_status', 'absent')->count(),
                'late' => $studentRecords->where('attendance_status', 'late')->count(),
                'leave' => $studentRecords->where('attendance_status', 'leave')->count(),
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        })->sortByDesc('percentage')->values();

        $summary = $this->buildSummary($records->flatten());

        return $this->success([
            'batch_id' => $request->input('batch_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'total_students' => $enrollments->count(),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $report,
        ]);
    }

    /**
     * Subject-wise attendance report.
     */
    public function subjectReport(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|string|exists:subjects,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'batch_id' => 'nullable|string|exists:batches,id',
        ]);

        $query = AttendanceLog::with('studentAttendance.student:id,first_name,last_name,student_id,roll_no')
            ->where('user_type', 'student')
            ->whereBetween('attendance_date', [$request->input('start_date'), $request->input('end_date')])
            ->whereHas('studentAttendance', function ($q) use ($request) {
                $q->where('subject_id', $request->input('subject_id'));
                if ($request->filled('batch_id')) {
                    $q->where('batch_id', $request->input('batch_id'));
                }
            });

        if ($request->filled('batch_id')) {
            $studentIds = Enrollment::where('batch_id', $request->input('batch_id'))
                ->where('status', 'active')
                ->pluck('student_id');
            $query->whereIn('user_id', $studentIds);
        }

        $records = $query->get();

        $aggregated = $records->groupBy('user_id')->map(function ($studentRecords, $studentId) {
            $student = $studentRecords->first()->studentAttendance?->student;
            $total = $studentRecords->count();
            $present = $studentRecords->where('attendance_status', 'present')->count();

            return [
                'student_id' => $studentId,
                'student_name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
                'name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
                'roll_no' => $student->roll_no ?? '',
                'total' => $total,
                'present' => $present,
                'absent' => $studentRecords->where('attendance_status', 'absent')->count(),
                'late' => $studentRecords->where('attendance_status', 'late')->count(),
                'leave' => $studentRecords->where('attendance_status', 'leave')->count(),
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        })->values();

        $detailRecords = $records->map(function ($log) {
            $student = $log->studentAttendance?->student;

            return [
                'date' => $log->attendance_date?->format('Y-m-d') ?? '',
                'student_name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
                'name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
                'status' => $log->attendance_status,
                'check_in' => $log->check_in?->format('H:i') ?? '',
            ];
        })->values();

        $summary = $this->buildSummary($records);

        return $this->success([
            'subject_id' => $request->input('subject_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $aggregated,
            'detail_records' => $detailRecords,
        ]);
    }

    /**
     * Teacher attendance report.
     */
    public function teacherReport(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => 'required|string|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = AttendanceLog::with('teacherAttendance.teacher:id,first_name,last_name,teacher_id')
            ->where('user_type', 'teacher')
            ->where('user_id', $request->input('teacher_id'))
            ->whereBetween('attendance_date', [$request->input('start_date'), $request->input('end_date')])
            ->orderBy('attendance_date')
            ->get();

        $summary = $this->buildSummary($records);

        return $this->success([
            'teacher_id' => $request->input('teacher_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $records->map(fn ($log) => [
                'date' => $log->attendance_date?->format('Y-m-d') ?? '',
                'status' => $log->attendance_status,
                'check_in' => $log->check_in?->format('H:i') ?? '',
                'check_out' => $log->check_out?->format('H:i') ?? '',
                'remarks' => $log->remarks ?? '',
            ])->values(),
        ]);
    }

    /**
     * Employee attendance report.
     */
    public function employeeReport(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = AttendanceLog::with('employeeAttendance.employee:id,first_name,last_name,employee_id')
            ->where('user_type', 'employee')
            ->where('user_id', $request->input('employee_id'))
            ->whereBetween('attendance_date', [$request->input('start_date'), $request->input('end_date')])
            ->orderBy('attendance_date')
            ->get();

        $summary = $this->buildSummary($records);

        return $this->success([
            'employee_id' => $request->input('employee_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $records->map(fn ($log) => [
                'date' => $log->attendance_date?->format('Y-m-d') ?? '',
                'status' => $log->attendance_status,
                'check_in' => $log->check_in?->format('H:i') ?? '',
                'check_out' => $log->check_out?->format('H:i') ?? '',
                'remarks' => $log->remarks ?? '',
            ])->values(),
        ]);
    }

    /**
     * Session attendance report (class-period marks).
     */
    public function sessionReport(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'user_type' => 'nullable|in:student,teacher',
        ]);

        $query = AttendanceLog::with([
            'session.subject:id,name',
            'session.batch:id,name',
            'studentAttendance.student:id,first_name,last_name,student_id,roll_no',
            'teacherAttendance.teacher:id,first_name,last_name,teacher_id',
        ])
            ->where('attendance_mode', 'session')
            ->whereBetween('attendance_date', [$request->input('start_date'), $request->input('end_date')]);

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }

        if ($request->filled('batch_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('session', fn ($s) => $s->where('batch_id', $request->input('batch_id')))
                    ->orWhereHas('studentAttendance', fn ($s) => $s->where('batch_id', $request->input('batch_id')));
            });
        }

        if ($request->filled('subject_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('session', fn ($s) => $s->where('subject_id', $request->input('subject_id')))
                    ->orWhereHas('studentAttendance', fn ($s) => $s->where('subject_id', $request->input('subject_id')))
                    ->orWhereHas('teacherAttendance', fn ($s) => $s->where('subject_id', $request->input('subject_id')));
            });
        }

        $records = $query->orderBy('attendance_date')->orderBy('check_in')->get();
        $summary = $this->buildSummary($records);

        return $this->success([
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'summary' => $summary,
            'total' => $summary['total'],
            'present' => $summary['present'],
            'absent' => $summary['absent'],
            'late' => $summary['late'],
            'leave' => $summary['leave'],
            'percentage' => $summary['percentage'],
            'records' => $records->map(fn ($log) => [
                'date' => $log->attendance_date?->format('Y-m-d') ?? '',
                'name' => $this->resolveUserName($log),
                'type' => $log->user_type,
                'status' => $log->attendance_status,
                'subject' => $log->session?->subject?->name
                    ?? $log->studentAttendance?->subject?->name
                    ?? $log->teacherAttendance?->subject?->name
                    ?? '',
                'batch' => $log->session?->batch?->name
                    ?? $log->studentAttendance?->batch?->name
                    ?? '',
                'check_in' => $log->check_in?->format('H:i') ?? '',
                'check_out' => $log->check_out?->format('H:i') ?? '',
            ])->values(),
        ]);
    }

    protected function buildSummary(Collection $records): array
    {
        $total = $records->count();
        $present = $records->where('attendance_status', 'present')->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $records->where('attendance_status', 'absent')->count(),
            'late' => $records->where('attendance_status', 'late')->count(),
            'leave' => $records->where('attendance_status', 'leave')->count(),
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }

    protected function formatDailyRecord(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'date' => $log->attendance_date?->format('Y-m-d') ?? '',
            'time' => $log->check_in?->format('h:i A') ?? '',
            'name' => $this->resolveUserName($log),
            'type' => $log->user_type,
            'status' => $log->attendance_status,
            'check_in' => $log->check_in?->format('H:i') ?? '',
            'check_out' => $log->check_out?->format('H:i') ?? '',
            'remarks' => $log->remarks ?? '',
        ];
    }

    protected function resolveUserName(AttendanceLog $log): string
    {
        return match ($log->user_type) {
            'student' => $this->formatPersonName($log->studentAttendance?->student),
            'teacher' => $this->formatPersonName($log->teacherAttendance?->teacher),
            'employee' => $this->formatPersonName($log->employeeAttendance?->employee),
            default => 'Unknown',
        };
    }

    protected function formatPersonName(?object $person): string
    {
        if (!$person) {
            return 'Unknown';
        }

        if (!empty($person->full_name)) {
            return trim($person->full_name);
        }

        return trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
    }
}
