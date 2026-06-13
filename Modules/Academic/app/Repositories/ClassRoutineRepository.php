<?php

namespace Modules\Academic\app\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Academic\app\Models\ClassRoutine;

class ClassRoutineRepository
{
    /**
     * Get paginated list of routines with filters.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ClassRoutine::query()->with([
            'batch', 'course', 'class', 'section', 'group',
            'subject', 'teacher.user', 'room', 'creator',
        ]);

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    /**
     * Get all routines matching filters (no pagination).
     */
    public function getAll(array $filters = []): Collection
    {
        $query = ClassRoutine::query()->with([
            'batch', 'course', 'class', 'section', 'group',
            'subject', 'teacher.user', 'room',
        ]);

        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Find a routine by ID with all relations.
     */
    public function findById(string $id): ?ClassRoutine
    {
        return ClassRoutine::with([
            'batch', 'course', 'class', 'section', 'group',
            'subject', 'teacher.user', 'room', 'creator', 'exceptions',
        ])->find($id);
    }

    /**
     * Create a new routine.
     */
    public function create(array $data): ClassRoutine
    {
        return ClassRoutine::create($data);
    }

    /**
     * Update an existing routine.
     */
    public function update(ClassRoutine $routine, array $data): ClassRoutine
    {
        $routine->update($data);
        return $routine->fresh([
            'batch', 'course', 'class', 'section', 'group',
            'subject', 'teacher.user', 'room',
        ]);
    }

    /**
     * Delete a routine (soft delete).
     */
    public function delete(ClassRoutine $routine): bool
    {
        return $routine->delete();
    }

    /**
     * Force delete a routine.
     */
    public function forceDelete(ClassRoutine $routine): bool
    {
        return $routine->forceDelete();
    }

    /**
     * Bulk insert routines.
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
     * Get routines grouped by day_of_week for a specific level.
     */
    public function getWeeklyGrid(string $level, string $levelId, array $filters = []): array
    {
        $query = ClassRoutine::with([
            'subject', 'teacher.user', 'room', 'batch', 'course', 'class',
        ])->published();

        $query = match ($level) {
            'batch' => $query->where('batch_id', $levelId),
            'course' => $query->where('course_id', $levelId)
                ->orWhereHas('batch', fn($q) => $q->where('course_id', $levelId)),
            'class' => $query->where('class_id', $levelId),
            default => $query,
        };

        if (!empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        $routines = $query->orderBy('start_time')->get();

        $days = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
        $grid = [];
        foreach ($days as $day) {
            $grid[$day] = $routines->filter(fn($r) => $r->day_of_week === $day)->values();
        }

        return $grid;
    }

    /**
     * Get multi-batch grid: routines grouped by time slot then day, with batch stacking.
     * Returns a structured grid where each cell can contain multiple routine cards (one per batch).
     */
    public function getMultiBatchWeeklyGrid(array $batchIds, array $filters = []): array
    {
        $query = ClassRoutine::with([
            'subject', 'teacher.user', 'room', 'batch', 'course', 'class',
        ])->whereIn('batch_id', $batchIds)
            ->notOffDay();

        // Apply status filter if explicitly provided; otherwise show all statuses
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }

        $routines = $query->orderBy('start_time')->orderBy('display_order')->get();

        // Separate lunch breaks and regular slots
        $lunchBreaks = $routines->filter(fn($r) => $r->is_lunch_break);
        $regularRoutines = $routines->filter(fn($r) => !$r->is_lunch_break);

        // Build time slots from unique start_time-end_time combinations
        $timeSlots = $regularRoutines->map(fn($r) => [
            'start' => $r->start_time,
            'end' => $r->end_time,
        ])->unique(function ($slot) {
            return $slot['start'] . '-' . $slot['end'];
        })->sortBy('start')->values();

        $days = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];
        $dayNames = [
            'sat' => 'Saturday', 'sun' => 'Sunday', 'mon' => 'Monday',
            'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday',
        ];

        $grid = [];

        // Build time slot rows
        foreach ($timeSlots as $slot) {
            $row = [
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'time_label' => $this->formatTime($slot['start']) . ' - ' . $this->formatTime($slot['end']),
                'is_lunch_break' => false,
                'cells' => [],
            ];

            foreach ($days as $day) {
                $dayRoutines = $regularRoutines->filter(fn($r) =>
                    $r->day_of_week === $day &&
                    $r->start_time === $slot['start'] &&
                    $r->end_time === $slot['end']
                )->values();

                $row['cells'][$day] = [
                    'day' => $day,
                    'day_name' => $dayNames[$day],
                    'routines' => $dayRoutines->map(fn($r) => $this->enrichRoutine($r)),
                    'count' => $dayRoutines->count(),
                ];
            }

            $grid[] = $row;
        }

        // Build lunch break rows
        $lunchTimeSlots = $lunchBreaks->map(fn($r) => [
            'start' => $r->start_time,
            'end' => $r->end_time,
        ])->unique(function ($slot) {
            return $slot['start'] . '-' . $slot['end'];
        })->sortBy('start')->values();

        foreach ($lunchTimeSlots as $slot) {
            $row = [
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'time_label' => $this->formatTime($slot['start']) . ' - ' . $this->formatTime($slot['end']),
                'is_lunch_break' => true,
                'cells' => [],
            ];

            foreach ($days as $day) {
                $dayLunch = $lunchBreaks->filter(fn($r) =>
                    $r->day_of_week === $day &&
                    $r->start_time === $slot['start'] &&
                    $r->end_time === $slot['end']
                )->values();

                $row['cells'][$day] = [
                    'day' => $day,
                    'day_name' => $dayNames[$day],
                    'routines' => $dayLunch->map(fn($r) => $this->enrichRoutine($r)),
                    'count' => $dayLunch->count(),
                    'is_lunch' => true,
                ];
            }

            $grid[] = $row;
        }

        // Sort grid by start_time
        usort($grid, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return [
            'days' => $days,
            'day_names' => $dayNames,
            'time_slots' => $grid,
            'batch_ids' => $batchIds,
        ];
    }

    /**
     * Check for conflicts when creating/updating a routine.
     * Enhanced to also check batch conflicts.
     */
    public function findConflicts(array $data, ?string $excludeId = null): Collection
    {
        $query = ClassRoutine::where('day_of_week', $data['day_of_week'])
            ->where(function ($q) use ($data) {
                // Overlapping time range
                $q->where(function ($q2) use ($data) {
                    $q2->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>', $data['start_time']);
                });
            })
            ->where(function ($q) use ($data) {
                // Teacher conflict OR room conflict OR batch conflict
                $q->where('teacher_id', $data['teacher_id']);
                if (!empty($data['room_id'])) {
                    $q->orWhere('room_id', $data['room_id']);
                }
                if (!empty($data['batch_id'])) {
                    $q->orWhere(function ($q2) use ($data) {
                        $q2->where('batch_id', $data['batch_id']);
                    });
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'teacher.user', 'room', 'batch'])->get();
    }

    /**
     * Find batch-specific conflicts (same batch, same day, overlapping time).
     */
    public function findBatchConflicts(array $data, ?string $excludeId = null): Collection
    {
        if (empty($data['batch_id'])) {
            return new Collection();
        }

        $query = ClassRoutine::where('batch_id', $data['batch_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($q) use ($data) {
                $q->where('start_time', '<', $data['end_time'])
                    ->where('end_time', '>', $data['start_time']);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'teacher.user', 'room'])->get();
    }

    /**
     * Find all conflicts across multiple batches for a given time slot.
     */
    public function findMultiBatchConflicts(array $batchIds, string $day, string $startTime, string $endTime, ?string $excludeId = null): array
    {
        $conflicts = [];

        // Teacher conflicts across batches
        $teacherConflicts = ClassRoutine::whereIn('batch_id', $batchIds)
            ->where('day_of_week', $day)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->with(['subject', 'teacher.user', 'room', 'batch'])
            ->get()
            ->groupBy('teacher_id');

        foreach ($teacherConflicts as $teacherId => $slots) {
            if ($slots->count() > 1) {
                $conflicts[] = [
                    'type' => 'teacher',
                    'teacher_id' => $teacherId,
                    'teacher_name' => $slots->first()->teacher?->user?->name ?? 'Unknown',
                    'slots' => $slots->map(fn($s) => [
                        'id' => $s->id,
                        'batch' => $s->batch?->name,
                        'subject' => $s->subject?->name,
                        'time' => $s->start_time . ' - ' . $s->end_time,
                    ]),
                    'severity' => 'high',
                ];
            }
        }

        // Room conflicts across batches
        $roomConflicts = ClassRoutine::whereIn('batch_id', $batchIds)
            ->where('day_of_week', $day)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->whereNotNull('room_id')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->with(['subject', 'teacher.user', 'room', 'batch'])
            ->get()
            ->groupBy('room_id');

        foreach ($roomConflicts as $roomId => $slots) {
            if ($slots->count() > 1) {
                $conflicts[] = [
                    'type' => 'room',
                    'room_id' => $roomId,
                    'room_name' => $slots->first()->room?->name ?? 'Unknown',
                    'slots' => $slots->map(fn($s) => [
                        'id' => $s->id,
                        'batch' => $s->batch?->name,
                        'subject' => $s->subject?->name,
                        'time' => $s->start_time . ' - ' . $s->end_time,
                    ]),
                    'severity' => 'high',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get teacher's weekly schedule.
     */
    public function getTeacherSchedule(string $teacherId, ?string $date = null): Collection
    {
        $query = ClassRoutine::with([
            'subject', 'teacher.user', 'batch', 'course', 'class', 'section', 'room',
        ])->where('teacher_id', $teacherId)
            ->published();

        if ($date) {
            $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
            $query->where('day_of_week', $dayOfWeek);
        }

        return $query->orderBy('day_of_week')->orderBy('start_time')->get();
    }

    /**
     * Get teacher's workload analysis.
     */
    public function getTeacherLoad(string $teacherId): array
    {
        $routines = ClassRoutine::where('teacher_id', $teacherId)
            ->published()
            ->get();

        $totalSlots = $routines->count();
        $totalMinutes = 0;
        $daysActive = $routines->pluck('day_of_week')->unique()->count();

        foreach ($routines as $r) {
            if ($r->start_time && $r->end_time) {
                $start = Carbon::createFromFormat('H:i:s', $r->start_time);
                $end = Carbon::createFromFormat('H:i:s', $r->end_time);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        $byDay = $routines->groupBy('day_of_week')->map(fn($dayRoutines) => [
            'count' => $dayRoutines->count(),
            'total_minutes' => $dayRoutines->sum(function ($r) {
                if (!$r->start_time || !$r->end_time) return 0;
                $start = Carbon::createFromFormat('H:i:s', $r->start_time);
                $end = Carbon::createFromFormat('H:i:s', $r->end_time);
                return $start->diffInMinutes($end);
            }),
        ]);

        return [
            'teacher_id' => $teacherId,
            'total_slots' => $totalSlots,
            'total_hours' => round($totalMinutes / 60, 1),
            'total_minutes' => $totalMinutes,
            'days_active' => $daysActive,
            'average_slots_per_day' => $daysActive > 0 ? round($totalSlots / $daysActive, 1) : 0,
            'by_day' => $byDay,
        ];
    }

    /**
     * Get room utilization statistics.
     */
    public function getRoomUtilization(string $roomId): array
    {
        $routines = ClassRoutine::where('room_id', $roomId)
            ->published()
            ->get();

        $totalSlots = $routines->count();
        $totalMinutes = 0;

        foreach ($routines as $r) {
            if ($r->start_time && $r->end_time) {
                $start = Carbon::createFromFormat('H:i:s', $r->start_time);
                $end = Carbon::createFromFormat('H:i:s', $r->end_time);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        $byDay = $routines->groupBy('day_of_week')->map(fn($dayRoutines) => [
            'count' => $dayRoutines->count(),
            'total_minutes' => $dayRoutines->sum(function ($r) {
                if (!$r->start_time || !$r->end_time) return 0;
                $start = Carbon::createFromFormat('H:i:s', $r->start_time);
                $end = Carbon::createFromFormat('H:i:s', $r->end_time);
                return $start->diffInMinutes($end);
            }),
        ]);

        return [
            'room_id' => $roomId,
            'total_slots' => $totalSlots,
            'total_hours' => round($totalMinutes / 60, 1),
            'total_minutes' => $totalMinutes,
            'days_used' => $routines->pluck('day_of_week')->unique()->count(),
            'by_day' => $byDay,
        ];
    }

    /**
     * Get student's routine based on their enrollments.
     */
    public function getStudentRoutine(string $studentId): Collection
    {
        return ClassRoutine::with([
            'subject', 'teacher.user', 'room', 'batch', 'course',
        ])->published()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get all distinct versions for a level.
     */
    public function getVersions(string $level, string $levelId): Collection
    {
        return ClassRoutine::where(match ($level) {
            'batch' => 'batch_id',
            'course' => 'course_id',
            'class' => 'class_id',
            default => 'id',
        }, $levelId)
            ->selectRaw('version, COUNT(*) as slot_count, MAX(created_at) as last_updated')
            ->groupBy('version')
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Get off days for a set of batches.
     */
    public function getOffDays(array $batchIds): Collection
    {
        return ClassRoutine::whereIn('batch_id', $batchIds)
            ->where('is_off_day', true)
            ->with('batch')
            ->get();
    }

    /**
     * Apply common filters to query.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }
        if (!empty($filters['batch_ids'])) {
            $query->whereIn('batch_id', (array) $filters['batch_ids']);
        }
        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }
        if (!empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
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
        if (!empty($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['status_except'])) {
            $query->where('status', '!=', $filters['status_except']);
        }
        if (!empty($filters['academic_session_id'])) {
            $query->where('academic_session_id', $filters['academic_session_id']);
        }
        if (!empty($filters['is_lunch_break'])) {
            $query->where('is_lunch_break', filter_var($filters['is_lunch_break'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['is_off_day'])) {
            $query->where('is_off_day', filter_var($filters['is_off_day'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['search'])) {
            $query->whereHas('subject', fn($q) => $q->where('name', 'LIKE', "%{$filters['search']}%"));
        }

        return $query;
    }

    /**
     * Format time string to 12-hour format.
     */
    private function formatTime(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('h:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * Enrich routine with formatted data for the grid.
     */
    private function enrichRoutine(ClassRoutine $routine): array
    {
        // Build teacher name with robust fallback chain
        $teacherName = 'N/A';
        if ($routine->teacher) {
            $teacher = $routine->teacher;
            // Direct string concatenation as first resort (most reliable)
            $directName = trim(($teacher->first_name ?? '') . ' ' . ($teacher->last_name ?? ''));
            $teacherName = $directName ?: (
                $teacher->user?->name
                ?? $teacher->full_name
                ?? $teacher->name
                ?? 'N/A'
            );
        }

        return [
            'id' => $routine->id,
            'batch_id' => $routine->batch_id,
            'batch_name' => $routine->batch?->name,
            'batch_code' => $routine->batch?->code,
            'course_id' => $routine->course_id,
            'course_name' => $routine->course?->name,
            'subject_id' => $routine->subject_id,
            'subject_name' => $routine->subject?->name,
            'subject_color' => $routine->subject_color,
            'teacher_id' => $routine->teacher_id,
            'teacher_name' => $teacherName,
            'room_id' => $routine->room_id,
            'room_name' => $routine->room?->name,
            'day_of_week' => $routine->day_of_week,
            'day_name' => $routine->day_name,
            'start_time' => $routine->start_time,
            'end_time' => $routine->end_time,
            'time_formatted' => $routine->time_formatted,
            'status' => $routine->status,
            'is_live' => $routine->is_live_now,
            'slot_name' => $routine->slot_name,
            'duration' => $routine->duration,
            'duration_formatted' => $routine->duration_formatted,
            'is_lunch_break' => $routine->is_lunch_break,
            'display_order' => $routine->display_order,
            'notes' => $routine->notes,
        ];
    }
}
