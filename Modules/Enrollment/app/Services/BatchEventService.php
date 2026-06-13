<?php

namespace Modules\Enrollment\app\Services;

use Modules\Enrollment\app\Models\Batch;

class BatchEventService
{
    public function __construct(protected ActivityLogService $logService, protected NotificationService $notifyService) {}

    public function onCreated(Batch $batch): void
    {
        $this->logService->logForModel(null, 'batch', $batch->id, 'created',
            "Batch '{$batch->name}' created",
            null, $batch->toArray());
    }

    public function onUpdated(Batch $batch, array $oldValues): void
    {
        $changes = array_diff_assoc($batch->toArray(), $oldValues);
        if (empty($changes)) return;

        $this->logService->logForModel(null, 'batch', $batch->id, 'updated',
            "Batch '{$batch->name}' updated: ".implode(', ', array_keys($changes)),
            $oldValues, $batch->toArray());
    }

    public function onSeatsFull(Batch $batch): void
    {
        $this->logService->logForModel(null, 'batch', $batch->id, 'full',
            "Batch '{$batch->name}' is now full ({$batch->enrolled_count}/{$batch->capacity})");
        $this->notifyService->notifyAdmins('batch_full', "Batch '{$batch->name}' is full!");
    }

    public function onStartingSoon(Batch $batch): void
    {
        $this->logService->logForModel(null, 'batch', $batch->id, 'starting_soon',
            "Batch '{$batch->name}' starts on {$batch->start_date}");
        $this->notifyService->notifyAdmins('batch_starting_soon',
            "Batch '{$batch->name}' starts on {$batch->start_date}");
    }
}
