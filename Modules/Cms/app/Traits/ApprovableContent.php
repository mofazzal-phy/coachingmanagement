<?php

namespace Modules\Cms\app\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cms\app\Enums\CmsApprovalStatus;

trait ApprovableContent
{
    public function initializeApprovableContent(): void
    {
        $this->mergeFillable([
            'approval_status',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $this->mergeCasts([
            'approved_at' => 'datetime',
        ]);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('approval_status', CmsApprovalStatus::PendingReview->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', CmsApprovalStatus::Approved->value);
    }

    public function isPendingReview(): bool
    {
        return $this->approval_status === CmsApprovalStatus::PendingReview->value;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === CmsApprovalStatus::Approved->value;
    }
}
