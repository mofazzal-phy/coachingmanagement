<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamRoutineGridBuilder;
use Modules\Exam\app\Services\ExamRoutineService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class TeacherExamRoutineController extends BaseApiController
{
    public function __construct(
        private ExamRoutineRepository $repository,
        private ExamRoutineService $routineService,
        private ExamRoutineGridBuilder $gridBuilder,
    ) {}

    /**
     * Get teacher's exam schedule.
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $teacherId = $this->getTeacherId();
        $date = $request->query('date');

        $schedule = $this->routineService->getTeacherSchedule($teacherId, $date);

        return $this->success($schedule);
    }

    /**
     * Get today's exam duties for the authenticated teacher.
     */
    public function todayDuties(): JsonResponse
    {
        $teacherId = $this->getTeacherId();
        $routines = $this->repository->getTodayForTeacher($teacherId);

        return $this->success([
            'routines' => $routines->map(fn($r) => [
                'id' => $r->id,
                'exam' => $r->exam?->name ?? 'N/A',
                'exam_type' => $r->exam?->examType?->name ?? 'N/A',
                'subject' => $r->subject?->name ?? 'N/A',
                'batch' => $r->batch?->name,
                'course' => $r->course?->name,
                'class' => $r->class?->name,
                'start_time' => $r->start_time_formatted,
                'end_time' => $r->end_time_formatted,
                'room' => $r->room?->name ?? 'TBD',
                'status' => $r->status,
            ]),
            'total' => $routines->count(),
        ]);
    }

    /**
     * Get exam routines for the teacher's subjects (not just duties).
     * This returns all published routines where the subject matches subjects the teacher teaches.
     */
    public function myExamRoutines(): JsonResponse
    {
        $teacherId = $this->getTeacherId();
        if (!$teacherId) {
            return $this->error('Teacher profile not linked to this account.', 404);
        }
        $routines = $this->routineService->getTeacherSubjectRoutines($teacherId);

        return $this->success($routines);
    }

    /**
     * Exams + batches scoped to the authenticated teacher for the leaderboard UI.
     */
    public function leaderboardContext(): JsonResponse
    {
        $teacherId = $this->getTeacherId();
        if (!$teacherId) {
            return $this->error('Teacher profile not linked to this account.', 404);
        }

        $teacher = \Modules\Teacher\app\Models\Teacher::find($teacherId);
        $subjectIds = $teacher
            ? $teacher->subjects()->pluck('subjects.id')->toArray()
            : [];

        $routines = $this->repository->getRoutinesByTeacherSubjects($teacherId, false);
        $exams = [];

        foreach ($routines->groupBy('exam_id') as $examId => $examRoutines) {
            $exam = $examRoutines->first()?->exam;
            if (!$exam || $exam->is_practice) {
                continue;
            }

            $batchMap = [];
            foreach ($examRoutines as $routine) {
                if (!$routine->batch_id) {
                    continue;
                }
                $batchMap[$routine->batch_id] = [
                    'id' => $routine->batch_id,
                    'name' => $routine->batch?->name ?? 'Batch',
                ];
            }

            $this->appendResultBatchesForTeacher($batchMap, (string) $examId, $subjectIds, $teacherId);
            $batchIds = array_keys($batchMap);

            $offlinePublished = $exam->isResultChannelPublished('offline')
                || $this->hasPublishedResultsForTeacher($examId, 'offline', $batchIds);
            $onlinePublished = $exam->isResultChannelPublished('online')
                || $this->hasPublishedResultsForTeacher($examId, 'online', $batchIds);

            $exams[] = [
                'id' => $exam->id,
                'name' => $exam->name,
                'result_status' => $exam->result_status,
                'online_result_status' => $exam->online_result_status,
                'offline_published' => $offlinePublished,
                'online_published' => $onlinePublished,
                'batches' => array_values($batchMap),
                'routine_count' => $examRoutines->count(),
            ];
        }

        usort($exams, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $this->success([
            'exams' => $exams,
            'total' => count($exams),
        ]);
    }

    /**
     * Include batches where this teacher already has published results (routine batch may differ from schedule).
     *
     * @param  array<string, array{id: string, name: string}>  $batchMap
     * @param  list<string>  $subjectIds
     */
    private function appendResultBatchesForTeacher(
        array &$batchMap,
        string $examId,
        array $subjectIds,
        string $teacherId,
    ): void {
        $resultRoutines = ExamResult::query()
            ->where('exam_id', $examId)
            ->where('status', 'published')
            ->whereHas('routine', function ($q) use ($subjectIds, $teacherId) {
                $q->where(function ($q2) use ($subjectIds, $teacherId) {
                    if (!empty($subjectIds)) {
                        $q2->whereIn('subject_id', $subjectIds);
                    }
                    $q2->orWhere('teacher_id', $teacherId);
                });
            })
            ->with('routine.batch')
            ->get()
            ->pluck('routine')
            ->filter();

        foreach ($resultRoutines as $routine) {
            if (!$routine?->batch_id) {
                continue;
            }
            $batchMap[$routine->batch_id] = [
                'id' => $routine->batch_id,
                'name' => $routine->batch?->name ?? 'Batch',
            ];
        }
    }

    /**
     * @param  array<int, string>  $batchIds
     */
    private function hasPublishedResultsForTeacher(string $examId, string $channel, array $batchIds): bool
    {
        $query = ExamResult::query()
            ->where('exam_id', $examId)
            ->where('status', 'published')
            ->whereHas('routine', function ($q) use ($channel, $batchIds) {
                if ($channel === 'online') {
                    $q->whereIn('delivery_mode', ['online', 'hybrid']);
                } else {
                    $q->where(function ($q2) {
                        $q2->whereNull('delivery_mode')
                            ->orWhereIn('delivery_mode', ['', 'offline']);
                    });
                }

                if (!empty($batchIds)) {
                    $q->whereIn('batch_id', $batchIds);
                }
            });

        return $query->exists();
    }

    /**
     * Get structured grid data for the teacher's exam routines.
     * Groups routines by exam and builds a separate grid per exam,
     * so each exam's time slots are consistent (like admin getGrid()).
     */
    public function grid(): JsonResponse
    {
        $teacherId = $this->getTeacherId();
        $routines = $this->repository->getRoutinesByTeacherSubjects($teacherId);

        // Define the days of the week starting from Saturday (Bangladesh context)
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        $batchMaps = $this->gridBuilder->buildBatchColorMaps($routines);
        $batchColorMap = $batchMaps['bg'];
        $batchTextColorMap = $batchMaps['text'];
        $globalBatches = $batchMaps['batches'];

        // ===== GROUP ROUTINES BY EXAM =====
        $routinesByExam = [];
        foreach ($routines as $routine) {
            $examId = $routine->exam_id ?? 'no-exam';
            if (!isset($routinesByExam[$examId])) {
                $routinesByExam[$examId] = [
                    'exam' => $routine->exam,
                    'routines' => [],
                ];
            }
            $routinesByExam[$examId]['routines'][] = $routine;
        }

        // ===== BUILD A GRID FOR EACH EXAM =====
        $examGrids = [];
        $allExams = [];

        foreach ($routinesByExam as $examId => $group) {
            $exam = $group['exam'];
            $examRoutines = collect($group['routines']);

            // --- Time slots from this exam's routines only ---
            $uniqueSlots = [];
            foreach ($examRoutines as $r) {
                if (!$r->start_time || !$r->end_time) continue;
                $st = $r->start_time->format('H:i:s');
                $et = $r->end_time->format('H:i:s');
                $key = $st . '-' . $et;
                if (!isset($uniqueSlots[$key])) {
                    $uniqueSlots[$key] = [
                        'start' => $st,
                        'end' => $et,
                        'count' => 0,
                    ];
                }
                $uniqueSlots[$key]['count']++;
            }

            uasort($uniqueSlots, function ($a, $b) {
                return strcmp($a['start'], $b['start']);
            });

            $timeSlots = [];
            $slotIndex = 1;
            $prevEnd = null;
            foreach ($uniqueSlots as $key => $slot) {
                if ($prevEnd !== null) {
                    $gapMinutes = (strtotime($slot['start']) - strtotime($prevEnd)) / 60;
                    if ($gapMinutes >= 45) {
                        $timeSlots[] = [
                            'key' => 'lunch_' . count($timeSlots),
                            'label' => 'Lunch Break',
                            'start' => null,
                            'end' => null,
                            'is_lunch' => true,
                        ];
                    }
                }
                $timeSlots[] = [
                    'key' => 'slot' . $slotIndex,
                    'label' => 'Slot ' . $slotIndex,
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'is_lunch' => false,
                ];
                $prevEnd = $slot['end'];
                $slotIndex++;
            }

            if (empty($timeSlots)) {
                $timeSlots = [
                    ['key' => 'slot1', 'label' => 'Slot 1', 'start' => '10:00:00', 'end' => '11:30:00', 'is_lunch' => false],
                    ['key' => 'lunch_1', 'label' => 'Lunch Break', 'start' => null, 'end' => null, 'is_lunch' => true],
                    ['key' => 'slot2', 'label' => 'Slot 2', 'start' => '12:00:00', 'end' => '13:30:00', 'is_lunch' => false],
                ];
            }

            // --- Day dates from actual routine exam_date values ---
            $dayDates = [];
            foreach ($examRoutines as $routine) {
                $examDate = $routine->exam_date;
                if (!$examDate) {
                    continue;
                }
                $dayName = $examDate->format('l');
                $dateStr = $examDate->format('Y-m-d');
                if (!isset($dayDates[$dayName]) || $dateStr < $dayDates[$dayName]) {
                    $dayDates[$dayName] = $dateStr;
                }
            }

            // --- Build the grid ---
            $grid = [];
            foreach ($days as $dayIndex => $dayName) {
                $grid[$dayName] = [];
                foreach ($timeSlots as $slot) {
                    if (($slot['is_lunch'] ?? false)) {
                        $grid[$dayName][$slot['key']] = [
                            'is_lunch' => true,
                            'label' => $slot['label'],
                            'routines' => [],
                        ];
                    } else {
                        $grid[$dayName][$slot['key']] = [
                            'is_lunch' => false,
                            'label' => $slot['label'],
                            'start' => $slot['start'],
                            'end' => $slot['end'],
                            'routines' => [],
                        ];
                    }
                }
            }

            // --- Populate grid with routines ---
            foreach ($examRoutines as $routine) {
                $examDate = $routine->exam_date;
                if (!$examDate) continue;

                $dayName = $examDate->format('l');
                if (!isset($grid[$dayName])) continue;

                $startTime = $routine->start_time ? $routine->start_time->format('H:i:s') : '';
                $endTime = $routine->end_time ? $routine->end_time->format('H:i:s') : '';

                $slotKey = null;
                foreach ($timeSlots as $slot) {
                    if ($slot['is_lunch'] ?? false) continue;
                    if ($startTime < $slot['end'] && $endTime > $slot['start']) {
                        $slotKey = $slot['key'];
                        break;
                    }
                }

                if (!$slotKey) {
                    $nearestSlot = null;
                    $nearestDiff = PHP_INT_MAX;
                    foreach ($timeSlots as $slot) {
                        if ($slot['is_lunch'] ?? false) continue;
                        $diff = abs(strtotime($startTime) - strtotime($slot['start']));
                        if ($diff < $nearestDiff) {
                            $nearestDiff = $diff;
                            $nearestSlot = $slot['key'];
                        }
                    }
                    $slotKey = $nearestSlot;
                }

                $subjectName = $routine->subject?->name ?? 'Unknown';
                $color = $this->getSubjectColor($subjectName);
                $batchColor = $batchColorMap[$routine->batch_id] ?? '#F3F4F6';
                $batchTextColor = $batchTextColorMap[$routine->batch_id] ?? '#4B5563';
                $deliveryMode = $routine->exam?->delivery_mode ?? 'offline';
                $examTypeName = $routine->examType?->name ?? '';

                $grid[$dayName][$slotKey]['routines'][] = [
                    'id' => $routine->id,
                    'subject_id' => $routine->subject_id,
                    'subject_name' => $subjectName,
                    'batch_id' => $routine->batch_id,
                    'batch_name' => $routine->batch?->name ?? 'N/A',
                    'batch_color' => $batchColor,
                    'batch_text_color' => $batchTextColor,
                    'delivery_mode' => $deliveryMode,
                    'delivery_label' => match ($deliveryMode) {
                        'online' => 'Online',
                        'hybrid' => 'Hybrid',
                        default => 'Offline',
                    },
                    'room_name' => $routine->room?->name ?? 'TBD',
                    'teacher_name' => $routine->teacher?->user?->name
                        ?? $routine->teacher?->first_name . ' ' . ($routine->teacher?->last_name ?? '')
                        ?? 'Not Assigned',
                    'exam_type_name' => $examTypeName,
                    'start_time' => $routine->start_time_formatted,
                    'end_time' => $routine->end_time_formatted,
                    'duration' => $routine->duration,
                    'color' => $color,
                    'status' => $routine->status,
                    'exam_name' => $routine->exam?->name ?? 'N/A',
                    'exam_date' => $examDate->format('Y-m-d'),
                ];
            }

            // Calculate first/last dates for this exam
            $firstDate = null;
            $lastDate = null;
            foreach ($examRoutines as $routine) {
                $d = $routine->exam_date;
                if (!$d) continue;
                $dateStr = $d->format('Y-m-d');
                if ($firstDate === null || $dateStr < $firstDate) $firstDate = $dateStr;
                if ($lastDate === null || $dateStr > $lastDate) $lastDate = $dateStr;
            }

            $examGrids[] = [
                'exam_id' => $examId,
                'exam' => $exam ? [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_type' => $exam->examType?->name ?? 'N/A',
                    'batch_name' => $exam->batch?->name ?? 'N/A',
                    'course_name' => $exam->course?->name ?? 'N/A',
                    'class_name' => $exam->class?->name ?? 'N/A',
                    'start_date' => $exam->start_date?->format('Y-m-d'),
                    'end_date' => $exam->end_date?->format('Y-m-d'),
                    'first_date' => $firstDate,
                    'last_date' => $lastDate,
                ] : null,
                'grid' => $grid,
                'days' => $days,
                'day_dates' => $dayDates,
                'time_slots' => $timeSlots,
                'first_date' => $firstDate,
                'last_date' => $lastDate,
                'routine_count' => $examRoutines->count(),
            ];

            // Collect exam info for the overview
            if ($exam && !in_array($examId, array_column($allExams, 'id'))) {
                $allExams[] = [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_type' => $exam->examType?->name ?? 'N/A',
                    'batch_name' => $exam->batch?->name ?? 'N/A',
                    'course_name' => $exam->course?->name ?? 'N/A',
                    'class_name' => $exam->class?->name ?? 'N/A',
                    'start_date' => $exam->start_date?->format('Y-m-d'),
                    'end_date' => $exam->end_date?->format('Y-m-d'),
                    'routine_count' => $examRoutines->count(),
                ];
            }
        }

        return $this->success([
            'exam_grids' => $examGrids,
            'days' => $days,
            'batches' => $globalBatches,
            'batch_colors' => $batchColorMap,
            'batch_text_colors' => $batchTextColorMap,
            'exams' => $allExams,
        ]);
    }

    /**
     * Get subject color by name.
     */
    private function getSubjectColor(string $subjectName): string
    {
        $colors = [
            'physics' => '#3B82F6',
            'chemistry' => '#10B981',
            'mathematics' => '#F97316',
            'english' => '#8B5CF6',
            'bangla' => '#EC4899',
            'biology' => '#06B6D4',
            'higher math' => '#EF4444',
            'accounting' => '#14B8A6',
            'business' => '#84CC16',
            'finance' => '#6366F1',
            'economics' => '#0EA5E9',
            'history' => '#A855F7',
            'geography' => '#D946EF',
            'ict' => '#F43F5E',
            'religion' => '#78716C',
        ];

        $lower = strtolower(trim($subjectName));
        foreach ($colors as $key => $color) {
            if (str_contains($lower, $key)) {
                return $color;
            }
        }

        // Fallback: generate color from hash
        $hash = crc32($subjectName);
        $hue = abs($hash) % 360;
        return "hsl({$hue}, 65%, 55%)";
    }

    /**
     * Get upcoming exam duties for the authenticated teacher.
     */
    public function upcomingDuties(): JsonResponse
    {
        $teacherId = $this->getTeacherId();

        $routines = $this->repository->getTeacherSchedule($teacherId)
            ->filter(fn($r) => $r->exam_date->format('Y-m-d') >= now()->format('Y-m-d'))
            ->take(10);

        return $this->success([
            'routines' => $routines->values()->map(fn($r) => [
                'id' => $r->id,
                'exam' => $r->exam?->name ?? 'N/A',
                'subject' => $r->subject?->name ?? 'N/A',
                'date' => $r->exam_date->format('d M, Y'),
                'day' => $r->exam_date->format('l'),
                'start_time' => $r->start_time_formatted,
                'end_time' => $r->end_time_formatted,
                'room' => $r->room?->name ?? 'TBD',
            ]),
            'total' => $routines->count(),
        ]);
    }

    /**
     * Mark attendance for an exam duty.
     */
    public function markAttendance(Request $request, string $routineId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:present,absent,late',
            'notes' => 'nullable|string|max:500',
        ]);

        $routine = $this->repository->findById($routineId);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        // Verify the teacher owns this routine
        $teacherId = $this->getTeacherId();
        if ($routine->teacher_id !== $teacherId) {
            return $this->forbidden('You are not assigned to this exam duty');
        }

        // Update routine with attendance info
        $routine->update([
            'teacher_attendance' => $request->input('status'),
            'teacher_notes' => $request->input('notes'),
            'attendance_marked_at' => now(),
        ]);

        return $this->success($routine->fresh(['exam', 'subject']), 'Attendance marked successfully');
    }

    /**
     * Get the authenticated teacher's ID.
     */
    private function getTeacherId(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'teacher') && $user->teacher) {
            return (string) $user->teacher->id;
        }

        $teacher = \Modules\Teacher\app\Models\Teacher::where('user_id', $user->id)->first();

        return $teacher ? (string) $teacher->id : null;
    }
}
