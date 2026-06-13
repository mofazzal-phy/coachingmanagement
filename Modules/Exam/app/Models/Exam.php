<?php

namespace Modules\Exam\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;

class Exam extends BaseModel
{
    protected $fillable = [
        'academic_session_id', 'exam_type_id',
        'batch_id', 'course_id', 'class_id', 'section_id',
        'name', 'start_date', 'end_date', 'description', 'status',
        'is_practice', 'delivery_mode',
        'eligibility_check_enabled', 'min_attendance_percent', 'exam_fee_applicable',
        'online_eligibility_check_enabled', 'online_min_attendance_percent', 'online_exam_fee_applicable',
        'offline_policy_scope', 'online_policy_scope',
        'result_status', 'result_publish_at',
        'online_result_status', 'online_result_publish_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_practice' => 'boolean',
        'eligibility_check_enabled' => 'boolean',
        'exam_fee_applicable' => 'boolean',
        'online_eligibility_check_enabled' => 'boolean',
        'online_exam_fee_applicable' => 'boolean',
        'min_attendance_percent' => 'float',
        'online_min_attendance_percent' => 'float',
        'result_publish_at' => 'datetime',
        'online_result_publish_at' => 'datetime',
    ];

    /**
     * @param  'offline'|'online'  $channel
     */
    public function resultPublicationStatusForChannel(string $channel = 'offline'): string
    {
        if ($channel === 'online') {
            return $this->online_result_status ?? 'draft';
        }

        return $this->result_status ?? 'draft';
    }

    public function isResultChannelPublished(string $channel = 'offline'): bool
    {
        return $this->resultPublicationStatusForChannel($channel) === 'published';
    }

    /**
     * @param  'offline'|'online'  $channel
     * @return array{check_enabled: bool, min_percent: ?float, fee_applicable: bool}
     */
    public function eligibilityRulesForChannel(string $channel = 'offline'): array
    {
        if ($channel === 'online') {
            return [
                'check_enabled' => (bool) $this->online_eligibility_check_enabled,
                'min_percent' => $this->online_min_attendance_percent,
                'fee_applicable' => (bool) $this->online_exam_fee_applicable,
            ];
        }

        return [
            'check_enabled' => (bool) $this->eligibility_check_enabled,
            'min_percent' => $this->min_attendance_percent,
            'fee_applicable' => (bool) $this->exam_fee_applicable,
        ];
    }

    protected array $searchable = ['name'];

    protected array $filterable = [
        'status', 'class_id', 'section_id', 'exam_type_id',
        'batch_id', 'course_id', 'academic_session_id',
        'is_practice', 'delivery_mode', 'result_status',
    ];

    public function session()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\AcademicSession::class, 'academic_session_id');
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function class()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Classes::class);
    }

    public function section()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Section::class);
    }

    public function routines()
    {
        return $this->hasMany(ExamRoutine::class);
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function studentEligibilities()
    {
        return $this->hasMany(ExamStudentEligibility::class);
    }

    public function batchChannelPolicies()
    {
        return $this->hasMany(ExamBatchChannelPolicy::class);
    }

    public function scopeOfficial($query)
    {
        return $query->where('is_practice', false);
    }

    public function scopePractice($query)
    {
        return $query->where('is_practice', true);
    }

    public function scopeResultsPublished($query)
    {
        return $query->where('result_status', 'published');
    }

    public function isResultsPublished(): bool
    {
        return $this->result_status === 'published';
    }
}
