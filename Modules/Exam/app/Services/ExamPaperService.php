<?php

namespace Modules\Exam\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Exam\app\Models\ExamAttempt;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Models\ExamRoutineQuestion;
use Modules\Exam\app\Models\Question;
use Modules\Student\app\Models\Student;
use Modules\Enrollment\app\Models\Enrollment;

class ExamPaperService
{
    public const ORDER_KEY = '__order';

    public function __construct(
        private readonly ?ExamEligibilityService $eligibilityService = null,
        private readonly ?ExamAssessmentService $assessmentService = null,
    ) {}

    /**
     * Sync approved questions onto a routine.
     *
     * @param  array<int, array{question_id: string, sort_order?: int, marks_override?: float|null}>  $items
     */
    /**
     * @return array{items: array<int, array<string, mixed>>, randomize: bool}|null
     */
    public function stashQuestionItems(ExamRoutine $routine): ?array
    {
        if ($this->countValidQuestions($routine) === 0) {
            return null;
        }

        $items = ExamRoutineQuestion::where('exam_routine_id', $routine->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ExamRoutineQuestion $pivot, int $index) => [
                'question_id' => $pivot->question_id,
                'sort_order' => $pivot->sort_order ?? ($index + 1),
                'marks_override' => $pivot->marks_override,
            ])
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'items' => $items,
            'randomize' => (bool) ($routine->randomize_questions ?? false),
        ];
    }

    /**
     * Copy question paper from another routine (same exam, subject, channel) when the new slot is empty.
     */
    public function copyQuestionsFromSiblingIfEmpty(ExamRoutine $routine): bool
    {
        if ($this->countValidQuestions($routine) > 0) {
            return false;
        }

        $channel = ExamRoutine::resolveDeliveryChannel($routine->delivery_mode ?? 'offline');

        $siblingsQuery = ExamRoutine::query()
            ->where('exam_id', $routine->exam_id)
            ->where('subject_id', $routine->subject_id)
            ->where('id', '!=', $routine->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('routineQuestions');

        if ($channel === 'online') {
            $siblingsQuery->whereIn('delivery_mode', ['online', 'hybrid']);
        } else {
            $siblingsQuery->where(function ($q) {
                $q->where('delivery_mode', 'offline')
                    ->orWhereNull('delivery_mode')
                    ->orWhere('delivery_mode', '');
            });
        }

        $sibling = $siblingsQuery->orderByDesc('created_at')->first();
        if (!$sibling || $this->countValidQuestions($sibling) === 0) {
            return false;
        }

        $items = ExamRoutineQuestion::where('exam_routine_id', $sibling->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ExamRoutineQuestion $pivot, int $index) => [
                'question_id' => $pivot->question_id,
                'sort_order' => $pivot->sort_order ?? ($index + 1),
                'marks_override' => $pivot->marks_override,
            ])
            ->values()
            ->all();

        if ($items === []) {
            return false;
        }

        $this->syncQuestions($routine, $items, (bool) $sibling->randomize_questions);

        return true;
    }

    public function syncQuestions(ExamRoutine $routine, array $items, bool $randomize = false): array
    {
        $questionIds = collect($items)->pluck('question_id')->filter()->unique()->values();

        if ($questionIds->isEmpty()) {
            ExamRoutineQuestion::where('exam_routine_id', $routine->id)->delete();
            $routine->update(['randomize_questions' => $randomize]);

            return ['count' => 0, 'questions' => []];
        }

        $approved = Question::whereIn('id', $questionIds)
            ->where('status', 'approved')
            ->where('subject_id', $routine->subject_id)
            ->pluck('id');

        $missing = $questionIds->diff($approved);
        if ($missing->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'Only approved questions matching the routine subject can be attached. Invalid: '
                . $missing->implode(', ')
            );
        }

        DB::transaction(function () use ($routine, $items, $randomize) {
            ExamRoutineQuestion::where('exam_routine_id', $routine->id)->delete();

            foreach ($items as $index => $item) {
                ExamRoutineQuestion::create([
                    'exam_routine_id' => $routine->id,
                    'question_id' => $item['question_id'],
                    'sort_order' => $item['sort_order'] ?? ($index + 1),
                    'marks_override' => $item['marks_override'] ?? null,
                ]);
            }

            $routine->update(['randomize_questions' => $randomize]);
        });

        return [
            'count' => count($items),
            'questions' => $this->getAttachedQuestions($routine->fresh(), true, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttachedQuestions(
        ExamRoutine $routine,
        bool $includeAnswers = false,
        ?bool $shuffle = null,
        ?array $fixedOrder = null
    ): array {
        $shuffle = $shuffle ?? (bool) ($routine->randomize_questions ?? false);
        $pivots = ExamRoutineQuestion::where('exam_routine_id', $routine->id)
            ->orderBy('sort_order')
            ->with('question')
            ->get();

        $items = $pivots->map(function (ExamRoutineQuestion $pivot) use ($includeAnswers) {
            $q = $pivot->question;
            if (!$q) {
                return null;
            }

            $marks = $pivot->marks_override ?? $q->marks;

            $row = $this->normalizeQuestionRow([
                'id' => $q->id,
                'pivot_id' => $pivot->id,
                'sort_order' => $pivot->sort_order,
                'question_type' => $q->question_type,
                'content' => $q->content,
                'stimulus' => $q->stimulus,
                'options' => $q->options,
                'parts' => $q->parts,
                'marks' => (float) $marks,
                'marks_override' => $pivot->marks_override,
                'difficulty' => $q->difficulty,
            ], $includeAnswers ? $q->correct_answer : null);

            return $row;
        })->filter()->values();

        if ($fixedOrder) {
            $map = $items->keyBy('id');
            $items = collect($fixedOrder)->map(fn ($id) => $map->get($id))->filter()->values();
        } elseif ($shuffle || (bool) ($routine->randomize_questions ?? false)) {
            $items = $items->shuffle()->values();
        }

        return $items->all();
    }

    public function generateQuestionPaperPdf(string $routineId, string $variant = 'student'): string
    {
        $routine = ExamRoutine::with(['exam.examType', 'subject', 'batch', 'course', 'class'])
            ->findOrFail($routineId);

        $includeAnswers = in_array($variant, ['answer_sheet', 'invigilator'], true);
        $questions = $this->getAttachedQuestions(
            $routine,
            $includeAnswers,
            (bool) ($routine->randomize_questions ?? false),
        );

        $totalMarks = collect($questions)->sum('marks');

        $data = [
            'variant' => $variant,
            'routine' => $routine,
            'exam' => $routine->exam,
            'subject' => $routine->subject?->name ?? 'N/A',
            'subject_code' => $routine->subject?->code ?? '',
            'questions' => $questions,
            'total_marks' => $totalMarks,
            'duration' => $routine->duration_minutes
                ?? $this->estimateDurationMinutes($routine),
            'generated_at' => now()->format('d M, Y h:i A'),
            'show_answers' => $includeAnswers,
        ];

        $pdf = Pdf::loadView('exam::question-paper-pdf', $data);

        return $pdf->output();
    }

    public function startAttempt(string $routineId, string $studentId): array
    {
        $routine = ExamRoutine::with('exam')->findOrFail($routineId);

        if ($routine->exam?->is_practice) {
            return $this->startPracticeAttempt($routineId, $studentId);
        }

        return $this->startOfficialAttempt($routineId, $studentId);
    }

    public function startPracticeAttempt(string $routineId, string $studentId): array
    {
        $routine = ExamRoutine::with('exam')->findOrFail($routineId);

        if (!$routine->exam?->is_practice) {
            throw new \RuntimeException('Only practice exams can be attempted here.');
        }

        if ($routine->status !== 'published') {
            throw new \RuntimeException('This practice set is not published yet.');
        }

        $this->assertStudentInBatch($studentId, $routine->batch_id);

        $questions = $this->loadPlayerQuestions($routine, false);
        if (empty($questions)) {
            throw new \RuntimeException('No questions attached to this practice set.');
        }

        $existing = ExamAttempt::where('exam_routine_id', $routineId)
            ->where('student_id', $studentId)
            ->where('is_practice', true)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            $order = $existing->answers[self::ORDER_KEY] ?? collect($questions)->pluck('id')->all();
            $questions = $this->getAttachedQuestions($routine, false, false, $order ?: null);

            $duration = $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);

            return [
                'attempt' => $this->transformAttempt($existing->fresh(['routine.exam', 'routine.subject'])),
                'questions' => $questions,
                'duration_minutes' => $duration,
                'remaining_seconds' => $this->computeRemainingSeconds($existing, $routine, $duration),
                'resumed' => true,
                'saved_answers' => collect($existing->answers ?? [])->except(self::ORDER_KEY)->all(),
            ];
        }

        $order = collect($questions)->pluck('id')->all();

        $attempt = ExamAttempt::create([
            'exam_routine_id' => $routineId,
            'student_id' => $studentId,
            'is_practice' => true,
            'started_at' => now(),
            'last_saved_at' => now(),
            'answers' => [self::ORDER_KEY => $order],
            'status' => 'in_progress',
        ]);

        $duration = $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);

        return [
            'attempt' => $this->transformAttempt($attempt->fresh(['routine.exam', 'routine.subject'])),
            'questions' => $questions,
            'duration_minutes' => $duration,
            'remaining_seconds' => $duration * 60,
            'resumed' => false,
            'saved_answers' => [],
        ];
    }

    public function startOfficialAttempt(string $routineId, string $studentId): array
    {
        $routine = ExamRoutine::with('exam')->findOrFail($routineId);
        $exam = $routine->exam;

        if (!$exam || $exam->is_practice) {
            throw new \RuntimeException('This is not an official exam routine.');
        }

        if (!$routine->isOnlineDelivery()) {
            throw new \RuntimeException('This exam is not available for online attempt.');
        }

        if ($routine->status !== 'published' || $exam->status !== 'published') {
            throw new \RuntimeException('This exam is not published yet.');
        }

        $this->assertStudentInBatch($studentId, $routine->batch_id);
        $this->assertExamWindowOpen($routine, true);

        $this->eligibilityService()->assertCanStartOnlineExam($exam->id, $studentId);

        $submitted = ExamAttempt::where('exam_routine_id', $routineId)
            ->where('student_id', $studentId)
            ->where('is_practice', false)
            ->where('status', 'submitted')
            ->exists();

        if ($submitted) {
            throw new \RuntimeException('You have already submitted this online exam.');
        }

        $questions = $this->loadPlayerQuestions($routine, false);
        if (empty($questions)) {
            throw new \RuntimeException('No questions attached to this exam.');
        }

        $existing = ExamAttempt::where('exam_routine_id', $routineId)
            ->where('student_id', $studentId)
            ->where('is_practice', false)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            $order = $existing->answers[self::ORDER_KEY] ?? collect($questions)->pluck('id')->all();
            $questions = $this->getAttachedQuestions($routine, false, false, $order ?: null);

            $duration = $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);

            return [
                'attempt' => $this->transformAttempt($existing->fresh(['routine.exam', 'routine.subject'])),
                'questions' => $questions,
                'duration_minutes' => $duration,
                'window_ends_at' => $this->routineWindowEnd($routine)?->toIso8601String(),
                'remaining_seconds' => $this->computeRemainingSeconds($existing, $routine, $duration),
                'resumed' => true,
                'saved_answers' => collect($existing->answers ?? [])->except(self::ORDER_KEY)->all(),
                'is_official' => true,
            ];
        }

        $order = collect($questions)->pluck('id')->all();

        $attempt = ExamAttempt::create([
            'exam_routine_id' => $routineId,
            'student_id' => $studentId,
            'is_practice' => false,
            'started_at' => now(),
            'last_saved_at' => now(),
            'answers' => [self::ORDER_KEY => $order],
            'status' => 'in_progress',
        ]);

        $duration = $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);
        $windowEnd = $this->routineWindowEnd($routine);

        return [
            'attempt' => $this->transformAttempt($attempt->fresh(['routine.exam', 'routine.subject'])),
            'questions' => $questions,
            'duration_minutes' => $duration,
            'window_ends_at' => $windowEnd?->toIso8601String(),
            'remaining_seconds' => $this->computeRemainingSeconds($attempt, $routine, $duration),
            'resumed' => false,
            'saved_answers' => [],
            'is_official' => true,
        ];
    }

    /**
     * Save or submit a practice attempt. Never writes to exam_results.
     */
    public function saveAttempt(string $attemptId, string $studentId, array $responses, bool $submit = false): array
    {
        $attempt = ExamAttempt::with(['routine.exam', 'routine.subject'])
            ->where('id', $attemptId)
            ->where('student_id', $studentId)
            ->firstOrFail();

        if ($attempt->status !== 'in_progress') {
            throw new \RuntimeException('This attempt has already been submitted.');
        }

        if (!$attempt->is_practice) {
            $this->assertExamWindowOpen($attempt->routine, false);
        }

        $existing = $attempt->answers ?? [];
        $order = $existing[self::ORDER_KEY] ?? [];

        $merged = array_merge($existing, $responses);
        if (!empty($order)) {
            $merged[self::ORDER_KEY] = $order;
        }

        $attempt->answers = $merged;
        $attempt->last_saved_at = now();

        if ($submit) {
            $evaluation = $this->evaluatePracticeAttempt($attempt);
            $attempt->score = $evaluation['score'];
            $attempt->total_marks = $evaluation['total_marks'];
            $attempt->submitted_at = now();
            $attempt->status = 'submitted';

            if (!$attempt->is_practice) {
                $this->assessmentService()->applyOnlineAttemptScores($attempt, $evaluation);
                $evaluation = $this->sanitizeEvaluationForStudent($evaluation);
            }
        }

        $attempt->save();

        $result = [
            'attempt' => $this->transformAttempt($attempt->fresh(['routine.exam', 'routine.subject'])),
        ];

        if ($submit) {
            $result['evaluation'] = $evaluation ?? (
                $attempt->is_practice
                    ? $this->evaluatePracticeAttempt($attempt)
                    : $this->sanitizeEvaluationForStudent($this->evaluatePracticeAttempt($attempt))
            );
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStudentPracticeRoutines(string $studentId): array
    {
        if (!$this->studentHasActiveEnrollment($studentId)) {
            return [];
        }

        $batchIds = $this->eligibilityService()->activeEnrollmentBatchIds($studentId)->all();
        if ($batchIds === []) {
            return [];
        }

        $routines = ExamRoutine::published()
            ->whereIn('batch_id', $batchIds)
            ->whereHas('exam', fn ($q) => $q->where('is_practice', true)->where('status', 'published'))
            ->with(['exam.examType', 'exam.class', 'subject', 'batch.course.class'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        return $routines->map(function (ExamRoutine $routine) use ($studentId) {
            $best = ExamAttempt::where('exam_routine_id', $routine->id)
                ->where('student_id', $studentId)
                ->where('status', 'submitted')
                ->orderByDesc('score')
                ->first();

            $attempts = ExamAttempt::where('exam_routine_id', $routine->id)
                ->where('student_id', $studentId)
                ->count();

            $inProgress = ExamAttempt::where('exam_routine_id', $routine->id)
                ->where('student_id', $studentId)
                ->where('status', 'in_progress')
                ->first();

            $questionCount = $this->countValidQuestions($routine);
            $displayMeta = $this->eligibilityService()->resolveRoutineDisplayMeta($routine, $studentId);

            return [
                'id' => $routine->id,
                'exam_id' => $routine->exam_id,
                'exam_name' => $routine->exam?->name,
                'exam_type' => $displayMeta['exam_type_name'],
                'subject_id' => $routine->subject_id,
                'subject_name' => $routine->subject?->name,
                'batch_name' => $displayMeta['batch_name'],
                'course_name' => $displayMeta['course_name'],
                'class_name' => $displayMeta['class_name'],
                'exam_date' => $routine->exam_date?->format('Y-m-d'),
                'duration_minutes' => $routine->duration_minutes,
                'question_count' => $questionCount,
                'total_marks' => $routine->total_marks,
                'attempt_count' => $attempts,
                'best_score' => $best?->score,
                'best_total' => $best?->total_marks,
                'in_progress_attempt_id' => $inProgress?->id,
                'can_start' => $questionCount > 0,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLiveExamRoutines(string $studentId): array
    {
        if (!$this->studentHasActiveEnrollment($studentId)) {
            return [];
        }

        $batchIds = $this->eligibilityService()->activeEnrollmentBatchIds($studentId)->all();
        if ($batchIds === []) {
            return [];
        }

        $routines = ExamRoutine::published()
            ->whereIn('batch_id', $batchIds)
            ->whereHas('exam', fn ($q) => $q
                ->where('is_practice', false)
                ->where('status', 'published'))
            ->with(['exam.examType', 'exam.class', 'exam.course', 'subject', 'batch.course.class', 'class'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (ExamRoutine $routine) => $routine->isOnlineDelivery())
            ->values();

        return $routines->map(function (ExamRoutine $routine) use ($studentId) {
            $window = $this->routineWindowState($routine);
            $questionCount = $this->countValidQuestions($routine);

            $submitted = ExamAttempt::where('exam_routine_id', $routine->id)
                ->where('student_id', $studentId)
                ->where('is_practice', false)
                ->where('status', 'submitted')
                ->exists();

            $inProgress = ExamAttempt::where('exam_routine_id', $routine->id)
                ->where('student_id', $studentId)
                ->where('is_practice', false)
                ->where('status', 'in_progress')
                ->first();

            $eligibility = $this->eligibilityService()
                ->getStudentStatus($routine->exam_id, $studentId, false, 'online');

            $eligible = ($eligibility['eligible'] ?? true) !== false
                && empty($eligibility['exam_fee_unpaid'])
                && empty($eligibility['exam_fee_pending_approval']);
            $canResume = $inProgress && !$submitted && $questionCount > 0;
            $canStartNew = $questionCount > 0
                && $window['status'] === 'live'
                && $eligible
                && !$submitted
                && !$inProgress;

            $blockReason = null;
            if ($submitted) {
                $blockReason = 'Already submitted';
            } elseif ($questionCount === 0) {
                $blockReason = 'No questions attached — contact admin';
            } elseif (!empty($eligibility['exam_fee_pending_approval'])) {
                $blockReason = $eligibility['message'] ?? 'Exam fee payment awaiting admin approval';
            } elseif (!empty($eligibility['exam_fee_unpaid'])) {
                $blockReason = $eligibility['message'] ?? 'Exam fee payment required';
            } elseif (!$eligible) {
                $blockReason = $eligibility['message'] ?? 'Not eligible for this exam';
            } elseif ($window['status'] === 'upcoming') {
                $blockReason = 'Exam has not started yet';
            } elseif ($window['status'] === 'ended' && !$canResume) {
                $blockReason = 'Exam window has ended';
            } elseif ($window['status'] !== 'live' && !$canResume) {
                $blockReason = 'Exam is not available now';
            }

            $displayMeta = $this->eligibilityService()->resolveRoutineDisplayMeta($routine, $studentId);

            return [
                'id' => $routine->id,
                'exam_id' => $routine->exam_id,
                'exam_name' => $routine->exam?->name,
                'exam_type' => $displayMeta['exam_type_name'],
                'delivery_mode' => $routine->effectiveDeliveryMode(),
                'subject_id' => $routine->subject_id,
                'subject_name' => $routine->subject?->name,
                'batch_name' => $displayMeta['batch_name'],
                'course_name' => $displayMeta['course_name'],
                'class_name' => $displayMeta['class_name'],
                'exam_date' => $routine->exam_date?->format('Y-m-d'),
                'start_time' => $routine->start_time_formatted ?? $routine->start_time,
                'end_time' => $routine->end_time_formatted ?? $routine->end_time,
                'duration_minutes' => $routine->duration_minutes ?? $this->estimateDurationMinutes($routine),
                'question_count' => $questionCount,
                'total_marks' => $routine->total_marks,
                'window_status' => $window['status'],
                'window_starts_at' => $window['starts_at'],
                'window_ends_at' => $window['ends_at'],
                'can_start' => $canStartNew || $canResume,
                'can_resume' => $canResume,
                'block_reason' => ($canStartNew || $canResume) ? null : $blockReason,
                'is_submitted' => $submitted,
                'in_progress_attempt_id' => $inProgress?->id,
                'exam_eligibility' => $eligibility,
            ];
        })->values()->all();
    }

    /**
     * Auto-submit in-progress official attempts whose exam window has ended.
     *
     * @return array{submitted: int, failed: int}
     */
    /**
     * @param  'offline'|'online'|null  $deliveryChannel
     * @param  array<int, string>|null  $routineIds  When set, only these draft routines are validated (e.g. channel publish set).
     */
    public function assertExamReadyForDelivery(
        \Modules\Exam\app\Models\Exam $exam,
        ?string $deliveryChannel = null,
        ?array $routineIds = null,
    ): void {
        if ($routineIds !== null && $routineIds !== []) {
            $routines = ExamRoutine::query()
                ->whereIn('id', $routineIds)
                ->where('status', 'draft')
                ->with('subject')
                ->get();
        } elseif ($exam->is_practice) {
            $query = ExamRoutine::where('exam_id', $exam->id)
                ->where('status', 'draft')
                ->with('subject');
            $this->applyDeliveryChannelScope($query, $deliveryChannel);
            $routines = $query->get();
        } else {
            $query = ExamRoutine::where('exam_id', $exam->id)
                ->where('status', 'draft')
                ->with('subject');
            $this->applyDeliveryChannelScope($query, $deliveryChannel ?? 'online');
            $routines = $query->get();

            if ($routines->isEmpty()) {
                return;
            }
        }

        $needsQuestions = $exam->is_practice;
        if (!$needsQuestions && $deliveryChannel !== 'offline') {
            $needsQuestions = $routines->contains(function ($routine) {
                return in_array($routine->delivery_mode ?? 'offline', ['online', 'hybrid'], true);
            });
        }

        if (!$needsQuestions) {
            return;
        }

        if ($routines->isEmpty()) {
            throw new \RuntimeException('Generate subject routines before publishing.');
        }

        foreach ($routines as $routine) {
            if ($this->countValidQuestions($routine) === 0) {
                $this->copyQuestionsFromSiblingIfEmpty($routine->fresh());
            }
        }

        $missing = [];
        foreach ($routines as $routine) {
            if ($this->countValidQuestions($routine->fresh()) === 0) {
                $missing[] = $routine->subject?->name ?? $routine->id;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Attach approved questions to all routines before publishing this '
                . ($exam->is_practice ? 'practice' : 'online')
                . ' exam. Missing: ' . implode(', ', $missing)
                . ' (use Setup Wizard Step 5 or attach from Question Bank).'
            );
        }
    }

    public function autoSubmitExpiredAttempts(): array
    {
        $attempts = ExamAttempt::with(['routine.exam', 'routine.subject'])
            ->where('is_practice', false)
            ->where('status', 'in_progress')
            ->get();

        $submitted = 0;
        $failed = 0;

        foreach ($attempts as $attempt) {
            $routine = $attempt->routine;
            if (!$routine) {
                continue;
            }

            $state = $this->routineWindowState($routine);
            if ($state['status'] !== 'ended') {
                continue;
            }

            try {
                $answers = collect($attempt->answers ?? [])->except(self::ORDER_KEY)->all();
                $this->saveAttempt($attempt->id, $attempt->student_id, $answers, true);
                $submitted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['submitted' => $submitted, 'failed' => $failed];
    }

    public function getMyAttempts(string $studentId, ?bool $isPractice = true): Collection
    {
        return ExamAttempt::with(['routine.exam', 'routine.subject'])
            ->where('student_id', $studentId)
            ->when($isPractice !== null, fn ($q) => $q->where('is_practice', $isPractice))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => $this->transformAttempt($a));
    }

    /**
     * @return array{score: float, total_marks: float, breakdown: array<int, array<string, mixed>>, weak_subjects: array<int, string>}
     */
    public function evaluatePracticeAttempt(ExamAttempt $attempt): array
    {
        $routine = $attempt->routine ?? ExamRoutine::with('subject')->find($attempt->exam_routine_id);
        $answers = $attempt->answers ?? [];
        $order = $answers[self::ORDER_KEY] ?? [];

        $questions = $this->getAttachedQuestions($routine, true, false, $order ?: null);

        $score = 0.0;
        $totalMarks = 0.0;
        $breakdown = [];
        $wrongSubjects = [];

        foreach ($questions as $q) {
            $marks = (float) ($q['marks'] ?? 0);
            $totalMarks += $marks;
            $qid = $q['id'];
            $studentAnswer = $answers[$qid] ?? null;

            $item = [
                'question_id' => $qid,
                'question_type' => $q['question_type'],
                'marks' => $marks,
                'student_answer' => $studentAnswer,
                'correct_answer' => $q['correct_answer'] ?? null,
                'is_correct' => null,
                'marks_obtained' => 0,
            ];

            if ($q['question_type'] === 'mcq') {
                $correctIndex = $q['correct_answer']['index'] ?? null;
                $givenIndex = is_array($studentAnswer)
                    ? ($studentAnswer['index'] ?? null)
                    : (is_numeric($studentAnswer) ? (int) $studentAnswer : null);

                $isCorrect = $correctIndex !== null && $givenIndex !== null && (int) $correctIndex === (int) $givenIndex;
                $item['is_correct'] = $isCorrect;
                $item['marks_obtained'] = $isCorrect ? $marks : 0;
                $score += $item['marks_obtained'];

                if (!$isCorrect && $routine?->subject?->name) {
                    $wrongSubjects[] = $routine->subject->name;
                }
            }

            $breakdown[] = $item;
        }

        $weakSubjects = collect($wrongSubjects)->countBy()->sortDesc()->keys()->take(3)->values()->all();

        return [
            'score' => round($score, 2),
            'total_marks' => round($totalMarks, 2),
            'percentage' => $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0,
            'breakdown' => $breakdown,
            'weak_subjects' => $weakSubjects,
        ];
    }

    public function transformAttempt(ExamAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'exam_routine_id' => $attempt->exam_routine_id,
            'student_id' => $attempt->student_id,
            'is_practice' => $attempt->is_practice,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'last_saved_at' => $attempt->last_saved_at?->toIso8601String(),
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'answers' => $attempt->status === 'in_progress'
                ? collect($attempt->answers ?? [])->except(self::ORDER_KEY)->all()
                : ($attempt->answers ?? []),
            'routine' => $attempt->routine ? [
                'id' => $attempt->routine->id,
                'subject_name' => $attempt->routine->subject?->name,
                'exam_name' => $attempt->routine->exam?->name,
            ] : null,
        ];
    }

    private function assertStudentInBatch(string $studentId, ?string $batchId): void
    {
        if (!$batchId) {
            return;
        }

        $enrolled = Enrollment::where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        if (!$enrolled) {
            throw new \RuntimeException('You are not enrolled in this batch.');
        }
    }

    private function studentHasActiveEnrollment(string $studentId): bool
    {
        return Enrollment::where('student_id', $studentId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPlayerQuestions(ExamRoutine $routine, bool $includeAnswers): array
    {
        $questions = $this->getAttachedQuestions(
            $routine,
            $includeAnswers,
            (bool) ($routine->randomize_questions ?? false),
        );

        return array_values(array_filter($questions, fn ($q) => !empty($q['content']) || !empty($q['stimulus'])));
    }

    private function countValidQuestions(ExamRoutine $routine): int
    {
        return count($this->loadPlayerQuestions($routine, false));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeQuestionRow(array $row, $correctAnswer = null): array
    {
        if (!empty($row['options']) && is_array($row['options'])) {
            $row['options'] = array_values(array_map(function ($opt) {
                if (is_array($opt)) {
                    return $opt['text'] ?? $opt['content'] ?? $opt['label'] ?? json_encode($opt);
                }
                return (string) $opt;
            }, $row['options']));
        }

        if (!empty($row['parts']) && is_array($row['parts'])) {
            $row['parts'] = array_values(array_map(function ($part) {
                if (!is_array($part)) {
                    return ['content' => (string) $part, 'marks' => 0];
                }
                return [
                    'key' => $part['key'] ?? null,
                    'label' => $part['label'] ?? null,
                    'content' => $part['content'] ?? $part['text'] ?? '',
                    'marks' => (float) ($part['marks'] ?? 0),
                ];
            }, $row['parts']));
        }

        if ($correctAnswer !== null) {
            $row['correct_answer'] = $correctAnswer;
        }

        return $row;
    }

    private function assertExamWindowOpen(ExamRoutine $routine, bool $forStart): void
    {
        $state = $this->routineWindowState($routine);

        if ($state['status'] === 'upcoming' && $forStart) {
            throw new \RuntimeException('This exam has not started yet.');
        }

        if ($state['status'] === 'ended') {
            throw new \RuntimeException('The exam window has ended.');
        }
    }

    /**
     * @return array{status: string, starts_at: ?string, ends_at: ?string}
     */
    private function routineWindowState(ExamRoutine $routine): array
    {
        $start = $this->routineWindowStart($routine);
        $end = $this->routineWindowEnd($routine);
        $now = now();

        if ($start && $now->lt($start)) {
            return [
                'status' => 'upcoming',
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $end?->toIso8601String(),
            ];
        }

        if ($end && $now->gt($end)) {
            return [
                'status' => 'ended',
                'starts_at' => $start?->toIso8601String(),
                'ends_at' => $end->toIso8601String(),
            ];
        }

        return [
            'status' => 'live',
            'starts_at' => $start?->toIso8601String(),
            'ends_at' => $end?->toIso8601String(),
        ];
    }

    private function routineWindowStart(ExamRoutine $routine): ?Carbon
    {
        return $routine->windowStartAt();
    }

    private function routineWindowEnd(ExamRoutine $routine): ?Carbon
    {
        return $routine->windowEndAt();
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @return array<string, mixed>
     */
    private function sanitizeEvaluationForStudent(array $evaluation): array
    {
        $evaluation['breakdown'] = collect($evaluation['breakdown'] ?? [])->map(function ($item) {
            unset($item['correct_answer']);
            if (($item['question_type'] ?? '') !== 'mcq') {
                $item['is_correct'] = null;
                $item['marks_obtained'] = 0;
            }
            return $item;
        })->values()->all();

        return $evaluation;
    }

    private function eligibilityService(): ExamEligibilityService
    {
        return $this->eligibilityService ?? app(ExamEligibilityService::class);
    }

    private function assessmentService(): ExamAssessmentService
    {
        return $this->assessmentService ?? app(ExamAssessmentService::class);
    }

    /**
     * @return array{duration_minutes: int, remaining_seconds: int, window_ends_at: ?string}
     */
    public function getInProgressPlayerMeta(ExamAttempt $attempt): array
    {
        $routine = $attempt->routine ?? ExamRoutine::find($attempt->exam_routine_id);
        $duration = $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);
        $windowEnd = !$attempt->is_practice ? $this->routineWindowEnd($routine) : null;

        return [
            'duration_minutes' => $duration,
            'remaining_seconds' => $this->computeRemainingSeconds($attempt, $routine, $duration),
            'window_ends_at' => $windowEnd?->toIso8601String(),
        ];
    }

    public function computeRemainingSeconds(ExamAttempt $attempt, ExamRoutine $routine, ?int $durationMinutes = null): int
    {
        $durationMinutes = $durationMinutes ?? $routine->duration_minutes ?? $this->estimateDurationMinutes($routine);
        $durationSec = max(60, $durationMinutes * 60);

        $elapsed = $attempt->started_at
            ? max(0, (int) $attempt->started_at->diffInSeconds(now()))
            : 0;

        $remaining = max(0, $durationSec - $elapsed);

        if (!$attempt->is_practice) {
            $windowEnd = $this->routineWindowEnd($routine);
            if ($windowEnd) {
                $windowSec = max(0, now()->diffInSeconds($windowEnd, false));
                $remaining = min($remaining, $windowSec);
            }
        }

        return (int) $remaining;
    }

    private function estimateDurationMinutes(ExamRoutine $routine): int
    {
        return $routine->effectiveDurationMinutes();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Modules\Exam\app\Models\ExamRoutine>  $query
     * @param  'offline'|'online'|null  $channel
     */
    private function applyDeliveryChannelScope($query, ?string $channel): void
    {
        if ($channel === 'offline') {
            $query->where(function ($q) {
                $q->where('delivery_mode', 'offline')
                    ->orWhereNull('delivery_mode')
                    ->orWhere('delivery_mode', '');
            });
        } elseif ($channel === 'online') {
            $query->whereIn('delivery_mode', ['online', 'hybrid']);
        }
    }
}
