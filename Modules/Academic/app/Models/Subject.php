<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;

class Subject extends BaseModel
{
    protected $table = 'subjects';

    protected $fillable = [
        'name', 'code', 'type', 'credit_hours', 'description', 'status'
    ];

    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status', 'type'];

    public function classes()
    {
        return $this->belongsToMany(Classes::class, 'class_subject', 'subject_id', 'class_id')
            ->withPivot(['total_marks', 'pass_marks']);
    }

    public function courses()
    {
        return $this->belongsToMany(\Modules\Enrollment\app\Models\Course::class, 'course_subject', 'subject_id', 'course_id')
            ->withPivot(['fee', 'monthly_fee', 'is_optional', 'is_mandatory', 'sort_order'])
            ->withTimestamps();
    }

    public function groups()
    {
        return $this->belongsToMany(AcademicGroup::class, 'group_subject', 'subject_id', 'group_id')
            ->withTimestamps();
    }
}
