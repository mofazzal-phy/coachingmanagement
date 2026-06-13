<?php

namespace Modules\Core\app\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected string $disk = 'public';

    /**
     * Upload a single file
     * 
     * @param UploadedFile $file
     * @param string $directory
     * @param string|null $filename
     * @return string|null Stored file path
     */
    public function upload(UploadedFile $file, string $directory = 'uploads', ?string $filename = null): ?string
    {
        try {
            $filename = $filename ?? Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $filename, $this->disk);
            return $path;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Upload multiple files
     * 
     * @param UploadedFile[] $files
     * @param string $directory
     * @return array Array of stored file paths
     */
    public function uploadMultiple(array $files, string $directory = 'uploads'): array
    {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $this->upload($file, $directory);
                if ($path) {
                    $paths[] = $path;
                }
            }
        }
        return $paths;
    }

    /**
     * Delete a file
     * 
     * @param string|null $path
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if (empty($path) || !Storage::disk($this->disk)->exists($path)) {
            return false;
        }
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Replace an existing file with a new one
     * 
     * @param UploadedFile $newFile
     * @param string|null $oldPath
     * @param string $directory
     * @return string|null New file path
     */
    public function replace(UploadedFile $newFile, ?string $oldPath, string $directory = 'uploads'): ?string
    {
        // Delete old file
        $this->delete($oldPath);
        
        // Upload new file
        return $this->upload($newFile, $directory);
    }

    /**
     * Get the full URL for a file
     * 
     * @param string|null $path
     * @return string|null
     */
    public function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Get absolute path for a file
     * 
     * @param string|null $path
     * @return string|null
     */
    public function path(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return Storage::disk($this->disk)->path($path);
    }

    /**
     * Check if file exists
     * 
     * @param string|null $path
     * @return bool
     */
    public function exists(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get file info
     * 
     * @param string|null $path
     * @return array|null
     */
    public function info(?string $path): ?array
    {
        if (empty($path) || !$this->exists($path)) {
            return null;
        }
        
        return [
            'name' => basename($path),
            'path' => $path,
            'url' => $this->url($path),
            'size' => Storage::disk($this->disk)->size($path),
            'mime_type' => Storage::disk($this->disk)->mimeType($path),
            'last_modified' => Storage::disk($this->disk)->lastModified($path),
        ];
    }
}