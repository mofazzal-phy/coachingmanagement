<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class StudyMaterial extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'description',
        'slug',
        'file_path',
        'file_size',
        'mime_type',
        'media_type',
        'class_id',
        'subject_id',
        'batch_id',
        'academic_session_id',
        'access_level',
        'sort_order',
        'status',
    ];

    protected array $searchable = ['title', 'description', 'slug'];

    protected array $filterable = [
        'status',
        'media_type',
        'access_level',
        'is_featured',
        'approval_status',
        'class_id',
        'subject_id',
        'batch_id',
        'academic_session_id',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Subject::class, 'subject_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Batch::class, 'batch_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(\Modules\Academic\app\Models\AcademicSession::class, 'academic_session_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
