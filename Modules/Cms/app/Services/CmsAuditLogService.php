<?php

namespace Modules\Cms\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\app\Models\CmsAuditLog;

class CmsAuditLogService
{
    public function log(
        string $entityType,
        string $entityId,
        string $action,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $performedBy = null
    ): ?CmsAuditLog {
        try {
            return CmsAuditLog::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'performed_by' => $performedBy ?? auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    public function logModelChange(Model $model, string $action, ?array $oldValues = null, ?array $newValues = null, ?string $description = null): ?CmsAuditLog
    {
        return $this->log(
            $model->getTable(),
            $model->getKey(),
            $action,
            $description,
            $oldValues,
            $newValues ?? $model->getChanges()
        );
    }

    public function getLogs(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = CmsAuditLog::with('performer:id,name')
            ->orderByDesc('created_at');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['performed_by'])) {
            $query->where('performed_by', $filters['performed_by']);
        }

        return $query->paginate($perPage);
    }

    public function getEntityTimeline(string $entityType, string $entityId, int $perPage = 30): LengthAwarePaginator
    {
        return CmsAuditLog::with('performer:id,name')
            ->forEntity($entityType, $entityId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
