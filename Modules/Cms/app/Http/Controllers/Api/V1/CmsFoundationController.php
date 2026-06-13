<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Enums\CmsContentEventType;
use Modules\Cms\app\Services\CmsAnalyticsService;
use Modules\Cms\app\Services\CmsApprovalQueueService;
use Modules\Cms\app\Services\CmsAuditLogService;
use Modules\Cms\app\Services\CmsMediaService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class CmsFoundationController extends BaseApiController
{
    public function __construct(
        protected CmsMediaService $mediaService,
        protected CmsAuditLogService $auditLogService,
        protected CmsAnalyticsService $analyticsService,
        protected CmsApprovalQueueService $approvalQueueService
    ) {}

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file',
            'type' => 'required|in:image,pdf,video',
            'subfolder' => 'nullable|string|max:80',
        ]);

        $result = $this->mediaService->upload(
            $validated['file'],
            $validated['type'],
            $validated['subfolder'] ?? 'general'
        );

        if (!$result) {
            return $this->error('File upload failed.', 422);
        }

        return $this->created($result);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            $this->auditLogService->getLogs($request->only([
                'entity_type',
                'entity_id',
                'action',
                'performed_by',
            ]), $perPage)
        );
    }

    public function entityAuditLogs(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $perPage = $this->getPerPage($request);

        return $this->paginatedResponse(
            $this->auditLogService->getEntityTimeline($entityType, $entityId, $perPage)
        );
    }

    public function trackEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:80',
            'content_id' => 'required|uuid',
            'event_type' => 'required|in:view,download',
        ]);

        $this->analyticsService->track(
            $validated['content_type'],
            $validated['content_id'],
            CmsContentEventType::from($validated['event_type'])
        );

        return $this->success([
            'tracked' => true,
            'summary' => $this->analyticsService->summary(
                $validated['content_type'],
                $validated['content_id']
            ),
        ]);
    }

    public function analyticsSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:80',
            'content_id' => 'required|uuid',
        ]);

        return $this->success(
            $this->analyticsService->summary(
                $validated['content_type'],
                $validated['content_id']
            )
        );
    }

    public function analyticsDashboard(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $days = max(1, min($days, 365));

        return $this->success($this->analyticsService->dashboard($days));
    }

    public function approvalQueue(Request $request): JsonResponse
    {
        $items = $this->approvalQueueService->getPendingItems(
            $request->entity_type,
            $request->search
        );

        return $this->success([
            'items' => $items,
            'total' => $items->count(),
            'counts_by_type' => $this->approvalQueueService->getCountsByType(),
        ]);
    }
}
