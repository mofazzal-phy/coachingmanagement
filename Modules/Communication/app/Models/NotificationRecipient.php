<?php

namespace Modules\Communication\app\Models;

use App\Models\User;
use Modules\Core\app\Models\BaseModel;

class NotificationRecipient extends BaseModel
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'is_highlighted',
        'read_at',
    ];

    protected $casts = [
        'is_highlighted' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
