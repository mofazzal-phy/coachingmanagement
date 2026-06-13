<?php

namespace Modules\Exam\app\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Exam\app\Models\ExamRoutine;

class FixBatchExamOverlaps extends Command
{
    protected $signature = 'exam:fix-batch-overlaps {--dry-run : Preview changes without saving}';

    protected $description = 'Cancel duplicate batch time overlaps within the same delivery mode (keeps earliest-created routine per slot)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $routines = ExamRoutine::whereNotIn('status', ['cancelled'])
            ->whereNotNull('batch_id')
            ->with(['subject', 'exam', 'batch'])
            ->orderBy('created_at')
            ->get();

        $cancelled = 0;

        DB::transaction(function () use ($routines, $dryRun, &$cancelled) {
            $kept = [];

            foreach ($routines as $routine) {
                $date = $routine->exam_date?->format('Y-m-d');
                $batchId = $routine->batch_id;
                if (!$date || !$batchId) {
                    continue;
                }

                if ($routine->exam?->is_practice) {
                    continue;
                }

                $channel = $routine->exam?->delivery_mode ?? 'offline';
                $start = $this->timeStr($routine->start_time);
                $end = $this->timeStr($routine->end_time);
                $overlapKey = null;

                foreach ($kept as $key => $slot) {
                    if ($slot['batch_id'] !== $batchId || $slot['date'] !== $date || $slot['channel'] !== $channel) {
                        continue;
                    }
                    if ($slot['start'] < $end && $slot['end'] > $start) {
                        $overlapKey = $key;
                        break;
                    }
                }

                if ($overlapKey !== null) {
                    $keptSlot = $kept[$overlapKey];
                    $this->line(sprintf(
                        '%s Cancel %s / %s (%s %s–%s) — overlaps %s / %s [%s]',
                        $dryRun ? '[dry-run]' : '[cancel]',
                        $routine->exam?->name,
                        $routine->subject?->name,
                        $date,
                        $start,
                        $end,
                        $keptSlot['exam_name'],
                        $keptSlot['subject_name'],
                        $channel,
                    ));

                    if (!$dryRun) {
                        $routine->update(['status' => 'cancelled']);
                    }
                    $cancelled++;
                    continue;
                }

                $kept[] = [
                    'batch_id' => $batchId,
                    'date' => $date,
                    'channel' => $channel,
                    'start' => $start,
                    'end' => $end,
                    'exam_name' => $routine->exam?->name,
                    'subject_name' => $routine->subject?->name,
                ];
            }
        });

        $this->info(($dryRun ? 'Would cancel' : 'Cancelled') . " {$cancelled} overlapping routine(s).");

        return self::SUCCESS;
    }

    private function timeStr(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        return substr((string) $time, 0, 8);
    }
}
