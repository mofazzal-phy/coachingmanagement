<?php

namespace Modules\Admission\app\Models;

use Modules\Core\app\Models\BaseModel;

class Admission extends BaseModel
{
    protected $fillable = [
        'admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender',
        'email', 'phone', 'address', 'city', 'state', 'zip_code', 'country',
        'applying_class_id', 'applying_session_id',
        'previous_school', 'previous_class',
        'father_name', 'father_phone', 'father_occupation',
        'mother_name', 'mother_phone', 'mother_occupation',
        'documents', 'remarks', 'status', 'reviewed_by', 'rejection_reason'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'documents' => 'array',
    ];

    protected array $searchable = ['admission_no', 'first_name', 'last_name', 'phone', 'email'];
    protected array $filterable = ['status', 'applying_class_id', 'gender'];
}
