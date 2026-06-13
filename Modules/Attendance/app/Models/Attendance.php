<?php

namespace Modules\Attendance\app\Models;

use Modules\Core\app\Models\BaseModel;

class Attendance extends BaseModel
{
    protected $table = 'attendance';

    protected $fillable = [
        'academic_session_id', 'class_id', 'section_id', 'subject_id',
        'student_id', 'date', 'status', 'remarks', 'marked_by'
    ];

    protected $casts = ['date' => 'date'];

    protected array $searchable = ['remarks'];
    protected array $filterable = ['status', 'date', 'class_id', 'section_id', 'subject_id', 'student_id'];

    public function student()
    {
        return $this->belongsTo(\Modules\Student\app\Models\Student::class);
    }

    public function class()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class);
    }

    public function section()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Section::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'marked_by');
    }
}
