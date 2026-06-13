<?php

namespace Modules\Academic\app\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\app\Models\BaseModel;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Teacher\app\Models\Teacher;

class ClassRoutine extends BaseModel
{
    use SoftDeletes;

    protected $table = 'class_routines';

    protected $fillable = [
        'batch_id', 'course_id', 'class_id', 'section_id', 'group_id',
        'subject_id', 'teacher_id', 'room_id',
        'day_of_week', 'start_time', 'end_time',
        'start_date', 'end_date',
        'version', 'status', 'created_by',
        'slot_name', 'duration', 'display_order',
        'is_lunch_break', 'is_off_day', 'off_day_date',
        'recurrence_pattern', 'recurrence_end_date', 'notes',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'version' => 'integer',
        'is_lunch_break' => 'boolean',
        'is_off_day' => 'boolean',
        'duration' => 'integer',
        'display_order' => 'integer',
        'off_day_date' => 'date',
        'recurrence_end_date' => 'date',
    ];

    protected array $searchable = [];

    protected array $filterable = [
        'batch_id', 'course_id', 'class_id', 'section_id', 'group_id',
        'subject_id', 'teacher_id', 'room_id',
        'day_of_week', 'status', 'version',
    ];

    // ===== RELATIONSHIPS =====

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
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

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exceptions()
    {
        return $this->hasMany(RoutineException::class, 'class_routine_id');
    }

    // ===== SCOPES =====

    public function scopeByBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId)
            ->orWhereHas('batch', fn($q) => $q->where('course_id', $courseId));
    }

    public function scopeByClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['published']);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereNull('start_date')->orWhere('start_date', '<=', $end);
        })->where(function ($q) use ($start, $end) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', $start);
        });
    }

    public function scopeToday($query)
    {
        $today = strtolower(now()->format('D'));
        $dayMap = [
            'sat' => 'sat', 'sun' => 'sun', 'mon' => 'mon',
            'tue' => 'tue', 'wed' => 'wed', 'thu' => 'thu', 'fri' => 'fri',
        ];
        return $query->where('day_of_week', $dayMap[$today] ?? null);
    }

    public function scopeLunchBreak($query)
    {
        return $query->where('is_lunch_break', true);
    }

    public function scopeNotLunchBreak($query)
    {
        return $query->where('is_lunch_break', false);
    }

    public function scopeOffDay($query)
    {
        return $query->where('is_off_day', true);
    }

    public function scopeNotOffDay($query)
    {
        return $query->where('is_off_day', false);
    }

    public function scopeByBatchIds($query, array $batchIds)
    {
        return $query->whereIn('batch_id', $batchIds);
    }

    public function scopeByRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeBySubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('start_time');
    }

    // ===== ACCESSORS =====

    public function getDayNameAttribute(): string
    {
        $map = [
            'sat' => 'Saturday', 'sun' => 'Sunday', 'mon' => 'Monday',
            'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday',
        ];
        return $map[$this->day_of_week] ?? $this->day_of_week;
    }

    public function getDayNameBanglaAttribute(): string
    {
        $map = [
            'sat' => 'শনিবার', 'sun' => 'রবিবার', 'mon' => 'সোমবার',
            'tue' => 'মঙ্গলবার', 'wed' => 'বুধবার', 'thu' => 'বৃহস্পতিবার', 'fri' => 'শুক্রবার',
        ];
        return $map[$this->day_of_week] ?? $this->day_of_week;
    }

    public function getTimeFormattedAttribute(): string
    {
        if (!$this->start_time) return '—';
        return Carbon::createFromFormat('H:i:s', $this->start_time)->format('h:i A')
            . ' - ' . Carbon::createFromFormat('H:i:s', $this->end_time)->format('h:i A');
    }

    public function getStartTimeFormattedAttribute(): string
    {
        if (!$this->start_time) return '—';
        return Carbon::createFromFormat('H:i:s', $this->start_time)->format('h:i A');
    }

    public function getEndTimeFormattedAttribute(): string
    {
        if (!$this->end_time) return '—';
        return Carbon::createFromFormat('H:i:s', $this->end_time)->format('h:i A');
    }

    public function getIsLiveNowAttribute(): bool
    {
        if (!$this->start_time || !$this->end_time) return false;
        $now = now();
        $today = strtolower($now->format('D'));
        if ($this->day_of_week !== $today) return false;

        $start = Carbon::createFromFormat('H:i:s', $this->start_time)->setDateFrom($now);
        $end = Carbon::createFromFormat('H:i:s', $this->end_time)->setDateFrom($now);

        return $now->between($start, $end);
    }

    public function getSubjectColorAttribute(): string
    {
        $colors = [
            'physics' => '#3B82F6', 'chemistry' => '#10B981', 'mathematics' => '#F59E0B',
            'math' => '#F59E0B', 'biology' => '#F97316', 'english' => '#8B5CF6',
            'ict' => '#14B8A6', 'bangla' => '#EF4444', 'বাংলা' => '#EF4444',
            'higher math' => '#6366F1', 'higher mathematics' => '#6366F1',
            'accounting' => '#EC4899', 'finance' => '#EC4899',
            'economics' => '#84CC16', 'history' => '#78716C',
            'geography' => '#06B6D4', 'social science' => '#A855F7',
            'islamic studies' => '#059669', 'religious studies' => '#059669',
        ];
        $name = strtolower($this->subject->name ?? '');
        foreach ($colors as $key => $color) {
            if (str_contains($name, $key)) return $color;
        }
        // Auto-assign from palette based on subject ID hash
        $palette = ['#3B82F6', '#10B981', '#F59E0B', '#F97316', '#8B5CF6', '#14B8A6', '#EF4444', '#6366F1', '#EC4899', '#84CC16'];
        $index = abs(crc32($this->subject_id ?? '0')) % count($palette);
        return $palette[$index];
    }

    public function getDurationAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) return '—';
        $start = Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = Carbon::createFromFormat('H:i:s', $this->end_time);
        $minutes = $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    }
}
