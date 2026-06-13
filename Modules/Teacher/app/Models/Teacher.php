<?php

namespace Modules\Teacher\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\AcademicGroup;

class Teacher extends BaseModel
{
    protected $fillable = [
        'user_id', 'teacher_id', 'first_name', 'last_name', 'email',
        'phone', 'gender', 'date_of_birth', 'qualification',
        'specialization', 'date_of_joining', 'address', 'photo', 'status',
        'teacher_type', 'group_id', 'experience_years', 'previous_institution',
        'salary_type', 'salary_amount'
    ];

    protected $appends = ['photo_url'];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_joining' => 'date',
        'experience_years' => 'integer',
        'salary_amount' => 'decimal:2',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }
        if (str_starts_with($this->photo, 'http')) {
            return $this->photo;
        }

        return asset('storage/' . ltrim($this->photo, '/'));
    }

    protected array $searchable = ['first_name', 'last_name', 'teacher_id', 'email', 'phone', 'teacher_type'];
    protected array $filterable = ['status', 'specialization', 'teacher_type', 'group_id'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function classes()
    {
        return $this->belongsToMany(Classes::class, 'class_teacher', 'teacher_id', 'class_id')
            ->withPivot('academic_session_id')
            ->withTimestamps();
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_id', 'subject_id')
            ->withTimestamps();
    }

    public function academicGroup()
    {
        return $this->belongsTo(AcademicGroup::class, 'group_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
