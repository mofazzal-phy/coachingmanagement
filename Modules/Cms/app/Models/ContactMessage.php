<?php

namespace Modules\Cms\app\Models;

use Modules\Core\app\Models\BaseModel;

class ContactMessage extends BaseModel
{
    protected $table = 'contact_messages';

    protected $fillable = [
        'name', 'phone', 'email', 'subject', 'message',
        'source', 'status', 'ip_address',
    ];

    protected array $searchable = ['name', 'phone', 'email', 'subject'];
    protected array $filterable = ['status', 'source'];
}
