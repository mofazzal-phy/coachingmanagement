<?php

namespace Modules\Cms\app\Traits;

use Illuminate\Http\UploadedFile;
use Modules\Cms\app\Services\CmsMediaService;

trait HasCmsMedia
{
    public function uploadCmsMedia(UploadedFile $file, string $type, ?string $subfolder = null): ?array
    {
        return app(CmsMediaService::class)->upload($file, $type, $subfolder ?? $this->getTable());
    }

    public function replaceCmsMedia(UploadedFile $file, string $type, ?string $oldPath, ?string $subfolder = null): ?array
    {
        return app(CmsMediaService::class)->replace($file, $type, $oldPath, $subfolder ?? $this->getTable());
    }

    public function deleteCmsMedia(?string $path): bool
    {
        return app(CmsMediaService::class)->delete($path);
    }

    public function cmsMediaUrl(?string $path): ?string
    {
        return app(CmsMediaService::class)->url($path);
    }
}
