<?php

namespace Modules\Academic\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Academic\app\Repositories\ClassRoutineRepository;
use Modules\Teacher\app\Models\Teacher;

class ClassRoutineService
{
    const SUBJECT_COLORS = [
        'physics' => '#3B82F6', 'chemistry' => '#10B981', 'mathematics' => '#F59E0B',
        'math' => '#F59E0B', 'biology' => '#F97316', 'english' => '#8B5CF6',
        'ict' => '#14B8A6', 'bangla' => '#EF4444',
        'higher math' => '#6366F1', 'accounting' => '#EC4899',
        'finance' => '#EC4899', 'economics' => '#84CC16',
        'history' => '#78716C', 'geography' => '#06B6D4',
        'social science' => '#A855F7', 'islamic studies' => '#059669',
    ];

    const BATCH_COLORS = [
        '#6366F1', '#EC4899', '#14B8A6', '#F97316',
        '#84CC16', '#8B5CF6', '#06B6D4', '#F43F5E',
    ];

    const DAYS_ORDER = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    public function __construct(
        private ClassRoutineRepository $repository
    ) {}

    /**
     * Smart auto-generation of class routines.
     */
    public function generate(array $data): array
    {
        $level = $data['level'] ?? 'batch';
        $levelId = $data['level_id'] ?? null;
        $subjectIds = $data['subject_ids'] ?? [];
        $constraints = $data['constraints'] ?? [];
        $days = $constraints['days'] ?? ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'];
        $maxPerDay = min((int)($constraints['max_per_day'] ?? 8), 12);
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $apply = $data['apply'] ?? false;
        $batchIds = $data['batch_ids'] ?? [];

        if (empty($levelId) && empty($batchIds) || empty($subjectIds)) {
            return ['error' => 'Level ID (or batch IDs) and subjects are required', 'generated' => []];
        }

        // Get teachers who teach these subjects
        $teachers = Teacher::whereHas('subjects', fn($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('user')
            ->get();

        if ($teachers->isEmpty()) {
            return ['error' => 'No teachers found for selected subjects', 'generated' => []];
        }

        // Get subject details
        $subjects = \Modules\Academic\app\Models\Subject::whereIn('id', $subjectIds)->get();

        // Calculate slots per subject
        $totalSubjects = $subjects->count();
        $totalSlots = count($days) * $maxPerDay;
        $baseSlotsPerSubject = intdiv($totalSlots, $totalSubjects);
        $remainder = $totalSlots % $totalSubjects;

        $subjectSlots = [];
        foreach ($subjects as $i => $subject) {
            $subjectSlots[$subject->id] = $baseSlotsPerSubject + ($i < $remainder ? 1 : 0);
        }

        // Build time slots
        $timeSlots = $this->generateTimeSlots($maxPerDay);

        // Track assignments for conflict detection
        $teacherSchedule = [];
        $roomSchedule = [];
        $daySlotCount = [];

        $generated = [];
        $warnings = [];
        $teacherWorkload = [];

        foreach ($teachers as $t) {
            $teacherWorkload[$t->id] = 0;
        }

        // Round-robin distribution
        $subjectQueue = [];
        foreach ($subjects as $subject) {
            for ($i = 0; $i < $subjectSlots[$subject->id]; $i++) {
                $subjectQueue[] = $subject->id;
            }
        }
        shuffle($subjectQueue);

        // Determine which batch IDs to use
        $targetBatchIds = $batchIds;
        if (empty($targetBatchIds) && $level === 'batch' && $levelId) {
            $targetBatchIds = [$levelId];
        }

        $slotIndex = 0;
        foreach ($days as $day) {
            $daySlotCount[$day] = 0;
            for ($s = 0; $s < $maxPerDay && $slotIndex < count($subjectQueue); $s++) {
                $subjectId = $subjectQueue[$slotIndex];
                $subject = $subjects->firstWhere('id', $subjectId);
                if (!$subject) {
                    $slotIndex++;
                    continue;
                }

                $timeSlot = $timeSlots[$s] ?? null;
                if (!$timeSlot) break;

                // For multi-batch generation, assign to each batch
                if (!empty($targetBatchIds)) {
                    foreach ($targetBatchIds as $batchId) {
                        $teacher = $this->findBestTeacher(
                            teachers: $teachers,
                            subjectId: $subjectId,
                            day: $day,
                            startTime: $timeSlot['start'],
                            endTime: $timeSlot['end'],
                            teacherSchedule: $teacherSchedule,
                            teacherWorkload: $teacherWorkload,
                        );

                        if (!$teacher) {
                            $warnings[] = "No available teacher for {$subject->name} on {$day} at {$timeSlot['start']}";
                            continue;
                        }

                        $teacherSchedule[$teacher->id][$day][] = [
                            'start' => $timeSlot['start'],
                            'end' => $timeSlot['end'],
                        ];
                        $teacherWorkload[$teacher->id]++;
                        $daySlotCount[$day]++;

                        $generated[] = [
                            'batch_id' => $batchId,
                            'course_id' => null,
                            'class_id' => null,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacher->id,
                            'room_id' => null,
                            'day_of_week' => $day,
                            'start_time' => $timeSlot['start'],
                            'end_time' => $timeSlot['end'],
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'version' => 1,
                            'status' => 'draft',
                            'created_by' => Auth::id(),
                            'slot_name' => 'Period ' . ($s + 1),
                            'duration' => $this->calculateDuration($timeSlot['start'], $timeSlot['end']),
                            '_subject_name' => $subject->name,
                            '_teacher_name' => $teacher->user?->name ?? $teacher->first_name ?? 'Unknown',
                            '_color' => $this->getSubjectColor($subject->name),
                        ];
                    }
                } else {
                    // Single level generation (existing behavior)
                    $teacher = $this->findBestTeacher(
                        teachers: $teachers,
                        subjectId: $subjectId,
                        day: $day,
                        startTime: $timeSlot['start'],
                        endTime: $timeSlot['end'],
                        teacherSchedule: $teacherSchedule,
                        teacherWorkload: $teacherWorkload,
                    );

                    if (!$teacher) {
                        $warnings[] = "No available teacher for {$subject->name} on {$day} at {$timeSlot['start']}";
                        $slotIndex++;
                        continue;
                    }

                    $teacherSchedule[$teacher->id][$day][] = [
                        'start' => $timeSlot['start'],
                        'end' => $timeSlot['end'],
                    ];
                    $teacherWorkload[$teacher->id]++;
                    $daySlotCount[$day]++;

                    $generated[] = [
                        'batch_id' => $level === 'batch' ? $levelId : null,
                        'course_id' => $level === 'course' ? $levelId : null,
                        'class_id' => $level === 'class' ? $levelId : null,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacher->id,
                        'room_id' => null,
                        'day_of_week' => $day,
                        'start_time' => $timeSlot['start'],
                        'end_time' => $timeSlot['end'],
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'version' => 1,
                        'status' => 'draft',
                        'created_by' => Auth::id(),
                        'slot_name' => 'Period ' . ($s + 1),
                        'duration' => $this->calculateDuration($timeSlot['start'], $timeSlot['end']),
                        '_subject_name' => $subject->name,
                        '_teacher_name' => $teacher->user?->name ?? $teacher->first_name ?? 'Unknown',
                        '_color' => $this->getSubjectColor($subject->name),
                    ];
                }

                $slotIndex++;
            }
        }

        // If apply is true, save to database
        if ($apply && !empty($generated)) {
            DB::transaction(function () use ($generated, $level, $levelId, $targetBatchIds) {
                // Archive old routines
                $query = ClassRoutine::query();
                if (!empty($targetBatchIds)) {
                    $query->whereIn('batch_id', $targetBatchIds);
                } elseif ($levelId) {
                    $query->where(match ($level) {
                        'batch' => 'batch_id',
                        'course' => 'course_id',
                        'class' => 'class_id',
                        default => 'id',
                    }, $levelId);
                }
                $query->delete();

                foreach ($generated as $slot) {
                    unset($slot['_subject_name'], $slot['_teacher_name'], $slot['_color']);
                    $this->repository->create($slot);
                }
            });
        }

        return [
            'generated' => $generated,
            'total_slots' => count($generated),
            'warnings' => $warnings,
            'teacher_workload' => $teacherWorkload,
            'day_distribution' => $daySlotCount,
        ];
    }

    /**
     * Validate conflicts for a set of routines.
     */
    public function validateConflicts(array $routines): array
    {
        $conflicts = [];

        foreach ($routines as $i => $r1) {
            foreach ($routines as $j => $r2) {
                if ($i >= $j) continue;
                if ($r1['day_of_week'] !== $r2['day_of_week']) continue;

                $overlap = $r1['start_time'] < $r2['end_time'] && $r1['end_time'] > $r2['start_time'];
                if (!$overlap) continue;

                // Teacher conflict (HARD)
                if (($r1['teacher_id'] ?? null) === ($r2['teacher_id'] ?? null)) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same teacher assigned to two slots at the same time',
                        'slots' => [$i, $j],
                        'teacher_id' => $r1['teacher_id'],
                    ];
                }

                // Room conflict (HARD)
                if (!empty($r1['room_id']) && ($r1['room_id'] ?? null) === ($r2['room_id'] ?? null)) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same room assigned to two slots at the same time',
                        'slots' => [$i, $j],
                        'room_id' => $r1['room_id'],
                    ];
                }

                // Batch conflict (HARD) - same batch can't have two classes at same time
                if (!empty($r1['batch_id']) && ($r1['batch_id'] ?? null) === ($r2['batch_id'] ?? null)) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same batch has two classes at the same time',
                        'slots' => [$i, $j],
                        'batch_id' => $r1['batch_id'],
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Swap two routine slots.
     */
    public function swapSlots(string $slot1Id, string $slot2Id): array
    {
        $slot1 = $this->repository->findById($slot1Id);
        $slot2 = $this->repository->findById($slot2Id);

        if (!$slot1 || !$slot2) {
            return ['error' => 'One or both slots not found'];
        }

        DB::transaction(function () use ($slot1, $slot2) {
            $tempDay = $slot1->day_of_week;
            $tempStart = $slot1->start_time;
            $tempEnd = $slot1->end_time;

            $slot1->update([
                'day_of_week' => $slot2->day_of_week,
                'start_time' => $slot2->start_time,
                'end_time' => $slot2->end_time,
            ]);

            $slot2->update([
                'day_of_week' => $tempDay,
                'start_time' => $tempStart,
                'end_time' => $tempEnd,
            ]);
        });

        return [
            'slot1' => $slot1->fresh(['subject', 'teacher.user', 'room']),
            'slot2' => $slot2->fresh(['subject', 'teacher.user', 'room']),
        ];
    }

    /**
     * Publish routines for a level (increment version).
     */
    public function publish(string $level, string $levelId): array
    {
        $routines = ClassRoutine::where(match ($level) {
            'batch' => 'batch_id',
            'course' => 'course_id',
            'class' => 'class_id',
            default => 'id',
        }, $levelId)->get();

        if ($routines->isEmpty()) {
            return ['error' => 'No routines found to publish'];
        }

        $maxVersion = $routines->max('version');
        $newVersion = ($maxVersion ?? 0) + 1;

        DB::transaction(function () use ($routines, $newVersion) {
            foreach ($routines as $routine) {
                $routine->update([
                    'status' => 'published',
                    'version' => $newVersion,
                ]);
            }
        });

        return [
            'published' => $routines->count(),
            'version' => $newVersion,
        ];
    }

    /**
     * Publish routines for multiple batches.
     */
    public function publishMultiBatch(array $batchIds): array
    {
        $count = ClassRoutine::whereIn('batch_id', $batchIds)
            ->where('status', 'draft')
            ->update(['status' => 'published']);

        return ['published' => $count];
    }

    /**
     * Archive routines for a level.
     */
    public function archive(string $level, string $levelId): array
    {
        $count = ClassRoutine::where(match ($level) {
            'batch' => 'batch_id',
            'course' => 'course_id',
            'class' => 'class_id',
            default => 'id',
        }, $levelId)->update(['status' => 'archived']);

        return ['archived' => $count];
    }

    /**
     * Archive routines for multiple batches.
     */
    public function archiveMultiBatch(array $batchIds): array
    {
        $count = ClassRoutine::whereIn('batch_id', $batchIds)
            ->update(['status' => 'archived']);

        return ['archived' => $count];
    }

    /**
     * Get routines by level.
     */
    public function getByLevel(string $level, string $levelId, ?string $sessionId = null, string $status = 'published'): Collection
    {
        $filters = [];
        if ($sessionId) $filters['academic_session_id'] = $sessionId;
        if ($status === 'all') {
            return $this->repository->getAll(array_merge($filters, ['status_except' => 'archived']));
        }
        $filters['status'] = $status;
        if ($level === 'batch') $filters['batch_id'] = $levelId;
        elseif ($level === 'course') $filters['course_id'] = $levelId;
        elseif ($level === 'class') $filters['class_id'] = $levelId;
        return $this->repository->getAll($filters);
    }

    /**
     * Get multi-batch grid data.
     */
    public function getMultiBatchGrid(array $batchIds, array $filters = []): array
    {
        return $this->repository->getMultiBatchWeeklyGrid($batchIds, $filters);
    }

    /**
     * Get teacher's weekly schedule.
     */
    public function getTeacherSchedule(string $teacherId, ?string $date = null): array
    {
        $routines = $this->repository->getTeacherSchedule($teacherId, $date);

        $teacher = Teacher::with('user')->find($teacherId);

        $days = [];
        foreach (self::DAYS_ORDER as $day) {
            $dayRoutines = $routines->filter(fn($r) => $r->day_of_week === $day)
                ->values()
                ->map(fn($r) => $this->enrichRoutine($r));

            $days[$day] = $dayRoutines;
        }

        return [
            'weekly' => $days,
            'flat' => $routines->map(fn($r) => $this->enrichRoutine($r)),
            'teacher_name' => $teacher?->user?->name ?? 'Unknown',
            'teacher_id' => $teacherId,
        ];
    }

    /**
     * Get teacher workload analysis.
     */
    public function getTeacherLoad(string $teacherId): array
    {
        return $this->repository->getTeacherLoad($teacherId);
    }

    /**
     * Get room utilization statistics.
     */
    public function getRoomUtilization(string $roomId): array
    {
        return $this->repository->getRoomUtilization($roomId);
    }

    /**
     * Get student's routine.
     */
    public function getStudentRoutine(string $studentId): array
    {
        $routines = $this->repository->getStudentRoutine($studentId);

        $days = [];
        foreach (self::DAYS_ORDER as $day) {
            $dayRoutines = $routines->filter(fn($r) => $r->day_of_week === $day)
                ->values()
                ->map(fn($r) => $this->enrichRoutine($r));

            $days[$day] = $dayRoutines;
        }

        return [
            'weekly' => $days,
            'flat' => $routines->map(fn($r) => $this->enrichRoutine($r)),
        ];
    }

    /**
     * Get conflicts for a level.
     */
    public function getConflicts(string $level, string $levelId): array
    {
        $routines = ClassRoutine::where(match ($level) {
            'batch' => 'batch_id',
            'course' => 'course_id',
            'class' => 'class_id',
            default => 'id',
        }, $levelId)
            ->with(['subject', 'teacher.user', 'room', 'batch'])
            ->get();

        $conflicts = [];

        foreach ($routines as $i => $r1) {
            foreach ($routines as $j => $r2) {
                if ($i >= $j) continue;
                if ($r1->day_of_week !== $r2->day_of_week) continue;

                if (!($r1->start_time < $r2->end_time && $r1->end_time > $r2->start_time)) continue;

                // Teacher conflict
                if ($r1->teacher_id === $r2->teacher_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'category' => 'teacher',
                        'reason' => "Teacher {$r1->teacher?->user?->name} has two classes at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'batch1' => $r1->batch?->name,
                        'batch2' => $r2->batch?->name,
                        'day' => $r1->day_of_week,
                        'time' => "{$r1->start_time} - {$r1->end_time}",
                    ];
                }

                // Room conflict
                if ($r1->room_id && $r1->room_id === $r2->room_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'category' => 'room',
                        'reason' => "Room {$r1->room?->name} has two classes at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'batch1' => $r1->batch?->name,
                        'batch2' => $r2->batch?->name,
                        'day' => $r1->day_of_week,
                        'time' => "{$r1->start_time} - {$r1->end_time}",
                    ];
                }

                // Batch conflict
                if ($r1->batch_id && $r1->batch_id === $r2->batch_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'category' => 'batch',
                        'reason' => "Batch {$r1->batch?->name} has two classes at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'day' => $r1->day_of_week,
                        'time' => "{$r1->start_time} - {$r1->end_time}",
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get conflicts across multiple batches.
     */
    public function getMultiBatchConflicts(array $batchIds): array
    {
        $routines = ClassRoutine::whereIn('batch_id', $batchIds)
            ->with(['subject', 'teacher.user', 'room', 'batch'])
            ->get();

        $conflicts = [];

        foreach ($routines as $i => $r1) {
            foreach ($routines as $j => $r2) {
                if ($i >= $j) continue;
                if ($r1->day_of_week !== $r2->day_of_week) continue;
                if (!($r1->start_time < $r2->end_time && $r1->end_time > $r2->start_time)) continue;

                // Teacher conflict across batches
                if ($r1->teacher_id === $r2->teacher_id && $r1->batch_id !== $r2->batch_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'category' => 'teacher',
                        'reason' => "Teacher {$r1->teacher?->user?->name} assigned to two batches at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'batch1' => $r1->batch?->name,
                        'batch2' => $r2->batch?->name,
                        'day' => $r1->day_of_week,
                        'time' => "{$r1->start_time} - {$r1->end_time}",
                    ];
                }

                // Room conflict across batches
                if ($r1->room_id && $r1->room_id === $r2->room_id && $r1->batch_id !== $r2->batch_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'category' => 'room',
                        'reason' => "Room {$r1->room?->name} assigned to two batches at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'batch1' => $r1->batch?->name,
                        'batch2' => $r2->batch?->name,
                        'day' => $r1->day_of_week,
                        'time' => "{$r1->start_time} - {$r1->end_time}",
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get dashboard stats for multiple batches.
     */
    public function getMultiBatchStats(array $batchIds): array
    {
        $routines = ClassRoutine::whereIn('batch_id', $batchIds)->get();

        $totalSlots = $routines->count();
        $publishedCount = $routines->where('status', 'published')->count();
        $draftCount = $routines->where('status', 'draft')->count();
        $archivedCount = $routines->where('status', 'archived')->count();

        $uniqueTeachers = $routines->pluck('teacher_id')->unique()->filter()->count();
        $uniqueSubjects = $routines->pluck('subject_id')->unique()->filter()->count();
        $uniqueRooms = $routines->pluck('room_id')->unique()->filter()->count();
        $uniqueBatches = $routines->pluck('batch_id')->unique()->filter()->count();

        // Today's classes
        $today = strtolower(now()->format('D'));
        $todayClasses = $routines->where('day_of_week', $today)->where('status', 'published')->count();

        // Live now
        $now = now();
        $liveNow = $routines->filter(function ($r) use ($now, $today) {
            if ($r->day_of_week !== $today || $r->status !== 'published') return false;
            if (!$r->start_time || !$r->end_time) return false;
            $start = Carbon::createFromFormat('H:i:s', $r->start_time)->setDateFrom($now);
            $end = Carbon::createFromFormat('H:i:s', $r->end_time)->setDateFrom($now);
            return $now->between($start, $end);
        })->count();

        // Batch student counts (from enrollments)
        $batchStudentCounts = [];
        if (!empty($batchIds)) {
            $batches = \Modules\Enrollment\Models\Batch::whereIn('id', $batchIds)->withCount('enrollments')->get();
            foreach ($batches as $batch) {
                $batchStudentCounts[$batch->id] = $batch->enrollments_count;
            }
        }

        return [
            'total_slots' => $totalSlots,
            'published' => $publishedCount,
            'drafts' => $draftCount,
            'archived' => $archivedCount,
            'unique_teachers' => $uniqueTeachers,
            'unique_subjects' => $uniqueSubjects,
            'unique_rooms' => $uniqueRooms,
            'unique_batches' => $uniqueBatches,
            'today_count' => $todayClasses,
            'live_count' => $liveNow,
            'batch_students' => $batchStudentCounts,
        ];
    }

    /**
     * Set lunch break for a set of routines.
     */
    public function setLunchBreak(array $batchIds, string $day, string $startTime, string $endTime): array
    {
        // Check if a lunch break slot already exists
        $existing = ClassRoutine::whereIn('batch_id', $batchIds)
            ->where('day_of_week', $day)
            ->where('is_lunch_break', true)
            ->get();

        // Delete existing lunch breaks for these batches on this day
        foreach ($existing as $e) {
            $e->delete();
        }

        // Create lunch break slots for each batch
        $created = [];
        foreach ($batchIds as $batchId) {
            $routine = ClassRoutine::create([
                'batch_id' => $batchId,
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_lunch_break' => true,
                'slot_name' => 'Lunch Break',
                'duration' => $this->calculateDuration($startTime, $endTime),
                'status' => 'published',
                'created_by' => Auth::id(),
            ]);
            $created[] = $routine;
        }

        return [
            'created' => count($created),
            'lunch_slots' => $created,
        ];
    }

    /**
     * Set off day for a batch on a specific date.
     */
    public function setOffDay(string $batchId, string $date, ?string $reason = null): ClassRoutine
    {
        return ClassRoutine::create([
            'batch_id' => $batchId,
            'is_off_day' => true,
            'off_day_date' => $date,
            'notes' => $reason,
            'status' => 'published',
            'created_by' => Auth::id(),
        ]);
    }

    // ===== PRIVATE HELPERS =====

    private function generateTimeSlots(int $count): array
    {
        $slots = [];
        $startHour = 8;
        $durationMinutes = 45;

        for ($i = 0; $i < $count; $i++) {
            $totalMinutes = $startHour * 60 + ($i * $durationMinutes);
            $endMinutes = $totalMinutes + $durationMinutes;

            $start = sprintf('%02d:%02d:00', intdiv($totalMinutes, 60), $totalMinutes % 60);
            $end = sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60);

            $slots[] = ['start' => $start, 'end' => $end];
        }

        return $slots;
    }

    private function findBestTeacher(
        Collection $teachers,
        string $subjectId,
        string $day,
        string $startTime,
        string $endTime,
        array &$teacherSchedule,
        array &$teacherWorkload,
    ): ?Teacher {
        $candidates = $teachers->filter(function ($teacher) use ($subjectId) {
            return $teacher->subjects->contains('id', $subjectId);
        });

        if ($candidates->isEmpty()) return null;

        $available = $candidates->filter(function ($teacher) use ($day, $startTime, $endTime, $teacherSchedule) {
            $slots = $teacherSchedule[$teacher->id][$day] ?? [];
            foreach ($slots as $slot) {
                if ($startTime < $slot['end'] && $endTime > $slot['start']) {
                    return false;
                }
            }
            return true;
        });

        if ($available->isEmpty()) return null;

        return $available->sortBy(fn($t) => $teacherWorkload[$t->id] ?? 0)->first();
    }

    private function enrichRoutine(ClassRoutine $routine): array
    {
        $data = $routine->toArray();
        $data['_color'] = $routine->subject_color;
        $data['_time_formatted'] = $routine->time_formatted;
        $data['_is_live'] = $routine->is_live_now;
        $data['_duration'] = $routine->duration;

        if ($routine->relationLoaded('teacher') && $routine->teacher) {
            $teacher = $routine->teacher;
            $data['teacher'] = [
                'id' => $teacher->id,
                'name' => $teacher->user?->name
                    ?? ($teacher->first_name && $teacher->last_name
                        ? $teacher->first_name . ' ' . $teacher->last_name
                        : ($teacher->first_name ?? $teacher->last_name ?? 'Unknown')),
                'first_name' => $teacher->first_name,
                'last_name' => $teacher->last_name,
            ];
        }

        return $data;
    }

    private function getSubjectColor(string $subjectName): string
    {
        $name = strtolower($subjectName);
        foreach (self::SUBJECT_COLORS as $key => $color) {
            if (str_contains($name, $key)) return $color;
        }
        $palette = ['#3B82F6', '#10B981', '#F59E0B', '#F97316', '#8B5CF6', '#14B8A6', '#EF4444', '#6366F1'];
        return $palette[abs(crc32($name)) % count($palette)];
    }

    private function calculateDuration(string $startTime, string $endTime): int
    {
        try {
            $start = Carbon::createFromFormat('H:i:s', $startTime);
            $end = Carbon::createFromFormat('H:i:s', $endTime);
            return $start->diffInMinutes($end);
        } catch (\Exception $e) {
            return 45;
        }
    }
}
