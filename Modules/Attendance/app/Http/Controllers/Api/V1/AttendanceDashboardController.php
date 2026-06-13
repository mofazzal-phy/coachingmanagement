<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Services\AttendanceAnalytics;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;

class AttendanceDashboardController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected AttendanceAnalytics $analytics
    ) {}

    /**
     * Get today's attendance overview for dashboard.
     */
    public function todayOverview(): JsonResponse
    {
        $stats = $this->engine->getTodayStats();

        return $this->success($stats);
    }

    /**
     * Get realtime attendance data (latest scans/logs).
     */
    public function realtimeData(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);

        $recentLogs = AttendanceLog::with([
            'studentAttendance.student:id,first_name,last_name',
            'teacherAttendance.teacher:id,first_name,last_name',
            'employeeAttendance.employee:id,first_name,last_name',
        ])
            ->where('attendance_date', Carbon::today())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $name = $this->resolveLogUserName($log);
                $typeLabel = ucfirst($log->user_type ?? 'user');

                return [
                    'id' => $log->id,
                    'user_type' => $log->user_type,
                    'type' => $log->user_type,
                    'name' => $name,
                    'user_name' => $name,
                    'status' => $log->attendance_status,
                    'source' => $log->attendance_source,
                    'detail' => $typeLabel . ' · ' . ucfirst($log->attendance_source ?? 'manual'),
                    'check_in' => $log->check_in?->format('h:i A'),
                    'time' => $log->check_in?->format('h:i A') ?? $log->created_at?->format('h:i A'),
                    'time_ago' => $log->created_at?->diffForHumans(),
                    'icon' => match ($log->user_type) {
                        'student' => '👨‍🎓',
                        'teacher' => '👨‍🏫',
                        'employee' => '👥',
                        default => '✓',
                    },
                ];
            });

        return $this->collectionResponse($recentLogs);
    }

    /**
     * Get monthly attendance trend chart data.
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_type' => 'nullable|in:student,teacher,employee',
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        $userType = $validated['user_type'] ?? 'student';
        $months = $validated['months'] ?? 6;

        $data = collect($this->analytics->monthlyTrend($userType, $months))->map(function ($item) {
            $total = (int) ($item['total'] ?? 0);
            $present = (int) ($item['present'] ?? 0);
            $month = $item['month'] ?? '';

            return [
                'month' => $month,
                'month_short' => $month ? Carbon::parse($month . '-01')->format('M') : '',
                'total' => $total,
                'present' => $present,
                'absent' => (int) ($item['absent'] ?? 0),
                'late' => (int) ($item['late'] ?? 0),
                'percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        });

        return $this->collectionResponse($data);
    }

    /**
     * Get low attendance alerts (students below threshold).
     */
    public function lowAttendanceAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => 'nullable|numeric|min:0|max:100',
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|string',
        ]);

        $threshold = $validated['threshold']
            ?? \Modules\Attendance\app\Services\AttendanceEngine::eligibilityThresholds()['warning_min'];
        $batchIds = null;
        if (!empty($validated['batch_ids'])) {
            $batchIds = array_map('trim', explode(',', $validated['batch_ids']));
        } elseif (!empty($validated['batch_id'])) {
            $batchIds = [$validated['batch_id']];
        }

        $students = collect($this->analytics->lowAttendanceStudents($threshold, $batchIds))->map(function ($item) {
            $batchName = null;
            if (!empty($item['student_id'])) {
                $enrollment = Enrollment::with('batch:id,name')
                    ->where('student_id', $item['student_id'])
                    ->where('status', 'active')
                    ->first();
                $batchName = $enrollment?->batch?->name;
            }

            return [
                ...$item,
                'student_name' => $item['name'] ?? 'Unknown',
                'batch_name' => $batchName,
            ];
        });

        return $this->collectionResponse($students);
    }

    /**
     * Get consecutive absent alerts.
     */
    public function consecutiveAbsentAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'batch_id' => 'nullable|string|exists:batches,id',
        ]);

        $days = $validated['days'] ?? 3;
        $records = collect($this->analytics->consecutiveAbsent($days, $validated['batch_id'] ?? null));

        return $this->collectionResponse($records);
    }

    /**
     * Get device status summary.
     */
    public function deviceStatus(): JsonResponse
    {
        $total = BiometricDevice::count();
        $online = BiometricDevice::where('status', 'online')->count();
        $offline = BiometricDevice::where('status', 'offline')->count();
        $error = BiometricDevice::where('status', 'error')->count();
        $active = BiometricDevice::where('is_active', true)->count();

        return $this->success([
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'error' => $error,
            'active' => $active,
            'online_percentage' => $total > 0 ? round(($online / $total) * 100, 1) : 0,
        ]);
    }

    protected function resolveLogUserName(AttendanceLog $log): string
    {
        return match ($log->user_type) {
            'student' => trim(
                ($log->studentAttendance?->student?->first_name ?? '') . ' ' .
                ($log->studentAttendance?->student?->last_name ?? '')
            ) ?: 'Unknown Student',
            'teacher' => trim(
                ($log->teacherAttendance?->teacher?->first_name ?? '') . ' ' .
                ($log->teacherAttendance?->teacher?->last_name ?? '')
            ) ?: 'Unknown Teacher',
            'employee' => trim(
                ($log->employeeAttendance?->employee?->first_name ?? '') . ' ' .
                ($log->employeeAttendance?->employee?->last_name ?? '')
            ) ?: 'Unknown Employee',
            default => 'Unknown',
        };
    }
}
