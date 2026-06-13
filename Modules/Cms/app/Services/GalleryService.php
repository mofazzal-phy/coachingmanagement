<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\Gallery;

class GalleryService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Gallery::with(['approver:id,name', 'editor:id,name'])
            ->search($filters['search'] ?? null);

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'type', 'status', 'category', 'album', 'is_featured',
        ])->filter()->toArray())
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?Gallery
    {
        return Gallery::with(['approver:id,name', 'editor:id,name'])->find($id);
    }

    public function publishedQuery(?string $category = null): Builder
    {
        $query = Gallery::query()
            ->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', CmsApprovalStatus::Approved->value);
            })
            ->publishedNow();

        if ($category) {
            $query->where('category', $category);
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderBy('sort_order');
    }

    public function getPublished(?string $category = null): Collection
    {
        return $this->publishedQuery($category)->get();
    }

    public function create(array $data, User $user): Gallery
    {
        $data['updated_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'inactive';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['category'] = $data['category'] ?? 'general';

        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $gallery = Gallery::create($data);

        $this->auditLogService->log(
            $gallery->getTable(),
            $gallery->getKey(),
            'created',
            'Gallery item created',
            null,
            $gallery->only(['title', 'type', 'category', 'status'])
        );

        return $this->findById($gallery->getKey());
    }

    public function update(Gallery $gallery, array $data, User $user): Gallery
    {
        if (($data['status'] ?? null) === 'active') {
            $this->assertCanActivate($gallery);
        }

        $old = $gallery->only(['title', 'type', 'category', 'status', 'approval_status']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'active' && empty($gallery->published_at)) {
            $data['published_at'] = now();
        }

        $gallery->update($data);

        $this->auditLogService->log(
            $gallery->getTable(),
            $gallery->getKey(),
            'updated',
            'Gallery item updated',
            $old,
            $gallery->fresh()->only(['title', 'type', 'category', 'status', 'approval_status'])
        );

        return $this->findById($gallery->getKey());
    }

    public function delete(Gallery $gallery, User $user): void
    {
        $this->auditLogService->log(
            $gallery->getTable(),
            $gallery->getKey(),
            'deleted',
            'Gallery item soft-deleted',
            $gallery->only(['title', 'type', 'category', 'status']),
            null
        );

        $gallery->delete();
    }

    public function submitForReview(Gallery $gallery, ?User $user = null, ?string $comment = null): Gallery
    {
        $this->approvalService->submit($gallery, $user, $comment);

        return $this->findById($gallery->getKey());
    }

    public function approve(Gallery $gallery, User $user, ?string $comment = null): Gallery
    {
        $this->approvalService->approve($gallery, $user, $comment);

        return $this->findById($gallery->getKey());
    }

    public function reject(Gallery $gallery, User $user, string $reason, ?string $comment = null): Gallery
    {
        $this->approvalService->reject($gallery, $user, $reason, $comment);

        return $this->findById($gallery->getKey());
    }

    public function activate(Gallery $gallery, User $user): Gallery
    {
        $this->assertCanActivate($gallery);

        return $this->update($gallery, [
            'status' => 'active',
            'published_at' => $gallery->published_at ?? now(),
            'approval_status' => $gallery->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function deactivate(Gallery $gallery, User $user): Gallery
    {
        return $this->update($gallery, ['status' => 'inactive'], $user);
    }

    public function enrich(Gallery $gallery): array
    {
        $data = $gallery->toArray();
        $data['file_url'] = $this->mediaService->url($gallery->file);
        $data['thumbnail_url'] = $this->mediaService->url($gallery->thumbnail);

        return $data;
    }

    public function enrichCollection(Collection $items): array
    {
        return $items->map(fn (Gallery $item) => $this->enrich($item))->values()->all();
    }

    public function storeValidationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:image,video',
            'category' => 'sometimes|in:general,achievement',
            'album' => 'nullable|string|max:255',
            'file' => 'required|string',
            'thumbnail' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'published_at' => 'nullable|date',
            'seo_keywords' => 'nullable|string|max:255',
            'og_image' => 'nullable|string',
            'canonical_url' => 'nullable|string|max:255',
        ];
    }

    public function updateValidationRules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:image,video',
            'category' => 'sometimes|in:general,achievement',
            'album' => 'nullable|string|max:255',
            'file' => 'sometimes|string',
            'thumbnail' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'published_at' => 'nullable|date',
            'seo_keywords' => 'nullable|string|max:255',
            'og_image' => 'nullable|string',
            'canonical_url' => 'nullable|string|max:255',
        ];
    }

    protected function assertCanActivate(Gallery $gallery): void
    {
        if ($gallery->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Gallery item must be approved before activating.');
        }

        if ($gallery->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected gallery items cannot be activated.');
        }
    }
}
