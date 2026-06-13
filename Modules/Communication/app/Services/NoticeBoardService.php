<?php

namespace Modules\Communication\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use App\Models\User;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Services\CmsAnalyticsService;
use Modules\Cms\app\Services\CmsApprovalService;
use Modules\Cms\app\Services\CmsAuditLogService;
use Modules\Cms\app\Services\CmsMediaService;
use Modules\Communication\app\Models\NoticeBoard;

class NoticeBoardService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
        protected CmsAnalyticsService $analyticsService
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return NoticeBoard::with(['creator:id,name', 'approver:id,name'])
            ->search($filters['search'] ?? null)
            ->filter(collect($filters)->only(['status', 'priority', 'audience', 'is_featured', 'approval_status'])->toArray())
            ->orderByDesc('publish_date')
            ->paginate($perPage);
    }

    public function find(string $id): ?NoticeBoard
    {
        return NoticeBoard::with(['creator:id,name', 'approver:id,name', 'editor:id,name'])->find($id);
    }

    public function create(array $data, User $user): NoticeBoard
    {
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['audience'] = $data['audience'] ?? 'all';

        $notice = NoticeBoard::create($data);

        $this->auditLogService->log(
            $notice->getTable(),
            $notice->getKey(),
            'created',
            'Notice created',
            null,
            $notice->only(['title', 'status', 'priority', 'audience', 'publish_date'])
        );

        return $notice->fresh(['creator:id,name']);
    }

    public function update(NoticeBoard $notice, array $data, User $user): NoticeBoard
    {
        if (($data['status'] ?? null) === 'published') {
            if ($notice->approval_status === CmsApprovalStatus::PendingReview->value) {
                throw new \RuntimeException('Notice must be approved before publishing.');
            }
            if ($notice->approval_status === CmsApprovalStatus::Rejected->value) {
                throw new \RuntimeException('Rejected notices cannot be published.');
            }
        }

        $old = $notice->only(['title', 'status', 'priority', 'audience', 'publish_date', 'approval_status']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'published' && empty($notice->published_at)) {
            $data['published_at'] = now();
        }

        $notice->update($data);

        $this->auditLogService->log(
            $notice->getTable(),
            $notice->getKey(),
            'updated',
            'Notice updated',
            $old,
            $notice->fresh()->only(['title', 'status', 'priority', 'audience', 'publish_date', 'approval_status'])
        );

        return $notice->fresh(['creator:id,name', 'approver:id,name', 'editor:id,name']);
    }

    public function delete(NoticeBoard $notice, User $user): void
    {
        $this->auditLogService->log(
            $notice->getTable(),
            $notice->getKey(),
            'deleted',
            'Notice soft-deleted',
            $notice->only(['title', 'status']),
            null
        );

        $notice->delete();
    }

    public function publishedQueryForUser(?User $user = null): Builder
    {
        $query = NoticeBoard::query()
            ->where('status', 'published')
            ->whereDate('publish_date', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', CmsApprovalStatus::Approved->value);
            });

        if ($user) {
            $audience = $this->audienceKeyForUser($user);

            $query->where(function (Builder $q) use ($user, $audience) {
                $q->where('audience', 'all')
                    ->orWhereNull('audience');

                if ($audience) {
                    $q->orWhere('audience', $audience);
                }

                $q->orWhere(function (Builder $sub) use ($user) {
                    $sub->where('audience', 'custom')
                        ->whereJsonContains('audience_ids', $user->id);
                });
            });
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderByDesc('publish_date');
    }

    public function getPublishedForUser(?User $user = null): Collection
    {
        return $this->publishedQueryForUser($user)->get();
    }

    public function enrichForPortal(NoticeBoard $notice): array
    {
        $data = $notice->toArray();
        $data['attachment_url'] = $this->firstAttachmentUrl($notice);

        return $data;
    }

    public function enrichCollectionForPortal(Collection $notices): array
    {
        return $notices->map(fn (NoticeBoard $notice) => $this->enrichForPortal($notice))->values()->all();
    }

    public function uploadAttachment(UploadedFile $file, string $type = 'pdf'): array
    {
        $payload = $this->mediaService->upload($file, $type, 'notices');

        if (!$payload) {
            throw new \RuntimeException('Attachment upload failed.');
        }

        return $payload;
    }

    public function submitForReview(NoticeBoard $notice, ?User $user = null, ?string $comment = null): NoticeBoard
    {
        return $this->approvalService->submit($notice, $user, $comment);
    }

    public function approve(NoticeBoard $notice, User $user, ?string $comment = null): NoticeBoard
    {
        return $this->approvalService->approve($notice, $user, $comment);
    }

    public function reject(NoticeBoard $notice, User $user, string $reason, ?string $comment = null): NoticeBoard
    {
        return $this->approvalService->reject($notice, $user, $reason, $comment);
    }

    public function publish(NoticeBoard $notice, User $user): NoticeBoard
    {
        if ($notice->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Notice must be approved before publishing.');
        }

        if ($notice->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected notices cannot be published.');
        }

        return $this->update($notice, [
            'status' => 'published',
            'published_at' => now(),
            'approval_status' => $notice->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function unpublish(NoticeBoard $notice, User $user): NoticeBoard
    {
        return $this->update($notice, ['status' => 'draft'], $user);
    }

    public function trackView(NoticeBoard $notice, ?User $user = null): void
    {
        $this->analyticsService->track(
            $notice->getTable(),
            $notice->getKey(),
            \Modules\Cms\app\Enums\CmsContentEventType::View,
            $user?->id
        );
    }

    protected function audienceKeyForUser(User $user): ?string
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return null;
        }

        return match (true) {
            $user->hasRole('student') => 'students',
            $user->hasRole('teacher') => 'teachers',
            $user->hasRole('employee') => 'staff',
            $user->hasRole('guardian') => 'parents',
            default => null,
        };
    }

    protected function firstAttachmentUrl(NoticeBoard $notice): ?string
    {
        $attachments = $notice->attachments ?? [];

        if (empty($attachments)) {
            return null;
        }

        $first = $attachments[0];

        if (!empty($first['url'])) {
            return $first['url'];
        }

        if (!empty($first['path'])) {
            return $this->mediaService->url($first['path']);
        }

        return null;
    }
}
