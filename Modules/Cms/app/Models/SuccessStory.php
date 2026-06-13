<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class SuccessStory extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'story',
        'student_name',
        'exam_name',
        'achievement_year',
        'result_summary',
        'featured_image',
        'gallery_images',
        'class_id',
        'batch_id',
        'sort_order',
        'status',
    ];

    protected array $searchable = [
        'title',
        'excerpt',
        'story',
        'student_name',
        'exam_name',
        'result_summary',
    ];

    protected array $filterable = [
        'status',
        'is_featured',
        'approval_status',
        'class_id',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'gallery_images' => 'array',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class, 'class_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Batch::class, 'batch_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
