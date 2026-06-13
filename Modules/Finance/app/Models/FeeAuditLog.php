<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class FeeAuditLog extends BaseModel
{
    protected $fillable = [
        'entity_type', 'entity_id', 'action', 'old_values', 'new_values',
        'performed_by', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    protected array $searchable = ['entity_type', 'entity_id', 'action', 'ip_address'];
    protected array $filterable = ['entity_type', 'action', 'performed_by'];

    public function performer()
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }

    public function scopeByEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
