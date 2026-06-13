<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Academic\app\Models\Room;
use Modules\Teacher\app\Models\Teacher;

class Batch extends BaseModel
{
    protected $table = 'batches';

    protected static function booted()
    {
        static::creating(function ($batch) {
            $batch->created_by = auth()->id();
        });
        static::created(function ($batch) {
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'batch', $batch->id, 'created',
                    "Batch '{$batch->name}' created", null, $batch->toArray()
                );
            }
        });
        static::updating(function ($batch) {
            $batch->updated_by = auth()->id();
        });
        static::updated(function ($batch) {
            if ($batch->wasChanged('status') && $batch->status === 'full') {
                if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                    app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                        null, 'batch', $batch->id, 'full',
                        "Batch '{$batch->name}' is full ({$batch->enrolled_count}/{$batch->capacity})"
                    );
                }
            }
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'batch', $batch->id, 'updated',
                    "Batch '{$batch->name}' updated", $batch->getOriginal(), $batch->toArray()
                );
            }
        });
        static::deleted(function ($batch) {
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'batch', $batch->id, 'deleted',
                    "Batch '{$batch->name}' deleted"
                );
            }
        });
    }

    protected $fillable = [
        'course_id', 'name', 'code', 'academic_session_id',
        'mode', 'shift', 'room_id', 'campus_location',
        'platform', 'meeting_link', 'recording_available',
        'days', 'start_time', 'end_time', 'start_date', 'end_date',
        'capacity', 'enrolled_count', 'waiting_limit', 'status', 'teacher_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'days' => 'array',
        'recording_available' => 'boolean',
    ];

    protected array $searchable = ['name', 'code', 'campus_location', 'platform'];
    protected array $filterable = ['status', 'mode', 'course_id', 'academic_session_id', 'room_id'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'batch_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByMode($query, $mode)
    {
        return $query->where('mode', $mode);
    }

    public function hasAvailableSeats(): bool
    {
        return $this->enrolled_count < $this->capacity;
    }

    public function availableSeats(): int
    {
        return max(0, $this->capacity - $this->enrolled_count);
    }
}
