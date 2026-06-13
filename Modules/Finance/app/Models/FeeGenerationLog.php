<?php

namespace Modules\Finance\app\Models;

use Modules\Core\app\Models\BaseModel;

class FeeGenerationLog extends BaseModel
{
    protected $table = 'fee_generation_logs';

    protected $fillable = [
        'enrollment_id', 'generation_type', 'summary', 'notes', 'generated_by',
    ];

    protected $casts = [
        'summary' => 'json',
    ];

    protected array $searchable = ['generation_type', 'notes'];
    protected array $filterable = ['enrollment_id', 'generation_type', 'generated_by'];

    public function enrollment()
    {
        return $this->belongsTo(\Modules\Enrollment\app\Models\Enrollment::class, 'enrollment_id');
    }

    public function generator()
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }
}
