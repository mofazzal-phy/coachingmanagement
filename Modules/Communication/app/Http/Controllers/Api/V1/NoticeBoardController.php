<?php

namespace Modules\Communication\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Communication\app\Models\NoticeBoard;
use Modules\Communication\app\Services\NoticeBoardService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class NoticeBoardController extends BaseApiController
{
    public function __construct(
        protected NoticeBoardService $noticeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            $this->noticeService->listForAdmin([
                'search' => $request->search,
                'status' => $request->status,
                'priority' => $request->priority,
                'audience' => $request->audience,
                'is_featured' => $request->is_featured,
                'approval_status' => $request->approval_status,
            ], $perPage)
        );
    }

    public function published(): JsonResponse
    {
        $notices = $this->noticeService->getPublishedForUser(auth()->user());

        return $this->success(
            $this->noticeService->enrichCollectionForPortal($notices)
        );
    }

    public function show(string $id): JsonResponse
    {
        $notice = $this->noticeService->find($id);

        if (!$notice) {
            return $this->notFound();
        }

        return $this->success($this->noticeService->enrichForPortal($notice));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'publish_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'attachments' => 'nullable|array',
            'audience' => 'sometimes|in:all,students,teachers,staff,parents,custom',
            'audience_ids' => 'nullable|array',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'seo_meta_title' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        $notice = $this->noticeService->create($validated, auth()->user());

        return $this->created($notice);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'publish_date' => 'sometimes|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'attachments' => 'nullable|array',
            'audience' => 'sometimes|in:all,students,teachers,staff,parents,custom',
            'audience_ids' => 'nullable|array',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'scheduled_at' => 'nullable|date',
            'seo_meta_title' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        $notice = $this->noticeService->update($notice, $validated, auth()->user());

        return $this->success($notice);
    }

    public function destroy(string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        $this->noticeService->delete($notice, auth()->user());

        return $this->noContent();
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file',
            'type' => 'sometimes|in:image,pdf,video',
        ]);

        $payload = $this->noticeService->uploadAttachment(
            $validated['file'],
            $validated['type'] ?? 'pdf'
        );

        return $this->created($payload);
    }

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        try {
            $notice = $this->noticeService->submitForReview(
                $notice,
                auth()->user(),
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($notice, 'Notice submitted for review');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        try {
            $notice = $this->noticeService->approve($notice, auth()->user(), $request->input('comment'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($notice, 'Notice approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        try {
            $notice = $this->noticeService->reject(
                $notice,
                auth()->user(),
                $request->reason,
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($notice, 'Notice rejected');
    }

    public function publish(string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        try {
            $notice = $this->noticeService->publish($notice, auth()->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($notice, 'Notice published');
    }

    public function unpublish(string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        $notice = $this->noticeService->unpublish($notice, auth()->user());

        return $this->success($notice, 'Notice unpublished');
    }

    public function auditLogs(Request $request, string $id): JsonResponse
    {
        $notice = NoticeBoard::find($id);

        if (!$notice) {
            return $this->notFound();
        }

        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            app(\Modules\Cms\app\Services\CmsAuditLogService::class)
                ->getEntityTimeline($notice->getTable(), $notice->getKey(), $perPage)
        );
    }
}
