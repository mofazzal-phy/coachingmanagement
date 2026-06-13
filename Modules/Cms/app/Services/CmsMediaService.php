<?php

namespace Modules\Cms\app\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Cms\app\Enums\CmsMediaType;
use Modules\Core\app\Services\FileUploadService;

class CmsMediaService
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function upload(UploadedFile $file, string $type, string $subfolder = 'general'): ?array
    {
        $mediaType = $this->resolveMediaType($type);
        $this->validateFile($file, $mediaType);

        $directory = $this->directoryFor($mediaType, $subfolder);
        $path = $this->fileUploadService->upload($file, $directory);

        if (!$path) {
            return null;
        }

        return $this->buildPayload($path, $mediaType, $file);
    }

    public function replace(UploadedFile $file, string $type, ?string $oldPath, string $subfolder = 'general'): ?array
    {
        $mediaType = $this->resolveMediaType($type);
        $this->validateFile($file, $mediaType);

        $directory = $this->directoryFor($mediaType, $subfolder);
        $path = $this->fileUploadService->replace($file, $oldPath, $directory);

        if (!$path) {
            return null;
        }

        return $this->buildPayload($path, $mediaType, $file);
    }

    public function delete(?string $path): bool
    {
        return $this->fileUploadService->delete($path);
    }

    public function url(?string $path): ?string
    {
        return $this->fileUploadService->url($path);
    }

    public function info(?string $path): ?array
    {
        return $this->fileUploadService->info($path);
    }

    protected function resolveMediaType(string $type): CmsMediaType
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'image', 'images' => CmsMediaType::Image,
            'pdf', 'document', 'documents' => CmsMediaType::Pdf,
            'video', 'videos' => CmsMediaType::Video,
            default => throw ValidationException::withMessages([
                'type' => ['Unsupported media type. Allowed: image, pdf, video.'],
            ]),
        };
    }

    protected function validateFile(UploadedFile $file, CmsMediaType $type): void
    {
        $rules = config("cms.media.{$type->value}", []);

        if ($file->getSize() > ($rules['max_size'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds maximum allowed size.'],
            ]);
        }

        $mime = $file->getMimeType() ?: '';
        $allowed = $rules['mimes'] ?? [];

        if (!empty($allowed) && !in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Invalid file type for ' . $type->value . ' upload.'],
            ]);
        }
    }

    protected function directoryFor(CmsMediaType $type, string $subfolder): string
    {
        $base = config('cms.media.base_directory', 'cms');

        return trim("{$base}/{$type->value}/{$subfolder}", '/');
    }

    protected function buildPayload(string $path, CmsMediaType $type, UploadedFile $file): array
    {
        return [
            'path' => $path,
            'url' => $this->url($path),
            'media_type' => $type->value,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }
}
