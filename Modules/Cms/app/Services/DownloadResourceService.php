<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\DownloadResource;

class DownloadResourceService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = DownloadResource::with(['approver:id,name', 'editor:id,name'])
            ->search($filters['search'] ?? null);

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'status', 'category', 'access_level', 'is_featured',
        ])->filter()->toArray())
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?DownloadResource
    {
        return DownloadResource::with(['approver:id,name', 'editor:id,name'])->find($id);
    }

    public function publishedQuery(): Builder
    {
        return DownloadResource::query()
            ->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', CmsApprovalStatus::Approved->value);
            })
            ->publishedNow()
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderBy('sort_order');
    }

    public function getPublished(): Collection
    {
        return $this->publishedQuery()->get();
    }

    public function listForUser(?User $user = null): Collection
    {
        $query = $this->publishedQuery();

        if (!$user) {
            return $query->where('access_level', 'public')->get();
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $query->get();
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('access_level', 'public')
                ->orWhere('access_level', 'authenticated');

            if ($user->hasAnyRole(['teacher', 'employee'])) {
                $q->orWhere('access_level', 'staff');
            }
        })->get();
    }

    public function userCanAccess(DownloadResource $resource, ?User $user = null): bool
    {
        if ($resource->status !== 'active') {
            return false;
        }

        if ($resource->approval_status && $resource->approval_status !== CmsApprovalStatus::Approved->value) {
            return false;
        }

        if (!$resource->isVisibleNow()) {
            return false;
        }

        if ($resource->access_level === 'public') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($resource->access_level === 'authenticated') {
            return true;
        }

        if ($resource->access_level === 'staff' && $user->hasAnyRole(['teacher', 'employee'])) {
            return true;
        }

        return false;
    }

    public function create(array $data, User $user): DownloadResource
    {
        $data['updated_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'inactive';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['category'] = $data['category'] ?? 'other';
        $data['access_level'] = $data['access_level'] ?? 'authenticated';

        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $resource = DownloadResource::create($data);

        $this->auditLogService->log(
            $resource->getTable(),
            $resource->getKey(),
            'created',
            'Download resource created',
            null,
            $resource->only(['title', 'slug', 'category', 'status'])
        );

        return $this->findById($resource->getKey());
    }

    public function update(DownloadResource $resource, array $data, User $user): DownloadResource
    {
        if (($data['status'] ?? null) === 'active') {
            $this->assertCanActivate($resource);
        }

        $old = $resource->only(['title', 'slug', 'status', 'category', 'approval_status']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'active' && empty($resource->published_at)) {
            $data['published_at'] = now();
        }

        $resource->update($data);

        $this->auditLogService->log(
            $resource->getTable(),
            $resource->getKey(),
            'updated',
            'Download resource updated',
            $old,
            $resource->fresh()->only(['title', 'slug', 'status', 'category', 'approval_status'])
        );

        return $this->findById($resource->getKey());
    }

    public function delete(DownloadResource $resource, User $user): void
    {
        $this->auditLogService->log(
            $resource->getTable(),
            $resource->getKey(),
            'deleted',
            'Download resource soft-deleted',
            $resource->only(['title', 'slug', 'status']),
            null
        );

        $resource->delete();
    }

    public function submitForReview(DownloadResource $resource, ?User $user = null, ?string $comment = null): DownloadResource
    {
        $this->approvalService->submit($resource, $user, $comment);

        return $this->findById($resource->getKey());
    }

    public function approve(DownloadResource $resource, User $user, ?string $comment = null): DownloadResource
    {
        $this->approvalService->approve($resource, $user, $comment);

        return $this->findById($resource->getKey());
    }

    public function reject(DownloadResource $resource, User $user, string $reason, ?string $comment = null): DownloadResource
    {
        $this->approvalService->reject($resource, $user, $reason, $comment);

        return $this->findById($resource->getKey());
    }

    public function activate(DownloadResource $resource, User $user): DownloadResource
    {
        $this->assertCanActivate($resource);

        return $this->update($resource, [
            'status' => 'active',
            'published_at' => $resource->published_at ?? now(),
            'approval_status' => $resource->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function deactivate(DownloadResource $resource, User $user): DownloadResource
    {
        return $this->update($resource, ['status' => 'inactive'], $user);
    }

    public function recordDownload(DownloadResource $resource, ?User $user = null): void
    {
        $resource->recordDownload($user?->id);
    }

    public function enrich(DownloadResource $resource): array
    {
        $data = $resource->toArray();
        $data['file_url'] = $this->mediaService->url($resource->file_path);

        return $data;
    }

    public function enrichCollection(Collection $items): array
    {
        return $items->map(fn (DownloadResource $item) => $this->enrich($item))->values()->all();
    }

    public function storeValidationRules(?string $id = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('download_resources', 'slug')->ignore($id),
            ],
            'category' => 'sometimes|in:brochure,form,syllabus,policy,other',
            'file_path' => 'required|string',
            'file_size' => 'nullable|integer|min:0',
            'mime_type' => 'nullable|string|max:120',
            'access_level' => 'sometimes|in:public,authenticated,staff',
            'sort_order' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'published_at' => 'nullable|date',
        ];
    }

    public function updateValidationRules(?string $id = null): array
    {
        $rules = $this->storeValidationRules($id);
        $rules['title'] = 'sometimes|string|max:255';
        $rules['slug'] = [
            'sometimes',
            'string',
            'max:255',
            Rule::unique('download_resources', 'slug')->ignore($id),
        ];
        $rules['file_path'] = 'sometimes|string';

        return $rules;
    }

    protected function assertCanActivate(DownloadResource $resource): void
    {
        if ($resource->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Download resource must be approved before activating.');
        }

        if ($resource->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected download resources cannot be activated.');
        }
    }
}
