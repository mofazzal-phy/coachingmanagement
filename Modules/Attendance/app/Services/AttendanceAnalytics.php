<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\app\Models\AttendanceLog;

class AttendanceAnalytics
{
    /**
     * Get the database driver name.
     */
    protected function getDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Get a DB-agnostic date format expression.
     * Returns raw SQL string for the column with format.
     */
    protected function dateFormatSql(string $column, string $format): string
    {
        return match ($this->getDriver()) {
            'mysql' => "DATE_FORMAT($column, '$format')",
            'pgsql' => "TO_CHAR($column, '$format')",
            'sqlite' => "strftime('$format', $column)",
            'sqlsrv' => "FORMAT($column, '$format')",
            default => "DATE_FORMAT($column, '$format')",
        };
    }

    /**
     * Get monthly attendance trend.
     */
    public function monthlyTrend(string $userType, int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        $monthExpr = $this->dateFormatSql('attendance_date', '%Y-%m');

        $records = AttendanceLog::where('user_type', $userType)
            ->where('attendance_date', '>=', $startDate)
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN attendance_status = 'late' THEN 1 ELSE 0 END) as late")
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get();

        return $records->toArray();
    }

    /**
     * Get subject-wise attendance for a student.
     */
    public function subjectWiseAttendance(string $studentId): array
    {
        $records = AttendanceLog::where('user_type', 'student')
            ->where('user_id', $studentId)
            ->whereHas('studentAttendance.subject')
            ->with('studentAttendance.subject:id,name')
            ->select(
                'attendance_logs.*',
                DB::raw('student_attendances.subject_id')
            )
            ->join('student_attendances', 'attendance_logs.id', '=', 'student_attendances.attendance_log_id')
            ->get()
            ->groupBy('studentAttendance.subject_id');

        $result = [];
        foreach ($records as $subjectId => $logs) {
            $subject = $logs->first()->studentAttendance->subject;
            $total = $logs->count();
            $present = $logs->whereIn('attendance_status', ['present', 'late'])->count();
            $result[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject?->name ?? 'Unknown',
                'total' => $total,
                'present' => $present,
                'absent' => $total - $present,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    /**
     * Get consecutive absent students.
     */
    public function consecutiveAbsent(int $days = 3, ?string $batchId = null): array
    {
        $thresholdDate = Carbon::now()->subDays($days);

        $query = AttendanceLog::where('user_type', 'student')
            ->where('attendance_status', 'absent')
            ->where('attendance_date', '>=', $thresholdDate)
            ->select('user_id', DB::raw('COUNT(*) as absent_days'))
            ->groupBy('user_id')
            ->having('absent_days', '>=', $days);

        if ($batchId) {
            $query->whereHas('studentAttendance', fn($q) => $q->where('batch_id', $batchId));
        }

        return $query->get()->map(function ($row) {
            $student = \Modules\Student\app\Models\Student::find($row->user_id);

            return [
                'user_id' => $row->user_id,
                'absent_days' => (int) $row->absent_days,
                'days' => (int) $row->absent_days,
                'name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
                'student_name' => $student ? trim($student->first_name . ' ' . ($student->last_name ?? '')) : 'Unknown',
            ];
        })->values()->all();
    }

    /**
     * Get low attendance students (below threshold).
     * Includes students with zero attendance logs (they have 0% attendance).
     *
     * @param float $threshold Percentage threshold (0-100)
     * @param array|null $batchIds Array of batch IDs to filter by, or null for all batches
     */
    public function lowAttendanceStudents(?float $threshold = null, array|null $batchIds = null): array
    {
        if ($threshold === null) {
            $threshold = AttendanceEngine::eligibilityThresholds()['warning_min'];
        }

        return $this->lowAttendanceStudentsBelow($threshold, $batchIds);
    }

    /**
     * @param  array<string>|null  $batchIds
     * @return list<array<string, mixed>>
     */
    public function lowAttendanceStudentsBelow(float $threshold, array|null $batchIds = null): array
    {
        $startDate = Carbon::now()->startOfMonth();

        // Get attendance stats for students who have logs this month
        $logQuery = AttendanceLog::where('user_type', 'student')
            ->where('attendance_date', '>=', $startDate)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) as present_count")
            )
            ->groupBy('user_id');

        if (!empty($batchIds)) {
            $logQuery->whereHas('studentAttendance', fn($q) => $q->whereIn('batch_id', $batchIds));
        }

        $logRecords = $logQuery->get()->keyBy('user_id');

        // Get all active enrolled students (including those with zero logs)
        $enrollmentModel = app(\Modules\Enrollment\app\Models\Enrollment::class);
        $enrollmentsQuery = $enrollmentModel->with('student')
            ->where('status', 'active');

        if (!empty($batchIds)) {
            $enrollmentsQuery->whereIn('batch_id', $batchIds);
        }

        $enrollments = $enrollmentsQuery->get();
        $result = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if (!$student) continue;

            $userId = $student->id;
            $log = $logRecords->get($userId);

            $total = $log ? $log->total : 0;
            $presentCount = $log ? $log->present_count : 0;
            $percentage = $total > 0 ? ($presentCount / $total) * 100 : 0;

            if ($percentage < $threshold) {
                $result[] = [
                    'student_id' => $userId,
                    'name' => $student->full_name ?? 'Unknown',
                    'total' => $total,
                    'present' => $presentCount,
                    'percentage' => round($percentage, 2),
                ];
            }
        }

        // Sort by percentage ascending (worst offenders first)
        usort($result, fn($a, $b) => $a['percentage'] <=> $b['percentage']);

        return $result;
    }

    /**
     * Get teacher attendance stats.
     */
    public function teacherStats(string $teacherId): array
    {
        $startDate = Carbon::now()->startOfMonth();

        $logs = AttendanceLog::where('user_type', 'teacher')
            ->where('user_id', $teacherId)
            ->where('attendance_date', '>=', $startDate)
            ->get();

        $total = $logs->count();
        $present = $logs->whereIn('attendance_status', ['present', 'late'])->count();
        $late = $logs->where('attendance_status', 'late')->count();

        return [
            'total_classes' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $total - $present,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get employee attendance stats.
     */
    public function employeeStats(string $employeeId): array
    {
        $startDate = Carbon::now()->startOfMonth();

        $logs = AttendanceLog::where('user_type', 'employee')
            ->where('user_id', $employeeId)
            ->where('attendance_date', '>=', $startDate)
            ->get();

        $total = $logs->count();
        $present = $logs->whereIn('attendance_status', ['present', 'late'])->count();
        $late = $logs->where('attendance_status', 'late')->count();

        return [
            'total_days' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $total - $present,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }
}
