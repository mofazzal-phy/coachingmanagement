<?php

namespace Modules\Academic\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Teacher\app\Models\Teacher;

class RoutineException extends BaseModel
{
    protected $table = 'routine_exceptions';

    protected $fillable = [
        'academic_session_id', 'class_routine_id', 'class_id', 'section_id', 'group_id',
        'exception_date', 'exception_type', 'original_subject_id', 'new_period_id',
        'substitute_teacher_id', 'reason', 'status', 'created_by',
    ];

    protected $casts = [
        'exception_date' => 'date',
    ];

    protected array $searchable = ['reason'];
    protected array $filterable = [
        'exception_date', 'exception_type', 'status', 'class_id', 'section_id', 'group_id', 'academic_session_id',
    ];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function classRoutine()
    {
        return $this->belongsTo(ClassRoutine::class, 'class_routine_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function group()
    {
        return $this->belongsTo(AcademicGroup::class, 'group_id');
    }

    public function originalSubject()
    {
        return $this->belongsTo(Subject::class, 'original_subject_id');
    }

    public function substituteTeacher()
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function newPeriod()
    {
        return $this->belongsTo(RoutinePeriod::class, 'new_period_id');
    }
}
