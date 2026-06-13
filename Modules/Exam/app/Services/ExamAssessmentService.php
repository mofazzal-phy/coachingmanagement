<?php

namespace Modules\Exam\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\app\Services\GradingService;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamAttempt;
use Modules\Exam\app\Models\ExamMeritSnapshot;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Models\ExamRoutine;

class ExamAssessmentService
{
    public const COMPONENT_KEYS = ['mcq', 'cq', 'written', 'practical'];

    public function __construct(
        private readonly GradingService $gradingService,
        private readonly LeaderboardSettingsService $leaderboardSettings,
    ) {
    }

    /**
     * @param  'offline'|'online'|null  $channel
     */
    public function resolveResultChannel(?string $channel): string
    {
        return in_array($channel, ['offline', 'online'], true) ? $channel : 'offline';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  'offline'|'online'|null  $channel
     */
    public function applyResultDeliveryChannelScope($query, ?string $channel): void
    {
        $channel = $this->resolveResultChannel($channel);
        $query->whereHas('routine', function ($q) use ($channel) {
            if ($channel === 'online') {
                $q->whereIn('delivery_mode', ['online', 'hybrid']);
            } else {
                $q->where(function ($q) {
                    $q->where('delivery_mode', 'offline')
                        ->orWhereNull('delivery_mode')
                        ->orWhere('delivery_mode', '');
                });
            }
        });
    }

    public function channelResultStatus(Exam $exam, string $channel): string
    {
        return $exam->resultPublicationStatusForChannel($this->resolveResultChannel($channel));
    }

    /**
     * Whether any published result rows exist for this exam channel.
     * Used on read paths when exam-level publish flags are out of sync with individual results.
     *
     * @param  list<string>|null  $batchIds
     */
    public function hasPublishedResultsForChannel(
        string $examId,
        string $channel,
        ?array $batchIds = null,
    ): bool {
        $channel = $this->resolveResultChannel($channel);
        $query = ExamResult::query()
            ->where('exam_id', $examId)
            ->where('status', 'published');

        $this->applyResultDeliveryChannelScope($query, $channel);

        if ($batchIds !== null && $batchIds !== []) {
            $query->whereHas('routine', fn ($q) => $q->whereIn('batch_id', $batchIds));
        }

        return $query->exists();
    }

    /**
     * Read path: channel is viewable when officially published or published rows already exist.
     *
     * @param  list<string>|null  $batchIds
     */
    public function canViewChannelResults(Exam $exam, string $channel, ?array $batchIds = null): bool
    {
        $channel = $this->resolveResultChannel($channel);

        if ($exam->isResultChannelPublished($channel)) {
            return true;
        }

        return $this->hasPublishedResultsForChannel($exam->id, $channel, $batchIds);
    }

    /**
     * Pick offline vs online marksheet channel without mixing hybrid exam results.
     *
     * @param  'offline'|'online'|null  $requested
     */
    public function resolveMarksheetDeliveryChannel(Exam $exam, string $studentId, ?string $requested = null): string
    {
        if (in_array($requested, ['offline', 'online'], true)) {
            return $requested;
        }

        $offlineQuery = ExamResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->where('status', 'published');
        $this->applyResultDeliveryChannelScope($offlineQuery, 'offline');
        $offlineCount = $offlineQuery->count();

        $onlineQuery = ExamResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->where('status', 'published');
        $this->applyResultDeliveryChannelScope($onlineQuery, 'online');
        $onlineCount = $onlineQuery->count();

        if ($offlineCount > 0 && $onlineCount === 0) {
            return 'offline';
        }
        if ($onlineCount > 0 && $offlineCount === 0) {
            return 'online';
        }

        $offlinePublished = $exam->isResultChannelPublished('offline');
        $onlinePublished = $exam->isResultChannelPublished('online');
        if ($offlinePublished && !$onlinePublished) {
            return 'offline';
        }
        if ($onlinePublished && !$offlinePublished) {
            return 'online';
        }

        throw new \InvalidArgumentException('Specify delivery_channel (offline or online) for this exam.');
    }

    /**
     * @return Collection<int, ExamResult>
     */
    public function assertRoutineAllowsMarkEntry(?ExamRoutine $routine): void
    {
        if (!$routine) {
            throw new \InvalidArgumentException('Exam routine not found.');
        }

        if (!in_array($routine->status, ['published', 'completed'], true)) {
            throw new \InvalidArgumentException(
                'Marks can only be entered after this exam routine is published.'
            );
        }

        $examDate = $routine->exam_date?->format('Y-m-d') ?? (string) $routine->exam_date;
        $endTime = $routine->end_time
            ? (\is_string($routine->end_time) ? substr($routine->end_time, 0, 8) : $routine->end_time->format('H:i:s'))
            : '23:59:59';

        $slotEnd = \Carbon\Carbon::parse($examDate . ' ' . $endTime);
        if (now()->lt($slotEnd)) {
            throw new \InvalidArgumentException(
                'This exam slot has not ended yet. Marks entry opens after ' . $slotEnd->format('d M, Y h:i A') . '.'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateMarksAgainstConfig(array $record, array $markConfig, float $totalMarks): void
    {
        $components = $this->activeComponents($markConfig);
        $breakdown = $record['marks_breakdown'] ?? [];

        if (!empty($components)) {
            foreach ($components as $key => $config) {
                if (!array_key_exists($key, $breakdown)) {
                    continue;
                }
                $value = $breakdown[$key];
                if ($value === null || $value === '') {
                    continue;
                }
                $max = (float) ($config['max_marks'] ?? 0);
                if ($max > 0 && (float) $value > $max) {
                    throw new \InvalidArgumentException(
                        strtoupper($key) . " marks cannot exceed {$max}."
                    );
                }
            }
        }

        $obtained = $record['marks_obtained'] ?? $this->sumBreakdown($breakdown);
        if ($obtained !== null && $totalMarks > 0 && (float) $obtained > $totalMarks) {
            throw new \InvalidArgumentException("Total marks cannot exceed {$totalMarks}.");
        }
    }

    public function saveBulkResults(array $payload, string $enteredBy): Collection
    {
        $routine = ExamRoutine::find($payload['exam_routine_id']);
        $this->assertRoutineAllowsMarkEntry($routine);
        $markConfig = $this->normalizeMarkConfig($routine?->mark_config);
        $totalMarks = $this->resolveTotalMarks($routine, (float) $payload['total_marks']);
        $saved = collect();

        foreach ($payload['results'] as $record) {
            $this->validateMarksAgainstConfig($record, $markConfig, $totalMarks);
            $saved->push($this->saveSingleResult(
                examId: $payload['exam_id'],
                examRoutineId: $payload['exam_routine_id'],
                subjectId: $payload['subject_id'],
                totalMarks: $totalMarks,
                markConfig: $markConfig,
                record: $record,
                enteredBy: $enteredBy,
            ));
        }

        return $saved;
    }

    public function saveSingleResult(
        string $examId,
        string $examRoutineId,
        string $subjectId,
        float $totalMarks,
        array $markConfig,
        array $record,
        string $enteredBy,
    ): ExamResult {
        $routine = ExamRoutine::find($examRoutineId);
        $this->assertRoutineAllowsMarkEntry($routine);
        $markConfig = $this->normalizeMarkConfig($routine?->mark_config);
        $totalMarks = $this->resolveTotalMarks($routine, $totalMarks);

        $existing = ExamResult::where('exam_routine_id', $examRoutineId)
            ->where('student_id', $record['student_id'])
            ->first();

        $requestedStatus = $record['status'] ?? null;
        $breakdown = $this->resolveBreakdown($record, $markConfig);

        if ($existing && !empty($existing->marks_breakdown) && is_array($existing->marks_breakdown)) {
            $breakdown = array_merge($existing->marks_breakdown, $breakdown);
        }

        $this->validateMarksAgainstConfig(
            array_merge($record, ['marks_breakdown' => $breakdown, 'marks_obtained' => $this->sumBreakdown($breakdown)]),
            $markConfig,
            $totalMarks
        );
        $marksObtained = $this->sumBreakdown($breakdown);

        if ($existing && $requestedStatus !== 'absent' && $this->resultMarksUnchanged($existing, $marksObtained, $breakdown, $record['remarks'] ?? null)) {
            return $existing;
        }

        $status = $requestedStatus ?? 'pending';
        if ($existing?->status === 'published' && $requestedStatus !== 'absent') {
            $status = $this->resultMarksUnchanged($existing, $marksObtained, $breakdown, $record['remarks'] ?? null)
                ? 'published'
                : 'pending';
        }

        if ($status === 'absent') {
            $marksObtained = null;
            $breakdown = [];
        }

        $payload = [
            'exam_id' => $examId,
            'exam_routine_id' => $examRoutineId,
            'student_id' => $record['student_id'],
            'subject_id' => $subjectId,
            'marks_obtained' => $marksObtained,
            'marks_breakdown' => $breakdown ?: null,
            'total_marks' => $totalMarks,
            'grade' => $record['grade'] ?? null,
            'grade_point' => $record['grade_point'] ?? null,
            'remarks' => $record['remarks'] ?? null,
            'status' => $status === 'published' ? 'published' : ($status === 'absent' ? 'absent' : 'pending'),
            'evaluation_status' => $this->deriveEvaluationStatus($breakdown, $markConfig, $status),
            'entered_by' => $enteredBy,
        ];

        if ($marksObtained !== null) {
            $calculated = $this->calculateResultGrade($marksObtained, $totalMarks, $breakdown, $markConfig);
            $payload['grade'] = $calculated['grade'];
            $payload['grade_point'] = $calculated['grade_point'];
        } elseif ($status === 'absent') {
            $payload['grade'] = null;
            $payload['grade_point'] = null;
            $payload['evaluation_status'] = 'complete';
        }

        return ExamResult::updateOrCreate(
            [
                'exam_routine_id' => $examRoutineId,
                'student_id' => $record['student_id'],
            ],
            $payload
        );
    }

    /**
     * Persist MCQ auto-score from an online exam attempt (CQ/written remain for manual entry).
     *
     * @param  array<string, mixed>  $evaluation
     */
    public function applyOnlineAttemptScores(ExamAttempt $attempt, array $evaluation): ExamResult
    {
        $routine = $attempt->routine ?? ExamRoutine::find($attempt->exam_routine_id);
        if (!$routine) {
            throw new \RuntimeException('Exam routine not found for attempt.');
        }

        $enteredBy = auth()->id();
        if (!$enteredBy) {
            $attempt->loadMissing('student');
            $enteredBy = $attempt->student?->user_id;
        }
        if (!$enteredBy) {
            throw new \RuntimeException('Cannot record result: user context missing.');
        }

        $markConfig = $this->normalizeMarkConfig($routine->mark_config);
        $totalMarks = $this->resolveTotalMarks($routine, (float) ($routine->total_marks ?? $evaluation['total_marks'] ?? 0));

        $mcqScore = (float) ($evaluation['score'] ?? 0);
        $breakdown = ['mcq' => $mcqScore];

        $hasManualComponents = collect($markConfig)->contains(
            fn ($cfg, $key) => in_array($key, ['cq', 'written', 'practical'], true)
                && (float) ($cfg['max_marks'] ?? 0) > 0
        );

        $hasCqQuestions = collect($evaluation['breakdown'] ?? [])->contains(
            fn ($item) => in_array($item['question_type'] ?? '', ['cq', 'written', 'practical'], true)
        );

        $evaluationStatus = ($hasManualComponents || $hasCqQuestions) ? 'partial' : 'complete';
        $marksObtained = $mcqScore;

        $gradeData = $marksObtained > 0
            ? $this->calculateResultGrade($marksObtained, $totalMarks, $breakdown, $markConfig)
            : ['grade' => null, 'grade_point' => null];

        return ExamResult::updateOrCreate(
            [
                'exam_routine_id' => $routine->id,
                'student_id' => $attempt->student_id,
            ],
            [
                'exam_id' => $routine->exam_id,
                'subject_id' => $routine->subject_id,
                'exam_attempt_id' => $attempt->id,
                'marks_obtained' => $marksObtained,
                'marks_breakdown' => $breakdown,
                'total_marks' => $totalMarks,
                'grade' => $gradeData['grade'],
                'grade_point' => $gradeData['grade_point'],
                'status' => 'pending',
                'evaluation_status' => $evaluationStatus,
                'remarks' => 'Auto-scored MCQ from online attempt',
                'entered_by' => $enteredBy,
            ]
        );
    }

    /**
     * True when marks, breakdown, and remarks match an existing row (skip no-op saves).
     */
    public function resultMarksUnchanged(
        ExamResult $existing,
        ?float $marksObtained,
        array $breakdown,
        ?string $remarks,
    ): bool {
        $existingBreakdown = $existing->marks_breakdown ?? [];
        $normalizedNew = $this->normalizeBreakdownForCompare($breakdown);
        $normalizedOld = $this->normalizeBreakdownForCompare(is_array($existingBreakdown) ? $existingBreakdown : []);

        if ($normalizedNew !== $normalizedOld) {
            return false;
        }

        $oldMarks = $existing->marks_obtained !== null ? round((float) $existing->marks_obtained, 2) : null;
        $newMarks = $marksObtained !== null ? round((float) $marksObtained, 2) : null;

        if ($oldMarks !== $newMarks) {
            return false;
        }

        $oldRemarks = trim((string) ($existing->remarks ?? ''));
        $newRemarks = trim((string) ($remarks ?? ''));

        return $oldRemarks === $newRemarks;
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<string, string>
     */
    private function normalizeBreakdownForCompare(array $breakdown): array
    {
        $out = [];
        foreach ($breakdown as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out[(string) $key] = (string) round((float) $value, 2);
        }
        ksort($out);

        return $out;
    }

    public function sumBreakdown(?array $breakdown): ?float
    {
        if (empty($breakdown)) {
            return null;
        }

        $sum = 0.0;
        $hasValue = false;

        foreach ($breakdown as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $sum += (float) $value;
            $hasValue = true;
        }

        return $hasValue ? round($sum, 2) : null;
    }

    public function deriveEvaluationStatus(?array $breakdown, ?array $markConfig, ?string $status = null): string
    {
        if ($status === 'absent') {
            return 'complete';
        }

        $breakdown = $breakdown ?? [];
        $components = $this->activeComponents($markConfig);

        if (empty($components)) {
            return $this->sumBreakdown($breakdown) !== null ? 'complete' : 'pending';
        }

        $filled = 0;
        $required = count($components);
        $manualPending = false;

        foreach ($components as $key => $config) {
            $value = $breakdown[$key] ?? null;
            $hasValue = $value !== null && $value !== '';

            if ($hasValue) {
                $filled++;
                continue;
            }

            if (($config['evaluation'] ?? 'manual') === 'manual') {
                $manualPending = true;
            }
        }

        if ($filled === 0) {
            return 'pending';
        }

        if ($filled === $required) {
            return 'complete';
        }

        return $manualPending ? 'partial' : 'partial';
    }

    /**
     * @return array<string, array{max_marks: float, pass_marks: float, evaluation: string, enabled?: bool}>
     */
    public function normalizeMarkConfig(?array $markConfig): array
    {
        if (empty($markConfig)) {
            return [];
        }

        $normalized = [];

        foreach (self::COMPONENT_KEYS as $key) {
            if (!isset($markConfig[$key]) || !is_array($markConfig[$key])) {
                continue;
            }

            $component = $markConfig[$key];
            $enabled = $component['enabled'] ?? true;

            if (!$enabled) {
                continue;
            }

            $normalized[$key] = [
                'max_marks' => (float) ($component['max_marks'] ?? 0),
                'pass_marks' => (float) ($component['pass_marks'] ?? 0),
                'evaluation' => (string) ($component['evaluation'] ?? 'manual'),
                'enabled' => true,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array{max_marks: float, pass_marks: float, evaluation: string}>
     */
    public function activeComponents(?array $markConfig): array
    {
        return $this->normalizeMarkConfig($markConfig);
    }

    /**
     * Letter grade from total; MCQ+CQ (etc.) each must meet pass_marks or grade is F.
     *
     * @return array{grade: string, grade_point: float, percentage: float}
     */
    public function calculateResultGrade(float $marksObtained, float $totalMarks, ?array $breakdown, ?array $markConfig): array
    {
        $breakdown = $breakdown ?? [];
        $components = $this->activeComponents($markConfig);
        $withPass = array_filter(
            $components,
            fn ($config) => (float) ($config['pass_marks'] ?? 0) > 0
        );

        if (count($components) >= 2 && count($withPass) >= 1) {
            foreach ($withPass as $key => $config) {
                $passMarks = (float) $config['pass_marks'];
                $value = $breakdown[$key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                if ((float) $value < $passMarks) {
                    $percentage = $totalMarks > 0
                        ? round(($marksObtained / $totalMarks) * 100, 2)
                        : 0.0;

                    return [
                        'grade' => 'F',
                        'grade_point' => 0.0,
                        'percentage' => $percentage,
                    ];
                }
            }

            $allPresent = true;
            foreach ($withPass as $key => $config) {
                $value = $breakdown[$key] ?? null;
                if ($value === null || $value === '') {
                    $allPresent = false;
                    break;
                }
            }

            if ($allPresent) {
                $allPass = true;
                foreach ($withPass as $key => $config) {
                    if ((float) ($breakdown[$key] ?? 0) < (float) $config['pass_marks']) {
                        $allPass = false;
                        break;
                    }
                }

                if (!$allPass) {
                    $percentage = $totalMarks > 0
                        ? round(($marksObtained / $totalMarks) * 100, 2)
                        : 0.0;

                    return [
                        'grade' => 'F',
                        'grade_point' => 0.0,
                        'percentage' => $percentage,
                    ];
                }
            }
        }

        return $this->gradingService->calculateFromMarks($marksObtained, $totalMarks);
    }

    public function resultSubjectPassed(ExamResult $result): bool
    {
        if ($result->status === 'absent') {
            return true;
        }

        if ($result->marks_obtained === null) {
            return false;
        }

        $markConfig = $this->normalizeMarkConfig($result->routine?->mark_config);
        $breakdown = is_array($result->marks_breakdown) ? $result->marks_breakdown : [];
        $components = $this->activeComponents($markConfig);
        $withPass = array_filter(
            $components,
            fn ($config) => (float) ($config['pass_marks'] ?? 0) > 0
        );

        if (count($components) >= 2 && count($withPass) >= 1) {
            foreach ($withPass as $key => $config) {
                $value = $breakdown[$key] ?? null;
                if ($value === null || $value === '') {
                    return false;
                }
                if ((float) $value < (float) $config['pass_marks']) {
                    return false;
                }
            }

            return true;
        }

        return $result->marks_obtained >= (float) ($result->routine?->pass_marks ?? 0);
    }

    public function resolveTotalMarks(?ExamRoutine $routine, float $fallback): float
    {
        if (!$routine) {
            return $fallback > 0 ? $fallback : 100;
        }

        $components = $this->activeComponents($routine->mark_config);
        if (!empty($components)) {
            $sum = array_sum(array_column($components, 'max_marks'));
            if ($sum > 0) {
                return $sum;
            }
        }

        if ($routine->total_marks > 0) {
            return (float) $routine->total_marks;
        }

        return $fallback > 0 ? $fallback : 100;
    }

    private function resolveBreakdown(array $record, array $markConfig): array
    {
        if (!empty($record['marks_breakdown']) && is_array($record['marks_breakdown'])) {
            return $record['marks_breakdown'];
        }

        if (array_key_exists('marks_obtained', $record) && $record['marks_obtained'] !== null && $record['marks_obtained'] !== '') {
            $key = empty($markConfig) ? 'total' : array_key_first($markConfig);

            return [$key => (float) $record['marks_obtained']];
        }

        return [];
    }

    /**
     * Preview data before publishing exam-level results.
     *
     * @return array<string, mixed>
     */
    public function getPublishPreview(string $examId, ?string $deliveryChannel = 'offline'): array
    {
        $channel = $this->resolveResultChannel($deliveryChannel);
        $exam = Exam::with(['examType', 'batch', 'course', 'class', 'routines.subject'])->findOrFail($examId);

        $query = ExamResult::where('exam_id', $examId);
        $this->applyResultDeliveryChannelScope($query, $channel);
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $evaluationCounts = (clone $query)->selectRaw('evaluation_status, COUNT(*) as total')
            ->groupBy('evaluation_status')
            ->pluck('total', 'evaluation_status');

        $evaluationPending = (int) (($evaluationCounts['pending'] ?? 0) + ($evaluationCounts['partial'] ?? 0));
        $pending = (int) ($counts['pending'] ?? 0);
        $published = (int) ($counts['published'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);

        $passFail = $this->estimatePassFail($examId, $channel);
        $channelStatus = $this->channelResultStatus($exam, $channel);

        $routineCount = $exam->routines->filter(function ($routine) use ($channel) {
            $routineChannel = ExamRoutine::resolveDeliveryChannel($routine->delivery_mode ?: 'offline');

            return $routineChannel === $channel;
        })->count();

        $warnings = [];
        if ($evaluationPending > 0) {
            $warnings[] = "{$evaluationPending} result(s) still have incomplete evaluation.";
        }
        if ($pending === 0 && $channelStatus !== 'published') {
            $warnings[] = 'No pending results to publish. Enter marks first.';
        }
        if ($routineCount === 0) {
            $warnings[] = 'This exam has no ' . $channel . ' routines configured.';
        }

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'result_status' => $channelStatus,
                'result_publish_at' => $channel === 'online'
                    ? $exam->online_result_publish_at?->toIso8601String()
                    : $exam->result_publish_at?->toIso8601String(),
                'delivery_channel' => $channel,
                'offline_result_status' => $exam->result_status ?? 'draft',
                'online_result_status' => $exam->online_result_status ?? 'draft',
                'batch' => $exam->batch?->name,
                'course' => $exam->course?->name,
                'class' => $exam->class?->name,
                'routine_count' => $routineCount,
            ],
            'counts' => [
                'pending' => $pending,
                'published' => $published,
                'absent' => $absent,
                'total' => $counts->sum(),
                'evaluation_pending' => $evaluationPending,
                'evaluation_complete' => (int) ($evaluationCounts['complete'] ?? 0),
            ],
            'pass_fail_estimate' => $passFail,
            'warnings' => $warnings,
            'can_publish' => $pending > 0 && $channelStatus !== 'published',
            'is_published' => $channelStatus === 'published',
        ];
    }

    /**
     * Publish all pending results and mark the exam as published.
     *
     * @return array<string, mixed>
     */
    public function publishExamResults(
        string $examId,
        string $userId,
        bool $allowPartial = false,
        ?string $deliveryChannel = 'offline',
    ): array {
        $exam = Exam::findOrFail($examId);
        $channel = $this->resolveResultChannel($deliveryChannel);

        if ($this->channelResultStatus($exam, $channel) === 'published') {
            throw new \InvalidArgumentException(
                ucfirst($channel) . ' results for this exam are already published.'
            );
        }

        $evaluationQuery = ExamResult::where('exam_id', $examId)
            ->whereIn('evaluation_status', ['pending', 'partial'])
            ->where('status', '!=', 'absent');
        $this->applyResultDeliveryChannelScope($evaluationQuery, $channel);
        $evaluationPending = $evaluationQuery->count();

        if ($evaluationPending > 0 && !$allowPartial) {
            throw new \InvalidArgumentException(
                "{$evaluationPending} result(s) have incomplete evaluation. Complete them or use allow_partial=true."
            );
        }

        $pendingQuery = ExamResult::where('exam_id', $examId)->where('status', 'pending');
        $this->applyResultDeliveryChannelScope($pendingQuery, $channel);
        $pendingCount = $pendingQuery->count();

        if ($pendingCount === 0) {
            throw new \InvalidArgumentException('No pending ' . $channel . ' results to publish.');
        }

        return DB::transaction(function () use ($exam, $examId, $evaluationPending, $allowPartial, $channel) {
            if ($channel === 'online') {
                $exam->update(['online_result_status' => 'processing']);
            } else {
                $exam->update(['result_status' => 'processing']);
            }

            $publishQuery = ExamResult::where('exam_id', $examId)->where('status', 'pending');
            $this->applyResultDeliveryChannelScope($publishQuery, $channel);
            $publishedCount = $publishQuery->update(['status' => 'published']);

            if ($channel === 'online') {
                $exam->update([
                    'online_result_status' => 'published',
                    'online_result_publish_at' => now(),
                ]);
                $publishAt = $exam->fresh()->online_result_publish_at?->toIso8601String();
            } else {
                $exam->update([
                    'result_status' => 'published',
                    'result_publish_at' => now(),
                ]);
                $publishAt = $exam->fresh()->result_publish_at?->toIso8601String();
            }

            $exam->refresh();
            $this->warmMeritSnapshotsOnPublish($examId, $channel);

            return [
                'exam_id' => $examId,
                'published_count' => $publishedCount,
                'delivery_channel' => $channel,
                'result_status' => 'published',
                'result_publish_at' => $publishAt,
                'had_partial_evaluation' => $allowPartial && $evaluationPending > 0,
            ];
        });
    }

    /**
     * Compute merit list with competition ranking (ties share the same rank).
     *
     * @return array<string, mixed>
     */
    public function computeMeritList(
        string $examId,
        ?string $scopeType = null,
        ?string $scopeId = null,
        int $topN = 20,
        ?string $deliveryChannel = 'offline',
        bool $useSnapshot = true,
    ): array {
        $channel = $this->resolveResultChannel($deliveryChannel);
        $exam = Exam::with(['batch', 'course', 'class'])->findOrFail($examId);

        if ($useSnapshot) {
            $cached = $this->getMeritSnapshotPayload($exam, $channel, $scopeType, $scopeId);
            if ($cached !== null) {
                $limited = $topN > 0
                    ? array_slice($cached['merit_list'], 0, $topN)
                    : $cached['merit_list'];

                return array_merge($cached, [
                    'top' => $topN,
                    'merit_list' => $limited,
                    'from_snapshot' => true,
                ]);
            }
        }

        $aggregated = $this->aggregateStudentTotals($examId, 'published', $scopeType, $scopeId, $channel);

        $ranked = $this->applyCompetitionRanking($aggregated);
        $totalStudents = count($ranked);
        $limited = $topN > 0 ? array_slice($ranked, 0, $topN) : $ranked;

        $scopeMeta = $this->resolveMeritScopeMeta($exam, $scopeType, $scopeId);

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'result_status' => $this->channelResultStatus($exam, $channel),
                'delivery_channel' => $channel,
            ],
            'scope' => $scopeMeta,
            'merit_scope' => $scopeMeta,
            'ranking_rule' => 'competition',
            'total_students' => $totalStudents,
            'top' => $topN,
            'merit_list' => $limited,
            'subject_toppers' => $this->getSubjectToppersList($examId, $channel, $scopeType, $scopeId),
            'disclaimer' => $scopeMeta['label']
                ? 'Rank among ' . $scopeMeta['label'] . ' students based on published subjects.'
                : 'Rank based on published subjects.',
            'from_snapshot' => false,
        ];
    }

    /**
     * Build a student-facing leaderboard with scoped ranking and self-row guarantee.
     *
     * @return array<string, mixed>
     */
    public function buildStudentLeaderboard(
        string $examId,
        string $studentId,
        ?string $deliveryChannel = 'offline',
        int $topN = 50,
    ): array {
        $channel = $this->resolveResultChannel($deliveryChannel);
        $exam = Exam::with(['batch', 'course', 'class'])->findOrFail($examId);
        $student = \Modules\Student\app\Models\Student::findOrFail($studentId);

        if ($exam->is_practice) {
            throw new \InvalidArgumentException('Leaderboard is not available for practice exams.');
        }

        $publishedQuery = ExamResult::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('status', 'published');
        $this->applyResultDeliveryChannelScope($publishedQuery, $channel);

        if (!$publishedQuery->exists()) {
            throw new \InvalidArgumentException('No published results found for this exam.');
        }

        if (!$this->canViewChannelResults($exam, $channel)) {
            throw new \InvalidArgumentException(ucfirst($channel) . ' results are not published yet.');
        }

        $settings = $this->leaderboardSettings->get();
        $topN = max(1, min($topN, $settings['student_top_limit']));
        $meritScope = $this->resolveMeritScopeForRanking($exam, $student);
        $merit = $this->computeMeritList(
            $examId,
            $meritScope['type'],
            $meritScope['id'],
            $topN,
            $channel,
        );

        $standing = $this->getStudentExamStanding($examId, $studentId, $channel, $meritScope);
        $topIds = collect($merit['merit_list'])->pluck('student_id')->all();
        $inTopList = in_array($studentId, $topIds, true);

        $leaderboard = $this->applyStudentPrivacyToRows(
            array_map(
                fn ($row) => array_merge($row, ['is_me' => $row['student_id'] === $studentId]),
                $merit['merit_list'],
            ),
            $settings['anonymize_names'],
        );

        if (!$inTopList && $standing) {
            $ranked = $this->applyCompetitionRanking($this->aggregateStudentTotals(
                $examId,
                'published',
                $meritScope['type'],
                $meritScope['id'],
                $channel,
            ));

            foreach ($ranked as $row) {
                if ($row['student_id'] === $studentId) {
                    $leaderboard[] = array_merge($row, ['is_me' => true]);
                    break;
                }
            }
        }

        $publishAt = $channel === 'online'
            ? $exam->online_result_publish_at?->toIso8601String()
            : $exam->result_publish_at?->toIso8601String();

        return [
            'exam' => array_merge($merit['exam'], ['published_at' => $publishAt]),
            'merit_scope' => $meritScope,
            'ranking_rule' => $merit['ranking_rule'],
            'total_students' => $merit['total_students'],
            'top' => $topN,
            'my_standing' => $standing
                ? array_merge($standing, ['in_top_list' => $inTopList])
                : null,
            'leaderboard' => $leaderboard,
            'subject_toppers' => $this->getSubjectToppersList(
                $examId,
                $channel,
                $meritScope['type'] ?? null,
                $meritScope['id'] ?? null,
            ),
            'disclaimer' => $merit['disclaimer'],
            'privacy' => [
                'anonymize_names' => $settings['anonymize_names'],
                'student_top_limit' => $settings['student_top_limit'],
            ],
            'from_snapshot' => $merit['from_snapshot'] ?? false,
            'is_provisional' => false,
        ];
    }

    /**
     * Unofficial MCQ-only standings before official result publication.
     *
     * @return array<string, mixed>
     */
    public function buildProvisionalMcqLeaderboard(
        string $examId,
        string $studentId,
        int $topN = 50,
    ): array {
        $settings = $this->leaderboardSettings->get();
        if (!$settings['show_provisional_mcq']) {
            throw new \InvalidArgumentException('Provisional MCQ leaderboard is disabled by your institution.');
        }

        $exam = Exam::with(['batch', 'course', 'class'])->findOrFail($examId);
        $student = \Modules\Student\app\Models\Student::findOrFail($studentId);
        $channel = 'online';

        if ($exam->is_practice) {
            throw new \InvalidArgumentException('Leaderboard is not available for practice exams.');
        }

        if ($this->canViewChannelResults($exam, $channel)) {
            throw new \InvalidArgumentException(
                'Official online results are published. Use the official leaderboard instead.'
            );
        }

        $hasMcqRow = ExamResult::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->whereNotNull('exam_attempt_id')
            ->whereHas('routine', fn ($q) => $q->whereIn('delivery_mode', ['online', 'hybrid']))
            ->exists();

        if (!$hasMcqRow) {
            throw new \InvalidArgumentException('No provisional MCQ scores found for this exam.');
        }

        $topN = max(1, min($topN, $settings['student_top_limit']));
        $meritScope = $this->resolveMeritScopeForRanking($exam, $student);
        $aggregated = $this->aggregateProvisionalMcqTotals(
            $examId,
            $meritScope['type'] ?? null,
            $meritScope['id'] ?? null,
        );

        if ($aggregated === []) {
            throw new \InvalidArgumentException('No provisional MCQ standings available yet.');
        }

        $ranked = $this->applyCompetitionRanking($aggregated);
        $limited = array_slice($ranked, 0, $topN);
        $standing = null;
        $inTopList = false;

        foreach ($ranked as $row) {
            if ($row['student_id'] === $studentId) {
                $standing = array_merge($row, ['in_top_list' => false]);
                break;
            }
        }

        $topIds = collect($limited)->pluck('student_id')->all();
        $inTopList = in_array($studentId, $topIds, true);
        if ($standing) {
            $standing['in_top_list'] = $inTopList;
        }

        $leaderboard = $this->applyStudentPrivacyToRows(
            array_map(
                fn ($row) => array_merge($row, ['is_me' => $row['student_id'] === $studentId]),
                $limited,
            ),
            $settings['anonymize_names'],
        );

        if ($standing && !$inTopList) {
            $leaderboard[] = array_merge($standing, ['is_me' => true]);
        }

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'delivery_channel' => $channel,
                'published_at' => null,
            ],
            'merit_scope' => $meritScope,
            'ranking_rule' => 'competition',
            'total_students' => count($ranked),
            'top' => $topN,
            'my_standing' => $standing,
            'leaderboard' => $leaderboard,
            'subject_toppers' => [],
            'disclaimer' => 'Unofficial MCQ-only standings before final result publication. CQ/written marks are not included.',
            'privacy' => [
                'anonymize_names' => $settings['anonymize_names'],
                'student_top_limit' => $settings['student_top_limit'],
            ],
            'is_provisional' => true,
            'from_snapshot' => false,
        ];
    }

    /**
     * Get position and GPA for a single student in an exam.
     *
     * @return array<string, mixed>|null
     */
    public function getStudentExamStanding(
        string $examId,
        string $studentId,
        ?string $deliveryChannel = 'offline',
        ?array $meritScope = null,
    ): ?array {
        $channel = $this->resolveResultChannel($deliveryChannel);

        if ($meritScope === null) {
            $exam = Exam::with(['batch', 'course', 'class'])->findOrFail($examId);
            $student = \Modules\Student\app\Models\Student::findOrFail($studentId);
            $meritScope = $this->resolveMeritScopeForRanking($exam, $student);
        }

        $aggregated = $this->aggregateStudentTotals(
            $examId,
            'published',
            $meritScope['type'] ?? null,
            $meritScope['id'] ?? null,
            $channel,
        );
        $ranked = $this->applyCompetitionRanking($aggregated);

        foreach ($ranked as $row) {
            if ($row['student_id'] === $studentId) {
                return [
                    'position' => $row['rank'],
                    'total_students' => count($ranked),
                    'total_marks' => $row['total_marks'],
                    'total_possible' => $row['total_possible'],
                    'percentage' => $row['percentage'],
                    'gpa' => $row['gpa'],
                    'overall_grade' => $row['overall_grade'],
                    'passed' => $row['passed'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExamAnalytics(string $examId, ?string $batchId = null): array
    {
        $exam = Exam::with(['batch', 'course', 'class', 'routines'])->findOrFail($examId);
        $scopeType = $batchId ? 'batch' : null;
        $scopeId = $batchId;
        $aggregated = $this->aggregateStudentTotals($examId, 'published', $scopeType, $scopeId);

        $totalStudents = count($aggregated);
        $passed = count(array_filter($aggregated, fn ($r) => $r['passed']));
        $failed = $totalStudents - $passed;
        $avgPercentage = $totalStudents > 0
            ? round(array_sum(array_column($aggregated, 'percentage')) / $totalStudents, 2)
            : 0.0;

        $gradeDistribution = [];
        foreach ($aggregated as $row) {
            $grade = $row['overall_grade'] ?? 'F';
            $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
        }
        ksort($gradeDistribution);

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'result_status' => $exam->result_status,
            ],
            'batch_id' => $batchId,
            'total_students' => $totalStudents,
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => $totalStudents > 0 ? round(($passed / $totalStudents) * 100, 2) : 0.0,
            'fail_rate' => $totalStudents > 0 ? round(($failed / $totalStudents) * 100, 2) : 0.0,
            'average_percentage' => $avgPercentage,
            'grade_distribution' => $gradeDistribution,
            'top_performers' => array_slice($this->applyCompetitionRanking($aggregated), 0, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubjectAnalysis(string $examId, ?string $batchId = null): array
    {
        $exam = Exam::findOrFail($examId);
        $studentIds = $this->resolveScopeStudentIds($batchId ? 'batch' : null, $batchId);

        $query = ExamResult::where('exam_id', $examId)
            ->where('status', 'published')
            ->with(['subject', 'routine']);

        if ($studentIds !== null) {
            $query->whereIn('student_id', $studentIds);
        }

        $results = $query->get();
        $subjects = [];

        foreach ($results->groupBy('subject_id') as $subjectId => $subjectResults) {
            $subject = $subjectResults->first()->subject;
            $marks = $subjectResults->filter(fn ($r) => $r->marks_obtained !== null);
            $passed = $subjectResults->filter(fn ($r) => $this->resultSubjectPassed($r));

            $percentages = $marks->map(function ($r) {
                $total = (float) ($r->total_marks ?: 1);

                return round(((float) $r->marks_obtained / $total) * 100, 2);
            });

            $subjects[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject?->name ?? 'Unknown',
                'subject_code' => $subject?->code ?? '',
                'total_students' => $subjectResults->count(),
                'appeared' => $marks->count(),
                'absent' => $subjectResults->where('status', 'absent')->count(),
                'passed' => $passed->count(),
                'failed' => $marks->count() - $passed->count(),
                'pass_rate' => $marks->count() > 0
                    ? round(($passed->count() / $marks->count()) * 100, 2)
                    : 0.0,
                'average_marks' => $marks->count() > 0
                    ? round($marks->avg('marks_obtained'), 2)
                    : 0.0,
                'average_percentage' => $percentages->count() > 0
                    ? round($percentages->avg(), 2)
                    : 0.0,
                'highest_marks' => $marks->count() > 0 ? (float) $marks->max('marks_obtained') : 0.0,
                'lowest_marks' => $marks->count() > 0 ? (float) $marks->min('marks_obtained') : 0.0,
            ];
        }

        usort($subjects, fn ($a, $b) => ($a['average_percentage'] ?? 0) <=> ($b['average_percentage'] ?? 0));

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
            ],
            'batch_id' => $batchId,
            'subjects' => $subjects,
            'weakest_subjects' => array_slice($subjects, 0, 3),
            'strongest_subjects' => array_slice(array_reverse($subjects), 0, 3),
        ];
    }

    /**
     * Build marksheet data for PDF generation.
     *
     * @return array<string, mixed>
     */
    public function buildMarksheetData(
        string $examId,
        string $studentId,
        ?string $deliveryChannel = null,
    ): array {
        $exam = Exam::with(['examType', 'batch', 'course', 'class', 'session'])->findOrFail($examId);
        $student = \Modules\Student\app\Models\Student::with(['user', 'currentClass'])->findOrFail($studentId);

        $channel = $this->resolveMarksheetDeliveryChannel($exam, $studentId, $deliveryChannel);

        $resultsQuery = ExamResult::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('status', 'published')
            ->with(['subject', 'routine']);
        $this->applyResultDeliveryChannelScope($resultsQuery, $channel);

        $results = $resultsQuery->get();

        if ($results->isEmpty()) {
            throw new \InvalidArgumentException('No published results found for this student.');
        }

        if (!$this->canViewChannelResults($exam, $channel)) {
            throw new \InvalidArgumentException(ucfirst($channel) . ' results are not published yet.');
        }

        $meritScope = $this->resolveMeritScopeForRanking($exam, $student);
        $standing = $this->getStudentExamStanding($examId, $studentId, $channel, $meritScope);
        $subjectHighs = $this->getSubjectHighestMarks($examId, $channel);

        $subjects = $results->map(function ($r) use ($subjectHighs) {
            $total = (float) ($r->total_marks ?: 1);
            $obtained = $r->marks_obtained;
            $high = $subjectHighs[$r->subject_id] ?? null;

            return [
                'subject' => $r->subject?->name ?? 'N/A',
                'subject_code' => $r->subject?->code ?? '',
                'marks_obtained' => $obtained,
                'total_marks' => $r->total_marks,
                'pass_marks' => $r->routine?->pass_marks,
                'grade' => $r->grade,
                'grade_point' => $r->grade_point,
                'percentage' => $obtained !== null ? round(($obtained / $total) * 100, 2) : null,
                'status' => $r->status,
                'marks_breakdown' => $r->marks_breakdown,
                'highest_in_exam' => $high['highest_marks'] ?? null,
                'highest_scorer' => $high['topper_name'] ?? null,
                'is_subject_topper' => $high && $obtained !== null && (float) $obtained >= (float) $high['highest_marks'],
            ];
        })->values()->all();

        return [
            'exam' => [
                'name' => $exam->name,
                'type' => $exam->examType?->name ?? 'N/A',
                'session' => $exam->session?->name ?? 'N/A',
                'published_at' => $channel === 'online'
                    ? $exam->online_result_publish_at?->format('d M, Y')
                    : $exam->result_publish_at?->format('d M, Y'),
                'delivery_channel' => $channel,
                'class' => $exam->class?->name,
                'course' => $exam->course?->name,
                'batch' => $exam->batch?->name,
            ],
            'student' => [
                'name' => $student->user?->name ?? trim($student->first_name . ' ' . $student->last_name),
                'student_id' => $student->student_id ?? $student->id,
                'roll_no' => $student->roll_no,
                'class' => $student->currentClass?->name ?? null,
            ],
            'standing' => $standing,
            'merit_scope' => $meritScope,
            'subjects' => $subjects,
            'generated_at' => now()->format('d M, Y h:i A'),
        ];
    }

    /**
     * @return array<string, array{highest_marks: float, topper_name: string, topper_roll: ?string}>
     */
    public function getSubjectHighestMarks(string $examId, ?string $deliveryChannel = 'offline'): array
    {
        $highs = [];
        foreach ($this->getSubjectToppersList($examId, $deliveryChannel) as $row) {
            $highs[$row['subject_id']] = [
                'highest_marks' => (float) $row['highest_marks'],
                'topper_name' => $row['topper_name'],
                'topper_roll' => $row['topper_roll'],
            ];
        }

        return $highs;
    }

    /**
     * Subject-wise highest scorer per published subject (not a full subject rank list).
     *
     * @return list<array<string, mixed>>
     */
    public function getSubjectToppersList(
        string $examId,
        ?string $deliveryChannel = 'offline',
        ?string $scopeType = null,
        ?string $scopeId = null,
    ): array {
        $channel = $this->resolveResultChannel($deliveryChannel);
        $query = ExamResult::where('exam_id', $examId)
            ->where('status', 'published')
            ->whereNotNull('marks_obtained')
            ->with(['student', 'subject']);

        $this->applyResultDeliveryChannelScope($query, $channel);

        $studentIds = $this->resolveScopeStudentIds($scopeType, $scopeId, $examId);
        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }
            $query->whereIn('student_id', $studentIds);
        }

        $results = $query->get();
        $toppers = [];

        foreach ($results->groupBy('subject_id') as $subjectId => $rows) {
            $top = $rows->sortByDesc(fn ($r) => (float) $r->marks_obtained)->first();
            if (!$top) {
                continue;
            }

            $total = (float) ($top->total_marks ?? 0);
            $obtained = (float) $top->marks_obtained;
            $percentage = $total > 0 ? round(($obtained / $total) * 100, 2) : 0.0;

            $toppers[] = [
                'subject_id' => $subjectId,
                'subject_name' => $top->subject?->name ?? 'Subject',
                'subject_code' => $top->subject?->code ?? '',
                'highest_marks' => round($obtained, 2),
                'total_marks' => round($total, 2),
                'percentage' => $percentage,
                'topper_name' => trim(($top->student?->first_name ?? '') . ' ' . ($top->student?->last_name ?? '')),
                'topper_roll' => $top->student?->roll_no,
            ];
        }

        usort($toppers, fn ($a, $b) => strcmp($a['subject_name'], $b['subject_name']));

        return $toppers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExamResultSummaryForStudent(
        string $examId,
        string $studentId,
        ?string $deliveryChannel = 'offline',
    ): array {
        $channel = $this->resolveResultChannel($deliveryChannel);
        $exam = Exam::with(['batch', 'course', 'class'])->findOrFail($examId);
        $student = \Modules\Student\app\Models\Student::findOrFail($studentId);

        $meritScope = $this->resolveMeritScopeForRanking($exam, $student);

        return [
            'standing' => $this->getStudentExamStanding($examId, $studentId, $channel, $meritScope),
            'merit_scope' => $meritScope,
            'subject_highs' => $this->getSubjectHighestMarks($examId, $channel),
            'delivery_channel' => $channel,
        ];
    }

    /**
     * Resolve merit ranking scope — student enrollments take priority over exam-level fields.
     *
     * @return array{type: string, id: ?string, label: string}
     */
    public function resolveMeritScopeForRanking(
        Exam $exam,
        ?\Modules\Student\app\Models\Student $student = null,
    ): array {
        if ($student) {
            $examBatchIds = ExamRoutine::where('exam_id', $exam->id)
                ->whereNotNull('batch_id')
                ->pluck('batch_id')
                ->unique();

            if ($exam->batch_id) {
                $examBatchIds = $examBatchIds->push($exam->batch_id)->unique();
            }

            $enrollmentQuery = Enrollment::where('student_id', $student->id)
                ->where('status', 'active')
                ->with('batch');

            if ($examBatchIds->isNotEmpty()) {
                $enrollmentQuery->whereIn('batch_id', $examBatchIds->all());
            }

            $enrollment = $enrollmentQuery->first();

            if ($enrollment?->batch_id) {
                return [
                    'type' => 'batch',
                    'id' => $enrollment->batch_id,
                    'label' => $enrollment->batch?->name ?? 'Batch',
                ];
            }
        }

        if ($exam->batch_id) {
            return [
                'type' => 'batch',
                'id' => $exam->batch_id,
                'label' => $exam->batch?->name ?? 'Batch',
            ];
        }

        if ($exam->course_id) {
            return [
                'type' => 'course',
                'id' => $exam->course_id,
                'label' => $exam->course?->name ?? 'Course',
            ];
        }

        $classId = $exam->class_id ?? $student?->current_class_id;

        return [
            'type' => 'class',
            'id' => $classId,
            'label' => $exam->class?->name ?? 'Class',
        ];
    }

    /**
     * @return array{type: ?string, id: ?string, label: ?string}
     */
    private function resolveMeritScopeMeta(Exam $exam, ?string $scopeType, ?string $scopeId): array
    {
        if (!$scopeType || !$scopeId) {
            return ['type' => null, 'id' => null, 'label' => null];
        }

        if ($scopeType === 'batch') {
            $batch = $exam->batch_id === $scopeId
                ? $exam->batch
                : \Modules\Enrollment\app\Models\Batch::find($scopeId);

            return [
                'type' => 'batch',
                'id' => $scopeId,
                'label' => $batch?->name ?? 'Batch',
            ];
        }

        if ($scopeType === 'course') {
            $course = $exam->course_id === $scopeId
                ? $exam->course
                : \Modules\Enrollment\app\Models\Course::find($scopeId);

            return [
                'type' => 'course',
                'id' => $scopeId,
                'label' => $course?->name ?? 'Course',
            ];
        }

        $class = $exam->class_id === $scopeId
            ? $exam->class
            : \Modules\Academic\app\Models\Classes::find($scopeId);

        return [
            'type' => 'class',
            'id' => $scopeId,
            'label' => $class?->name ?? 'Class',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildResultSheetRows(
        string $examId,
        ?string $batchId = null,
        ?string $deliveryChannel = 'offline',
    ): array {
        $aggregated = $this->aggregateStudentTotals(
            $examId,
            'published',
            $batchId ? 'batch' : null,
            $batchId,
            $this->resolveResultChannel($deliveryChannel),
        );
        $ranked = $this->applyCompetitionRanking($aggregated);

        return array_map(function ($row) {
            return [
                'rank' => $row['rank'],
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'roll_no' => $row['roll_no'],
                'total_marks' => $row['total_marks'],
                'total_possible' => $row['total_possible'],
                'percentage' => $row['percentage'],
                'gpa' => $row['gpa'],
                'overall_grade' => $row['overall_grade'],
                'passed' => $row['passed'] ? 'Yes' : 'No',
            ];
        }, $ranked);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregateStudentTotals(
        string $examId,
        string $statusFilter = 'published',
        ?string $scopeType = null,
        ?string $scopeId = null,
        ?string $deliveryChannel = null,
    ): array {
        $query = ExamResult::where('exam_id', $examId)
            ->where('status', $statusFilter)
            ->with(['student', 'routine']);

        if ($deliveryChannel) {
            $this->applyResultDeliveryChannelScope($query, $deliveryChannel);
        }

        $studentIds = $this->resolveScopeStudentIds($scopeType, $scopeId, $examId);
        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }
            $query->whereIn('student_id', $studentIds);
        }

        $results = $query->get();
        $aggregated = [];

        foreach ($results->groupBy('student_id') as $studentId => $rows) {
            $student = $rows->first()->student;
            $totalObtained = $rows->sum(fn ($r) => (float) ($r->marks_obtained ?? 0));
            $totalPossible = $rows->sum(fn ($r) => (float) ($r->total_marks ?? 0));
            $percentage = $totalPossible > 0 ? round(($totalObtained / $totalPossible) * 100, 2) : 0.0;
            $gpaPoints = $rows->filter(fn ($r) => $r->grade_point !== null)->pluck('grade_point');
            $gpa = $gpaPoints->count() > 0 ? round($gpaPoints->avg(), 2) : 0.0;
            $overall = $this->gradingService->calculateFromPercentage($percentage);

            $passed = $rows->every(fn ($r) => $this->resultSubjectPassed($r));

            $aggregated[] = [
                'student_id' => $studentId,
                'student_name' => $student
                    ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''))
                    : 'Unknown',
                'roll_no' => $student?->roll_no,
                'total_marks' => round($totalObtained, 2),
                'total_possible' => round($totalPossible, 2),
                'percentage' => $percentage,
                'gpa' => $gpa,
                'overall_grade' => $overall['grade'],
                'passed' => $passed,
            ];
        }

        usort($aggregated, function ($a, $b) {
            if ($a['total_marks'] !== $b['total_marks']) {
                return $b['total_marks'] <=> $a['total_marks'];
            }

            return $b['percentage'] <=> $a['percentage'];
        });

        return $aggregated;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyCompetitionRanking(array $rows): array
    {
        $ranked = [];
        $rank = 0;
        $previousKey = null;

        foreach ($rows as $index => $row) {
            $scoreKey = $row['total_marks'] . ':' . $row['percentage'];
            if ($previousKey === null || $scoreKey !== $previousKey) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $ranked[] = $row;
            $previousKey = $scoreKey;
        }

        return $ranked;
    }

    /**
     * @return array{passed: int, failed: int, total: int}
     */
    private function estimatePassFail(string $examId, ?string $deliveryChannel = 'offline'): array
    {
        $query = ExamResult::where('exam_id', $examId)
            ->whereIn('status', ['pending', 'published'])
            ->with(['student', 'routine']);
        $this->applyResultDeliveryChannelScope($query, $this->resolveResultChannel($deliveryChannel));
        $results = $query->get();

        $aggregated = [];

        foreach ($results->groupBy('student_id') as $studentId => $rows) {
            $totalObtained = $rows->sum(fn ($r) => (float) ($r->marks_obtained ?? 0));
            $totalPossible = $rows->sum(fn ($r) => (float) ($r->total_marks ?? 0));
            $passed = $rows->every(fn ($r) => $this->resultSubjectPassed($r));
            $aggregated[] = ['passed' => $passed];
        }

        $passed = count(array_filter($aggregated, fn ($r) => $r['passed']));
        $total = count($aggregated);

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
        ];
    }

    public function warmMeritSnapshotsOnPublish(string $examId, string $channel): void
    {
        $channel = $this->resolveResultChannel($channel);
        $exam = Exam::find($examId);
        if (!$exam) {
            return;
        }

        $publishAt = $channel === 'online'
            ? $exam->online_result_publish_at
            : $exam->result_publish_at;

        $allMerit = $this->computeMeritList($examId, null, null, 0, $channel, false);
        $this->storeMeritSnapshot($exam, $channel, null, null, $allMerit, $publishAt);

        $batchIds = ExamRoutine::where('exam_id', $examId)
            ->whereNotNull('batch_id')
            ->pluck('batch_id')
            ->unique()
            ->values();

        foreach ($batchIds as $batchId) {
            $scopedMerit = $this->computeMeritList($examId, 'batch', (string) $batchId, 0, $channel, false);
            if (($scopedMerit['total_students'] ?? 0) > 0) {
                $this->storeMeritSnapshot($exam, $channel, 'batch', (string) $batchId, $scopedMerit, $publishAt);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMeritSnapshotPayload(
        Exam $exam,
        string $channel,
        ?string $scopeType,
        ?string $scopeId,
    ): ?array {
        $publishAt = $channel === 'online'
            ? $exam->online_result_publish_at
            : $exam->result_publish_at;

        if (!$publishAt) {
            return null;
        }

        $snapshot = ExamMeritSnapshot::query()
            ->where('exam_id', $exam->id)
            ->where('delivery_channel', $channel)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if (!$snapshot || !$snapshot->channel_published_at?->equalTo($publishAt)) {
            return null;
        }

        $scopeMeta = $snapshot->scope_meta ?? $this->resolveMeritScopeMeta($exam, $scopeType, $scopeId);

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'result_status' => $this->channelResultStatus($exam, $channel),
                'delivery_channel' => $channel,
            ],
            'scope' => $scopeMeta,
            'merit_scope' => $scopeMeta,
            'ranking_rule' => $snapshot->ranking_rule,
            'total_students' => $snapshot->total_students,
            'merit_list' => $snapshot->merit_list ?? [],
            'subject_toppers' => $snapshot->subject_toppers ?? [],
            'disclaimer' => ($scopeMeta['label'] ?? null)
                ? 'Rank among ' . $scopeMeta['label'] . ' students based on published subjects.'
                : 'Rank based on published subjects.',
        ];
    }

    /**
     * @param  array<string, mixed>  $merit
     */
    private function storeMeritSnapshot(
        Exam $exam,
        string $channel,
        ?string $scopeType,
        ?string $scopeId,
        array $merit,
        ?\Illuminate\Support\Carbon $publishAt,
    ): void {
        $ranked = $this->applyCompetitionRanking(
            $this->aggregateStudentTotals($exam->id, 'published', $scopeType, $scopeId, $channel)
        );

        ExamMeritSnapshot::updateOrCreate(
            [
                'exam_id' => $exam->id,
                'delivery_channel' => $channel,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ],
            [
                'channel_published_at' => $publishAt,
                'total_students' => count($ranked),
                'ranking_rule' => $merit['ranking_rule'] ?? 'competition',
                'scope_meta' => $merit['merit_scope'] ?? $merit['scope'] ?? null,
                'merit_list' => $ranked,
                'subject_toppers' => $merit['subject_toppers'] ?? [],
                'computed_at' => now(),
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregateProvisionalMcqTotals(
        string $examId,
        ?string $scopeType = null,
        ?string $scopeId = null,
    ): array {
        $query = ExamResult::where('exam_id', $examId)
            ->whereNotNull('exam_attempt_id')
            ->whereIn('status', ['pending', 'published'])
            ->with(['student', 'routine']);

        $this->applyResultDeliveryChannelScope($query, 'online');

        $studentIds = $this->resolveScopeStudentIds($scopeType, $scopeId, $examId);
        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }
            $query->whereIn('student_id', $studentIds);
        }

        $results = $query->get()->filter(fn (ExamResult $result) => $this->extractMcqScore($result) !== null);
        $aggregated = [];

        foreach ($results->groupBy('student_id') as $studentId => $rows) {
            $student = $rows->first()->student;
            $totalObtained = $rows->sum(fn (ExamResult $r) => (float) $this->extractMcqScore($r));
            $totalPossible = $rows->sum(fn (ExamResult $r) => (float) $this->extractMcqTotal($r));
            $percentage = $totalPossible > 0 ? round(($totalObtained / $totalPossible) * 100, 2) : 0.0;
            $overall = $this->gradingService->calculateFromPercentage($percentage);

            $aggregated[] = [
                'student_id' => $studentId,
                'student_name' => $student
                    ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''))
                    : 'Unknown',
                'roll_no' => $student?->roll_no,
                'total_marks' => round($totalObtained, 2),
                'total_possible' => round($totalPossible, 2),
                'percentage' => $percentage,
                'gpa' => 0.0,
                'overall_grade' => $overall['grade'],
                'passed' => true,
            ];
        }

        usort($aggregated, function ($a, $b) {
            if ($a['total_marks'] !== $b['total_marks']) {
                return $b['total_marks'] <=> $a['total_marks'];
            }

            return $b['percentage'] <=> $a['percentage'];
        });

        return $aggregated;
    }

    private function extractMcqScore(ExamResult $result): ?float
    {
        $breakdown = is_array($result->marks_breakdown) ? $result->marks_breakdown : [];
        if (array_key_exists('mcq', $breakdown) && $breakdown['mcq'] !== null && $breakdown['mcq'] !== '') {
            return (float) $breakdown['mcq'];
        }

        if ($result->exam_attempt_id && $result->marks_obtained !== null) {
            return (float) $result->marks_obtained;
        }

        return null;
    }

    private function extractMcqTotal(ExamResult $result): float
    {
        $config = $this->normalizeMarkConfig($result->routine?->mark_config);
        if (!empty($config['mcq']['max_marks'])) {
            return (float) $config['mcq']['max_marks'];
        }

        return (float) ($result->total_marks ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyStudentPrivacyToRows(array $rows, bool $anonymizeNames): array
    {
        if (!$anonymizeNames) {
            return $rows;
        }

        return array_map(function (array $row) {
            if (!empty($row['is_me'])) {
                return $row;
            }

            $row['student_name'] = 'Student #' . ($row['rank'] ?? '?');

            return $row;
        }, $rows);
    }

    /**
     * @return list<string>|null
     */
    private function resolveScopeStudentIds(?string $scopeType, ?string $scopeId, ?string $examId = null): ?array
    {
        if (!$scopeType || !$scopeId) {
            return null;
        }

        if ($scopeType === 'batch') {
            if ($examId) {
                $fromResults = ExamResult::where('exam_id', $examId)
                    ->where('status', 'published')
                    ->whereHas('routine', fn ($q) => $q->where('batch_id', $scopeId))
                    ->pluck('student_id')
                    ->unique()
                    ->values()
                    ->all();

                if ($fromResults !== []) {
                    return $fromResults;
                }
            }

            $fromEnrollment = Enrollment::where('batch_id', $scopeId)
                ->where('status', 'active')
                ->pluck('student_id')
                ->unique()
                ->values()
                ->all();

            return $fromEnrollment;
        }

        if ($scopeType === 'class') {
            return \Modules\Student\app\Models\Student::where('current_class_id', $scopeId)
                ->where('status', 'active')
                ->pluck('id')
                ->all();
        }

        if ($scopeType === 'course') {
            return Enrollment::where('course_id', $scopeId)
                ->where('status', 'active')
                ->pluck('student_id')
                ->all();
        }

        return null;
    }
}
