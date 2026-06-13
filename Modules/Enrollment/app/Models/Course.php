<?php

namespace Modules\Enrollment\app\Models;

use Modules\Core\app\Models\BaseModel;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\AcademicGroup;
use Modules\Academic\app\Models\Subject;

class Course extends BaseModel
{
    protected $table = 'courses';

    protected static function booted()
    {
        static::creating(function ($course) {
            $course->created_by = auth()->id();
        });
        static::created(function ($course) {
            \Illuminate\Support\Facades\Cache::forget('courses.list_all');
            \Illuminate\Support\Facades\Cache::forget('courses.statistics');
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'course', $course->id, 'created',
                    "Course '{$course->name}' created", null, $course->toArray()
                );
            }
        });
        static::updating(function ($course) {
            $course->updated_by = auth()->id();
        });
        static::updated(function ($course) {
            \Illuminate\Support\Facades\Cache::forget('courses.list_all');
            \Illuminate\Support\Facades\Cache::forget('courses.statistics');
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'course', $course->id, 'updated',
                    "Course '{$course->name}' updated", $course->getOriginal(), $course->toArray()
                );
            }
        });
        static::deleted(function ($course) {
            \Illuminate\Support\Facades\Cache::forget('courses.list_all');
            \Illuminate\Support\Facades\Cache::forget('courses.statistics');
            if (app()->bound(\Modules\Enrollment\app\Services\ActivityLogService::class)) {
                app(\Modules\Enrollment\app\Services\ActivityLogService::class)->logForModel(
                    null, 'course', $course->id, 'deleted',
                    "Course '{$course->name}' deleted", $course->toArray()
                );
            }
        });
    }

    protected $appends = ['cover_image_url'];

    protected $fillable = [
        'name', 'slug', 'code', 'category', 'level',
        'class_id', 'group_id', 'target',
        'has_online', 'has_offline',
        'duration_days', 'duration_label',
        'one_time_fee', 'enrollment_fee',
        'description', 'short_description', 'cover_image',
        'meta_title', 'meta_description',
        'learning_outcomes', 'syllabus',
        'is_featured', 'sort_order', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'one_time_fee' => 'decimal:2',
        'enrollment_fee' => 'decimal:2',
    ];

    protected array $searchable = ['name', 'slug', 'code', 'target', 'short_description'];
    protected array $filterable = ['status', 'category', 'class_id', 'group_id', 'is_featured'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'course_subject', 'course_id', 'subject_id')
            ->withPivot(['fee', 'monthly_fee', 'is_optional', 'is_mandatory', 'sort_order'])
            ->withTimestamps();
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'course_id');
    }

    public function enrollments()
    {
        return $this->hasManyThrough(Enrollment::class, Batch::class, 'course_id', 'batch_id');
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function group()
    {
        return $this->belongsTo(AcademicGroup::class, 'group_id');
    }

    public function scopeAcademic($query)
    {
        return $query->where('category', 'academic');
    }

    public function scopeAdmissionCoaching($query)
    {
        return $query->where('category', 'admission_coaching');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) {
            return null;
        }
        if (str_starts_with($this->cover_image, 'http')) {
            return $this->cover_image;
        }

        return asset('storage/' . ltrim($this->cover_image, '/'));
    }
}
