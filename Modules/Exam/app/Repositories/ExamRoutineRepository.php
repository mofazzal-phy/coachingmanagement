<?php

namespace Modules\Exam\app\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;

class ExamRoutineRepository
{
    public function resolveDeliveryMode(?string $examId, ?string $deliveryMode = null): string
    {
        if (in_array($deliveryMode, ['online', 'offline', 'hybrid'], true)) {
            return $deliveryMode;
        }

        if ($examId) {
            $examMode = Exam::where('id', $examId)->value('delivery_mode');
            if (in_array($examMode, ['online', 'offline', 'hybrid'], true)) {
                return $examMode;
            }
        }

        return 'offline';
    }

    /**
     * Default delivery channel for a newly created routine slot (offline unless explicitly set).
     */
    public function resolveNewRoutineDeliveryMode(?string $deliveryMode = null): string
    {
        return in_array($deliveryMode, ['online', 'offline', 'hybrid'], true)
            ? $deliveryMode
            : 'offline';
    }

    public function isPracticeExam(?string $examId): bool
    {
        if (!$examId) {
            return false;
        }

        return (bool) Exam::where('id', $examId)->value('is_practice');
    }

    /**
     * Conflict scope: offline schedules never clash with online schedules.
     *
     * @param  Builder<\Modules\Exam\app\Models\ExamRoutine>  $query
     * @param  'offline'|'online'  $channel
     */
    private function applyDeliveryChannelConflictScope(Builder $query, string $channel): void
    {
        $query->whereHas('exam', fn (Builder $q) => $q->where('is_practice', false));

        if ($channel === 'online') {
            $query->whereIn('delivery_mode', ['online', 'hybrid']);
        } else {
            $query->where(function (Builder $q) {
                $q->where('delivery_mode', 'offline')
                    ->orWhereNull('delivery_mode')
                    ->orWhere('delivery_mode', '');
            });
        }
    }

    /**
     * Get paginated list of exam routines with filters.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ExamRoutine::query()->with([
            'exam.examType', 'subject', 'batch', 'course', 'class',
            'group', 'room', 'teacher.user', 'creator',
        ]);

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    /**
     * Get all exam routines matching filters (no pagination).
     */
    public function getAll(array $filters = []): Collection
    {
        $query = ExamRoutine::query()->with([
            'exam.examType', 'subject', 'batch', 'course', 'class',
            'group', 'room', 'teacher.user',
        ]);

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Find an exam routine by ID with all relations.
     */
    public function findById(string $id): ?ExamRoutine
    {
        return ExamRoutine::with([
            'exam.examType', 'subject', 'batch', 'course', 'class',
            'group', 'room', 'teacher.user', 'creator', 'results.student',
        ])->find($id);
    }

    /**
     * Create a new exam routine.
     */
    public function create(array $data): ExamRoutine
    {
        $conflicts = $this->findBatchTimeConflict($data);
        if ($conflicts->isNotEmpty()) {
            $existing = $conflicts->first();
            $subject = $existing->subject?->name ?? 'another subject';
            $examName = $existing->exam?->name ?? 'another exam';
            $liveNote = ($existing->status === 'published' || $existing->status === 'completed')
                ? ' (already published)'
                : '';
            throw new \InvalidArgumentException(
                "This batch already has \"{$subject}\" in "
                . ($examName ?: 'another exam')
                . "{$liveNote} at an overlapping time on this date. Pick a different time slot."
            );
        }

        $data['delivery_mode'] = $this->resolveNewRoutineDeliveryMode($data['delivery_mode'] ?? null);

        return ExamRoutine::create($data);
    }

    /**
     * Update an existing exam routine.
     */
    public function update(ExamRoutine $routine, array $data): ExamRoutine
    {
        $merged = array_merge($routine->only([
            'exam_date', 'start_time', 'end_time', 'batch_id', 'subject_id', 'exam_id', 'delivery_mode',
        ]), $data);

        if (array_key_exists('duration_minutes', $data) && !empty($data['duration_minutes'])) {
            $routine->fill($data);
            $computedEnd = $routine->computeEndTimeFromDuration((int) $data['duration_minutes']);
            if ($computedEnd) {
                $data['end_time'] = $computedEnd;
                $merged['end_time'] = $computedEnd;
            }
        }

        if (array_key_exists('delivery_mode', $data)) {
            $data['delivery_mode'] = $this->resolveNewRoutineDeliveryMode($data['delivery_mode']);
            $merged['delivery_mode'] = $data['delivery_mode'];
        }

        $conflicts = $this->findBatchTimeConflict($merged, $routine->id);
        if ($conflicts->isNotEmpty()) {
            $existing = $conflicts->first();
            throw new \InvalidArgumentException(
                'Batch time conflict with '
                . ($existing->exam?->name ?? 'another exam')
                . ' (' . ($existing->subject?->name ?? 'subject') . ') at the same slot.'
            );
        }

        $routine->update($data);
        return $routine->fresh([
            'exam.examType', 'subject', 'batch', 'course', 'class',
            'group', 'room', 'teacher.user',
        ]);
    }

    /**
     * Delete an exam routine (soft delete).
     */
    public function delete(ExamRoutine $routine): bool
    {
        return $routine->delete();
    }

    /**
     * Force delete an exam routine.
     */
    public function forceDelete(ExamRoutine $routine): bool
    {
        return $routine->forceDelete();
    }

    /**
     * Bulk insert exam routines.
     */
    public function bulkCreate(array $routines): Collection
    {
        $models = new Collection();
        foreach ($routines as $data) {
            $models->push($this->create($data));
        }
        return $models;
    }

    /**
     * Get exam routines grouped by exam_date for a specific exam.
     */
    public function getByExam(string $examId, array $filters = []): Collection
    {
        $query = ExamRoutine::with([
            'subject', 'teacher.user', 'room', 'batch', 'course', 'class', 'examType', 'exam',
        ])->where('exam_id', $examId);

        if (!($filters['include_cancelled'] ?? false)) {
            $query->where('status', '!=', 'cancelled');
        }

        if (!($filters['include_practice'] ?? false)) {
            $this->excludePracticeExams($query);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->whereHas('batch.course', function ($q) use ($filters) {
                $q->where('class_id', $filters['class_id']);
            });
        }

        if (!empty($filters['course_id'])) {
            $query->whereHas('batch', function ($q) use ($filters) {
                $q->where('course_id', $filters['course_id']);
            });
        }

        if (!empty($filters['exam_type_id'])) {
            $query->where('exam_type_id', $filters['exam_type_id']);
        }

        $this->applyDeliveryChannelFilter($query, $filters['delivery_channel'] ?? null);

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get routines grouped by date for a specific level.
     */
    public function getByLevel(string $level, string $levelId, array $filters = []): Collection
    {
        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'teacher.user', 'room', 'batch', 'course', 'class',
        ]);

        $query = match ($level) {
            'batch' => $query->where('batch_id', $levelId),
            'course' => $query->where('course_id', $levelId)
                ->orWhereHas('batch', fn($q) => $q->where('course_id', $levelId)),
            'class' => $query->where('class_id', $levelId),
            default => $query,
        };

        if (!empty($filters['exam_id'])) {
            $query->where('exam_id', $filters['exam_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('exam_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('exam_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get routines grouped by date for calendar view.
     */
    public function getCalendarData(string $examId, ?string $month = null, ?string $year = null): array
    {
        $query = ExamRoutine::with([
            'subject', 'teacher.user', 'room',
        ])->where('exam_id', $examId)
            ->published();

        if ($month && $year) {
            $query->whereMonth('exam_date', $month)
                ->whereYear('exam_date', $year);
        }

        $routines = $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $calendar = [];
        foreach ($routines as $routine) {
            $date = $routine->exam_date->format('Y-m-d');
            if (!isset($calendar[$date])) {
                $calendar[$date] = [];
            }
            $calendar[$date][] = [
                'id' => $routine->id,
                'subject' => $routine->subject?->name ?? 'N/A',
                'subject_id' => $routine->subject_id,
                'start_time' => $routine->start_time_formatted,
                'end_time' => $routine->end_time_formatted,
                'room' => $routine->room?->name ?? 'TBD',
                'teacher' => $routine->teacher?->user?->name
                    ?? $routine->teacher?->full_name
                    ?? 'TBD',
                'status' => $routine->status,
                '_color' => $routine->subject_color,
            ];
        }

        return $calendar;
    }

    /**
     * Same batch cannot have overlapping routines at the same time (same delivery channel).
     * Different batches may overlap; same batch may repeat a subject on another time slot.
     * Practice exams are exempt. Online + offline at the same slot is allowed.
     */
    public function findBatchTimeConflict(array $data, ?string $excludeId = null): Collection
    {
        if (empty($data['batch_id']) || empty($data['exam_date']) || empty($data['start_time']) || empty($data['end_time'])) {
            return collect();
        }

        $examId = $data['exam_id'] ?? null;
        if ($this->isPracticeExam($examId)) {
            return collect();
        }

        $deliveryMode = $this->resolveNewRoutineDeliveryMode($data['delivery_mode'] ?? null);
        $channel = ExamRoutine::resolveDeliveryChannel($deliveryMode);

        $query = ExamRoutine::where('batch_id', $data['batch_id'])
            ->where('exam_date', $data['exam_date'])
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('exam', fn ($q) => $q->where('is_practice', false))
            ->where(function ($q) use ($data) {
                $q->where('start_time', '<', $data['end_time'])
                    ->where('end_time', '>', $data['start_time']);
            });

        $this->applyDeliveryChannelConflictScope($query, $channel);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'batch', 'exam'])->get();
    }

    /** @deprecated Use findBatchTimeConflict */
    public function findDuplicateSlot(array $data, ?string $excludeId = null): Collection
    {
        return $this->findBatchTimeConflict($data, $excludeId);
    }

    /**
     * Check for conflicts when creating/updating an exam routine.
     */
    public function findConflicts(array $data, ?string $excludeId = null): Collection
    {
        $query = ExamRoutine::where('exam_date', $data['exam_date'])
            ->where(function ($q) use ($data) {
                // Overlapping time range
                $q->where(function ($q2) use ($data) {
                    $q2->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>', $data['start_time']);
                });
            })
            ->where(function ($q) use ($data) {
                $hasTeacher = !empty($data['teacher_id']);
                $hasRoom = !empty($data['room_id']);

                if ($hasTeacher) {
                    $q->where('teacher_id', $data['teacher_id']);
                }
                if ($hasRoom) {
                    $hasTeacher ? $q->orWhere('room_id', $data['room_id']) : $q->where('room_id', $data['room_id']);
                }
                if (!$hasTeacher && !$hasRoom) {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'teacher.user', 'room'])->get();
    }

    /**
     * Get teacher's exam schedule.
     */
    public function getTeacherSchedule(string $teacherId, ?string $date = null): Collection
    {
        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'batch', 'course', 'class', 'room',
        ])->where('teacher_id', $teacherId)
            ->published();

        $this->excludePracticeExams($query);

        if ($date) {
            $query->where('exam_date', $date);
        }

        return $query->orderBy('exam_date')->orderBy('start_time')->get();
    }

    /**
     * Get student's exam routines based on their enrollments.
     */
    public function getStudentRoutines(string $studentId): Collection
    {
        // Get the student's enrolled batch IDs
        $batchIds = \Modules\Enrollment\app\Models\Enrollment::where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->unique()
            ->values()
            ->toArray();

        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'room', 'batch', 'course',
        ])->published()
            ->whereIn('batch_id', $batchIds);

        $this->excludePracticeExams($query);

        $query->whereHas('exam', fn (Builder $q) => $q->where('status', 'published'))
            ->offlineChannel();

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get upcoming exam routines for a student.
     */
    public function getUpcomingForStudent(string $studentId, int $limit = 5): Collection
    {
        // Get the student's enrolled batch IDs
        $batchIds = \Modules\Enrollment\app\Models\Enrollment::where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->unique()
            ->values()
            ->toArray();

        $now = now();

        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'room',
        ])->published()
            ->whereIn('batch_id', $batchIds);

        $this->excludePracticeExams($query);

        $query->whereHas('exam', fn (Builder $q) => $q->where('status', 'published'))
            ->offlineChannel();

        return $query->where(function ($q) use ($now) {
                $q->where('exam_date', '>', $now->format('Y-m-d'))
                    ->orWhere(function ($q2) use ($now) {
                        $q2->where('exam_date', $now->format('Y-m-d'))
                            ->where('end_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Get today's exam routines for a teacher.
     */
    public function getTodayForTeacher(string $teacherId): Collection
    {
        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'batch', 'course', 'class', 'room',
        ])->where('teacher_id', $teacherId)
            ->where('exam_date', now()->format('Y-m-d'));

        $this->excludePracticeExams($query);

        return $query->orderBy('start_time')->get();
    }

    /**
     * Get exam routines for a teacher's subjects (not just duties).
     * This finds routines where the subject_id matches subjects the teacher teaches.
     */
    public function getRoutinesByTeacherSubjects(string $teacherId, bool $restrictToTeacherBatches = true): Collection
    {
        $teacher = \Modules\Teacher\app\Models\Teacher::where('id', $teacherId)->first();
        if (!$teacher) {
            return new Collection([]);
        }

        // Get the subject IDs this teacher teaches (from subject_teacher pivot)
        $subjectIds = $teacher->subjects()->pluck('subjects.id')->toArray();

        $batchIds = [];
        if ($restrictToTeacherBatches) {
            // Batches where this teacher is coordinator, plus batches from active enrollments
            // where they are assigned as subject teacher.
            $batchIds = \Modules\Enrollment\app\Models\Batch::where('teacher_id', $teacherId)
                ->pluck('id')
                ->merge(
                    \Modules\Enrollment\app\Models\Enrollment::query()
                        ->where('status', 'active')
                        ->whereHas('subjects', fn ($q) => $q->where('enrollment_subject.teacher_id', $teacherId))
                        ->pluck('batch_id')
                )
                ->unique()
                ->filter()
                ->values()
                ->all();
        }

        $query = ExamRoutine::with([
            'exam.examType', 'subject', 'room', 'batch', 'course', 'class', 'group',
        ])->published();

        $this->excludePracticeExams($query);

        if (empty($subjectIds)) {
            $query->where('teacher_id', $teacherId);
        } else {
            $query->where(function ($q) use ($subjectIds, $teacherId) {
                $q->where('teacher_id', $teacherId)
                    ->orWhereIn('subject_id', $subjectIds);
            });
        }

        // Subject teachers are scoped to their batches; duty-only teachers match by teacher_id only.
        if ($restrictToTeacherBatches && !empty($batchIds) && !empty($subjectIds)) {
            $query->whereIn('batch_id', $batchIds);
        }

        return $query->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  Builder<\Modules\Exam\app\Models\ExamRoutine>  $query
     */
    private function applyDeliveryChannelFilter(Builder $query, ?string $channel): void
    {
        if ($channel === 'offline') {
            $query->where(function (Builder $q) {
                $q->where('delivery_mode', 'offline')
                    ->orWhereNull('delivery_mode')
                    ->orWhere('delivery_mode', '');
            });
        } elseif ($channel === 'online') {
            $query->whereIn('delivery_mode', ['online', 'hybrid']);
        }
    }

    /**
     * Official exam routines only — practice exams never appear on routine grids.
     */
    private function excludePracticeExams(Builder $query): Builder
    {
        return $query->whereHas('exam', function (Builder $q) {
            $q->where('is_practice', false);
        });
    }

    /**
     * Apply common filters to query.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!($filters['include_practice'] ?? false)) {
            $this->excludePracticeExams($query);
        }
        if (!empty($filters['exam_id'])) {
            $query->where('exam_id', $filters['exam_id']);
        }
        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }
        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }
        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }
        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['status_except'])) {
            $query->where('status', '!=', $filters['status_except']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('exam_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('exam_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['search'])) {
            $query->whereHas('subject', fn($q) => $q->where('name', 'LIKE', "%{$filters['search']}%"));
        }

        return $query;
    }
}
