<?php

namespace Modules\Academic\app\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\app\Models\BaseModel;

class Room extends BaseModel
{
    use SoftDeletes;

    protected $table = 'rooms';

    protected $fillable = [
        'name', 'code', 'capacity', 'building', 'floor', 'has_multimedia', 'status'
    ];

    protected $casts = [
        'has_multimedia' => 'boolean',
        'capacity' => 'integer',
    ];

    protected array $searchable = ['name', 'code', 'building'];
    protected array $filterable = ['status', 'building', 'floor'];
}
