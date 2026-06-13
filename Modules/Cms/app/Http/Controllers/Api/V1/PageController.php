<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\Page;
use Modules\Cms\app\Services\PageService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class PageController extends BaseApiController
{
    public function __construct(
        protected PageService $pageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            $this->pageService->listForAdmin([
                'search' => $request->search,
                'status' => $request->status,
                'template' => $request->template,
                'content_type' => $request->content_type,
                'is_featured' => $request->is_featured,
                'approval_status' => $request->approval_status,
            ], $perPage)
        );
    }

    public function published(Request $request): JsonResponse
    {
        $pages = $this->pageService->getPublished($request->content_type);

        return $this->success($this->pageService->enrichCollection($pages));
    }

    public function blogs(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            $this->pageService->listForAdmin([
                'search' => $request->search,
                'status' => $request->status,
                'template' => $request->template,
                'content_type' => 'blog',
                'is_featured' => $request->is_featured,
                'approval_status' => $request->approval_status,
            ], $perPage)
        );
    }

    public function publishedBlogs(): JsonResponse
    {
        $pages = $this->pageService->getPublished('blog');

        return $this->success($this->pageService->enrichCollection($pages));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $page = $this->pageService->findBySlug($slug);

        if (!$page) {
            return $this->notFound();
        }

        return $this->success($this->pageService->enrich($page));
    }

    public function showBlogBySlug(string $slug): JsonResponse
    {
        $page = $this->pageService->findBySlug($slug, 'blog');

        if (!$page) {
            return $this->notFound();
        }

        if ($page->status !== 'published') {
            return $this->notFound();
        }

        $page->recordView(auth()->id());

        return $this->success($this->pageService->enrich($page->fresh()));
    }

    public function show(string $id): JsonResponse
    {
        $page = $this->pageService->findById($id);

        if (!$page) {
            return $this->notFound();
        }

        return $this->success($this->pageService->enrich($page));
    }

    public function store(Request $request): JsonResponse
    {
        $contentType = $request->input('content_type', 'page');
        $validated = $request->validate(
            $this->pageService->storeValidationRules(null, $contentType)
        );

        $page = $this->pageService->create($validated, auth()->user());

        return $this->created($this->pageService->enrich($page));
    }

    public function storeBlog(Request $request): JsonResponse
    {
        $request->merge(['content_type' => 'blog']);

        return $this->store($request);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        $validated = $request->validate(
            $this->pageService->updateValidationRules($id, $page->content_type)
        );

        try {
            $page = $this->pageService->update($page, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->pageService->enrich($page));
    }

    public function destroy(string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        $this->pageService->delete($page, auth()->user());

        return $this->noContent();
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        try {
            $page = $this->pageService->submitForReview($page, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->pageService->enrich($page), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        try {
            $page = $this->pageService->approve($page, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->pageService->enrich($page), 'Content approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        try {
            $page = $this->pageService->reject(
                $page,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->pageService->enrich($page), 'Content rejected');
    }

    public function publish(string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        try {
            $page = $this->pageService->publish($page, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->pageService->enrich($page), 'Published');
    }

    public function unpublish(string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        $page = $this->pageService->unpublish($page, auth()->user());

        return $this->success($this->pageService->enrich($page), 'Unpublished');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $page = Page::find($id);

        if (!$page) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($page->getTable(), $page->getKey(), $perPage)
        );
    }
}
