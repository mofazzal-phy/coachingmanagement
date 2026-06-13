<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class Classes extends BaseModel
{
    protected $table = 'classes';

    protected $fillable = [
        'name', 'code', 'numeric_value', 'type', 'description', 'status'
    ];

    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status'];

    public function sections()
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject', 'class_id', 'subject_id')
            ->withPivot(['total_marks', 'pass_marks']);
    }

    public function teachers()
    {
        return $this->belongsToMany(\Modules\Teacher\app\Models\Teacher::class, 'class_teacher', 'class_id', 'teacher_id')
            ->withPivot(['section_id', 'is_class_teacher']);
    }
}
