<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class AcademicGroup extends BaseModel
{
    protected $table = 'academic_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    protected array $searchable = ['name', 'slug', 'description'];

    protected array $filterable = ['status'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'group_subject', 'group_id', 'subject_id')
            ->withTimestamps();
    }

    public function classSubjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject', 'group_id', 'subject_id')
            ->withPivot(['total_marks', 'pass_marks']);
    }
}
