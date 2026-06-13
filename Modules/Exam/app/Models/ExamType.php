<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;

class ExamType extends BaseModel
{
    protected $fillable = ['name', 'code', 'category', 'description', 'status'];

    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['status'];

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }
}
