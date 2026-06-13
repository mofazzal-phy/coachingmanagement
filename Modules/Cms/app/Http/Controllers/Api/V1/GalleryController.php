<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\Gallery;
use Modules\Cms\app\Services\GalleryService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class GalleryController extends BaseApiController
{
    public function __construct(
        protected GalleryService $galleryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $paginator = $this->galleryService->listForAdmin([
            'search' => $request->search,
            'type' => $request->type,
            'status' => $request->status,
            'category' => $request->category,
            'album' => $request->album,
            'is_featured' => $request->is_featured,
            'approval_status' => $request->approval_status,
        ], $perPage);

        return $this->paginatedResponse(
            $paginator->through(fn (Gallery $item) => $this->galleryService->enrich($item))
        );
    }

    public function published(Request $request): JsonResponse
    {
        $items = $this->galleryService->getPublished($request->category);

        return $this->success($this->galleryService->enrichCollection($items));
    }

    public function show(string $id): JsonResponse
    {
        $gallery = $this->galleryService->findById($id);

        if (!$gallery) {
            return $this->notFound();
        }

        return $this->success($this->galleryService->enrich($gallery));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->galleryService->storeValidationRules());
        $gallery = $this->galleryService->create($validated, auth()->user());

        return $this->created($this->galleryService->enrich($gallery));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        $validated = $request->validate($this->galleryService->updateValidationRules());

        try {
            $gallery = $this->galleryService->update($gallery, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->galleryService->enrich($gallery));
    }

    public function destroy(string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        $this->galleryService->delete($gallery, auth()->user());

        return $this->noContent();
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        try {
            $gallery = $this->galleryService->submitForReview($gallery, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->galleryService->enrich($gallery), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        try {
            $gallery = $this->galleryService->approve($gallery, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->galleryService->enrich($gallery), 'Gallery item approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        try {
            $gallery = $this->galleryService->reject(
                $gallery,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->galleryService->enrich($gallery), 'Gallery item rejected');
    }

    public function activate(string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        try {
            $gallery = $this->galleryService->activate($gallery, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->galleryService->enrich($gallery), 'Gallery item activated');
    }

    public function deactivate(string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        $gallery = $this->galleryService->deactivate($gallery, auth()->user());

        return $this->success($this->galleryService->enrich($gallery), 'Gallery item deactivated');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($gallery->getTable(), $gallery->getKey(), $perPage)
        );
    }
}
