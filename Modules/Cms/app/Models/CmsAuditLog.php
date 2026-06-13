<?php

namespace Modules\Cms\app\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\app\Models\BaseModel;

class CmsAuditLog extends BaseModel
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'performed_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected array $searchable = ['entity_type', 'entity_id', 'action', 'description'];
    protected array $filterable = ['entity_type', 'action', 'performed_by'];

    public function performer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }

    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }
}
