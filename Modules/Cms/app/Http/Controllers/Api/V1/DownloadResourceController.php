<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\DownloadResource;
use Modules\Cms\app\Services\DownloadResourceService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class DownloadResourceController extends BaseApiController
{
    public function __construct(
        protected DownloadResourceService $downloadResourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $paginator = $this->downloadResourceService->listForAdmin([
            'search' => $request->search,
            'status' => $request->status,
            'category' => $request->category,
            'access_level' => $request->access_level,
            'is_featured' => $request->is_featured,
            'approval_status' => $request->approval_status,
        ], $perPage);

        return $this->paginatedResponse(
            $paginator->through(fn (DownloadResource $item) => $this->downloadResourceService->enrich($item))
        );
    }

    public function published(): JsonResponse
    {
        $items = $this->downloadResourceService->getPublished();

        return $this->success($this->downloadResourceService->enrichCollection($items));
    }

    public function show(string $id): JsonResponse
    {
        $resource = $this->downloadResourceService->findById($id);

        if (!$resource) {
            return $this->notFound();
        }

        return $this->success($this->downloadResourceService->enrich($resource));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->downloadResourceService->storeValidationRules());
        $resource = $this->downloadResourceService->create($validated, auth()->user());

        return $this->created($this->downloadResourceService->enrich($resource));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        $validated = $request->validate($this->downloadResourceService->updateValidationRules($id));

        try {
            $resource = $this->downloadResourceService->update($resource, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->downloadResourceService->enrich($resource));
    }

    public function destroy(string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        $this->downloadResourceService->delete($resource, auth()->user());

        return $this->noContent();
    }

    public function download(string $id): JsonResponse
    {
        $resource = $this->downloadResourceService->findById($id);

        if (!$resource) {
            return $this->notFound();
        }

        $user = auth()->user();

        if (!$this->downloadResourceService->userCanAccess($resource, $user)) {
            return $this->forbidden('You do not have access to this download resource');
        }

        $this->downloadResourceService->recordDownload($resource, $user);

        return $this->success($this->downloadResourceService->enrich($resource->fresh()));
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        try {
            $resource = $this->downloadResourceService->submitForReview($resource, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->downloadResourceService->enrich($resource), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        try {
            $resource = $this->downloadResourceService->approve($resource, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->downloadResourceService->enrich($resource), 'Download resource approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        try {
            $resource = $this->downloadResourceService->reject(
                $resource,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->downloadResourceService->enrich($resource), 'Download resource rejected');
    }

    public function activate(string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        try {
            $resource = $this->downloadResourceService->activate($resource, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->downloadResourceService->enrich($resource), 'Download resource activated');
    }

    public function deactivate(string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        $resource = $this->downloadResourceService->deactivate($resource, auth()->user());

        return $this->success($this->downloadResourceService->enrich($resource), 'Download resource deactivated');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $resource = DownloadResource::find($id);

        if (!$resource) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($resource->getTable(), $resource->getKey(), $perPage)
        );
    }
}
