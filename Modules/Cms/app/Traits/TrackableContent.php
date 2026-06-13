<?php

namespace Modules\Cms\app\Traits;

use Modules\Cms\app\Enums\CmsContentEventType;
use Modules\Cms\app\Services\CmsAnalyticsService;

trait TrackableContent
{
    public function getCmsContentType(): string
    {
        return $this->getTable();
    }

    public function recordView(?string $userId = null): void
    {
        app(CmsAnalyticsService::class)->track(
            $this->getCmsContentType(),
            $this->getKey(),
            CmsContentEventType::View,
            $userId
        );
    }

    public function recordDownload(?string $userId = null): void
    {
        app(CmsAnalyticsService::class)->track(
            $this->getCmsContentType(),
            $this->getKey(),
            CmsContentEventType::Download,
            $userId
        );
    }
}
