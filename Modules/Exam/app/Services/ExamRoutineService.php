<?php

namespace Modules\Exam\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Teacher\app\Models\Teacher;
use Modules\Communication\app\Services\NotificationDispatchService;

class ExamRoutineService
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

    public function __construct(
        private ExamRoutineRepository $repository,
        private NotificationDispatchService $notificationDispatch,
    ) {}

    /**
     * Smart auto-generation of exam routines.
     *
     * Algorithm:
     * 1. Collect subjects, teachers, date range, time slots
     * 2. Distribute subjects across available dates
     * 3. Assign teachers with workload balancing
     * 4. Detect and avoid HARD conflicts (same teacher/room at same date/time)
     * 5. Return generated slots as draft
     */
    public function generate(array $data): array
    {
        $examId = $data['exam_id'] ?? null;
        $subjectIds = array_values(array_unique($data['subject_ids'] ?? []));
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $slotDuration = (int)($data['slot_duration'] ?? 120); // minutes
        $gapMinutes = (int)($data['gap_minutes'] ?? 30);
        $dayStartTime = $data['start_time'] ?? '09:00';
        $dayEndLimit = $data['end_time_limit'] ?? '17:00';
        $excludeDays = $data['exclude_days'] ?? ['fri'];
        $autoAssignRooms = $data['auto_assign_rooms'] ?? false;
        $autoAssignTeachers = $data['auto_assign_teachers'] ?? false;
        $apply = $data['apply'] ?? false;
        $mergeMode = in_array($data['merge_mode'] ?? 'append', ['append', 'replace'], true)
            ? $data['merge_mode']
            : 'append';

        if (!$examId || empty($subjectIds) || !$startDate || !$endDate) {
            return ['error' => 'Exam ID, subjects, start date, and end date are required', 'generated' => []];
        }

        $exam = Exam::with(['batch.course.subjects', 'course.subjects', 'class.subjects'])->find($examId);
        if (!$exam) {
            return ['error' => 'Exam not found', 'generated' => []];
        }

        $deliveryChannel = $this->resolveGenerationDeliveryMode($data, $exam);

        // Get subjects based on exam level
        $subjects = $this->getSubjectsForExam($exam);
        $filteredSubjects = $subjects->whereIn('id', $subjectIds);

        if ($filteredSubjects->isEmpty()) {
            return ['error' => 'No valid subjects found for selected subject IDs', 'generated' => []];
        }

        // Get teachers who teach these subjects
        $teachers = Teacher::whereHas('subjects', fn($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('user')
            ->get();

        // Calculate available dates
        $availableDates = $this->getAvailableDates($startDate, $endDate, $excludeDays);

        if (empty($availableDates)) {
            return ['error' => 'No available dates in the given range', 'generated' => []];
        }

        // Calculate slots per date
        $slotsPerDate = $this->calculateSlotsPerDate($dayStartTime, $dayEndLimit, $slotDuration, $gapMinutes);

        // Calculate total available slots
        $totalSlots = count($availableDates) * $slotsPerDate;

        $batchId = $data['batch_id'] ?? $exam->batch_id;
        $courseId = $data['course_id'] ?? $exam->course_id;
        $classId = $data['class_id'] ?? $exam->class_id;

        $teacherSchedule = [];
        $teacherWorkload = [];
        foreach ($teachers as $t) {
            $teacherWorkload[$t->id] = 0;
        }

        $scheduleChannel = $exam->is_practice
            ? null
            : ExamRoutine::resolveDeliveryChannel($deliveryChannel);

        $scheduledSubjects = [];

        $generated = [];
        $warnings = [];
        $subjectQueue = $filteredSubjects->sortBy('name')->values()->pluck('id')->all();
        $skippedExisting = 0;

        $ignoreSameExamSubjectIds = ($apply && $mergeMode === 'replace') ? $subjectQueue : [];
        $batchDayIntervals = $this->loadBatchDayIntervals(
            $batchId,
            $startDate,
            $endDate,
            $apply ? $examId : null,
            $scheduleChannel,
            $ignoreSameExamSubjectIds,
        );

        if ($subjectQueue === []) {
            return [
                'generated' => [],
                'total_slots' => 0,
                'total_subjects' => 0,
                'available_dates' => count($availableDates),
                'warnings' => array_merge($warnings, [
                    'No subjects selected for scheduling.',
                ]),
                'skipped_existing' => 0,
                'merge_mode' => $mergeMode,
            ];
        }

        if ($totalSlots < count($subjectQueue)) {
            $subjectCount = count($subjectQueue);
            $dateCount = count($availableDates);

            return [
                'error' => 'Not enough time slots for all subjects. Need at least '
                    . $subjectCount
                    . ' slot(s) but only have '
                    . $totalSlots
                    . ' ('
                    . $dateCount
                    . ' day(s) × '
                    . $slotsPerDate
                    . ' slot(s)/day). Extend the end date, widen the daily time window ('
                    . $dayStartTime
                    . '–'
                    . $dayEndLimit
                    . '), or reduce slot duration ('
                    . $slotDuration
                    . ' min).',
                'generated' => [],
            ];
        }

        $subjectIndex = 0;
        foreach ($availableDates as $date) {
            $dayEndAt = $this->parseDayEndLimit($date, $dayStartTime, $dayEndLimit);

            for ($s = 0; $s < $slotsPerDate && $subjectIndex < count($subjectQueue); $s++) {
                $slotStart = Carbon::parse($date . ' ' . $dayStartTime)
                    ->addMinutes($s * ($slotDuration + $gapMinutes));
                $slotEnd = $slotStart->copy()->addMinutes($slotDuration);

                if ($slotEnd->gt($dayEndAt)) {
                    break;
                }

                $subjectId = $subjectQueue[$subjectIndex];
                $subject = $filteredSubjects->firstWhere('id', $subjectId);
                if (!$subject) {
                    $subjectIndex++;
                    continue;
                }

                $startTime = $slotStart->format('H:i:s');
                $endTime = $slotEnd->format('H:i:s');
                if (isset($scheduledSubjects[$subjectId])) {
                    continue;
                }

                if ($this->intervalOverlapsBatchDay($batchDayIntervals, $date, $startTime, $endTime)) {
                    continue;
                }

                $teacherId = null;
                $teacher = null;
                if ($autoAssignTeachers) {
                    $teacher = $this->findBestTeacher(
                        teachers: $teachers,
                        subjectId: $subjectId,
                        date: $date,
                        startTime: substr($startTime, 0, 5),
                        endTime: substr($endTime, 0, 5),
                        teacherSchedule: $teacherSchedule,
                        teacherWorkload: $teacherWorkload,
                    );

                    if ($teacher) {
                        $teacherId = $teacher->id;
                        $teacherSchedule[$teacher->id][$date][] = [
                            'start' => substr($startTime, 0, 5),
                            'end' => substr($endTime, 0, 5),
                        ];
                        $teacherWorkload[$teacher->id]++;
                    } else {
                        $warnings[] = "No available teacher for {$subject->name} on {$date} at {$startTime}";
                    }
                }

                $generated[] = [
                    'exam_id' => $examId,
                    'subject_id' => $subjectId,
                    'exam_type_id' => $data['exam_type_id'] ?? null,
                    'exam_date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $slotDuration,
                    'batch_id' => $batchId,
                    'course_id' => $courseId,
                    'class_id' => $classId,
                    'teacher_id' => $teacherId,
                    'room_id' => null,
                    'status' => 'draft',
                    'delivery_mode' => $deliveryChannel,
                    'created_by' => Auth::id(),
                    '_subject_name' => $subject->name,
                    '_teacher_name' => $teacherId && $teacher
                        ? ($teacher->user?->name ?? $teacher->first_name ?? 'Unknown')
                        : 'Not assigned',
                    '_color' => $this->getSubjectColor($subject->name),
                ];

                $batchDayIntervals[$date][] = ['start' => $startTime, 'end' => $endTime];
                $scheduledSubjects[$subjectId] = true;
                $subjectIndex++;
            }
        }

        if ($subjectIndex < count($subjectQueue)) {
            $remaining = count($subjectQueue) - $subjectIndex;
            $skippedExisting = $remaining;
            $unscheduledNames = collect($subjectQueue)
                ->slice($subjectIndex)
                ->map(fn (string $id) => $filteredSubjects->firstWhere('id', $id)?->name ?? $id)
                ->values()
                ->all();

            if ($generated === []) {
                return [
                    'error' => 'No free time slot for: '
                        . implode(', ', $unscheduledNames)
                        . '. Same batch cannot overlap at the same time — widen the daily window, pick another date, or use Replace Selected Subjects.',
                    'generated' => [],
                    'warnings' => $warnings,
                    'skipped_existing' => $skippedExisting,
                    'merge_mode' => $mergeMode,
                ];
            }

            return [
                'error' => "Not enough valid slots for all subjects ({$remaining} subject(s) unscheduled). Widen date range or extend daily end time.",
                'generated' => $generated,
                'warnings' => $warnings,
                'skipped_existing' => $skippedExisting,
            ];
        }

        if ($apply && $generated !== []) {
            DB::transaction(function () use ($generated, $examId, $mergeMode, $scheduleChannel) {
                $paperService = app(ExamPaperService::class);
                /** @var array<string, array{items: array<int, array<string, mixed>>, randomize: bool}> $stashedQuestionsBySubject */
                $stashedQuestionsBySubject = [];

                if ($mergeMode === 'replace') {
                    $replaceSubjectIds = collect($generated)->pluck('subject_id')->unique()->values()->all();

                    $deleteQuery = ExamRoutine::where('exam_id', $examId)
                        ->whereNotIn('status', ['cancelled'])
                        ->whereIn('subject_id', $replaceSubjectIds);

                    $this->applyDeliveryChannelScope($deleteQuery, $scheduleChannel);
                    foreach ($deleteQuery->get() as $existing) {
                        if (isset($stashedQuestionsBySubject[$existing->subject_id])) {
                            continue;
                        }
                        $stash = $paperService->stashQuestionItems($existing);
                        if ($stash) {
                            $stashedQuestionsBySubject[$existing->subject_id] = $stash;
                        }
                    }
                    $deleteQuery->delete();
                }

                foreach ($generated as $slot) {
                    unset($slot['_subject_name'], $slot['_teacher_name'], $slot['_color']);
                    $routine = $this->repository->create($slot);
                    $subjectId = $routine->subject_id;
                    if (!empty($stashedQuestionsBySubject[$subjectId])) {
                        $stash = $stashedQuestionsBySubject[$subjectId];
                        $paperService->syncQuestions($routine, $stash['items'], $stash['randomize']);
                    } else {
                        $paperService->copyQuestionsFromSiblingIfEmpty($routine);
                    }
                }
            });
        }

        return [
            'generated' => $generated,
            'total_slots' => count($generated),
            'total_subjects' => count($subjectQueue),
            'available_dates' => count($availableDates),
            'warnings' => $warnings,
            'teacher_workload' => $teacherWorkload,
            'skipped_existing' => $skippedExisting,
            'merge_mode' => $mergeMode,
        ];
    }

    /**
     * Validate conflicts for a set of exam routines.
     */
    public function validateConflicts(array $routines): array
    {
        $conflicts = [];

        foreach ($routines as $i => $r1) {
            foreach ($routines as $j => $r2) {
                if ($i >= $j) continue;

                // Same date check
                if (($r1['exam_date'] ?? '') !== ($r2['exam_date'] ?? '')) continue;

                // Time overlap check
                $overlap = $r1['start_time'] < $r2['end_time'] && $r1['end_time'] > $r2['start_time'];
                if (!$overlap) continue;

                // Teacher conflict (HARD)
                if (($r1['teacher_id'] ?? null) === ($r2['teacher_id'] ?? null) && !empty($r1['teacher_id'])) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same teacher assigned to two exams at the same time',
                        'slots' => [$i, $j],
                        'teacher_id' => $r1['teacher_id'],
                    ];
                }

                // Room conflict (HARD)
                if (!empty($r1['room_id']) && ($r1['room_id'] ?? null) === ($r2['room_id'] ?? null)) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same room assigned to two exams at the same time',
                        'slots' => [$i, $j],
                        'room_id' => $r1['room_id'],
                    ];
                }

                // Same batch + same schedule channel at overlapping time (HARD)
                $sameBatch = ($r1['batch_id'] ?? null) === ($r2['batch_id'] ?? null) && !empty($r1['batch_id']);
                $sameChannel = ExamRoutine::resolveDeliveryChannel($r1['delivery_mode'] ?? 'offline')
                    === ExamRoutine::resolveDeliveryChannel($r2['delivery_mode'] ?? 'offline');
                $eitherPractice = !empty($r1['is_practice']) || !empty($r2['is_practice']);
                if ($sameBatch && $sameChannel && !$eitherPractice) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => 'Same batch cannot have two '
                            . ExamRoutine::resolveDeliveryChannel($r1['delivery_mode'] ?? 'offline')
                            . ' exams at overlapping times',
                        'slots' => [$i, $j],
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Remove draft/published routines whose subjects are not in the allowed list.
     *
     * @param  array<int, string>  $subjectIds
     */
    public function pruneRoutinesExceptSubjects(string $examId, array $subjectIds, ?string $deliveryMode = null): int
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds)));
        if (empty($subjectIds)) {
            return 0;
        }

        $query = ExamRoutine::where('exam_id', $examId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereNotIn('subject_id', $subjectIds);

        if ($deliveryMode && in_array($deliveryMode, ['offline', 'online', 'hybrid'], true)) {
            $this->applyDeliveryChannelScope(
                $query,
                ExamRoutine::resolveDeliveryChannel($deliveryMode)
            );
        }

        return $query->delete();
    }

    private function resolveGenerationDeliveryMode(array $data, ?Exam $exam): string
    {
        $mode = $data['delivery_mode'] ?? $exam?->delivery_mode ?? 'offline';

        return in_array($mode, ['online', 'offline', 'hybrid'], true) ? $mode : 'offline';
    }

    /**
     * Publish exam routines for an exam.
     *
     * @param  'offline'|'online'|null  $deliveryChannel  When set, only draft routines in that channel are published and validated.
     * @param  array{batch_id?: string, class_id?: string, course_id?: string, month?: int|string, year?: int|string}  $scopeFilters  Optional grid filters so publish only affects the visible scope.
     */
    public function publish(string $examId, ?string $deliveryChannel = null, array $scopeFilters = []): array
    {
        $routinesQuery = ExamRoutine::where('exam_id', $examId)
            ->where('status', '!=', 'cancelled')
            ->with(['exam.examType', 'batch', 'subject', 'teacher.user']);

        $this->applyDeliveryChannelScope($routinesQuery, $deliveryChannel);
        $this->applyRoutineScopeFilters($routinesQuery, $scopeFilters);
        $routines = $routinesQuery->get();

        if ($routines->isEmpty()) {
            return ['error' => 'No routines found to publish'];
        }

        $exam = $routines->first()->exam;

        $conflicts = $this->getConflicts($examId, $deliveryChannel);
        $hardConflicts = array_values(array_filter(
            $conflicts,
            fn (array $c) => ($c['type'] ?? '') === 'HARD'
        ));
        if ($hardConflicts !== []) {
            $first = $hardConflicts[0];

            return ['error' => 'Cannot publish: ' . ($first['reason'] ?? 'Schedule conflict detected')];
        }

        if ($exam) {
            try {
                $draftIds = $routines
                    ->where('status', 'draft')
                    ->pluck('id')
                    ->values()
                    ->all();
                app(ExamPaperService::class)->assertExamReadyForDelivery(
                    $exam,
                    $deliveryChannel,
                    $draftIds,
                );
            } catch (\RuntimeException $e) {
                return ['error' => $e->getMessage()];
            }
        }

        $count = DB::transaction(function () use ($routines, $exam) {
            $updated = 0;
            $notifiedTeachers = [];
            $notifiedBatches = [];

            if ($exam && $exam->status === 'draft') {
                $exam->update(['status' => 'published']);
            }

            foreach ($routines as $routine) {
                if ($routine->status === 'draft') {
                    $routine->update(['status' => 'published']);
                    $updated++;

                    // Collect unique teacher IDs for notification
                    if ($routine->teacher_id && !in_array($routine->teacher_id, $notifiedTeachers)) {
                        $notifiedTeachers[] = $routine->teacher_id;
                    }

                    // Collect unique batch IDs for student notification
                    if ($routine->batch_id && !in_array($routine->batch_id, $notifiedBatches)) {
                        $notifiedBatches[] = $routine->batch_id;
                    }
                }
            }

            // ===== Send notifications to invigilators (assigned teachers) =====
            if ($exam) {
                $teacherItems = [];
                foreach ($notifiedTeachers as $teacherId) {
                    $teacher = Teacher::with('user')->find($teacherId);
                    $userId = $teacher?->user_id;
                    if (!$userId) {
                        continue;
                    }

                    $teacherRoutines = $routines->where('teacher_id', $teacherId);
                    $routineCount = $teacherRoutines->count();
                    $firstDate = $teacherRoutines->first()?->exam_date?->format('d M, Y') ?? 'N/A';

                    $teacherItems[] = [
                        'user_id' => $userId,
                        'title' => '📋 Exam Routine Published',
                        'message' => "Exam \"{$exam->name}\" has been published. You have {$routineCount} invigilation duty/duties starting from {$firstDate}. Please check your exam schedule.",
                    ];
                }

                if (!empty($teacherItems)) {
                    $this->notificationDispatch->sendPersonalized($teacherItems, [
                        'type' => 'in_app',
                        'audience' => 'teachers',
                        'is_highlighted' => true,
                        'sent_by' => auth()->id(),
                    ]);
                }
            }

            // ===== Send notifications to students (batch + class) =====
            if ($exam) {
                $studentUserIds = $this->resolveStudentUserIdsForRoutines($routines, $notifiedBatches);
                $firstDate = $routines->first()?->exam_date?->format('d M, Y') ?? 'N/A';

                if (!empty($studentUserIds)) {
                    $this->notificationDispatch->sendToUsers(
                        $studentUserIds,
                        '📝 Exam Routine Published',
                        "Exam routine for \"{$exam->name}\" has been published. Your exam(s) start from {$firstDate}. Please check your exam schedule.",
                        [
                            'type' => 'in_app',
                            'audience' => 'students',
                            'is_highlighted' => true,
                            'sent_by' => auth()->id(),
                        ]
                    );
                }
            }

            return $updated;
        });

        return [
            'published' => $count,
            'total' => $routines->count(),
            'notifications_sent' => true,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamRoutine>  $routines
     * @param  array<int, string>  $batchIds
     * @return array<int, string>
     */
    private function resolveStudentUserIdsForRoutines($routines, array $batchIds): array
    {
        $userIds = [];

        if (!empty($batchIds)) {
            $enrollments = \Modules\Enrollment\app\Models\Enrollment::whereIn('batch_id', $batchIds)
                ->where('status', 'active')
                ->with('student')
                ->get();

            foreach ($enrollments as $enrollment) {
                if ($enrollment->student?->user_id) {
                    $userIds[] = $enrollment->student->user_id;
                }
            }
        }

        $classIds = $routines->pluck('class_id')->filter()->unique()->values()->all();
        if (!empty($classIds)) {
            $students = \Modules\Student\app\Models\Student::whereIn('current_class_id', $classIds)
                ->where('status', 'active')
                ->whereNotNull('user_id')
                ->pluck('user_id');

            foreach ($students as $userId) {
                $userIds[] = $userId;
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    /**
     * Complete exam routines for an exam.
     */
    public function complete(string $examId): array
    {
        $count = ExamRoutine::where('exam_id', $examId)
            ->where('status', 'published')
            ->update(['status' => 'completed']);

        return ['completed' => $count];
    }

    /**
     * Cancel exam routines for an exam.
     */
    public function cancel(string $examId): array
    {
        $count = ExamRoutine::where('exam_id', $examId)
            ->whereIn('status', ['draft', 'published'])
            ->update(['status' => 'cancelled']);

        return ['cancelled' => $count];
    }

    /**
     * Get conflicts for an exam.
     *
     * @param  'offline'|'online'|null  $deliveryChannel
     */
    public function getConflicts(string $examId, ?string $deliveryChannel = null): array
    {
        $routinesQuery = ExamRoutine::where('exam_id', $examId)
            ->where('status', '!=', 'cancelled')
            ->with(['subject', 'teacher.user', 'room', 'batch', 'exam']);

        $this->applyDeliveryChannelScope($routinesQuery, $deliveryChannel);
        $routines = $routinesQuery->get();

        if ($routines->isEmpty()) {
            return [];
        }

        $conflicts = [];

        $batchIds = $routines->pluck('batch_id')->filter()->unique();
        $foreignQuery = ExamRoutine::whereIn('batch_id', $batchIds)
            ->where('exam_id', '!=', $examId)
            ->whereIn('status', ['published', 'completed', 'draft'])
            ->with(['subject', 'exam', 'batch']);

        $this->applyDeliveryChannelScope($foreignQuery, $deliveryChannel);
        $foreignRoutines = $batchIds->isNotEmpty() ? $foreignQuery->get() : collect();

        foreach ($routines as $r1) {
            foreach ($foreignRoutines as $r2) {
                if ($r1->exam?->is_practice || $r2->exam?->is_practice) {
                    continue;
                }
                if ($r1->deliveryChannel() !== $r2->deliveryChannel()) {
                    continue;
                }
                if (!$r1->overlapsScheduleWith($r2)) {
                    continue;
                }
                $conflicts[] = [
                    'type' => 'HARD',
                    'reason' => "Batch {$r1->batch?->name} conflicts with another {$r1->deliveryChannel()} exam ({$r2->exam?->name} / {$r2->subject?->name})",
                    'slot1' => $r1->id,
                    'slot2' => $r2->id,
                    'subject1' => $r1->subject?->name,
                    'subject2' => $r2->subject?->name,
                    'batch_id' => $r1->batch_id,
                    'date' => $r1->exam_date->format('Y-m-d'),
                    'time' => "{$r1->start_time_formatted} - {$r1->end_time_formatted}",
                ];
            }
        }

        foreach ($routines as $i => $r1) {
            foreach ($routines as $j => $r2) {
                if ($i >= $j) continue;
                if (!$r1->overlapsScheduleWith($r2)) continue;

                // Teacher conflict
                if ($r1->teacher_id && $r1->teacher_id === $r2->teacher_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => "Teacher {$r1->teacher?->user?->name} has two exams at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'date' => $r1->exam_date->format('Y-m-d'),
                        'time' => "{$r1->start_time_formatted} - {$r1->end_time_formatted}",
                    ];
                }

                // Room conflict
                if ($r1->room_id && $r1->room_id === $r2->room_id) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => "Room {$r1->room?->name} has two exams at same time",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'date' => $r1->exam_date->format('Y-m-d'),
                        'time' => "{$r1->start_time_formatted} - {$r1->end_time_formatted}",
                    ];
                }

                // Same batch + same schedule channel — students cannot sit two papers at once
                if ($r1->batch_id && $r1->batch_id === $r2->batch_id
                    && !$r1->exam?->is_practice && !$r2->exam?->is_practice
                    && $r1->deliveryChannel() === $r2->deliveryChannel()) {
                    $conflicts[] = [
                        'type' => 'HARD',
                        'reason' => "Batch {$r1->batch?->name} has overlapping {$r1->deliveryChannel()} exams ({$r1->subject?->name} vs {$r2->subject?->name})",
                        'slot1' => $r1->id,
                        'slot2' => $r2->id,
                        'subject1' => $r1->subject?->name,
                        'subject2' => $r2->subject?->name,
                        'batch_id' => $r1->batch_id,
                        'date' => $r1->exam_date->format('Y-m-d'),
                        'time' => "{$r1->start_time_formatted} - {$r1->end_time_formatted}",
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get teacher's exam schedule.
     */
    public function getTeacherSchedule(string $teacherId, ?string $date = null): array
    {
        $routines = $this->repository->getTeacherSchedule($teacherId, $date);

        $teacher = Teacher::with('user')->find($teacherId);

        $grouped = $routines->groupBy(fn($r) => $r->exam_date->format('Y-m-d'))
            ->map(fn($group) => $group->map(fn($r) => $this->enrichRoutine($r))->values());

        return [
            'grouped' => $grouped,
            'flat' => $routines->map(fn($r) => $this->enrichRoutine($r)),
            'teacher_name' => $teacher?->user?->name ?? 'Unknown',
            'teacher_id' => $teacherId,
            'total' => $routines->count(),
        ];
    }

    /**
     * Get student's exam routines.
     */
    public function getStudentRoutines(string $studentId): array
    {
        $routines = $this->repository->getStudentRoutines($studentId);
        $colorMaps = (new ExamRoutineGridBuilder())->buildBatchColorMaps($routines);
        $present = fn (ExamRoutine $r) => $this->presentRoutine($r, $colorMaps['bg'], $colorMaps['text']);

        $grouped = $routines->groupBy(fn ($r) => $r->exam_date->format('Y-m-d'))
            ->map(fn ($group) => $group->map($present)->values());

        return [
            'grouped' => $grouped,
            'flat' => $routines->map($present),
            'total' => $routines->count(),
        ];
    }

    /**
     * Get exam routines for a teacher's subjects (not just duties).
     * This returns routines where the subject matches subjects the teacher teaches.
     */
    public function getTeacherSubjectRoutines(string $teacherId): array
    {
        $routines = $this->repository->getRoutinesByTeacherSubjects($teacherId);
        $colorMaps = (new ExamRoutineGridBuilder())->buildBatchColorMaps($routines);
        $present = fn (ExamRoutine $r) => $this->presentRoutine($r, $colorMaps['bg'], $colorMaps['text']);

        $grouped = $routines->groupBy(fn ($r) => $r->exam_date->format('Y-m-d'))
            ->map(fn ($group) => $group->map($present)->values());

        return [
            'grouped' => $grouped,
            'flat' => $routines->map($present),
            'total' => $routines->count(),
        ];
    }

    /**
     * Get upcoming exam routines for a student.
     */
    public function getUpcomingForStudent(string $studentId, int $limit = 5): array
    {
        $routines = $this->repository->getUpcomingForStudent($studentId, $limit);

        return [
            'routines' => $routines->map(fn($r) => $this->enrichRoutine($r)),
            'next_exam' => $routines->first() ? $this->enrichRoutine($routines->first()) : null,
            'total_upcoming' => $routines->count(),
        ];
    }

    /**
     * Get subjects for an exam based on its level.
     */
    public function getSubjectsForExam(Exam $exam): Collection
    {
        if ($exam->batch_id) {
            // Batch-level exam: get subjects from batch's course
            return $exam->batch?->course?->subjects ?? collect();
        } elseif ($exam->course_id) {
            // Course-level exam: get subjects from course
            return $exam->course?->subjects ?? collect();
        } elseif ($exam->class_id) {
            // Class-level exam: get subjects from class_teacher pivot
            return $exam->class?->subjects ?? collect();
        }

        return collect();
    }

    /**
     * Get available dates between start and end date, excluding specified days.
     */
    private function getAvailableDates(string $startDate, string $endDate, array $excludeDays): array
    {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $dayMap = [
            'sat' => Carbon::SATURDAY, 'sun' => Carbon::SUNDAY,
            'mon' => Carbon::MONDAY, 'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY, 'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
        ];

        // Normalize day names: accept full names (e.g. 'Friday', 'friday') or short names (e.g. 'fri')
        $excludeNumeric = array_map(function ($d) use ($dayMap) {
            $normalized = strtolower(trim($d));
            // Truncate to 3 chars to handle full day names like 'friday' → 'fri'
            $short = substr($normalized, 0, 3);
            return $dayMap[$short] ?? $dayMap[$normalized] ?? null;
        }, $excludeDays);
        $excludeNumeric = array_filter($excludeNumeric);

        while ($current->lte($end)) {
            if (!in_array($current->dayOfWeek, $excludeNumeric)) {
                $dates[] = $current->format('Y-m-d');
            }
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Calculate how many slots fit in a day.
     */
    private function calculateSlotsPerDate(string $startTime, string $endTime, int $slotDuration, int $gapMinutes): int
    {
        $totalMinutes = $this->minutesInTimeWindow($startTime, $endTime);
        if ($slotDuration <= 0 || $totalMinutes < $slotDuration) {
            return 0;
        }

        $count = 0;
        $cursor = 0;
        while ($cursor + $slotDuration <= $totalMinutes) {
            $count++;
            $cursor += $slotDuration + $gapMinutes;
        }

        return $count;
    }

    private function minutesInTimeWindow(string $startTime, string $endTime): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return max(0, (int) $start->diffInMinutes($end));
    }

    private function parseDayEndLimit(string $date, string $dayStartTime, string $dayEndLimit): Carbon
    {
        $start = Carbon::parse($date . ' ' . $dayStartTime);
        $end = Carbon::parse($date . ' ' . $dayEndLimit);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Find the best teacher for a subject slot using workload balancing.
     */
    private function findBestTeacher(
        Collection $teachers,
        string $subjectId,
        string $date,
        string $startTime,
        string $endTime,
        array &$teacherSchedule,
        array &$teacherWorkload,
    ): ?Teacher {
        $candidates = $teachers->filter(function ($teacher) use ($subjectId) {
            return $teacher->subjects->contains('id', $subjectId);
        });

        if ($candidates->isEmpty()) return null;

        // Filter out teachers with time conflicts
        $available = $candidates->filter(function ($teacher) use ($date, $startTime, $endTime, $teacherSchedule) {
            $slots = $teacherSchedule[$teacher->id][$date] ?? [];
            foreach ($slots as $slot) {
                if ($startTime < $slot['end'] && $endTime > $slot['start']) {
                    return false; // Time conflict
                }
            }
            return true;
        });

        if ($available->isEmpty()) return null;

        // Sort by workload (lowest first) for balancing
        return $available->sortBy(fn($t) => $teacherWorkload[$t->id] ?? 0)->first();
    }

    /**
     * @param  array<string, string>  $bgMap
     * @param  array<string, string>  $textMap
     * @return array<string, mixed>
     */
    private function presentRoutine(ExamRoutine $routine, array $bgMap, array $textMap): array
    {
        $data = $this->enrichRoutine($routine);
        $mode = $routine->delivery_mode ?: 'offline';
        $channel = $routine->deliveryChannel();

        $data['batch_color'] = $bgMap[$routine->batch_id] ?? ExamRoutineGridBuilder::BATCH_BG_PALETTE[0];
        $data['batch_text_color'] = $textMap[$routine->batch_id] ?? ExamRoutineGridBuilder::BATCH_TEXT_PALETTE[0];
        $data['delivery_mode'] = $mode;
        $data['delivery_channel'] = $channel;
        $data['delivery_label'] = match ($mode) {
            'online' => 'Online',
            'hybrid' => 'Hybrid',
            default => 'Offline',
        };

        return $data;
    }

    /**
     * Enrich routine with computed properties.
     */
    private function enrichRoutine(ExamRoutine $routine): array
    {
        $data = $routine->toArray();
        $data['_color'] = $routine->subject_color;
        $data['_time_formatted'] = $routine->start_time_formatted . ' - ' . $routine->end_time_formatted;
        $data['_duration'] = $routine->duration;

        // Add flat field aliases for frontend convenience
        $data['subject_name'] = $routine->subject?->name ?? $data['subject_name'] ?? null;
        $data['exam_name'] = $routine->exam?->name ?? $data['exam_name'] ?? null;
        $data['batch_name'] = $routine->batch?->name ?? $data['batch_name'] ?? null;
        $data['room_name'] = $routine->room?->name ?? $routine->room?->room_number ?? $data['room_name'] ?? null;
        $data['exam_type_name'] = $routine->exam?->examType?->name ?? $data['exam_type_name'] ?? null;
        $data['pass_marks'] = $routine->exam?->pass_marks ?? $data['pass_marks'] ?? null;
        $data['total_marks'] = $routine->total_marks ?? $routine->exam?->total_marks ?? $data['total_marks'] ?? null;

        // Normalize exam_date to Y-m-d format (strip time component)
        if (isset($data['exam_date'])) {
            $data['exam_date'] = date('Y-m-d', strtotime($data['exam_date']));
        }

        // Normalize teacher data
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

    /**
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    private function loadBatchDayIntervals(
        ?string $batchId,
        string $startDate,
        string $endDate,
        ?string $sameExamId = null,
        ?string $deliveryMode = null,
        array $ignoreSameExamSubjectIds = [],
    ): array {
        if (!$batchId) {
            return [];
        }

        $ignoreSameExamSubjectIds = array_values(array_unique(array_filter($ignoreSameExamSubjectIds)));

        $query = ExamRoutine::where('batch_id', $batchId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('exam_date', [$startDate, $endDate])
            ->whereHas('exam', fn ($q) => $q->where('is_practice', false));

        if ($deliveryMode === 'online') {
            $query->whereIn('delivery_mode', ['online', 'hybrid']);
        } elseif ($deliveryMode === 'offline') {
            $query->where(function ($q) {
                $q->where('delivery_mode', 'offline')
                    ->orWhereNull('delivery_mode')
                    ->orWhere('delivery_mode', '');
            });
        }

        $intervals = [];
        foreach ($query->get() as $routine) {
            if (
                $sameExamId
                && $routine->exam_id === $sameExamId
                && in_array($routine->subject_id, $ignoreSameExamSubjectIds, true)
            ) {
                continue;
            }

            $date = $routine->exam_date?->format('Y-m-d');
            if (!$date) {
                continue;
            }
            $intervals[$date][] = [
                'start' => $this->normalizeTimeString($routine->start_time),
                'end' => $this->normalizeTimeString($routine->end_time),
            ];
        }

        return $intervals;
    }

    /**
     * @param  array<string, array<int, array{start: string, end: string}>>  $batchDayIntervals
     */
    private function intervalOverlapsBatchDay(array $batchDayIntervals, string $date, string $start, string $end): bool
    {
        foreach ($batchDayIntervals[$date] ?? [] as $slot) {
            if ($slot['start'] < $end && $slot['end'] > $start) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTimeString($time): string
    {
        if (!$time) {
            return '00:00:00';
        }

        return \is_string($time) ? substr($time, 0, 8) : $time->format('H:i:s');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Modules\Exam\app\Models\ExamRoutine>  $query
     * @param  'offline'|'online'|null  $channel
     */
    private function applyDeliveryChannelScope($query, ?string $channel): void
    {
        if ($channel === 'offline') {
            $query->offlineChannel();
        } elseif ($channel === 'online') {
            $query->onlineChannel();
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Modules\Exam\app\Models\ExamRoutine>  $query
     * @param  array{batch_id?: string, class_id?: string, course_id?: string, month?: int|string, year?: int|string}  $filters
     */
    private function applyRoutineScopeFilters($query, array $filters): void
    {
        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }
        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $month = (int) $filters['month'];
            $year = (int) $filters['year'];
            if ($month >= 1 && $month <= 12 && $year >= 2000) {
                $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $query->whereBetween('exam_date', [$start->toDateString(), $end->toDateString()]);
            }
        }
    }

    /**
     * Get subject color by name.
     */
    private function getSubjectColor(string $subjectName): string
    {
        $name = strtolower($subjectName);
        foreach (self::SUBJECT_COLORS as $key => $color) {
            if (str_contains($name, $key)) return $color;
        }
        $palette = ['#3B82F6', '#10B981', '#F59E0B', '#F97316', '#8B5CF6', '#14B8A6', '#EF4444', '#6366F1'];
        return $palette[abs(crc32($name)) % count($palette)];
    }
}
