<?php

namespace Modules\Exam\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Exam\app\Models\ExamResult;

class ExamRoutineGridBuilder
{
    public const WEEKDAYS = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** Light pastel backgrounds — one per batch */
    public const BATCH_BG_PALETTE = [
        '#DBEAFE', '#D1FAE5', '#FCE7F3', '#EDE9FE', '#FFEDD5',
        '#CFFAFE', '#FEF3C7', '#E0E7FF', '#CCFBF1', '#F3E8FF',
        '#FEE2E2', '#ECFCCB', '#F5F3FF', '#FFE4E6', '#F1F5F9',
    ];

    /** Muted text colors paired with BATCH_BG_PALETTE */
    public const BATCH_TEXT_PALETTE = [
        '#1E40AF', '#065F46', '#9D174D', '#5B21B6', '#9A3412',
        '#0E7490', '#92400E', '#3730A3', '#0F766E', '#6D28D9',
        '#991B1B', '#3F6212', '#5B21B6', '#BE123C', '#475569',
    ];

    /**
     * @param  Collection<int, mixed>  $routines
     * @return array{bg: array<string, string>, text: array<string, string>, batches: array<int, array<string, mixed>>}
     */
    public function buildBatchColorMaps(Collection $routines): array
    {
        $batchColorMap = [];
        $batchTextColorMap = [];
        $batches = [];
        $batchIds = [];
        $index = 0;

        foreach ($routines as $routine) {
            if (!$routine->batch_id || in_array($routine->batch_id, $batchIds, true)) {
                continue;
            }

            $batchIds[] = $routine->batch_id;
            $slot = $index % count(self::BATCH_BG_PALETTE);
            $bg = self::BATCH_BG_PALETTE[$slot];
            $text = self::BATCH_TEXT_PALETTE[$slot];
            $batchColorMap[$routine->batch_id] = $bg;
            $batchTextColorMap[$routine->batch_id] = $text;
            $index++;

            $batches[] = [
                'id' => $routine->batch_id,
                'name' => $routine->batch?->name ?? 'Batch ' . $routine->batch_id,
                'color' => $bg,
                'text_color' => $text,
                'student_count' => $routine->batch?->students_count ?? $routine->batch?->enrollments_count ?? 0,
            ];
        }

        return [
            'bg' => $batchColorMap,
            'text' => $batchTextColorMap,
            'batches' => $batches,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $routines
     * @return array<int, array<string, mixed>>
     */
    public function buildTimeSlots(Collection $routines, bool $includeLunch = false): array
    {
        $uniqueSlots = [];
        foreach ($routines as $r) {
            if (!$r->start_time) {
                continue;
            }
            $st = $r->timeToString($r->start_time);
            $et = $r->end_time
                ? $r->timeToString($r->end_time)
                : ($r->windowEndAt()?->format('H:i:s') ?? '');
            if (!$st || !$et) {
                continue;
            }
            $key = $st . '-' . $et;
            if (!isset($uniqueSlots[$key])) {
                $uniqueSlots[$key] = ['start' => $st, 'end' => $et];
            }
        }

        uasort($uniqueSlots, fn ($a, $b) => strcmp($a['start'], $b['start']));

        $timeSlots = [];
        $slotIndex = 1;
        $prevEnd = null;

        foreach ($uniqueSlots as $slot) {
            if ($includeLunch && $prevEnd !== null) {
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
            ];
            if ($includeLunch) {
                $timeSlots[] = ['key' => 'lunch_1', 'label' => 'Lunch Break', 'start' => null, 'end' => null, 'is_lunch' => true];
                $timeSlots[] = ['key' => 'slot2', 'label' => 'Slot 2', 'start' => '12:00:00', 'end' => '13:30:00', 'is_lunch' => false];
            }
        }

        return $timeSlots;
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeSlots
     * @return array<int, array<string, mixed>>
     */
    public function buildMonthWeeks(int $year, int $month, array $timeSlots): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $weekStart = $monthStart->copy()->startOfWeek(Carbon::SATURDAY);

        $weeks = [];
        $weekNumber = 0;

        while ($weekStart->lte($monthEnd)) {
            $columns = [];
            $grid = [];
            $cursor = $weekStart->copy();
            $hasDayInMonth = false;
            $firstInMonth = null;
            $lastInMonth = null;

            $workColumns = [];
            $fridayOff = null;

            for ($i = 0; $i < 7; $i++) {
                $dateStr = $cursor->format('Y-m-d');
                $dayName = self::WEEKDAYS[$i];
                $inMonth = (int) $cursor->month === $month;
                if ($inMonth) {
                    $hasDayInMonth = true;
                    if ($firstInMonth === null) {
                        $firstInMonth = $dateStr;
                    }
                    $lastInMonth = $dateStr;
                }

                $grid[$dateStr] = $this->emptyDaySlots($timeSlots);

                if ($dayName === 'Friday') {
                    $fridayOff = [
                        'day_name' => 'Friday',
                        'date' => $dateStr,
                        'in_month' => $inMonth,
                        'date_label' => Carbon::parse($dateStr)->format('M j'),
                    ];
                } else {
                    $workColumns[] = [
                        'day_name' => $dayName,
                        'date' => $dateStr,
                        'in_month' => $inMonth,
                        'is_friday_off' => false,
                    ];
                }

                $cursor->addDay();
            }

            if ($hasDayInMonth) {
                $weekNumber++;

                $weeks[] = [
                    'week_number' => $weekNumber,
                    'label' => '',
                    'week_start' => $weekStart->format('Y-m-d'),
                    'week_end' => $weekStart->copy()->addDays(6)->format('Y-m-d'),
                    'month_range_start' => $firstInMonth,
                    'month_range_end' => $lastInMonth,
                    'columns' => $workColumns,
                    'friday_off' => $fridayOff,
                    'grid' => $grid,
                ];
            }

            $weekStart->addWeek();
        }

        return $this->pruneTrailingWeeks($weeks);
    }

    /**
     * Drop a sparse 6th row (≤2 in-month days) so the grid stays at most 5 week blocks.
     *
     * @param  array<int, array<string, mixed>>  $weeks
     * @return array<int, array<string, mixed>>
     */
    private function pruneTrailingWeeks(array $weeks): array
    {
        while (count($weeks) > 5) {
            array_pop($weeks);
        }

        return $weeks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeSlots
     * @return array<string, array<string, mixed>>
     */
    private function emptyDaySlots(array $timeSlots): array
    {
        $day = [];
        foreach ($timeSlots as $slot) {
            if ($slot['is_lunch'] ?? false) {
                $day[$slot['key']] = [
                    'is_lunch' => true,
                    'label' => $slot['label'],
                    'routines' => [],
                ];
            } else {
                $day[$slot['key']] = [
                    'is_lunch' => false,
                    'label' => $slot['label'],
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'routines' => [],
                ];
            }
        }

        return $day;
    }

    /**
     * @return array{published: array<string, int>, pending: array<string, int>}
     */
    public function resultCountsForRoutines(Collection $routines): array
    {
        $ids = $routines->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return ['published' => [], 'pending' => []];
        }

        $published = ExamResult::query()
            ->whereIn('exam_routine_id', $ids)
            ->where('status', 'published')
            ->selectRaw('exam_routine_id, COUNT(*) as aggregate_count')
            ->groupBy('exam_routine_id')
            ->pluck('aggregate_count', 'exam_routine_id')
            ->all();

        $pending = ExamResult::query()
            ->whereIn('exam_routine_id', $ids)
            ->where('status', 'pending')
            ->selectRaw('exam_routine_id, COUNT(*) as aggregate_count')
            ->groupBy('exam_routine_id')
            ->pluck('aggregate_count', 'exam_routine_id')
            ->all();

        return ['published' => $published, 'pending' => $pending];
    }

    /**
     * @param  array<int, array<string, mixed>>  $weeks
     * @param  Collection<int, mixed>  $routines
     * @param  array<int, array<string, mixed>>  $timeSlots
     * @param  array<string, string>  $batchColorMap
     * @param  array<string, string>  $batchTextColorMap
     * @param  array{published: array<string, int>, pending: array<string, int>}  $resultCounts
     */
    public function populateWeekGrids(
        array &$weeks,
        Collection $routines,
        array $timeSlots,
        array $batchColorMap,
        array $batchTextColorMap = [],
        array $resultCounts = ['published' => [], 'pending' => []],
    ): void {
        $weeksByDate = [];
        foreach ($weeks as $wIdx => $week) {
            foreach ($week['columns'] as $col) {
                $weeksByDate[$col['date']] = $wIdx;
            }
            if (!empty($week['friday_off']['date'])) {
                $weeksByDate[$week['friday_off']['date']] = $wIdx;
            }
        }

        foreach ($routines as $routine) {
            $examDate = $routine->exam_date;
            if (!$examDate) {
                continue;
            }
            $dateStr = $examDate->format('Y-m-d');
            $wIdx = $weeksByDate[$dateStr] ?? null;
            if ($wIdx === null) {
                continue;
            }

            $slotKey = $this->matchRoutineToSlot($routine, $timeSlots);
            if (!$slotKey || !isset($weeks[$wIdx]['grid'][$dateStr][$slotKey])) {
                continue;
            }

            $rid = $routine->id;
            $pub = (int) ($resultCounts['published'][$rid] ?? 0);
            $pend = (int) ($resultCounts['pending'][$rid] ?? 0);

            $weeks[$wIdx]['grid'][$dateStr][$slotKey]['routines'][] = $this->formatRoutineCell(
                $routine,
                $batchColorMap,
                $batchTextColorMap,
                $pub,
                $pend,
            );
        }
    }

    public function computeLifecyclePhase(mixed $routine, int $publishedCount, int $pendingCount): string
    {
        $status = $routine->status ?? 'draft';

        if ($status === 'cancelled') {
            return 'cancelled';
        }
        if ($status === 'draft') {
            return 'draft';
        }
        if ($status === 'completed') {
            return 'completed';
        }

        if (!$this->isExamSlotEnded($routine)) {
            return 'scheduled';
        }

        if ($publishedCount > 0 && $pendingCount === 0) {
            return 'results_out';
        }
        if ($pendingCount > 0) {
            return 'awaiting_publish';
        }
        if ($publishedCount > 0) {
            return 'results_out';
        }

        return 'awaiting_marks';
    }

    private function isExamSlotEnded(mixed $routine): bool
    {
        $end = $routine->windowEndAt();

        return $end ? now()->gte($end) : false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeSlots
     */
    public function matchRoutineToSlot(mixed $routine, array $timeSlots): ?string
    {
        $startTime = $routine->timeToString($routine->start_time);
        $endTime = $routine->end_time
            ? $routine->timeToString($routine->end_time)
            : ($routine->windowEndAt()?->format('H:i:s') ?? '');

        if (!$startTime || !$endTime) {
            return null;
        }

        // Each grid row is keyed by exact start/end — never fold into a wider overlapping row.
        foreach ($timeSlots as $slot) {
            if ($slot['is_lunch'] ?? false) {
                continue;
            }
            if ($startTime === $slot['start'] && $endTime === $slot['end']) {
                return $slot['key'];
            }
        }

        foreach ($timeSlots as $slot) {
            if ($slot['is_lunch'] ?? false) {
                continue;
            }
            if ($startTime === $slot['start']) {
                return $slot['key'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $batchColorMap
     * @return array<string, mixed>
     */
    public function formatRoutineCell(
        mixed $routine,
        array $batchColorMap,
        array $batchTextColorMap = [],
        int $publishedCount = 0,
        int $pendingCount = 0,
    ): array {
        $subjectName = $routine->subject?->name ?? 'Unknown';
        $lifecycle = $this->computeLifecyclePhase($routine, $publishedCount, $pendingCount);

        $deliveryMode = $routine->delivery_mode ?: 'offline';
        $deliveryChannel = $routine->deliveryChannel();
        $isPractice = (bool) ($routine->exam?->is_practice ?? false);

        return [
            'id' => $routine->id,
            'exam_date' => $routine->exam_date->format('Y-m-d'),
            'subject_id' => $routine->subject_id,
            'subject_name' => $subjectName,
            'batch_id' => $routine->batch_id,
            'batch_name' => $routine->batch?->name ?? 'N/A',
            'batch_color' => $batchColorMap[$routine->batch_id] ?? '#F3F4F6',
            'batch_text_color' => $batchTextColorMap[$routine->batch_id] ?? '#4B5563',
            'delivery_mode' => $deliveryMode,
            'delivery_channel' => $deliveryChannel,
            'is_practice' => $isPractice,
            'delivery_label' => $isPractice ? 'Practice' : match ($deliveryMode) {
                'online' => 'Online',
                'hybrid' => 'Hybrid',
                default => 'Offline',
            },
            'room_name' => $routine->room?->name ?? 'TBD',
            'teacher_name' => $routine->teacher?->user?->name
                ?? trim(($routine->teacher?->first_name ?? '') . ' ' . ($routine->teacher?->last_name ?? ''))
                ?: 'Not Assigned',
            'exam_type_name' => $routine->examType?->name ?? '',
            'start_time' => $routine->start_time_formatted,
            'end_time' => $routine->end_time_formatted,
            'duration' => $routine->duration,
            'color' => $this->subjectColor($subjectName),
            'status' => $routine->status,
            'results_published_count' => $publishedCount,
            'results_pending_count' => $pendingCount,
            'lifecycle' => $lifecycle,
        ];
    }

    public function resolveMonthYear(?int $month, ?int $year, ?string $firstDate, ?string $examStart): array
    {
        if ($month && $year) {
            return [$month, $year];
        }

        $anchor = $firstDate ?: $examStart ?: now()->format('Y-m-d');
        $c = Carbon::parse($anchor);

        return [$c->month, $c->year];
    }

    /**
     * Week title anchored on Friday OFF (not "1st/2nd week").
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function buildFridayOffWeekLabel(array $columns, Carbon $monthStart): string
    {
        $fridayDate = null;
        $firstWork = null;
        $lastWork = null;

        foreach ($columns as $col) {
            if (!($col['in_month'] ?? false)) {
                continue;
            }
            if ($col['is_friday_off'] ?? false) {
                $fridayDate = $col['date'];

                continue;
            }
            if ($firstWork === null) {
                $firstWork = $col['date'];
            }
            $lastWork = $col['date'];
        }

        $monthName = $monthStart->format('F Y');

        if ($fridayDate) {
            $fri = Carbon::parse($fridayDate)->format('l, M j');
            if ($firstWork && $lastWork) {
                $from = Carbon::parse($firstWork)->format('M j');
                $to = Carbon::parse($lastWork)->format('M j');

                return sprintf('OFF %s · Sat–Thu %s – %s (%s)', $fri, $from, $to, $monthName);
            }

            return sprintf('OFF %s (%s)', $fri, $monthName);
        }

        if ($firstWork && $lastWork) {
            return sprintf(
                '%s – %s (%s)',
                Carbon::parse($firstWork)->format('M j'),
                Carbon::parse($lastWork)->format('M j'),
                $monthName
            );
        }

        return $monthName;
    }

    private function weekOrdinalLabel(int $n): string
    {
        $labels = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', 6 => '6th'];

        return $labels[$n] ?? ($n . 'th');
    }

    private function subjectColor(string $subjectName): string
    {
        $colors = [
            'physics' => '#3B82F6', 'chemistry' => '#10B981', 'mathematics' => '#F59E0B',
            'math' => '#F59E0B', 'biology' => '#F97316', 'english' => '#8B5CF6',
        ];
        $key = strtolower($subjectName);

        return $colors[$key] ?? '#64748b';
    }
}
