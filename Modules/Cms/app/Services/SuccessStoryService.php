<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\SuccessStory;

class SuccessStoryService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SuccessStory::with(['approver:id,name', 'editor:id,name', 'schoolClass:id,name', 'batch:id,name'])
            ->search($filters['search'] ?? null);

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'status', 'is_featured', 'class_id', 'batch_id',
        ])->filter()->toArray())
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?SuccessStory
    {
        return SuccessStory::with([
            'approver:id,name',
            'editor:id,name',
            'schoolClass:id,name',
            'batch:id,name',
        ])->find($id);
    }

    public function findBySlug(string $slug): ?SuccessStory
    {
        return SuccessStory::with(['schoolClass:id,name', 'batch:id,name'])
            ->where('slug', $slug)
            ->first();
    }

    public function publishedQuery(): Builder
    {
        return SuccessStory::query()
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

    public function create(array $data, User $user): SuccessStory
    {
        $data['updated_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'inactive';
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $story = SuccessStory::create($data);

        $this->auditLogService->log(
            $story->getTable(),
            $story->getKey(),
            'created',
            'Success story created',
            null,
            $story->only(['title', 'slug', 'status'])
        );

        return $this->findById($story->getKey());
    }

    public function update(SuccessStory $story, array $data, User $user): SuccessStory
    {
        if (($data['status'] ?? null) === 'active') {
            $this->assertCanActivate($story);
        }

        $old = $story->only(['title', 'slug', 'status', 'approval_status']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'active' && empty($story->published_at)) {
            $data['published_at'] = now();
        }

        $story->update($data);

        $this->auditLogService->log(
            $story->getTable(),
            $story->getKey(),
            'updated',
            'Success story updated',
            $old,
            $story->fresh()->only(['title', 'slug', 'status', 'approval_status'])
        );

        return $this->findById($story->getKey());
    }

    public function delete(SuccessStory $story, User $user): void
    {
        $this->auditLogService->log(
            $story->getTable(),
            $story->getKey(),
            'deleted',
            'Success story soft-deleted',
            $story->only(['title', 'slug', 'status']),
            null
        );

        $story->delete();
    }

    public function submitForReview(SuccessStory $story, ?User $user = null, ?string $comment = null): SuccessStory
    {
        $this->approvalService->submit($story, $user, $comment);

        return $this->findById($story->getKey());
    }

    public function approve(SuccessStory $story, User $user, ?string $comment = null): SuccessStory
    {
        $this->approvalService->approve($story, $user, $comment);

        return $this->findById($story->getKey());
    }

    public function reject(SuccessStory $story, User $user, string $reason, ?string $comment = null): SuccessStory
    {
        $this->approvalService->reject($story, $user, $reason, $comment);

        return $this->findById($story->getKey());
    }

    public function activate(SuccessStory $story, User $user): SuccessStory
    {
        $this->assertCanActivate($story);

        return $this->update($story, [
            'status' => 'active',
            'published_at' => $story->published_at ?? now(),
            'approval_status' => $story->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function deactivate(SuccessStory $story, User $user): SuccessStory
    {
        return $this->update($story, ['status' => 'inactive'], $user);
    }

    public function enrich(SuccessStory $story): array
    {
        $data = $story->toArray();
        $data['featured_image_url'] = $this->mediaService->url($story->featured_image);
        $data['gallery_image_urls'] = collect($story->gallery_images ?? [])
            ->map(fn ($path) => $this->mediaService->url($path))
            ->filter()
            ->values()
            ->all();

        return $data;
    }

    public function enrichCollection(Collection $items): array
    {
        return $items->map(fn (SuccessStory $item) => $this->enrich($item))->values()->all();
    }

    public function storeValidationRules(?string $id = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('success_stories', 'slug')->ignore($id),
            ],
            'excerpt' => 'nullable|string',
            'story' => 'required|string',
            'student_name' => 'nullable|string|max:255',
            'exam_name' => 'nullable|string|max:255',
            'achievement_year' => 'nullable|string|max:20',
            'result_summary' => 'nullable|string|max:255',
            'featured_image' => 'nullable|string',
            'gallery_images' => 'nullable|array',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'batch_id' => 'nullable|uuid|exists:batches,id',
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

    public function updateValidationRules(?string $id = null): array
    {
        $rules = $this->storeValidationRules($id);
        $rules['title'] = 'sometimes|string|max:255';
        $rules['slug'] = [
            'sometimes',
            'string',
            'max:255',
            Rule::unique('success_stories', 'slug')->ignore($id),
        ];
        $rules['story'] = 'sometimes|string';

        return $rules;
    }

    protected function assertCanActivate(SuccessStory $story): void
    {
        if ($story->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Success story must be approved before activating.');
        }

        if ($story->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected success stories cannot be activated.');
        }
    }
}
