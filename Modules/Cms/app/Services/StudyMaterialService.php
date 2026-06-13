<?php

namespace Modules\Cms\app\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\StudyMaterial;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Student\app\Models\Student;

class StudyMaterialService
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsApprovalService $approvalService,
    ) {}

    public function listForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = StudyMaterial::with([
            'approver:id,name',
            'editor:id,name',
            'schoolClass:id,name',
            'subject:id,name',
            'batch:id,name',
            'academicSession:id,name',
        ])->search($filters['search'] ?? null);

        if (!empty($filters['approval_status'])) {
            if ($filters['approval_status'] === 'none') {
                $query->whereNull('approval_status');
            } else {
                $query->where('approval_status', $filters['approval_status']);
            }
        }

        $query->filter(collect($filters)->only([
            'status', 'media_type', 'access_level', 'is_featured',
            'class_id', 'subject_id', 'batch_id', 'academic_session_id',
        ])->filter()->toArray())
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?StudyMaterial
    {
        return StudyMaterial::with([
            'approver:id,name',
            'editor:id,name',
            'schoolClass:id,name',
            'subject:id,name',
            'batch:id,name',
            'academicSession:id,name',
        ])->find($id);
    }

    public function publishedQuery(): Builder
    {
        return StudyMaterial::query()
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

    public function listForStudent(Student $student): Collection
    {
        $enrollments = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['subjects:id'])
            ->get();

        $batchIds = $enrollments->pluck('batch_id')->filter()->unique()->values();
        $classIds = $enrollments->pluck('enrolled_class_id')->filter()->unique()->values();
        $sessionIds = $enrollments->pluck('academic_session_id')->filter()->unique()->values();
        $subjectIds = $enrollments->flatMap(fn ($enrollment) => $enrollment->subjects->pluck('id'))->unique()->values();

        return $this->publishedQuery()
            ->whereIn('access_level', ['public', 'student'])
            ->where(function (Builder $query) use ($batchIds, $classIds, $sessionIds, $subjectIds) {
                $query->where(function (Builder $global) {
                    $global->whereNull('batch_id')
                        ->whereNull('class_id')
                        ->whereNull('subject_id')
                        ->whereNull('academic_session_id');
                })->orWhere(function (Builder $scoped) use ($batchIds, $classIds, $sessionIds, $subjectIds) {
                    $scoped->where(function (Builder $q) use ($batchIds) {
                        $q->whereNull('batch_id');
                        if ($batchIds->isNotEmpty()) {
                            $q->orWhereIn('batch_id', $batchIds);
                        }
                    })->where(function (Builder $q) use ($classIds) {
                        $q->whereNull('class_id');
                        if ($classIds->isNotEmpty()) {
                            $q->orWhereIn('class_id', $classIds);
                        }
                    })->where(function (Builder $q) use ($sessionIds) {
                        $q->whereNull('academic_session_id');
                        if ($sessionIds->isNotEmpty()) {
                            $q->orWhereIn('academic_session_id', $sessionIds);
                        }
                    })->where(function (Builder $q) use ($subjectIds) {
                        $q->whereNull('subject_id');
                        if ($subjectIds->isNotEmpty()) {
                            $q->orWhereIn('subject_id', $subjectIds);
                        }
                    });
                });
            })
            ->with(['subject:id,name', 'batch:id,name', 'schoolClass:id,name'])
            ->get();
    }

    public function studentCanAccess(StudyMaterial $material, Student $student): bool
    {
        return $this->listForStudent($student)->contains('id', $material->id);
    }

    public function create(array $data, User $user): StudyMaterial
    {
        $data['updated_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'inactive';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['access_level'] = $data['access_level'] ?? 'student';
        $data['media_type'] = $data['media_type'] ?? 'pdf';

        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $material = StudyMaterial::create($data);

        $this->auditLogService->log(
            $material->getTable(),
            $material->getKey(),
            'created',
            'Study material created',
            null,
            $material->only(['title', 'media_type', 'access_level', 'status'])
        );

        return $this->findById($material->getKey());
    }

    public function update(StudyMaterial $material, array $data, User $user): StudyMaterial
    {
        if (($data['status'] ?? null) === 'active') {
            $this->assertCanActivate($material);
        }

        $old = $material->only(['title', 'status', 'approval_status', 'access_level']);
        $data['updated_by'] = $user->id;

        if (($data['status'] ?? null) === 'active' && empty($material->published_at)) {
            $data['published_at'] = now();
        }

        $material->update($data);

        $this->auditLogService->log(
            $material->getTable(),
            $material->getKey(),
            'updated',
            'Study material updated',
            $old,
            $material->fresh()->only(['title', 'status', 'approval_status', 'access_level'])
        );

        return $this->findById($material->getKey());
    }

    public function delete(StudyMaterial $material, User $user): void
    {
        $this->auditLogService->log(
            $material->getTable(),
            $material->getKey(),
            'deleted',
            'Study material soft-deleted',
            $material->only(['title', 'status']),
            null
        );

        $material->delete();
    }

    public function submitForReview(StudyMaterial $material, ?User $user = null, ?string $comment = null): StudyMaterial
    {
        $this->approvalService->submit($material, $user, $comment);

        return $this->findById($material->getKey());
    }

    public function approve(StudyMaterial $material, User $user, ?string $comment = null): StudyMaterial
    {
        $this->approvalService->approve($material, $user, $comment);

        return $this->findById($material->getKey());
    }

    public function reject(StudyMaterial $material, User $user, string $reason, ?string $comment = null): StudyMaterial
    {
        $this->approvalService->reject($material, $user, $reason, $comment);

        return $this->findById($material->getKey());
    }

    public function activate(StudyMaterial $material, User $user): StudyMaterial
    {
        $this->assertCanActivate($material);

        return $this->update($material, [
            'status' => 'active',
            'published_at' => $material->published_at ?? now(),
            'approval_status' => $material->approval_status ?: CmsApprovalStatus::Approved->value,
        ], $user);
    }

    public function deactivate(StudyMaterial $material, User $user): StudyMaterial
    {
        return $this->update($material, ['status' => 'inactive'], $user);
    }

    public function recordDownload(StudyMaterial $material, ?User $user = null): void
    {
        $material->recordDownload($user?->id);
    }

    public function enrich(StudyMaterial $material): array
    {
        $data = $material->toArray();
        $data['file_url'] = $this->mediaService->url($material->file_path);

        return $data;
    }

    public function enrichCollection(Collection $items): array
    {
        return $items->map(fn (StudyMaterial $item) => $this->enrich($item))->values()->all();
    }

    public function storeValidationRules(?string $id = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('study_materials', 'slug')->ignore($id),
            ],
            'file_path' => 'required|string',
            'file_size' => 'nullable|integer|min:0',
            'mime_type' => 'nullable|string|max:120',
            'media_type' => 'required|in:pdf,video,image,document',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'batch_id' => 'nullable|uuid|exists:batches,id',
            'academic_session_id' => 'nullable|uuid|exists:academic_sessions,id',
            'access_level' => 'sometimes|in:public,student,teacher,staff',
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
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('study_materials', 'slug')->ignore($id),
            ],
            'file_path' => 'sometimes|string',
            'file_size' => 'nullable|integer|min:0',
            'mime_type' => 'nullable|string|max:120',
            'media_type' => 'sometimes|in:pdf,video,image,document',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'batch_id' => 'nullable|uuid|exists:batches,id',
            'academic_session_id' => 'nullable|uuid|exists:academic_sessions,id',
            'access_level' => 'sometimes|in:public,student,teacher,staff',
            'sort_order' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'published_at' => 'nullable|date',
        ];
    }

    protected function assertCanActivate(StudyMaterial $material): void
    {
        if ($material->approval_status === CmsApprovalStatus::PendingReview->value) {
            throw new \RuntimeException('Study material must be approved before activating.');
        }

        if ($material->approval_status === CmsApprovalStatus::Rejected->value) {
            throw new \RuntimeException('Rejected study materials cannot be activated.');
        }
    }
}
