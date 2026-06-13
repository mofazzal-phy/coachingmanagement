<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\Testimonial;

class TestimonialService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Testimonial::with(['approver:id,name', 'editor:id,name'])
            ->search($filters['search'] ?? null);

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'status', 'is_featured',
        ])->filter()->toArray())
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?Testimonial
    {
        return Testimonial::with(['approver:id,name', 'editor:id,name'])->find($id);
    }

    public function publishedQuery(): Builder
    {
        return Testimonial::query()
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

    public function create(array $data, User $user): Testimonial
    {
        $data['updated_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'inactive';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['rating'] = $data['rating'] ?? 5;

        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $testimonial = Testimonial::create($data);

        $this->auditLogService->log(
            $testimonial->getTable(),
            $testimonial->getKey(),
            'created',
            'Testimonial created',
            null,
            $testimonial->only(['name', 'status', 'rating'])
        );

        return $this->findById($testimonial->getKey());
    }

    public function update(Testimonial $testimonial, array $data, User $user): Testimonial
    {
        if (($data['status'] ?? null) === 'active') {
            $this->assertCanActivate($testimonial);
        }

        $old = $testimonial->only(['name', 'status', 'approval_status', 'rating']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'active' && empty($testimonial->published_at)) {
            $data['published_at'] = now();
        }

        $testimonial->update($data);

        $this->auditLogService->log(
            $testimonial->getTable(),
            $testimonial->getKey(),
            'updated',
            'Testimonial updated',
            $old,
            $testimonial->fresh()->only(['name', 'status', 'approval_status', 'rating'])
        );

        return $this->findById($testimonial->getKey());
    }

    public function delete(Testimonial $testimonial, User $user): void
    {
        $this->auditLogService->log(
            $testimonial->getTable(),
            $testimonial->getKey(),
            'deleted',
            'Testimonial soft-deleted',
            $testimonial->only(['name', 'status']),
            null
        );

        $testimonial->delete();
    }

    public function submitForReview(Testimonial $testimonial, ?User $user = null, ?string $comment = null): Testimonial
    {
        $this->approvalService->submit($testimonial, $user, $comment);

        return $this->findById($testimonial->getKey());
    }

    public function approve(Testimonial $testimonial, User $user, ?string $comment = null): Testimonial
    {
        $this->approvalService->approve($testimonial, $user, $comment);

        return $this->findById($testimonial->getKey());
    }

    public function reject(Testimonial $testimonial, User $user, string $reason, ?string $comment = null): Testimonial
    {
        $this->approvalService->reject($testimonial, $user, $reason, $comment);

        return $this->findById($testimonial->getKey());
    }

    public function activate(Testimonial $testimonial, User $user): Testimonial
    {
        $this->assertCanActivate($testimonial);

        return $this->update($testimonial, [
            'status' => 'active',
            'published_at' => $testimonial->published_at ?? now(),
            'approval_status' => $testimonial->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function deactivate(Testimonial $testimonial, User $user): Testimonial
    {
        return $this->update($testimonial, ['status' => 'inactive'], $user);
    }

    public function enrich(Testimonial $testimonial): array
    {
        $data = $testimonial->toArray();
        $data['image_url'] = $this->mediaService->url($testimonial->image);

        return $data;
    }

    public function enrichCollection(Collection $items): array
    {
        return $items->map(fn (Testimonial $item) => $this->enrich($item))->values()->all();
    }

    public function storeValidationRules(?string $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|string',
            'rating' => 'sometimes|integer|min:1|max:5',
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
            'name' => 'sometimes|string|max:255',
            'designation' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'content' => 'sometimes|string',
            'image' => 'nullable|string',
            'rating' => 'sometimes|integer|min:1|max:5',
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

    protected function assertCanActivate(Testimonial $testimonial): void
    {
        if ($testimonial->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Testimonial must be approved before activating.');
        }

        if ($testimonial->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected testimonials cannot be activated.');
        }
    }
}
