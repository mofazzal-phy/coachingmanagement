<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\app\Models\AttendanceLog;
use Modules\Attendance\app\Models\ClassSession;
use Modules\Attendance\app\Models\TeacherClassLedger;

class TeacherClassLedgerService
{
    public function __construct(
        protected ClassSessionService $classSessionService,
        protected SessionAttendanceService $sessionAttendance,
        protected AttendanceEngine $engine,
    ) {}

    /**
     * Sync ledger rows from class sessions for a date.
     *
     * @return array{created: int, updated: int, total: int, entries: Collection}
     */
    public function syncForDate(Carbon $date, ?array $batchIds = null, bool $refreshClassSessions = true): array
    {
        if ($refreshClassSessions) {
            $this->classSessionService->syncForDate($date, $batchIds);
        }

        $query = ClassSession::with('teacher:id,first_name,last_name,teacher_id,teacher_type')
            ->where('session_date', $date->toDateString());

        if ($batchIds) {
            $query->whereIn('batch_id', $batchIds);
        }

        $sessions = $query->get();
        $created = 0;
        $updated = 0;
        $entries = collect();

        foreach ($sessions as $session) {
            if (!$session->teacher_id) {
                continue;
            }

            $existing = TeacherClassLedger::where('class_session_id', $session->id)->first();
            $ledgerStatus = $this->mapLedgerStatus($session);

            $ledger = TeacherClassLedger::updateOrCreate(
                ['class_session_id' => $session->id],
                [
                    'teacher_id' => $session->teacher_id,
                    'teacher_type' => $session->teacher?->teacher_type ?? 'guest',
                    'batch_id' => $session->batch_id,
                    'subject_id' => $session->subject_id,
                    'session_date' => $session->session_date,
                    'status' => $existing && in_array($existing->status, ['completed', 'no_show'], true)
                        ? $existing->status
                        : $ledgerStatus,
                    'payable_units' => $existing?->payable_units ?? 1,
                ]
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }

            $entries->push($ledger);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $entries->count(),
            'entries' => $entries,
        ];
    }

    /**
     * Bulk mark class ledger entries and link session attendance.
     *
     * @return array{success_count: int, entries: Collection}
     */
    public function bulkMark(array $records, string $date): array
    {
        $dateObj = Carbon::parse($date);
        $marked = collect();

        foreach ($records as $record) {
            $ledger = $this->resolveLedger($record);
            if (!$ledger || !$ledger->teacher_id) {
                continue;
            }

            $markStatus = $record['status'] ?? 'completed';
            $attendanceStatus = $record['attendance_status'] ?? $this->attendanceStatusFromLedger($markStatus);

            $log = null;
            if (in_array($markStatus, ['completed', 'no_show', 'cancelled'], true)) {
                $linked = $this->sessionAttendance->ensureFromClassSession($ledger->class_session_id);
                $sessionId = $linked['attendance_session']->id;
                $classSession = $linked['class_session'];

                $logs = $this->engine->bulkMarkTeacherAttendance(
                    records: [[
                        'teacher_id' => $ledger->teacher_id,
                        'status' => $attendanceStatus,
                        'check_in' => $record['check_in'] ?? null,
                        'check_out' => $record['check_out'] ?? null,
                        'remarks' => $record['remarks'] ?? null,
                        'subject_id' => $ledger->subject_id,
                    ]],
                    date: $date,
                    subjectId: $ledger->subject_id,
                    classId: $classSession->class_id,
                    sessionId: $sessionId,
                );

                $log = $logs[0] ?? null;
            }
            if ($log instanceof AttendanceLog) {
                $ledger->attendance_log_id = $log->id;
            }

            $ledger->status = $markStatus;
            $ledger->notes = $record['notes'] ?? $ledger->notes;
            if ($markStatus === 'completed') {
                $ledger->payable_units = $record['payable_units'] ?? $ledger->payable_units ?? 1;
                ClassSession::where('id', $ledger->class_session_id)->update(['status' => 'completed']);
            } elseif ($markStatus === 'cancelled') {
                ClassSession::where('id', $ledger->class_session_id)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => $record['notes'] ?? 'Cancelled from class ledger',
                ]);
            } elseif ($markStatus === 'no_show') {
                $ledger->payable_units = 0;
            }

            $ledger->save();
            $marked->push($ledger->fresh(['teacher', 'classSession.subject', 'classSession.batch', 'batch', 'subject']));
        }

        return [
            'success_count' => $marked->count(),
            'entries' => $marked,
        ];
    }

    protected function resolveLedger(array $record): ?TeacherClassLedger
    {
        if (!empty($record['ledger_id'])) {
            return TeacherClassLedger::find($record['ledger_id']);
        }

        if (!empty($record['class_session_id'])) {
            return TeacherClassLedger::where('class_session_id', $record['class_session_id'])->first();
        }

        return null;
    }

    protected function mapLedgerStatus(ClassSession $session): string
    {
        return match ($session->status) {
            'cancelled' => 'cancelled',
            'completed' => 'completed',
            default => 'scheduled',
        };
    }

    protected function attendanceStatusFromLedger(string $ledgerStatus): string
    {
        return match ($ledgerStatus) {
            'no_show' => 'absent',
            'cancelled' => 'leave',
            default => 'present',
        };
    }
}
