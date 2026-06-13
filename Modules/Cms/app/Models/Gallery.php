<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class Gallery extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'description',
        'type',
        'category',
        'album',
        'file',
        'thumbnail',
        'sort_order',
        'status',
    ];

    protected array $searchable = ['title', 'description', 'album'];
    protected array $filterable = ['type', 'status', 'category', 'album', 'is_featured', 'approval_status'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
