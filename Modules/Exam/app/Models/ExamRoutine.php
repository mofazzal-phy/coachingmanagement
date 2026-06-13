<?php

namespace Modules\Exam\app\Models;

use App\Models\User;
use Modules\Core\app\Models\BaseModel;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Teacher\app\Models\Teacher;

class ExamRoutine extends BaseModel
{
    protected $fillable = [
        'exam_id', 'subject_id', 'exam_type_id',
        'batch_id', 'course_id', 'class_id', 'group_id',
        'exam_date', 'start_time', 'end_time',
        'room_id', 'teacher_id',
        'total_marks', 'pass_marks', 'mark_config', 'instructions',
        'duration_minutes', 'randomize_questions',
        'status', 'delivery_mode', 'created_by',
    ];

    public function effectiveDeliveryMode(): string
    {
        $mode = $this->delivery_mode ?? $this->exam?->delivery_mode ?? 'offline';

        return in_array($mode, ['online', 'offline', 'hybrid'], true) ? $mode : 'offline';
    }

    /**
     * Grid / conflict channel — offline vs online are fully isolated schedules.
     * Uses the routine column only (not exam-level delivery_mode).
     */
    public function deliveryChannel(): string
    {
        $mode = $this->delivery_mode ?: 'offline';

        return in_array($mode, ['online', 'hybrid'], true) ? 'online' : 'offline';
    }

    public static function resolveDeliveryChannel(?string $deliveryMode): string
    {
        return in_array($deliveryMode, ['online', 'hybrid'], true) ? 'online' : 'offline';
    }

    public function isOnlineDelivery(): bool
    {
        return $this->deliveryChannel() === 'online';
    }

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'mark_config' => 'array',
        'randomize_questions' => 'boolean',
    ];

    protected array $searchable = [];

    protected array $filterable = [
        'status', 'exam_id', 'subject_id',
        'batch_id', 'course_id', 'class_id',
        'teacher_id', 'room_id',
    ];

    // ========== Relationships ==========

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Subject::class);
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

    public function group()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\AcademicGroup::class);
    }

    public function room()
    {
        return $this->belongsTo(\Modules\Academic\app\Models\Room::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function routineQuestions()
    {
        return $this->hasMany(ExamRoutineQuestion::class);
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_routine_questions')
            ->withPivot(['sort_order', 'marks_override'])
            ->orderByPivot('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    // ========== Scopes ==========

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }

    public function scopeByBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeByClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeOfflineChannel($query)
    {
        return $query->where(function ($q) {
            $q->where('delivery_mode', 'offline')
                ->orWhereNull('delivery_mode')
                ->orWhere('delivery_mode', '');
        });
    }

    public function scopeOnlineChannel($query)
    {
        return $query->whereIn('delivery_mode', ['online', 'hybrid']);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('exam_date', $date);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('exam_date', '>=', now()->toDateString())
            ->where('status', 'published');
    }

    public function scopeToday($query)
    {
        return $query->where('exam_date', now()->toDateString());
    }

    // ========== Time helpers ==========

    public function timeToString(mixed $value): string
    {
        if (!$value) {
            return '00:00:00';
        }

        if (\is_string($value)) {
            return \strlen($value) >= 8 ? \substr($value, 0, 8) : $value . ':00';
        }

        return $value->format('H:i:s');
    }

    public function windowStartAt(): ?\Carbon\Carbon
    {
        if (!$this->exam_date || !$this->start_time) {
            return null;
        }

        $date = $this->exam_date->format('Y-m-d');

        return \Carbon\Carbon::parse($date . ' ' . $this->timeToString($this->start_time));
    }

    /**
     * Effective exam window end — prefers duration_minutes over stored end_time.
     */
    public function windowEndAt(): ?\Carbon\Carbon
    {
        $start = $this->windowStartAt();
        if (!$start) {
            return null;
        }

        if ($this->duration_minutes) {
            return $start->copy()->addMinutes((int) $this->duration_minutes);
        }

        if (!$this->end_time) {
            return null;
        }

        $date = $this->exam_date->format('Y-m-d');
        $end = \Carbon\Carbon::parse($date . ' ' . $this->timeToString($this->end_time));
        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Whether this routine's exam window overlaps another on the same batch.
     * Uses exam_date + time — not raw start_time/end_time column comparison.
     */
    public function overlapsScheduleWith(self $other): bool
    {
        if (!$this->batch_id || $this->batch_id !== $other->batch_id) {
            return false;
        }

        if ($this->deliveryChannel() !== $other->deliveryChannel()) {
            return false;
        }

        $start1 = $this->windowStartAt();
        $end1 = $this->windowEndAt();
        $start2 = $other->windowStartAt();
        $end2 = $other->windowEndAt();

        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        return $start1->lt($end2) && $end1->gt($start2);
    }

    public function effectiveDurationMinutes(): int
    {
        if ($this->duration_minutes) {
            return max(1, (int) $this->duration_minutes);
        }

        $start = $this->windowStartAt();
        $end = $this->windowEndAt();
        if (!$start || !$end) {
            return 60;
        }

        return max(1, (int) $start->diffInMinutes($end));
    }

    public function computeEndTimeFromDuration(?int $durationMinutes = null): ?string
    {
        $minutes = $durationMinutes ?? $this->duration_minutes;
        if (!$minutes || !$this->start_time || !$this->exam_date) {
            return null;
        }

        $start = $this->windowStartAt();

        return $start?->copy()->addMinutes((int) $minutes)->format('H:i:s');
    }

    // ========== Accessors ==========

    public function getStartTimeFormattedAttribute(): string
    {
        if (!$this->start_time) return '--:--';
        return \Carbon\Carbon::parse($this->start_time)->format('h:i A');
    }

    public function getEndTimeFormattedAttribute(): string
    {
        $end = $this->windowEndAt();
        if (!$end) return '--:--';
        return $end->format('h:i A');
    }

    public function getDurationAttribute(): string
    {
        $minutes = $this->effectiveDurationMinutes();
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return $hours . 'h ' . $mins . 'm';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }

        return $mins . 'm';
    }

    /**
     * Resolve a routine for mark entry by exam, subject, and optional class scope.
     */
    public static function resolveForMarkEntry(string $examId, string $subjectId, ?string $classId = null): ?self
    {
        return static::query()
            ->where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->orderByDesc('created_at')
            ->first();
    }
}
