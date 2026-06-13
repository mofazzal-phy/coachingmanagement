<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\Page;

class PageService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
        protected CmsAnalyticsService $analyticsService
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Page::with(['creator:id,name', 'author:id,name', 'approver:id,name'])
            ->search($filters['search'] ?? null);

        if (!empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'status', 'template', 'is_featured',
        ])->filter()->toArray())
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?Page
    {
        return Page::with(['creator:id,name', 'author:id,name', 'approver:id,name', 'editor:id,name'])
            ->find($id);
    }

    public function findBySlug(string $slug, ?string $contentType = null): ?Page
    {
        $query = Page::with(['creator:id,name', 'author:id,name'])
            ->where('slug', $slug);

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return $query->first();
    }

    public function getPublished(?string $contentType = null): Collection
    {
        $query = $this->publishedQuery($contentType);

        return $query->get();
    }

    public function publishedQuery(?string $contentType = null): Builder
    {
        $query = Page::query()
            ->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', CmsApprovalStatus::Approved->value);
            })
            ->publishedNow();

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function create(array $data, User $user): Page
    {
        $contentType = $data['content_type'] ?? 'page';
        $data['content_type'] = $contentType;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['author_id'] = $data['author_id'] ?? $user->id;

        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        if (!empty($data['content']) && empty($data['reading_time'])) {
            $data['reading_time'] = $this->estimateReadingTime($data['content']);
        }

        $page = Page::create($data);

        $this->auditLogService->log(
            $page->getTable(),
            $page->getKey(),
            'created',
            'Page created',
            null,
            $page->only(['title', 'slug', 'content_type', 'status'])
        );

        return $this->findById($page->getKey());
    }

    public function update(Page $page, array $data, User $user): Page
    {
        if (($data['status'] ?? null) === 'published') {
            if ($page->approval_status === CmsApprovalStatus::PendingReview->value) {
                throw new \RuntimeException('Content must be approved before publishing.');
            }
            if ($page->approval_status === CmsApprovalStatus::Rejected->value) {
                throw new \RuntimeException('Rejected content cannot be published.');
            }
        }

        $old = $page->only(['title', 'slug', 'content_type', 'status', 'approval_status']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'published' && empty($page->published_at)) {
            $data['published_at'] = now();
        }

        if (!empty($data['content'])) {
            $data['reading_time'] = $data['reading_time'] ?? $this->estimateReadingTime($data['content']);
        }

        $page->update($data);

        $this->auditLogService->log(
            $page->getTable(),
            $page->getKey(),
            'updated',
            'Page updated',
            $old,
            $page->fresh()->only(['title', 'slug', 'content_type', 'status', 'approval_status'])
        );

        return $this->findById($page->getKey());
    }

    public function delete(Page $page, User $user): void
    {
        $this->auditLogService->log(
            $page->getTable(),
            $page->getKey(),
            'deleted',
            'Page soft-deleted',
            $page->only(['title', 'slug', 'content_type', 'status']),
            null
        );

        $page->delete();
    }

    public function submitForReview(Page $page, ?User $user = null, ?string $comment = null): Page
    {
        $this->approvalService->submit($page, $user, $comment);

        return $this->findById($page->getKey());
    }

    public function approve(Page $page, User $user, ?string $comment = null): Page
    {
        $this->approvalService->approve($page, $user, $comment);

        return $this->findById($page->getKey());
    }

    public function reject(Page $page, User $user, string $reason, ?string $comment = null): Page
    {
        $this->approvalService->reject($page, $user, $reason, $comment);

        return $this->findById($page->getKey());
    }

    public function publish(Page $page, User $user): Page
    {
        if ($page->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Content must be approved before publishing.');
        }

        if ($page->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected content cannot be published.');
        }

        return $this->update($page, [
            'status' => 'published',
            'published_at' => now(),
            'approval_status' => $page->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function unpublish(Page $page, User $user): Page
    {
        return $this->update($page, ['status' => 'draft'], $user);
    }

    public function enrich(Page $page): array
    {
        $data = $page->toArray();
        $data['featured_image_url'] = $this->mediaService->url($page->featured_image);

        return $data;
    }

    public function enrichCollection(Collection $pages): array
    {
        return $pages->map(fn (Page $page) => $this->enrich($page))->values()->all();
    }

    public function storeValidationRules(?string $id = null, string $contentType = 'page'): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pages', 'slug')
                    ->where('content_type', $contentType)
                    ->ignore($id),
            ],
            'content' => 'required|string',
            'content_type' => 'sometimes|in:page,blog',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'og_image' => 'nullable|string',
            'canonical_url' => 'nullable|string|max:255',
            'template' => 'sometimes|string',
            'sections' => 'nullable|array',
            'tags' => 'nullable|array',
            'reading_time' => 'nullable|integer|min:1',
            'author_id' => 'nullable|uuid|exists:users,id',
            'status' => 'sometimes|in:draft,published',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'published_at' => 'nullable|date',
        ];
    }

    public function updateValidationRules(string $id, ?string $contentType = null): array
    {
        $existing = Page::find($id);
        $type = $contentType ?? $existing?->content_type ?? 'page';
        $rules = $this->storeValidationRules($id, $type);
        foreach ($rules as $key => $rule) {
            if ($key === 'title' || $key === 'slug' || $key === 'content') {
                if (is_array($rule)) {
                    $rules[$key][0] = 'sometimes';
                } else {
                    $rules[$key] = 'sometimes|' . $rule;
                }
            }
        }

        return $rules;
    }

    protected function estimateReadingTime(string $content): int
    {
        $words = str_word_count(strip_tags($content));

        return max(1, (int) ceil($words / 200));
    }
}
