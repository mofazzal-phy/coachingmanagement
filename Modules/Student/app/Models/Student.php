<?php

namespace Modules\Student\app\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\app\Models\BaseModel;

class Student extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'student_id', 'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'gender', 'blood_group', 'religion', 'address',
        'present_address', 'permanent_address',
        'city', 'state', 'zip_code', 'country',
        'current_class_id', 'current_section_id', 'roll_no',
        'academic_session_id', 'admission_date', 'photo',
        'father_name', 'father_phone', 'father_occupation',
        'mother_name', 'mother_phone', 'mother_occupation',
        'emergency_contact', 'emergency_phone',
        'previous_school', 'previous_class',
        'status', 'remarks'
    ];

    protected $appends = ['photo_url'];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
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

    protected array $searchable = [
        'student_id', 'first_name', 'last_name', 'email', 'phone', 'roll_no'
    ];

    protected array $filterable = [
        'status', 'current_class_id', 'current_section_id', 'gender', 'blood_group'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }

    public function currentClass()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class, 'current_class_id');
    }

    public function currentSection()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Section::class, 'current_section_id');
    }

    public function enrollments()
    {
        return $this->hasMany(\Modules\Enrollment\app\Models\Enrollment::class);
    }

    public function attendances()
    {
        return $this->hasMany(\Modules\Attendance\app\Models\Attendance::class);
    }

    public function feeCollections()
    {
        return $this->hasMany(\Modules\Finance\app\Models\FeeCollection::class);
    }

    public function examResults()
    {
        return $this->hasMany(\Modules\Exam\app\Models\ExamResult::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
