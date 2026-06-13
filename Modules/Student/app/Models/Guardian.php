<?php

namespace Modules\Student\app\Models;

use Modules\Core\app\Models\BaseModel;

class Guardian extends BaseModel
{
    protected $fillable = [
        'student_id', 'father_name', 'father_phone', 'father_email', 'father_occupation',
        'mother_name', 'mother_phone', 'mother_email', 'mother_occupation',
        'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_email',
        'address', 'is_primary',
        // Actual guardians table columns (schema-accurate)
        'name', 'relation', 'phone', 'email', 'occupation', 'photo',
    ];

    protected array $searchable = ['father_name', 'mother_name', 'guardian_name', 'guardian_phone'];
    protected array $filterable = ['student_id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
