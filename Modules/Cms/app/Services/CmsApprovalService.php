<?php

namespace Modules\Cms\app\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use RuntimeException;

class CmsApprovalService
{
    public function __construct(
        protected CmsAuditLogService $auditLogService
    ) {}

    public function submit(Model $model, ?User $user = null, ?string $comment = null): Model
    {
        $this->assertApprovable($model);

        $status = $model->approval_status;
        if ($status && !in_array($status, [
            CmsApprovalStatus::Draft->value,
            CmsApprovalStatus::Rejected->value,
        ], true)) {
            throw new RuntimeException('Only draft or rejected content can be submitted for review.');
        }

        return DB::transaction(function () use ($model, $comment) {
            $old = $model->only(['approval_status', 'rejection_reason']);
            $model->update([
                'approval_status' => CmsApprovalStatus::PendingReview->value,
                'rejection_reason' => null,
            ]);

            $this->auditLogService->log(
                $model->getTable(),
                $model->getKey(),
                'submit_for_review',
                $comment,
                $old,
                $model->only(['approval_status', 'rejection_reason'])
            );

            return $model->fresh();
        });
    }

    public function approve(Model $model, User $user, ?string $comment = null): Model
    {
        $this->assertApprovable($model);

        if ($model->approval_status !== CmsApprovalStatus::PendingReview->value) {
            throw new RuntimeException('Only pending content can be approved.');
        }

        return DB::transaction(function () use ($model, $user, $comment) {
            $old = $model->only(['approval_status', 'approved_by', 'approved_at', 'rejection_reason']);
            $model->update([
                'approval_status' => CmsApprovalStatus::Approved->value,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->auditLogService->log(
                $model->getTable(),
                $model->getKey(),
                'approve',
                $comment,
                $old,
                $model->only(['approval_status', 'approved_by', 'approved_at', 'rejection_reason'])
            );

            return $model->fresh();
        });
    }

    public function reject(Model $model, User $user, string $reason, ?string $comment = null): Model
    {
        $this->assertApprovable($model);

        if ($model->approval_status !== CmsApprovalStatus::PendingReview->value) {
            throw new RuntimeException('Only pending content can be rejected.');
        }

        return DB::transaction(function () use ($model, $user, $reason, $comment) {
            $old = $model->only(['approval_status', 'approved_by', 'approved_at', 'rejection_reason']);
            $model->update([
                'approval_status' => CmsApprovalStatus::Rejected->value,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->auditLogService->log(
                $model->getTable(),
                $model->getKey(),
                'reject',
                $comment ?: $reason,
                $old,
                $model->only(['approval_status', 'rejection_reason'])
            );

            return $model->fresh();
        });
    }

    protected function assertApprovable(Model $model): void
    {
        if (!in_array('approval_status', $model->getFillable(), true) && !array_key_exists('approval_status', $model->getAttributes())) {
            throw new RuntimeException('This content type does not support approval workflow.');
        }
    }
}
