<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cms\app\Traits\ApprovableContent;
use Modules\Cms\app\Traits\HasCmsMedia;
use Modules\Cms\app\Traits\PublishableContent;
use Modules\Cms\app\Traits\TrackableContent;
use Modules\Core\app\Models\BaseModel;

class Slider extends BaseModel
{
    use PublishableContent;
    use ApprovableContent;
    use TrackableContent;
    use HasCmsMedia;

    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'sort_order',
        'status',
    ];

    protected array $searchable = ['title', 'description'];
    protected array $filterable = ['status', 'is_featured', 'approval_status'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
