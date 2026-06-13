<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\Testimonial;
use Modules\Cms\app\Services\TestimonialService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class TestimonialController extends BaseApiController
{
    public function __construct(
        protected TestimonialService $testimonialService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $paginator = $this->testimonialService->listForAdmin([
            'search' => $request->search,
            'status' => $request->status,
            'is_featured' => $request->is_featured,
            'approval_status' => $request->approval_status,
        ], $perPage);

        return $this->paginatedResponse(
            $paginator->through(fn (Testimonial $item) => $this->testimonialService->enrich($item))
        );
    }

    public function published(): JsonResponse
    {
        $items = $this->testimonialService->getPublished();

        return $this->success($this->testimonialService->enrichCollection($items));
    }

    public function show(string $id): JsonResponse
    {
        $testimonial = $this->testimonialService->findById($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        return $this->success($this->testimonialService->enrich($testimonial));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->testimonialService->storeValidationRules());
        $testimonial = $this->testimonialService->create($validated, auth()->user());

        return $this->created($this->testimonialService->enrich($testimonial));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        $validated = $request->validate($this->testimonialService->updateValidationRules());

        try {
            $testimonial = $this->testimonialService->update($testimonial, $validated, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->testimonialService->enrich($testimonial));
    }

    public function destroy(string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        $this->testimonialService->delete($testimonial, auth()->user());

        return $this->noContent();
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        try {
            $testimonial = $this->testimonialService->submitForReview($testimonial, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->testimonialService->enrich($testimonial), 'Submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        try {
            $testimonial = $this->testimonialService->approve($testimonial, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->testimonialService->enrich($testimonial), 'Testimonial approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        try {
            $testimonial = $this->testimonialService->reject(
                $testimonial,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->testimonialService->enrich($testimonial), 'Testimonial rejected');
    }

    public function activate(string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        try {
            $testimonial = $this->testimonialService->activate($testimonial, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->testimonialService->enrich($testimonial), 'Testimonial activated');
    }

    public function deactivate(string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        $testimonial = $this->testimonialService->deactivate($testimonial, auth()->user());

        return $this->success($this->testimonialService->enrich($testimonial), 'Testimonial deactivated');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($testimonial->getTable(), $testimonial->getKey(), $perPage)
        );
    }
}
