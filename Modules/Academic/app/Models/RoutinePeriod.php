<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class RoutinePeriod extends BaseModel
{
    protected $table = 'routine_periods';

    protected $fillable = [
        'academic_session_id', 'name', 'start_time', 'end_time',
        'sort_order', 'is_break', 'status'
    ];

    protected $casts = [
        'is_break' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected array $searchable = ['name'];
    protected array $filterable = ['status', 'is_break', 'academic_session_id'];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}
