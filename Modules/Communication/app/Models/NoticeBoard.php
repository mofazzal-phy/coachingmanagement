<?php

namespace Modules\Communication\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class NoticeBoard extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'content',
        'audience',
        'audience_ids',
        'priority',
        'publish_date',
        'expiry_date',
        'attachments',
        'created_by',
        'updated_by',
        'status',
        'seo_meta_title',
        'seo_meta_description',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'expiry_date' => 'date',
        'attachments' => 'array',
        'audience_ids' => 'array',
    ];

    protected array $searchable = ['title', 'content'];
    protected array $filterable = ['status', 'priority', 'audience', 'is_featured', 'approval_status'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
