<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\SuccessStory;
use Modules\Cms\app\Services\SuccessStoryService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class SuccessStoryController extends BaseApiController
{
    public function __construct(
        protected SuccessStoryService $successStoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $paginator = $this->successStoryService->listForAdmin([
            'search' => $request->search,
            'status' => $request->status,
            'is_featured' => $request->is_featured,
            'approval_status' => $request->approval_status,
            'class_id' => $request->class_id,
            'batch_id' => $request->batch_id,
        ], $perPage);

        return $this->paginatedResponse(
            $paginator->through(fn (SuccessStory $item) => $this->successStoryService->enrich($item))
        );
    }

    public function published(): JsonResponse
    {
        $items = $this->successStoryService->getPublished();

        return $this->success($this->successStoryService->enrichCollection($items));
    }

    public function show(string $id): JsonResponse
    {
        $story = $this->successStoryService->findById($id);

        if (!$story) {
            return $this->notFound();
        }

        return $this->success($this->successStoryService->enrich($story));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $story = $this->successStoryService->findBySlug($slug);

        if (!$story || $story->status !== 'active') {
            return $this->notFound();
        }

        $story->recordView(auth()->id());

        return $this->success($this->successStoryService->enrich($story->fresh()));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->successStoryService->storeValidationRules());
        $story = $this->successStoryService->create($validated, auth()->user());

        return $this->created($this->successStoryService->enrich($story));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        $validated = $request->validate($this->successStoryService->updateValidationRules($id));

        try {
            $story = $this->successStoryService->update($story, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->successStoryService->enrich($story));
    }

    public function destroy(string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        $this->successStoryService->delete($story, auth()->user());

        return $this->noContent();
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        try {
            $story = $this->successStoryService->submitForReview($story, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->successStoryService->enrich($story), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        try {
            $story = $this->successStoryService->approve($story, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->successStoryService->enrich($story), 'Success story approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        try {
            $story = $this->successStoryService->reject(
                $story,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->successStoryService->enrich($story), 'Success story rejected');
    }

    public function activate(string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        try {
            $story = $this->successStoryService->activate($story, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->successStoryService->enrich($story), 'Success story activated');
    }

    public function deactivate(string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        $story = $this->successStoryService->deactivate($story, auth()->user());

        return $this->success($this->successStoryService->enrich($story), 'Success story deactivated');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $story = SuccessStory::find($id);

        if (!$story) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($story->getTable(), $story->getKey(), $perPage)
        );
    }
}
