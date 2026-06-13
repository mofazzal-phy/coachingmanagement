<?php

namespace Modules\Enrollment\app\Services;

use Illuminate\Support\Facades\Storage;

class DocumentService
{
    protected string $tempPath = 'tmp/enrollment';
    protected string $permPath = 'documents';

    /**
     * Move documents from temp to permanent storage.
     */
    public function moveToPermanent(array $documents, string $studentId, string $enrollmentId): void
    {
        foreach ($documents as $doc) {
            if (empty($doc['path']) || empty($doc['type'])) continue;

            $tempFullPath = $doc['path'];
            $fileName = basename($tempFullPath);
            $permFullPath = "{$this->permPath}/{$studentId}/{$fileName}";

            // Move file if it exists in temp
            if (Storage::exists($tempFullPath)) {
                Storage::move($tempFullPath, $permFullPath);
            }

            \Modules\Enrollment\app\Models\Document::create([
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'document_type' => $doc['type'],
                'file_name' => $doc['original_name'] ?? $fileName,
                'file_path' => $permFullPath,
                'file_size' => $doc['size'] ?? null,
                'mime_type' => $doc['mime_type'] ?? null,
                'uploaded_at' => now(),
            ]);
        }
    }

    /**
     * Clean up temp files from an abandoned session.
     */
    public function cleanTempFiles(array $documents): void
    {
        foreach ($documents as $doc) {
            if (!empty($doc['path']) && Storage::exists($doc['path'])) {
                Storage::delete($doc['path']);
            }
        }
    }

    /**
     * Upload a single temp file.
     */
    public function uploadTemp($file, string $sessionId): array
    {
        $path = $file->store("{$this->tempPath}/{$sessionId}");
        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }
}
