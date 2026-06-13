<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;

class Document extends BaseModel
{
    protected $table = 'documents';

    protected $fillable = [
        'student_id', 'enrollment_id', 'document_type',
        'file_name', 'file_path', 'file_size', 'mime_type',
        'is_verified', 'verified_by', 'verified_at', 'uploaded_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];
}
