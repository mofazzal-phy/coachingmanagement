<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\StudyMaterial;
use Modules\Cms\app\Services\StudyMaterialService;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Student\app\Models\Student;

class StudyMaterialController extends BaseApiController
{
    public function __construct(
        protected StudyMaterialService $studyMaterialService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $paginator = $this->studyMaterialService->listForAdmin([
            'search' => $request->search,
            'status' => $request->status,
            'media_type' => $request->media_type,
            'access_level' => $request->access_level,
            'is_featured' => $request->is_featured,
            'approval_status' => $request->approval_status,
            'class_id' => $request->class_id,
            'subject_id' => $request->subject_id,
            'batch_id' => $request->batch_id,
            'academic_session_id' => $request->academic_session_id,
        ], $perPage);

        return $this->paginatedResponse(
            $paginator->through(fn (StudyMaterial $item) => $this->studyMaterialService->enrich($item))
        );
    }

    public function published(): JsonResponse
    {
        $items = $this->studyMaterialService->getPublished();

        return $this->success($this->studyMaterialService->enrichCollection($items));
    }

    public function show(string $id): JsonResponse
    {
        $material = $this->studyMaterialService->findById($id);

        if (!$material) {
            return $this->notFound();
        }

        return $this->success($this->studyMaterialService->enrich($material));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->studyMaterialService->storeValidationRules());
        $material = $this->studyMaterialService->create($validated, auth()->user());

        return $this->created($this->studyMaterialService->enrich($material));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        $validated = $request->validate($this->studyMaterialService->updateValidationRules($id));

        try {
            $material = $this->studyMaterialService->update($material, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->studyMaterialService->enrich($material));
    }

    public function destroy(string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        $this->studyMaterialService->delete($material, auth()->user());

        return $this->noContent();
    }

    public function download(string $id): JsonResponse
    {
        $material = $this->studyMaterialService->findById($id);

        if (!$material) {
            return $this->notFound();
        }

        $user = auth()->user();

        if (!$this->canAccessStudyMaterial($material, $user)) {
            return $this->forbidden('You do not have access to this study material');
        }

        $this->studyMaterialService->recordDownload($material, $user);

        return $this->success($this->studyMaterialService->enrich($material->fresh()));
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        try {
            $material = $this->studyMaterialService->submitForReview($material, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->studyMaterialService->enrich($material), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        try {
            $material = $this->studyMaterialService->approve($material, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->studyMaterialService->enrich($material), 'Study material approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        try {
            $material = $this->studyMaterialService->reject(
                $material,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->studyMaterialService->enrich($material), 'Study material rejected');
    }

    public function activate(string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        try {
            $material = $this->studyMaterialService->activate($material, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->studyMaterialService->enrich($material), 'Study material activated');
    }

    public function deactivate(string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        $material = $this->studyMaterialService->deactivate($material, auth()->user());

        return $this->success($this->studyMaterialService->enrich($material), 'Study material deactivated');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $material = StudyMaterial::find($id);

        if (!$material) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($material->getTable(), $material->getKey(), $perPage)
        );
    }

    protected function canAccessStudyMaterial(StudyMaterial $material, ?User $user): bool
    {
        if ($user?->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($material->status !== 'active') {
            return false;
        }

        if ($material->approval_status && $material->approval_status !== CmsApprovalStatus::Approved->value) {
            return false;
        }

        if (!$material->isVisibleNow()) {
            return false;
        }

        if ($material->access_level === 'public') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($material->access_level === 'teacher' && $user->hasAnyRole(['teacher'])) {
            return true;
        }

        if ($material->access_level === 'staff' && $user->hasAnyRole(['teacher', 'employee'])) {
            return true;
        }

        if ($material->access_level === 'student') {
            $student = Student::where('user_id', $user->id)->first();

            if ($student) {
                return $this->studyMaterialService->studentCanAccess($material, $student);
            }
        }

        return false;
    }
}
