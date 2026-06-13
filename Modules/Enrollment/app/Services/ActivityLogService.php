<?php

namespace Modules\Enrollment\app\Services;

class ActivityLogService
{
    public function log(string $enrollmentId, string $action, ?string $description = null, ?string $oldStatus = null, ?string $newStatus = null, ?array $metadata = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        \Modules\Enrollment\app\Models\EnrollmentActivityLog::create([
            'enrollment_id' => $enrollmentId,
            'action' => $action,
            'description' => $description,
            'performed_by' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Log an activity for any model type (not just enrollments).
     */
    public function logForModel(?string $enrollmentId, string $modelType, string $modelId, string $action, ?string $description = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        \Modules\Enrollment\app\Models\EnrollmentActivityLog::create([
            'enrollment_id' => $enrollmentId,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'description' => $description,
            'performed_by' => auth()->id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }

    public function getTimeline(string $enrollmentId): array
    {
        return \Modules\Enrollment\app\Models\EnrollmentActivityLog::where('enrollment_id', $enrollmentId)
            ->with('performer:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get all audit logs with filters.
     */
    public function getAllLogs(array $filters = [], int $perPage = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = \Modules\Enrollment\app\Models\EnrollmentActivityLog::with('performer:id,name')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['model_type'])) $query->where('model_type', $filters['model_type']);
        if (!empty($filters['action'])) $query->where('action', $filters['action']);
        if (!empty($filters['user_id'])) $query->where('performed_by', $filters['user_id']);

        return $query->paginate($perPage);
    }
}
