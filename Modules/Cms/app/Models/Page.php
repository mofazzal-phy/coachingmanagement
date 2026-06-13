<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class Page extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'slug',
        'content_type',
        'content',
        'excerpt',
        'featured_image',
        'meta_title',
        'meta_description',
        'template',
        'sections',
        'created_by',
        'author_id',
        'status',
        'tags',
        'reading_time',
    ];

    protected $casts = [
        'sections' => 'array',
        'tags' => 'array',
    ];

    protected array $searchable = ['title', 'slug', 'content', 'excerpt'];
    protected array $filterable = ['status', 'template', 'content_type', 'is_featured', 'approval_status'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
