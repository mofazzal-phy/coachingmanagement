<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Attendance\app\Models\ClassSession;

class ClassSessionService
{
    /**
     * Generate or refresh class sessions from published routines for a date.
     *
     * @return array{created: int, updated: int, total: int, sessions: Collection}
     */
    public function syncForDate(Carbon $date, ?array $batchIds = null): array
    {
        $dayCode = $this->dayCodeFromDate($date);
        if (!$dayCode) {
            return ['created' => 0, 'updated' => 0, 'total' => 0, 'sessions' => collect()];
        }

        $query = ClassRoutine::query()
            ->published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('day_of_week', $dayCode)
            ->forDateRange($date, $date)
            ->ordered();

        if ($batchIds) {
            $query->whereIn('batch_id', $batchIds);
        }

        $routines = $query->get();
        $created = 0;
        $updated = 0;
        $sessions = collect();

        foreach ($routines as $routine) {
            $existing = ClassSession::where('routine_id', $routine->id)
                ->where('session_date', $date->toDateString())
                ->first();

            $session = ClassSession::updateOrCreate(
                [
                    'routine_id' => $routine->id,
                    'session_date' => $date->toDateString(),
                ],
                $this->payloadFromRoutine($routine, $date)
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }

            $sessions->push($session);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $sessions->count(),
            'sessions' => $sessions,
        ];
    }

    /**
     * Ensure a single class session exists for a routine on a date.
     */
    public function ensureFromRoutine(ClassRoutine $routine, Carbon $date): ClassSession
    {
        return ClassSession::updateOrCreate(
            [
                'routine_id' => $routine->id,
                'session_date' => $date->toDateString(),
            ],
            $this->payloadFromRoutine($routine, $date)
        );
    }

    /**
     * Find the routine slot active at a given time for a batch.
     */
    public function findRoutineAtTime(string $batchId, Carbon $at): ?ClassRoutine
    {
        $dayCode = $this->dayCodeFromDate($at);
        if (!$dayCode) {
            return null;
        }

        $currentTime = $at->format('H:i:s');

        return ClassRoutine::query()
            ->published()
            ->notLunchBreak()
            ->notOffDay()
            ->where('batch_id', $batchId)
            ->where('day_of_week', $dayCode)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->forDateRange($at, $at)
            ->ordered()
            ->first();
    }

    public function updateStatus(ClassSession $session, string $status, ?string $cancelReason = null): ClassSession
    {
        $data = ['status' => $status];

        if ($status === 'cancelled') {
            $data['cancelled_at'] = now();
            $data['cancel_reason'] = $cancelReason;
        }

        if ($status === 'in_progress' && $session->status === 'scheduled') {
            $data['status'] = 'in_progress';
        }

        $session->update($data);

        return $session->fresh();
    }

    protected function payloadFromRoutine(ClassRoutine $routine, Carbon $date): array
    {
        return [
            'batch_id' => $routine->batch_id,
            'course_id' => $routine->course_id,
            'class_id' => $routine->class_id,
            'subject_id' => $routine->subject_id,
            'teacher_id' => $routine->teacher_id,
            'room_id' => $routine->room_id,
            'slot_id' => null,
            'start_time' => $routine->start_time,
            'end_time' => $routine->end_time,
            'source' => 'routine',
            'status' => 'scheduled',
        ];
    }

    public function dayCodeFromDate(Carbon $date): ?string
    {
        $map = [
            'Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed',
            'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat',
        ];

        return $map[$date->format('D')] ?? null;
    }
}
