<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\app\Models\Batch;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Models\ExamType;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Exam\app\Services\ExamEligibilityService;
use Modules\Exam\app\Services\ExamPaperService;
use Modules\Communication\app\Services\NotificationDispatchService;

class ExamController extends BaseApiController
{
    public function __construct(
        private readonly ExamAssessmentService $assessmentService,
        private readonly ExamEligibilityService $eligibilityService,
        private readonly ExamPaperService $paperService,
        private readonly NotificationDispatchService $notificationDispatch,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $query = Exam::with(['examType', 'session', 'batch', 'course', 'class', 'section', 'routines'])
            ->search($request->search)
            ->filter($request->only([
                'status', 'class_id', 'section_id', 'exam_type_id',
                'batch_id', 'course_id', 'academic_session_id', 'delivery_mode',
            ]));

        if ($request->has('is_practice')) {
            $query->where('is_practice', filter_var($request->input('is_practice'), FILTER_VALIDATE_BOOLEAN));
        }

        $exams = $query->orderBy('start_date', 'desc')->paginate($perPage);
        return $this->paginatedResponse($exams);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'nullable|string|exists:academic_sessions,id',
            'exam_type_id' => 'nullable|string|exists:exam_types,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,completed,cancelled',
            'is_practice' => 'sometimes|boolean',
            'delivery_mode' => 'sometimes|in:offline,online,hybrid',
            'eligibility_check_enabled' => 'sometimes|boolean',
            'min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'exam_fee_applicable' => 'sometimes|boolean',
            'online_eligibility_check_enabled' => 'sometimes|boolean',
            'online_min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'online_exam_fee_applicable' => 'sometimes|boolean',
            'offline_policy_scope' => 'sometimes|in:all,batch',
            'online_policy_scope' => 'sometimes|in:all,batch',
        ]);

        $validated = $this->applyExamCreateDefaults($validated);

        if (empty($validated['academic_session_id']) || empty($validated['exam_type_id'])) {
            return $this->validationError(
                ['setup' => 'Configure an academic session and exam type in settings first.'],
                'Cannot create exam: missing session or paper format defaults.'
            );
        }

        $status = $validated['status'] ?? 'draft';
        $validated['status'] = $status;
        $validated['result_status'] = $validated['result_status'] ?? 'draft';

        if ($status !== 'draft' && !$this->hasExamLevel($validated)) {
            return $this->validationError(
                ['level' => 'At least one of batch_id, course_id, or class_id is required'],
                'Please select a batch, course, or class for this exam'
            );
        }

        if ($this->findDuplicateExam($validated)) {
            return $this->validationError(
                ['name' => $validated['name']],
                'An exam with this name already exists for this batch/session. Pick another name or open the existing exam.'
            );
        }

        $exam = Exam::create($validated);
        return $this->created($exam->load(['examType', 'batch', 'course', 'class', 'section']));
    }

    public function show(string $id): JsonResponse
    {
        $exam = Exam::with([
            'examType', 'batch', 'course', 'class', 'section',
            'routines.subject', 'routines.teacher.user', 'routines.room',
            'results',
        ])->find($id);
        if (!$exam) return $this->notFound();
        return $this->success($exam);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $exam = Exam::find($id);
        if (!$exam) return $this->notFound();

        $validated = $request->validate([
            'academic_session_id' => 'sometimes|nullable|string|exists:academic_sessions,id',
            'exam_type_id' => 'sometimes|string|exists:exam_types,id',
            'class_id' => 'nullable|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,completed,cancelled',
            'is_practice' => 'sometimes|boolean',
            'delivery_mode' => 'sometimes|in:offline,online,hybrid',
            'eligibility_check_enabled' => 'sometimes|boolean',
            'min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'exam_fee_applicable' => 'sometimes|boolean',
            'online_eligibility_check_enabled' => 'sometimes|boolean',
            'online_min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'online_exam_fee_applicable' => 'sometimes|boolean',
            'offline_policy_scope' => 'sometimes|in:all,batch',
            'online_policy_scope' => 'sometimes|in:all,batch',
        ]);

        $merged = array_merge($exam->only([
            'name', 'batch_id', 'course_id', 'class_id', 'academic_session_id', 'is_practice',
        ]), $validated);
        $nextStatus = $validated['status'] ?? $exam->status;

        if ($this->findDuplicateExam($merged, $exam->id)) {
            return $this->validationError(
                ['name' => $merged['name'] ?? $exam->name],
                'An exam with this name already exists for this batch/session. Pick another name or open the existing exam.'
            );
        }

        if ($nextStatus !== 'draft' && !$this->hasExamLevel($merged)) {
            return $this->validationError(
                ['level' => 'At least one of batch_id, course_id, or class_id is required'],
                'Please select a batch, course, or class for this exam'
            );
        }

        $exam->update($validated);
        return $this->success($exam->fresh(['examType', 'batch', 'course', 'class', 'section']));
    }

    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::find($id);
        if (!$exam) return $this->notFound();
        $exam->delete();
        return $this->noContent();
    }

    public function publish(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $exam = Exam::find($id);
        if (!$exam) return $this->notFound();

        if (!$this->hasExamLevel($exam->only(['batch_id', 'course_id', 'class_id']))) {
            return $this->validationError(
                ['level' => 'At least one of batch_id, course_id, or class_id is required'],
                'Please select a batch, course, or class before publishing this exam'
            );
        }

        $channel = $request->input('delivery_channel');
        if (!in_array($channel, ['offline', 'online'], true)) {
            $channel = null;
        }

        try {
            $this->paperService->assertExamReadyForDelivery($exam, $channel);
        } catch (\RuntimeException $e) {
            return $this->validationError([], $e->getMessage());
        }

        $exam->update(['status' => 'published']);
        $exam->refresh();

        if ($this->eligibilityService->isCheckEnabled($exam)) {
            $this->eligibilityService->evaluateExam($exam->id);
        }

        return $this->success($exam, 'Exam published successfully');
    }

    public function results(string $id): JsonResponse
    {
        $exam = Exam::with(['results.student', 'results.subject', 'routines'])->find($id);
        if (!$exam) return $this->notFound();
        return $this->success($exam);
    }

    public function publishPreview(Request $request, string $id): JsonResponse
    {
        $exam = Exam::find($id);
        if (!$exam) {
            return $this->notFound();
        }

        $channel = $request->query('delivery_channel', 'offline');

        try {
            return $this->success($this->assessmentService->getPublishPreview($id, $channel));
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        }
    }

    public function downloadMarksheet(Request $request, string $examId, string $studentId): \Illuminate\Http\Response|JsonResponse
    {
        $channel = $request->query('delivery_channel');

        try {
            $pdf = app(\Modules\Exam\app\Services\ExamReportPdfService::class)
                ->generateMarksheetPdf($examId, $studentId, $channel);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="marksheet-' . $studentId . '.pdf"',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error('Failed to generate marksheet: ' . $e->getMessage(), 500);
        }
    }

    public function publishResults(Request $request, string $id): JsonResponse
    {
        $exam = Exam::find($id);
        if (!$exam) {
            return $this->notFound();
        }

        $allowPartial = $request->boolean('allow_partial');
        $channel = $request->input('delivery_channel', 'offline');

        try {
            $result = $this->assessmentService->publishExamResults(
                $id,
                (string) auth()->id(),
                $allowPartial,
                $channel,
            );
            $this->notifyExamResultsPublished($exam->fresh(), $channel);

            return $this->success($result, 'Exam results published successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        }
    }

    private function notifyExamResultsPublished(Exam $exam, string $channel = 'offline'): void
    {
        $query = \Modules\Exam\app\Models\ExamResult::where('exam_id', $exam->id)
            ->where('status', 'published')
            ->with('student');
        $this->assessmentService->applyResultDeliveryChannelScope($query, $channel);
        $studentUserIds = $query->get()
            ->pluck('student.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($studentUserIds)) {
            return;
        }

        $channelLabel = $channel === 'online' ? 'Online ' : 'Offline ';
        $this->notificationDispatch->sendToUsers(
            $studentUserIds,
            '🎉 Exam Results Published',
            "{$channelLabel}results for \"{$exam->name}\" are now available. Check your exam results page for marks and position.",
            [
                'type' => 'in_app',
                'audience' => 'students',
                'is_highlighted' => true,
            ]
        );
    }

    /**
     * Get exams by batch.
     */
    public function byBatch(string $batchId): JsonResponse
    {
        $batch = Batch::find($batchId);
        $examIds = collect();

        $examIds = $examIds->merge(
            Exam::query()->where('batch_id', $batchId)->pluck('id')
        );

        $examIds = $examIds->merge(
            ExamRoutine::query()
                ->where('batch_id', $batchId)
                ->whereNotNull('exam_id')
                ->distinct()
                ->pluck('exam_id')
        );

        if ($batch?->course_id) {
            $examIds = $examIds->merge(
                Exam::query()->where('course_id', $batch->course_id)->pluck('id')
            );
        }

        $ids = $examIds->filter()->unique()->values();

        $exams = Exam::with(['examType', 'batch', 'course', 'routines'])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids->all()))
            ->when($ids->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->collectionResponse($exams);
    }

    /**
     * Get exams by course.
     */
    public function byCourse(string $courseId): JsonResponse
    {
        $exams = Exam::with(['examType', 'course', 'routines'])
            ->where('course_id', $courseId)
            ->orderBy('start_date', 'desc')
            ->get();
        return $this->collectionResponse($exams);
    }

    /**
     * Get exams by class.
     */
    public function byClass(string $classId): JsonResponse
    {
        $exams = Exam::with(['examType', 'class', 'routines'])
            ->where('class_id', $classId)
            ->orderBy('start_date', 'desc')
            ->get();
        return $this->collectionResponse($exams);
    }

    /**
     * Canonical exam name suggestions for wizard (presets + distinct names already in use).
     */
    public function nameSuggestions(): JsonResponse
    {
        $presets = [
            'Daily Exam',
            'Weekly Exam',
            'Monthly Exam',
            'Yearly Exam',
            'Model Test',
            'Mock Test',
            'Mid Term Exam',
            'Final Exam',
            'Selection Test',
        ];

        $existing = Exam::query()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $suggestions = collect($presets)
            ->merge($existing)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this->success([
            'suggestions' => $suggestions,
            'presets' => $presets,
            'existing' => $existing,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasExamLevel(array $data): bool
    {
        return !empty($data['batch_id']) || !empty($data['course_id']) || !empty($data['class_id']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyExamCreateDefaults(array $data): array
    {
        if (empty($data['academic_session_id'])) {
            $session = AcademicSession::where('is_current', true)->first()
                ?? AcademicSession::orderByDesc('created_at')->first();
            if ($session) {
                $data['academic_session_id'] = $session->id;
            }
        }

        if (empty($data['exam_type_id'])) {
            $type = ExamType::where('status', 'active')
                ->where(function ($q) {
                    $q->where('code', 'BOTH')->orWhere('name', 'like', '%MCQ%CQ%');
                })
                ->first()
                ?? ExamType::where('status', 'active')->first();
            if ($type) {
                $data['exam_type_id'] = $type->id;
            }
        }

        $today = now()->toDateString();
        $data['start_date'] = $data['start_date'] ?? $today;
        $data['end_date'] = $data['end_date'] ?? $data['start_date'];
        $data['delivery_mode'] = $data['delivery_mode'] ?? 'offline';
        $data['is_practice'] = false;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findDuplicateExam(array $data, ?string $excludeId = null): ?Exam
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $query = Exam::query()
            ->where('name', $name)
            ->whereNotIn('status', ['cancelled']);

        if (!empty($data['academic_session_id'])) {
            $query->where('academic_session_id', $data['academic_session_id']);
        }

        if (!empty($data['batch_id'])) {
            $query->where('batch_id', $data['batch_id']);
        }

        $query->where('is_practice', (bool) ($data['is_practice'] ?? false));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }
}
