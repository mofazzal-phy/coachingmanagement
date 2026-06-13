<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class Section extends BaseModel
{
    protected $table = 'sections';

    protected $fillable = [
        'class_id', 'name', 'code', 'capacity', 'description', 'status'
    ];

    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status', 'class_id'];

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(\Modules\Teacher\app\Models\Teacher::class, 'class_teacher')
            ->withPivot(['is_class_teacher']);
    }
}
