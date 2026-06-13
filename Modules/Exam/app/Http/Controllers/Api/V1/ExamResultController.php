<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\GradingService;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Communication\app\Services\NotificationDispatchService;

class ExamResultController extends BaseApiController
{
    public function __construct(
        private readonly GradingService $gradingService,
        private readonly ExamAssessmentService $assessmentService,
        private readonly NotificationDispatchService $notificationDispatch,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $query = ExamResult::with(['student', 'subject', 'exam', 'routine'])
            ->filter($request->only(['exam_id', 'student_id', 'subject_id', 'status', 'evaluation_status']));

        if ($request->filled('exam_routine_id')) {
            $query->where('exam_routine_id', $request->exam_routine_id);
        }

        if ($request->filled('class_id')) {
            $query->whereHas('student', fn ($q) => $q->where('current_class_id', $request->class_id));
        }

        if ($request->boolean('pending_manual_only')) {
            $query->whereIn('evaluation_status', ['pending', 'partial']);
        }

        if ($request->filled('delivery_channel')) {
            $this->assessmentService->applyResultDeliveryChannelScope($query, $request->input('delivery_channel'));
        }

        $results = $query->orderByDesc('updated_at')->paginate($perPage);

        return $this->paginatedResponse($results);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|string|exists:exams,id',
            'exam_routine_id' => 'required|string|exists:exam_routines,id',
            'student_id' => 'required|string|exists:students,id',
            'subject_id' => 'required|string|exists:subjects,id',
            'marks_obtained' => 'nullable|numeric|min:0',
            'marks_breakdown' => 'sometimes|array',
            'total_marks' => 'required|numeric|min:1',
            'grade' => 'nullable|string|max:10',
            'grade_point' => 'nullable|numeric|min:0|max:5',
            'remarks' => 'nullable|string',
            'status' => 'sometimes|in:pending,published,absent',
        ]);

        $routine = ExamRoutine::find($validated['exam_routine_id']);
        $markConfig = $this->assessmentService->normalizeMarkConfig($routine?->mark_config);
        $totalMarks = $this->assessmentService->resolveTotalMarks($routine, (float) $validated['total_marks']);

        $result = $this->assessmentService->saveSingleResult(
            examId: $validated['exam_id'],
            examRoutineId: $validated['exam_routine_id'],
            subjectId: $validated['subject_id'],
            totalMarks: $totalMarks,
            markConfig: $markConfig,
            record: array_merge($validated, ['status' => 'pending']),
            enteredBy: auth()->id(),
        );

        return $this->created($result->load(['student', 'subject']));
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|string|exists:exams,id',
            'exam_routine_id' => 'required|string|exists:exam_routines,id',
            'subject_id' => 'required|string|exists:subjects,id',
            'total_marks' => 'required|numeric|min:1',
            'results' => 'required|array|min:1',
            'results.*.student_id' => 'required|string|exists:students,id',
            'results.*.marks_obtained' => 'nullable|numeric|min:0',
            'results.*.marks_breakdown' => 'sometimes|array',
            'results.*.grade' => 'nullable|string|max:10',
            'results.*.grade_point' => 'nullable|numeric|min:0|max:5',
            'results.*.status' => 'sometimes|in:pending,published,absent',
            'results.*.remarks' => 'nullable|string',
        ]);

        $created = $this->assessmentService->saveBulkResults($validated, auth()->id());

        return $this->created($created->values()->all(), 'Results saved successfully');
    }

    public function show(string $id): JsonResponse
    {
        $result = ExamResult::with(['student', 'subject', 'exam', 'routine'])->find($id);
        if (!$result) return $this->notFound();
        return $this->success($result);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $result = ExamResult::find($id);
        if (!$result) return $this->notFound();

        $validated = $request->validate([
            'marks_obtained' => 'nullable|numeric|min:0',
            'marks_breakdown' => 'sometimes|array',
            'grade' => 'nullable|string|max:10',
            'grade_point' => 'nullable|numeric|min:0|max:5',
            'remarks' => 'nullable|string',
            'status' => 'sometimes|in:pending,published,absent',
        ]);

        $routine = $result->routine;
        $this->assessmentService->assertRoutineAllowsMarkEntry($routine);
        $markConfig = $this->assessmentService->normalizeMarkConfig($routine?->mark_config);

        if (!empty($validated['marks_breakdown'])) {
            $this->assessmentService->validateMarksAgainstConfig(
                array_merge($validated, ['marks_breakdown' => $validated['marks_breakdown']]),
                $markConfig,
                (float) $result->total_marks
            );
            $validated['marks_obtained'] = $this->assessmentService->sumBreakdown($validated['marks_breakdown']);
            $validated['evaluation_status'] = $this->assessmentService->deriveEvaluationStatus(
                $validated['marks_breakdown'],
                $markConfig,
                $validated['status'] ?? $result->status
            );
        } elseif (array_key_exists('marks_obtained', $validated)) {
            $validated['marks_breakdown'] = ['total' => $validated['marks_obtained']];
            $validated['evaluation_status'] = $validated['marks_obtained'] !== null ? 'complete' : 'pending';
        }

        if (array_key_exists('marks_obtained', $validated) && $validated['marks_obtained'] !== null) {
            $calculated = $this->gradingService->calculateFromMarks(
                (float) $validated['marks_obtained'],
                (float) $result->total_marks
            );
            $validated['grade'] = $validated['grade'] ?? $calculated['grade'];
            $validated['grade_point'] = $validated['grade_point'] ?? $calculated['grade_point'];
        }

        $result->update($validated);

        return $this->success($result->fresh(['student', 'subject', 'routine']));
    }

    public function destroy(string $id): JsonResponse
    {
        $result = ExamResult::find($id);
        if (!$result) return $this->notFound();
        $result->delete();
        return $this->noContent();
    }

    public function publish(string $id): JsonResponse
    {
        $result = ExamResult::find($id);
        if (!$result) return $this->notFound();

        $result->update(['status' => 'published']);
        $this->notifyStudentResultPublished($result->fresh(['student', 'exam', 'subject']));

        return $this->success($result->fresh(['student', 'subject', 'exam', 'routine']), 'Result published successfully');
    }

    public function unpublish(string $id): JsonResponse
    {
        $result = ExamResult::find($id);
        if (!$result) return $this->notFound();

        $result->update(['status' => 'pending']);

        return $this->success($result->fresh(['student', 'subject', 'exam', 'routine']), 'Result moved to pending');
    }

    public function bulkPublish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|string|exists:exams,id',
            'exam_routine_id' => 'sometimes|string|exists:exam_routines,id',
            'subject_id' => 'sometimes|string|exists:subjects,id',
            'class_id' => 'sometimes|string|exists:classes,id',
        ]);

        $publishedResults = ExamResult::where('exam_id', $validated['exam_id'])
            ->where('status', 'pending');

        if (!empty($validated['exam_routine_id'])) {
            $publishedResults->where('exam_routine_id', $validated['exam_routine_id']);
        }

        if (!empty($validated['subject_id'])) {
            $publishedResults->where('subject_id', $validated['subject_id']);
        }

        if (!empty($validated['class_id'])) {
            $publishedResults->whereHas('student', fn ($q) => $q->where('current_class_id', $validated['class_id']));
        }

        $resultsToNotify = (clone $publishedResults)->with(['student', 'exam', 'subject'])->get();
        $count = $publishedResults->update(['status' => 'published']);

        foreach ($resultsToNotify as $result) {
            $this->notifyStudentResultPublished($result);
        }

        $examId = $validated['exam_id'];
        $remainingPending = ExamResult::where('exam_id', $examId)->where('status', 'pending')->count();
        if ($count > 0 && $remainingPending === 0) {
            \Modules\Exam\app\Models\Exam::where('id', $examId)->update([
                'result_status' => 'published',
                'result_publish_at' => now(),
            ]);
        }

        return $this->success(['published_count' => $count], "{$count} result(s) published successfully");
    }

    private function notifyStudentResultPublished(ExamResult $result): void
    {
        $userId = $result->student?->user_id;
        if (!$userId) {
            return;
        }

        $examName = $result->exam?->name ?? 'Exam';
        $subjectName = $result->subject?->name ?? 'Subject';
        $marks = $result->marks_obtained ?? '—';
        $grade = $result->grade ?? '—';

        $this->notificationDispatch->sendToUsers(
            [$userId],
            '🎉 Exam Result Published',
            "Your result for \"{$examName}\" ({$subjectName}) is now available. Marks: {$marks}, Grade: {$grade}.",
            [
                'type' => 'in_app',
                'audience' => 'students',
                'is_highlighted' => true,
            ]
        );
    }

    public function subjectsSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|string|exists:exams,id',
            'status' => 'sometimes|in:pending,published,absent',
            'evaluation_status' => 'sometimes|in:pending,partial,complete',
            'pending_manual_only' => 'sometimes|boolean',
            'delivery_channel' => 'sometimes|in:offline,online',
        ]);

        $query = ExamResult::query()
            ->where('exam_id', $validated['exam_id'])
            ->with([
                'subject:id,name',
                'routine' => fn ($q) => $q->select('id', 'subject_id', 'batch_id', 'exam_date', 'start_time', 'end_time', 'status')
                    ->with('batch:id,name'),
            ]);

        $this->applyListFilters($query, $validated);

        if (!empty($validated['delivery_channel'])) {
            $this->assessmentService->applyResultDeliveryChannelScope($query, $validated['delivery_channel']);
        }

        $grouped = $query->get()->groupBy('subject_id')->map(function ($rows) {
            $first = $rows->first();

            return [
                'subject_id' => $first->subject_id,
                'subject_name' => $first->subject?->name ?? 'Unknown',
                'exam_routine_id' => $first->exam_routine_id,
                'batch_name' => $first->routine?->batch?->name,
                'routine_status' => $first->routine?->status,
                'exam_date' => $first->routine?->exam_date?->format('Y-m-d'),
                'count' => $rows->count(),
            ];
        })->sortBy('subject_name')->values();

        return $this->success($grouped);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|string|exists:exams,id',
            'subject_id' => 'sometimes|string|exists:subjects,id',
            'class_id' => 'sometimes|string|exists:classes,id',
            'delivery_channel' => 'sometimes|in:offline,online',
        ]);

        $query = ExamResult::where('exam_id', $validated['exam_id']);

        if (!empty($validated['delivery_channel'])) {
            app(\Modules\Exam\app\Services\ExamAssessmentService::class)
                ->applyResultDeliveryChannelScope($query, $validated['delivery_channel']);
        }

        if (!empty($validated['subject_id'])) {
            $query->where('subject_id', $validated['subject_id']);
        }

        if (!empty($validated['class_id'])) {
            $query->whereHas('student', fn ($q) => $q->where('current_class_id', $validated['class_id']));
        }

        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $evaluationCounts = (clone $query)->selectRaw('evaluation_status, COUNT(*) as total')
            ->groupBy('evaluation_status')
            ->pluck('total', 'evaluation_status');

        return $this->success([
            'pending' => (int) ($counts['pending'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'total' => (int) $counts->sum(),
            'evaluation_pending' => (int) ($evaluationCounts['pending'] ?? 0),
            'evaluation_partial' => (int) ($evaluationCounts['partial'] ?? 0),
            'evaluation_complete' => (int) ($evaluationCounts['complete'] ?? 0),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyListFilters($query, array $filters): void
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['evaluation_status'])) {
            $query->where('evaluation_status', $filters['evaluation_status']);
        }

        if (!empty($filters['pending_manual_only'])) {
            $query->whereIn('evaluation_status', ['pending', 'partial']);
        }
    }
}
