<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class AcademicSession extends BaseModel
{
    protected $table = 'academic_sessions';

    protected $fillable = [
        'name', 'start_date', 'end_date', 'is_current', 'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    protected array $searchable = ['name'];
    protected array $filterable = ['status', 'is_current'];
}
