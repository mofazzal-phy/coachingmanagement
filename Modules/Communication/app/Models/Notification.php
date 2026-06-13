<?php

namespace Modules\Communication\app\Models;

use Modules\Core\app\Models\BaseModel;

class Notification extends BaseModel
{
    protected $fillable = [
        'title', 'message', 'type', 'audience', 'audience_ids',
        'sent_by', 'scheduled_at', 'sent_at', 'status'
    ];

    protected $casts = [
        'audience_ids' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected array $searchable = ['title', 'message'];
    protected array $filterable = ['type', 'audience', 'status'];

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
