<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicCourse extends Model
{
    protected $table = 'academic_courses';

    protected $fillable = [
        'course_name',
        'course_code',
        'batch_name',
        'section_name',
        'teacher_name',
        'schedule',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
