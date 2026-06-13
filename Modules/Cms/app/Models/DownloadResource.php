<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class DownloadResource extends BaseModel
{
    use SoftDeletes;
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $table = 'download_resources';

    protected $fillable = [
        'title',
        'description',
        'slug',
        'category',
        'file_path',
        'file_size',
        'mime_type',
        'access_level',
        'sort_order',
        'status',
    ];

    protected array $searchable = ['title', 'description', 'slug'];

    protected array $filterable = [
        'status',
        'category',
        'access_level',
        'is_featured',
        'approval_status',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
