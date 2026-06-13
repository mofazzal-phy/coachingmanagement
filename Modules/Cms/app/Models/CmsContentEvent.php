<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\app\Models\BaseModel;

class CmsContentEvent extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'content_type',
        'content_id',
        'event_type',
        'user_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected array $filterable = ['content_type', 'content_id', 'event_type', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
