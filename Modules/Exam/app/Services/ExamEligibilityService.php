<?php

namespace Modules\Exam\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Models\ExamStudentEligibility;
use Modules\Settings\app\Models\Setting;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Finance\app\Models\FeeStructure;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Finance\app\Models\StudentFeeNotification;
use Modules\Student\app\Models\Student;
use RuntimeException;

class ExamEligibilityService
{
    public function __construct(
        private readonly AttendanceEngine $attendanceEngine,
        private readonly ExamBatchChannelPolicyService $batchPolicyService,
    ) {}

    public function isCheckEnabled(Exam $exam, string $channel = 'offline', ?string $batchId = null): bool
    {
        if ($exam->is_practice) {
            return false;
        }

        return (bool) $this->batchPolicyService->rulesForScope($exam, $channel, $batchId)['check_enabled'];
    }

    public function isFeeApplicable(Exam $exam, string $channel = 'offline', ?string $batchId = null): bool
    {
        return (bool) $this->batchPolicyService->rulesForScope($exam, $channel, $batchId)['fee_applicable'];
    }

    /**
     * @return array{eligible_min: float, warning_min: float}
     */
    public function thresholds(Exam $exam, string $channel = 'offline', ?string $batchId = null): array
    {
        $rules = $this->batchPolicyService->rulesForScope($exam, $channel, $batchId);
        $eligibleMin = $rules['min_percent'] !== null
            ? (float) $rules['min_percent']
            : (float) (Setting::where('key', 'attendance_eligibility_eligible_min')->value('value') ?? 75);

        $warningMin = (float) (Setting::where('key', 'attendance_eligibility_warning_min')->value('value') ?? 60);

        return [
            'eligible_min' => $eligibleMin,
            'warning_min' => $warningMin,
            'has_warning_band' => $warningMin < $eligibleMin,
        ];
    }

    /**
     * Attendance window: session start (or 90d lookback) through exam start (or today).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function attendanceWindow(Exam $exam): array
    {
        $exam->loadMissing('session');

        $end = $exam->start_date
            ? Carbon::parse($exam->start_date)->min(Carbon::today())
            : Carbon::today();

        if ($exam->session?->start_date) {
            $start = Carbon::parse($exam->session->start_date);
        } else {
            $start = $end->copy()->subDays(90);
        }

        if ($start->greaterThan($end)) {
            $start = $end->copy()->subDays(30);
        }

        return [$start, $end];
    }

    public function resolveStatus(float $percentage, Exam $exam, string $channel = 'offline', ?string $batchId = null): array
    {
        $thresholds = $this->thresholds($exam, $channel, $batchId);
        $eligibleMin = $thresholds['eligible_min'];
        $warningMin = $thresholds['warning_min'];

        if ($percentage >= $eligibleMin) {
            return [
                'status' => ExamStudentEligibility::STATUS_ELIGIBLE,
                'label' => 'Eligible',
                'eligible' => true,
                'can_download_admit' => true,
            ];
        }

        // Warning band only when it sits below the eligible threshold (e.g. 60–74% with eligible at 75%)
        if ($eligibleMin > $warningMin && $percentage >= $warningMin) {
            return [
                'status' => ExamStudentEligibility::STATUS_WARNING,
                'label' => 'Warning',
                'eligible' => true,
                'can_download_admit' => true,
            ];
        }

        return [
            'status' => ExamStudentEligibility::STATUS_BLOCKED,
            'label' => 'Blocked',
            'eligible' => false,
            'can_download_admit' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function computeForStudent(Exam $exam, string $studentId, bool $persist = true, string $channel = 'offline'): array
    {
        $batchId = $this->resolveStudentExamBatchId($exam, $studentId);

        if (!$this->isCheckEnabled($exam, $channel, $batchId)) {
            return $this->applyExamFeeGate($exam, $studentId, [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'student_id' => $studentId,
                'status' => ExamStudentEligibility::STATUS_ELIGIBLE,
                'label' => 'Not required',
                'eligible' => true,
                'can_download_admit' => true,
                'attendance_percent' => null,
                'is_override' => false,
                'check_enabled' => false,
                'message' => $channel === 'online'
                    ? 'Attendance check is disabled for this batch.'
                    : 'Attendance eligibility is disabled for this batch.',
            ], $channel, $batchId);
        }

        [$start, $end] = $this->attendanceWindow($exam);
        $summary = $this->attendanceEngine->getStudentSummary($studentId, $start, $end);
        $percentage = (float) ($summary['percentage'] ?? 0);
        $resolved = $this->resolveStatus($percentage, $exam, $channel, $batchId);
        $thresholds = $this->thresholds($exam, $channel, $batchId);

        $record = ExamStudentEligibility::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->first();

        if ($record?->is_override) {
            return $this->formatRow($exam, $studentId, $record, $summary, $thresholds, true, $channel);
        }

        if ($persist) {
            $record = ExamStudentEligibility::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $resolved['status'],
                    'attendance_percent' => $percentage,
                    'is_override' => false,
                    'override_reason' => null,
                    'overridden_by' => null,
                    'overridden_at' => null,
                    'computed_at' => now(),
                ]
            );
        }

        return $this->applyExamFeeGate(
            $exam,
            $studentId,
            [
                'exam_id' => $exam->id,
                'student_id' => $studentId,
                'status' => $resolved['status'],
                'label' => $resolved['label'],
                'eligible' => $resolved['eligible'],
                'can_download_admit' => $resolved['can_download_admit'],
                'attendance_percent' => $percentage,
                'attendance_summary' => [
                    'total' => $summary['total'] ?? 0,
                    'present' => $summary['present'] ?? 0,
                    'absent' => $summary['absent'] ?? 0,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ],
                'is_override' => false,
                'override_reason' => null,
                'computed_at' => $record?->computed_at?->toIso8601String(),
                'thresholds' => $thresholds,
                'check_enabled' => $this->isCheckEnabled($exam, $channel, $batchId),
            ],
            $channel,
            $batchId
        );
    }

    /**
     * @return array{synced: int, students: array<int, array<string, mixed>>}
     */
    public function evaluateExam(string $examId, string $channel = 'offline', ?string $batchId = null): array
    {
        $exam = Exam::findOrFail($examId);
        $students = $this->studentsForExam($exam, $channel);

        if ($batchId) {
            $students = $students->filter(function (Student $student) use ($batchId) {
                return Enrollment::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->where('batch_id', $batchId)
                    ->exists();
            })->values();
        }

        $rows = [];

        foreach ($students as $student) {
            $rows[] = $this->computeForStudent($exam, $student->id, true, $channel);
        }

        return [
            'exam_id' => $examId,
            'delivery_channel' => $channel,
            'batch_id' => $batchId,
            'check_enabled' => $batchId
                ? $this->isCheckEnabled($exam, $channel, $batchId)
                : $this->isCheckEnabled($exam, $channel),
            'synced' => count($rows),
            'students' => $rows,
            'summary' => $this->summarizeRows($rows),
        ];
    }

    /**
     * Summary counts for admin cards without building full student rows.
     *
     * @return array{eligible: int, warning: int, blocked: int, overridden: int, total: int}
     */
    public function summaryForExam(string $examId, string $channel = 'offline'): array
    {
        $exam = Exam::findOrFail($examId);
        $students = $this->studentsForExam($exam, $channel);
        $studentIds = $students->pluck('id');
        $totalEnrolled = $studentIds->count();

        $records = ExamStudentEligibility::query()
            ->where('exam_id', $examId)
            ->when($studentIds->isNotEmpty(), fn ($q) => $q->whereIn('student_id', $studentIds->all()))
            ->get();

        if ($records->isEmpty()) {
            return [
                'eligible' => 0,
                'warning' => 0,
                'blocked' => 0,
                'overridden' => 0,
                'total' => $totalEnrolled,
            ];
        }

        $rows = $records->map(fn ($r) => [
            'status' => $r->status,
            'is_override' => $r->is_override,
        ])->all();

        $summary = $this->summarizeRows($rows);
        $summary['total'] = max($totalEnrolled, $summary['total']);

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{eligible: int, warning: int, blocked: int, overridden: int, total: int}
     */
    public function summarizeRows(array $rows): array
    {
        $counts = ['eligible' => 0, 'warning' => 0, 'blocked' => 0, 'overridden' => 0];

        foreach ($rows as $row) {
            if (!empty($row['is_override']) || ($row['status'] ?? '') === ExamStudentEligibility::STATUS_OVERRIDDEN) {
                $counts['overridden']++;
                continue;
            }

            $status = strtolower((string) ($row['status'] ?? ExamStudentEligibility::STATUS_ELIGIBLE));
            if ($status === 'not_eligible' || $status === 'blocked') {
                $counts['blocked']++;
            } elseif (isset($counts[$status])) {
                $counts[$status]++;
            } else {
                $counts['eligible']++;
            }
        }

        $counts['total'] = count($rows);

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStudentStatus(string $examId, string $studentId, bool $refresh = false, string $channel = 'offline'): array
    {
        $exam = Exam::findOrFail($examId);
        $batchId = $this->resolveStudentExamBatchId($exam, $studentId);

        if (!$this->isCheckEnabled($exam, $channel, $batchId)) {
            return $this->applyExamFeeGate($exam, $studentId, [
                'exam_id' => $examId,
                'exam_name' => $exam->name,
                'student_id' => $studentId,
                'status' => ExamStudentEligibility::STATUS_ELIGIBLE,
                'label' => 'Not required',
                'eligible' => true,
                'can_download_admit' => true,
                'attendance_percent' => null,
                'is_override' => false,
                'check_enabled' => false,
                'message' => $channel === 'online'
                    ? 'Attendance check is disabled for this batch.'
                    : 'Attendance eligibility is disabled for this batch.',
            ], $channel, $batchId);
        }

        $record = ExamStudentEligibility::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();

        if ($refresh || !$record || (!$record->is_override && !$record->computed_at)) {
            return $this->computeForStudent($exam, $studentId, true, $channel);
        }

        if ($record->is_override) {
            [$start, $end] = $this->attendanceWindow($exam);
            $summary = $this->attendanceEngine->getStudentSummary($studentId, $start, $end);

            return $this->formatRow($exam, $studentId, $record, $summary, $this->thresholds($exam, $channel, $batchId), true, $channel);
        }

        $resolved = $this->resolveStatus((float) $record->attendance_percent, $exam, $channel, $batchId);

        $canDownload = $resolved['can_download_admit'] || $record->is_override;
        $eligible = $resolved['eligible'] || $record->is_override;

        return $this->applyExamFeeGate($exam, $studentId, [
            'exam_id' => $examId,
            'student_id' => $studentId,
            'status' => $record->status,
            'label' => $resolved['label'],
            'eligible' => $eligible,
            'can_download_admit' => $canDownload,
            'attendance_percent' => (float) $record->attendance_percent,
            'is_override' => $record->is_override,
            'override_reason' => $record->override_reason,
            'overridden_at' => $record->overridden_at?->toIso8601String(),
            'computed_at' => $record->computed_at?->toIso8601String(),
            'thresholds' => $this->thresholds($exam, $channel, $batchId),
            'check_enabled' => true,
            'message' => $eligible
                ? null
                : 'Your attendance is below the required minimum for this exam.',
        ], $channel, $batchId);
    }

    public function override(string $examId, string $studentId, string $reason, string $userId): array
    {
        $exam = Exam::findOrFail($examId);
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('Override reason is required.');
        }

        $this->studentsForExam($exam)->firstWhere('id', $studentId)
            ?? throw new RuntimeException('Student is not enrolled for this exam scope.');

        [$start, $end] = $this->attendanceWindow($exam);
        $summary = $this->attendanceEngine->getStudentSummary($studentId, $start, $end);
        $percentage = (float) ($summary['percentage'] ?? 0);

        $record = ExamStudentEligibility::updateOrCreate(
            [
                'exam_id' => $examId,
                'student_id' => $studentId,
            ],
            [
                'status' => ExamStudentEligibility::STATUS_OVERRIDDEN,
                'attendance_percent' => $percentage,
                'is_override' => true,
                'override_reason' => $reason,
                'overridden_by' => $userId,
                'overridden_at' => now(),
                'computed_at' => now(),
            ]
        );

        return $this->formatRow($exam, $studentId, $record, $summary, $this->thresholds($exam), true);
    }

    /**
     * @throws RuntimeException
     */
    public function assertCanStartOnlineExam(string $examId, string $studentId): array
    {
        $status = $this->getStudentStatus($examId, $studentId, true, 'online');

        if (($status['eligible'] ?? true) === false) {
            throw new RuntimeException($status['message'] ?? 'You are not eligible to start this online exam.');
        }

        return $status;
    }

    public function assertCanDownloadAdmitCard(string $examId, string $studentId): array
    {
        $exam = Exam::findOrFail($examId);
        $status = $this->getStudentStatus($examId, $studentId, true, 'offline');

        $unpaidExamFee = $this->getUnpaidExamFeeForExam($exam, $studentId, 'offline');
        if ($unpaidExamFee) {
            throw new RuntimeException($unpaidExamFee['message']);
        }

        if (($status['check_enabled'] ?? false) && !($status['can_download_admit'] ?? false)) {
            $pct = $status['attendance_percent'] ?? 0;
            $thresholds = $status['thresholds'] ?? [];
            $min = $thresholds['eligible_min'] ?? 75;
            throw new RuntimeException(
                "Admit card unavailable: attendance is {$pct}% (minimum required {$min}%). Contact the office for assistance."
            );
        }

        return $status;
    }

    /**
     * Unpaid exam-fee notification or assignment for this exam's batch scope.
     *
     * @return array{title: string, amount: float, message: string}|null
     */
    public function getUnpaidExamFeeForExam(Exam $exam, string $studentId, string $channel = 'offline'): ?array
    {
        $batchId = $this->resolveStudentExamBatchId($exam, $studentId);
        if (!$this->isFeeApplicable($exam, $channel, $batchId)) {
            return null;
        }

        $batchIds = $this->resolveExamBatchIds($exam);

        $enrollmentQuery = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active');

        if ($batchIds->isNotEmpty()) {
            $enrollmentQuery->whereIn('batch_id', $batchIds->all());
        } elseif ($exam->academic_session_id) {
            $enrollmentQuery->where('academic_session_id', $exam->academic_session_id);
        }

        $enrollmentIds = $enrollmentQuery->pluck('id');
        if ($enrollmentIds->isEmpty()) {
            return null;
        }

        $notifications = StudentFeeNotification::query()
            ->where('student_id', $studentId)
            ->whereIn('enrollment_id', $enrollmentIds->all())
            ->where('type', 'exam_fee')
            ->with('feeStructure')
            ->get()
            ->filter(function ($notification) use ($exam) {
                $metaExamId = $notification->meta['exam_id'] ?? null;
                return $metaExamId === $exam->id;
            })
            ->values();

        if ($notifications->isEmpty()) {
            return null;
        }

        $unpaidTitles = [];
        $unpaidAmount = 0.0;
        $primaryNotification = null;
        $primaryEnrollmentId = null;

        foreach ($notifications as $notification) {
            $title = $notification->title ?? 'Exam Fee';
            $notifAmount = (float) ($notification->amount ?? 0);

            $assignment = StudentFeeAssignment::query()
                ->where('enrollment_id', $notification->enrollment_id)
                ->where('fee_structure_id', $notification->fee_structure_id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->first();

            if ($assignment) {
                $remaining = max(
                    0,
                    ($assignment->final_amount + ($assignment->late_fee_applied ?? 0)) - $assignment->paid_amount
                );
                if ($remaining > 0) {
                    $unpaidTitles[] = $title;
                    $unpaidAmount += $remaining;
                    $primaryNotification ??= $notification;
                    $primaryEnrollmentId ??= $notification->enrollment_id;
                    continue;
                }
            }

            if (in_array($notification->status, ['unread', 'read'], true)) {
                $unpaidTitles[] = $title;
                $unpaidAmount += $notifAmount;
                $primaryNotification ??= $notification;
                $primaryEnrollmentId ??= $notification->enrollment_id;
            }
        }

        if ($unpaidTitles === []) {
            return null;
        }

        $title = $unpaidTitles[0];
        $amountLabel = number_format($unpaidAmount, 0);

        return [
            'title' => $title,
            'amount' => $unpaidAmount,
            'notification_id' => $primaryNotification?->id,
            'enrollment_id' => $primaryEnrollmentId,
            'due_date' => $primaryNotification?->due_date?->format('Y-m-d'),
            'message' => $channel === 'online'
                ? "Online exam locked: please pay your exam fee ({$title}, ৳{$amountLabel}) from the student portal first."
                : "Admit card unavailable: please pay your exam fee ({$title}, ৳{$amountLabel}) from the student portal first.",
        ];
    }

    /**
     * Cash/manual payment submitted but not yet confirmed by admin.
     *
     * @return array{message: string, payment_method: string, transaction_id: string}|null
     */
    protected function getPendingExamFeePayment(Exam $exam, string $studentId): ?array
    {
        $batchIds = $this->resolveExamBatchIds($exam);

        $query = PaymentTransaction::query()
            ->where('student_id', $studentId)
            ->where('status', 'pending');

        if ($batchIds->isNotEmpty()) {
            $query->whereHas('enrollment', fn ($q) => $q->whereIn('batch_id', $batchIds->all()));
        }

        $pending = $query
            ->whereHas('allocations.feeAssignment.feeStructure', fn ($q) => $q->where('exam_id', $exam->id))
            ->orderByDesc('created_at')
            ->first();

        if (!$pending) {
            return null;
        }

        return [
            'message' => 'Your exam fee payment is awaiting admin approval. '
                . ($pending->payment_method === 'cash' ? 'Cash' : ucfirst((string) $pending->payment_method))
                . ' payment must be confirmed before you can access this exam.',
            'payment_method' => (string) $pending->payment_method,
            'transaction_id' => $pending->id,
        ];
    }

    /**
     * Configured exam fee amount for a student's enrollment scope (even if already paid).
     *
     * @return array{title: string, amount: float, due_date: string|null}|null
     */
    public function getExamFeeInfoForStudent(Exam $exam, string $studentId, string $channel = 'offline'): ?array
    {
        $batchId = $this->resolveStudentExamBatchId($exam, $studentId);
        if (!$this->isFeeApplicable($exam, $channel, $batchId)) {
            return null;
        }

        $batchIds = $this->resolveExamBatchIds($exam);
        $enrollmentQuery = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active');

        if ($batchIds->isNotEmpty()) {
            $enrollmentQuery->whereIn('batch_id', $batchIds->all());
        } elseif ($exam->academic_session_id) {
            $enrollmentQuery->where('academic_session_id', $exam->academic_session_id);
        }

        $enrollment = $enrollmentQuery->with('batch.course')->first();
        if (!$enrollment) {
            return null;
        }

        $notification = StudentFeeNotification::query()
            ->where('student_id', $studentId)
            ->where('enrollment_id', $enrollment->id)
            ->where('type', 'exam_fee')
            ->where('meta->exam_id', $exam->id)
            ->orderByDesc('created_at')
            ->first();

        if ($notification) {
            return [
                'title' => $notification->title ?? 'Exam Fee',
                'amount' => (float) ($notification->amount ?? 0),
                'due_date' => $notification->due_date?->format('Y-m-d'),
                'status' => $notification->status,
                'enrollment_id' => $enrollment->id,
                'notification_id' => $notification->id,
            ];
        }

        $structureQuery = FeeStructure::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'active');

        if ($enrollment->batch?->course_id) {
            $structureQuery->where('course_id', $enrollment->batch->course_id);
        }

        $structure = $structureQuery->first();
        if (!$structure) {
            return null;
        }

        return [
            'title' => $structure->description ?? 'Exam Fee',
            'amount' => (float) $structure->amount,
            'due_date' => $structure->due_date?->format('Y-m-d'),
            'status' => null,
            'enrollment_id' => $enrollment->id,
            'notification_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    protected function applyExamFeeGate(
        Exam $exam,
        string $studentId,
        array $status,
        string $channel = 'offline',
        ?string $batchId = null,
    ): array {
        $batchId ??= $this->resolveStudentExamBatchId($exam, $studentId);
        $status['delivery_channel'] = $channel;
        $status['batch_id'] = $batchId;
        $status['exam_fee_applicable'] = $this->isFeeApplicable($exam, $channel, $batchId);
        $status['exam_fee_info'] = $this->getExamFeeInfoForStudent($exam, $studentId, $channel);
        if (!empty($status['exam_fee_info']['enrollment_id'])) {
            $status['enrollment_id'] = $status['exam_fee_info']['enrollment_id'];
        }

        $pending = $this->getPendingExamFeePayment($exam, $studentId);
        if ($pending) {
            $status['exam_fee_pending_approval'] = true;
            $status['exam_fee_unpaid'] = true;
            $status['pending_payment'] = $pending;
            $status['can_download_admit'] = false;
            $status['eligible'] = false;
            $status['message'] = $pending['message'];

            return $status;
        }

        $unpaidExamFee = $this->getUnpaidExamFeeForExam($exam, $studentId, $channel);
        if (!$unpaidExamFee) {
            $status['exam_fee_unpaid'] = false;
            $status['exam_fee_pending_approval'] = false;

            return $status;
        }

        $status['can_download_admit'] = false;
        $status['exam_fee_unpaid'] = true;
        $status['unpaid_exam_fee'] = $unpaidExamFee;
        $status['enrollment_id'] = $unpaidExamFee['enrollment_id'] ?? ($status['enrollment_id'] ?? null);
        $status['message'] = $unpaidExamFee['message'];
        $status['eligible'] = false;

        return $status;
    }

    /**
     * Batch IDs tied to this exam's routine grid (optionally per delivery channel).
     *
     * @return Collection<int, string>
     */
    protected function resolveExamBatchIds(Exam $exam, ?string $channel = null): Collection
    {
        if ($channel !== null) {
            return $this->batchPolicyService->batchIdsForExamChannel($exam->id, $channel);
        }

        $fromRoutines = ExamRoutine::query()
            ->where('exam_id', $exam->id)
            ->whereNotNull('batch_id')
            ->where('status', '!=', 'cancelled')
            ->distinct()
            ->pluck('batch_id');

        $ids = collect();

        if ($exam->batch_id) {
            $ids->push($exam->batch_id);
        }

        return $ids->merge($fromRoutines)->filter()->unique()->values();
    }

    /**
     * Active enrollment batch IDs for a student.
     *
     * @return Collection<int, string>
     */
    public function resolveStudentExamBatchId(Exam $exam, string $studentId): ?string
    {
        $examBatchIds = $this->resolveExamBatchIds($exam);

        $query = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active');

        if ($examBatchIds->isNotEmpty()) {
            $query->whereIn('batch_id', $examBatchIds->all());
        }

        return $query->value('batch_id');
    }

    public function activeEnrollmentBatchIds(string $studentId): Collection
    {
        return Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Whether a published routine belongs to one of the student's active batches.
     */
    public function isStudentInRoutineScope(ExamRoutine $routine, string $studentId): bool
    {
        if (!$routine->batch_id) {
            return false;
        }

        return $this->activeEnrollmentBatchIds($studentId)->contains($routine->batch_id);
    }

    /**
     * Restrict an exam-routine query to the student's enrolled batches only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ExamRoutine>  $query
     */
    public function scopeRoutinesToStudentBatches($query, string $studentId): void
    {
        $batchIds = $this->activeEnrollmentBatchIds($studentId)->all();

        if ($batchIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('batch_id', $batchIds);
    }

    /**
     * Batch/course/class labels for a student-facing routine card.
     * Prefers the student's enrollment class over exam-level defaults.
     *
     * @return array{batch_name: ?string, course_name: ?string, class_name: ?string, exam_type_name: ?string}
     */
    public function resolveRoutineDisplayMeta(ExamRoutine $routine, string $studentId): array
    {
        $routine->loadMissing(['exam.examType', 'exam.class', 'exam.course', 'batch.course.class', 'class']);

        $exam = $routine->exam;
        $batch = $routine->batch;
        $course = $batch?->course ?? $exam?->course;

        $enrollment = $routine->batch_id
            ? Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->where('batch_id', $routine->batch_id)
                ->with('enrolledClass')
                ->first()
            : null;

        $className = $enrollment?->enrolledClass?->name
            ?? $routine->class?->name
            ?? $course?->class?->name
            ?? $exam?->class?->name;

        return [
            'batch_name' => $batch?->name,
            'course_name' => $course?->name,
            'class_name' => $className,
            'exam_type_name' => $exam?->examType?->name,
        ];
    }

    /**
     * Whether a student belongs to this exam's audience (inverse of studentsForExam for one id).
     */
    public function isStudentInExamScope(Exam $exam, string $studentId): bool
    {
        $student = Student::find($studentId);
        if (!$student || !$this->isActiveStudent($student)) {
            return false;
        }

        $exam->loadMissing(['session']);
        $batchIds = $this->resolveExamBatchIds($exam);

        if ($batchIds->isNotEmpty()) {
            $query = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->whereIn('batch_id', $batchIds->all());

            if ($this->enrollmentExistsForExam($query, $exam)) {
                return true;
            }
        }

        if ($exam->course_id) {
            $query = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->whereHas('batch', fn ($q) => $q->where('course_id', $exam->course_id));

            if ($this->enrollmentExistsForExam($query, $exam)) {
                return true;
            }
        } elseif ($exam->class_id) {
            $query = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->whereHas('batch.course', fn ($q) => $q->where('class_id', $exam->class_id));

            if ($this->enrollmentExistsForExam($query, $exam)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Enrollment>  $query
     */
    protected function enrollmentExistsForExam($query, Exam $exam): bool
    {
        if ($exam->academic_session_id) {
            $scoped = clone $query;
            if ($scoped->where('academic_session_id', $exam->academic_session_id)->exists()) {
                return true;
            }
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsForExam(Exam $exam, ?string $channel = null): Collection
    {
        $exam->loadMissing(['session']);

        $batchIds = $this->resolveExamBatchIds($exam, $channel);

        if ($batchIds->isNotEmpty()) {
            return $this->studentsFromBatchIds($batchIds, $exam);
        }

        if ($channel !== null) {
            return collect();
        }

        $enrollmentQuery = Enrollment::query()
            ->where('status', 'active')
            ->with(['student.user']);

        if ($exam->course_id) {
            $enrollmentQuery->whereHas('batch', fn ($q) => $q->where('course_id', $exam->course_id));
        } elseif ($exam->class_id) {
            $enrollmentQuery->whereHas('batch.course', fn ($q) => $q->where('class_id', $exam->class_id));
        } else {
            return collect();
        }

        return $this->pluckActiveStudentsFromEnrollments($enrollmentQuery, $exam);
    }

    /**
     * @param  Collection<int, string>  $batchIds
     * @return Collection<int, Student>
     */
    protected function studentsFromBatchIds(Collection $batchIds, Exam $exam): Collection
    {
        $query = Enrollment::query()
            ->where('status', 'active')
            ->whereIn('batch_id', $batchIds->all())
            ->with(['student.user']);

        return $this->pluckActiveStudentsFromEnrollments($query, $exam);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Enrollment>  $enrollmentQuery
     * @return Collection<int, Student>
     */
    protected function pluckActiveStudentsFromEnrollments($enrollmentQuery, Exam $exam): Collection
    {
        if ($exam->academic_session_id) {
            $scoped = clone $enrollmentQuery;
            $scoped->where('academic_session_id', $exam->academic_session_id);
            $students = $this->collectStudentsFromEnrollmentQuery($scoped);
            if ($students->isNotEmpty()) {
                return $students;
            }
        }

        return $this->collectStudentsFromEnrollmentQuery($enrollmentQuery);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Enrollment>  $query
     * @return Collection<int, Student>
     */
    protected function collectStudentsFromEnrollmentQuery($query): Collection
    {
        return $query->get()
            ->pluck('student')
            ->filter(fn ($s) => $s && $this->isActiveStudent($s))
            ->unique('id')
            ->values();
    }

    protected function isActiveStudent(Student $student): bool
    {
        $status = strtolower((string) ($student->status ?? 'active'));

        return !in_array($status, ['inactive', 'suspended', 'left', 'deleted', 'blocked'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForExam(
        string $examId,
        bool $refresh = false,
        string $channel = 'offline',
        ?string $batchId = null,
    ): array {
        $exam = Exam::findOrFail($examId);
        $students = $this->studentsForExam($exam, $channel);

        if ($batchId) {
            $students = $students->filter(function (Student $student) use ($batchId) {
                return Enrollment::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->where('batch_id', $batchId)
                    ->exists();
            })->values();
        }

        $rows = [];

        $shouldRefresh = $refresh && (
            $batchId
                ? $this->isCheckEnabled($exam, $channel, $batchId)
                : $this->batchPolicyService->policyScope($exam, $channel) === 'all'
                    && $this->isCheckEnabled($exam, $channel)
        );

        foreach ($students as $student) {
            $row = $this->getStudentStatus(
                $examId,
                $student->id,
                $shouldRefresh,
                $channel
            );
            $row['student'] = [
                'id' => $student->id,
                'name' => $student->user?->name ?? $student->full_name ?? trim($student->first_name . ' ' . $student->last_name),
                'student_id' => $student->student_id ?? $student->id_number,
            ];
            $rows[] = $row;
        }

        usort($rows, fn ($a, $b) => ($a['attendance_percent'] ?? 0) <=> ($b['attendance_percent'] ?? 0));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array{eligible_min: float, warning_min: float}  $thresholds
     * @return array<string, mixed>
     */
    protected function formatRow(
        Exam $exam,
        string $studentId,
        ExamStudentEligibility $record,
        array $summary,
        array $thresholds,
        bool $fromOverride,
        string $channel = 'offline',
    ): array {
        return $this->applyExamFeeGate($exam, $studentId, [
            'exam_id' => $exam->id,
            'exam_name' => $exam->name,
            'student_id' => $studentId,
            'status' => $record->status,
            'label' => $fromOverride ? 'Overridden' : ($this->resolveStatus((float) $record->attendance_percent, $exam, $channel)['label'] ?? ''),
            'eligible' => true,
            'can_download_admit' => true,
            'attendance_percent' => (float) $record->attendance_percent,
            'attendance_summary' => [
                'total' => $summary['total'] ?? 0,
                'present' => $summary['present'] ?? 0,
                'absent' => $summary['absent'] ?? 0,
            ],
            'is_override' => (bool) $record->is_override,
            'override_reason' => $record->override_reason,
            'overridden_at' => $record->overridden_at?->toIso8601String(),
            'computed_at' => $record->computed_at?->toIso8601String(),
            'thresholds' => $thresholds,
            'check_enabled' => $this->isCheckEnabled($exam, $channel),
        ], $channel);
    }
}
