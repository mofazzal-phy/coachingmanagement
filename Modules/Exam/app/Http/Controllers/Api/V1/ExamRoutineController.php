<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Exam\app\Http\Requests\BulkStoreExamRoutineRequest;
use Modules\Exam\app\Http\Requests\GenerateRoutineRequest;
use Modules\Exam\app\Http\Requests\StoreExamRoutineRequest;
use Modules\Exam\app\Http\Requests\UpdateExamRoutineRequest;
use Modules\Exam\app\Http\Resources\ExamRoutineResource;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamRoutineService;
use Modules\Exam\app\Services\ExamRoutineGridBuilder;
use Modules\Exam\app\Services\ExamRoutinePdfService;
use Modules\Exam\app\Services\ExamPaperService;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Core\app\Http\Controllers\BaseApiController;

class ExamRoutineController extends BaseApiController
{
    public function __construct(
        private ExamRoutineRepository $repository,
        private ExamRoutineService $routineService,
        private ExamRoutineGridBuilder $gridBuilder,
        private ExamPaperService $paperService,
    ) {}

    /**
     * Paginated list of exam routines with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'exam_id', 'batch_id', 'course_id', 'class_id', 'group_id',
            'subject_id', 'teacher_id', 'room_id', 'status', 'search',
            'from_date', 'to_date',
        ]);
        $perPage = $this->getPerPage($request);
        $routines = $this->repository->paginate($filters, $perPage);

        return $this->paginatedResponse($routines);
    }

    /**
     * Store a new exam routine.
     */
    public function store(StoreExamRoutineRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';

        // Normalize time to H:i:s format for DB storage
        if (isset($data['start_time']) && preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
            $data['start_time'] .= ':00';
        }
        if (isset($data['end_time']) && preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
            $data['end_time'] .= ':00';
        }

        $batchConflicts = $this->repository->findBatchTimeConflict($data);
        if ($batchConflicts->isNotEmpty()) {
            $c = $batchConflicts->first();
            $liveNote = in_array($c->status, ['published', 'completed'], true) ? ' (already published)' : '';
            return $this->validationError(
                ['batch_conflict' => $batchConflicts->pluck('id')->all()],
                'This exam already has '
                . ($c->subject?->name ?? 'a subject')
                . $liveNote
                . ' scheduled at an overlapping time on this date for this batch.'
                . ' Pick another slot, reschedule, or remove the existing routine.'
            );
        }

        $conflicts = $this->repository->findConflicts($data);
        if ($conflicts->isNotEmpty()) {
            return $this->validationError(
                ['conflict' => 'Time slot conflicts detected'],
                'Cannot create routine: teacher or room has a conflict at this date/time'
            );
        }

        try {
            $routine = $this->repository->create($data);
            $questionsCopied = $this->paperService->copyQuestionsFromSiblingIfEmpty($routine);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        }

        $message = 'Exam routine created successfully';
        if (!empty($questionsCopied)) {
            $message .= ' Questions copied from the same subject on another date.';
        }

        return $this->created(
            new ExamRoutineResource($routine->load([
                'exam.examType', 'subject', 'teacher.user', 'room',
                'batch', 'course', 'class',
            ])),
            $message
        );
    }

    /**
     * Show a single exam routine.
     */
    public function show(string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        return $this->success(new ExamRoutineResource($routine));
    }

    /**
     * Update an exam routine.
     */
    public function update(UpdateExamRoutineRequest $request, string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        $data = $request->validated();

        // Normalize time to H:i:s format for DB storage
        if (isset($data['start_time']) && preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
            $data['start_time'] .= ':00';
        }
        if (isset($data['end_time']) && preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
            $data['end_time'] .= ':00';
        }

        // Check for conflicts (exclude current routine)
        $conflictData = array_merge($routine->toArray(), $data);
        $conflicts = $this->repository->findConflicts($conflictData, $id);
        if ($conflicts->isNotEmpty()) {
            return $this->validationError(
                ['conflict' => 'Time slot conflicts detected'],
                'Cannot update routine: teacher or room has a conflict at this date/time'
            );
        }

        $routine = $this->repository->update($routine, $data);

        return $this->success(
            new ExamRoutineResource($routine),
            'Exam routine updated successfully'
        );
    }

    /**
     * Delete an exam routine (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        $this->repository->delete($routine);

        return $this->noContent('Exam routine deleted successfully');
    }

    /**
     * Auto-generate exam routines.
     */
    public function generate(GenerateRoutineRequest $request): JsonResponse
    {
        $validated = $request->validated();
        \Illuminate\Support\Facades\Log::info('[ExamRoutineController] Generate called', [
            'exam_id' => $validated['exam_id'] ?? null,
            'subject_count' => count($validated['subject_ids'] ?? []),
            'subject_ids' => $validated['subject_ids'] ?? [],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'apply' => $validated['apply'] ?? false,
        ]);

        $result = $this->routineService->generate($validated);

        if (isset($result['error'])) {
            \Illuminate\Support\Facades\Log::warning('[ExamRoutineController] Generate service error', [
                'error' => $result['error'],
            ]);
            return $this->error($result['error'], 422);
        }

        \Illuminate\Support\Facades\Log::info('[ExamRoutineController] Generate success', [
            'total_slots' => $result['total_slots'] ?? 0,
        ]);

        return $this->success($result, 'Exam routine generated successfully');
    }

    /**
     * Remove routines for subjects that are no longer part of the exam schedule.
     */
    public function pruneSubjects(Request $request, string $examId): JsonResponse
    {
        $data = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'required|string|exists:subjects,id',
            'delivery_mode' => 'sometimes|in:offline,online,hybrid',
        ]);

        $removed = $this->routineService->pruneRoutinesExceptSubjects(
            $examId,
            $data['subject_ids'],
            $data['delivery_mode'] ?? null,
        );

        return $this->success(
            ['removed' => $removed],
            $removed > 0
                ? "Removed {$removed} routine(s) for deselected subjects."
                : 'No extra routines to remove.',
        );
    }

    /**
     * Publish routines for an exam.
     */
    public function publish(Request $request, string $examId): JsonResponse
    {
        $channel = $request->input('delivery_channel');
        if (!in_array($channel, ['offline', 'online'], true)) {
            $channel = null;
        }

        $scope = array_filter([
            'batch_id' => $request->input('batch_id'),
            'class_id' => $request->input('class_id'),
            'course_id' => $request->input('course_id'),
            'month' => $request->input('month'),
            'year' => $request->input('year'),
        ], fn ($value) => filled($value));

        $result = $this->routineService->publish($examId, $channel, $scope);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Exam routines published successfully');
    }

    /**
     * Complete routines for an exam.
     */
    public function complete(string $examId): JsonResponse
    {
        $result = $this->routineService->complete($examId);

        return $this->success($result, 'Exam routines completed successfully');
    }

    /**
     * Cancel routines for an exam.
     */
    public function cancel(string $examId): JsonResponse
    {
        $result = $this->routineService->cancel($examId);

        return $this->success($result, 'Exam routines cancelled successfully');
    }

    /**
     * Get conflicts for an exam.
     */
    public function conflicts(Request $request, string $examId): JsonResponse
    {
        $channel = $request->input('delivery_channel');
        if (!in_array($channel, ['offline', 'online'], true)) {
            $channel = null;
        }

        $conflicts = $this->routineService->getConflicts($examId, $channel);

        return $this->success([
            'conflicts' => $conflicts,
            'total' => count($conflicts),
        ]);
    }

    /**
     * Get routines by exam.
     */
    public function getByExam(Request $request, string $examId): JsonResponse
    {
        $filters = [];
        if ($request->filled('delivery_channel')) {
            $channel = $request->input('delivery_channel');
            if (in_array($channel, ['offline', 'online'], true)) {
                $filters['delivery_channel'] = $channel;
            }
        }

        $routines = $this->repository->getByExam($examId, $filters);
        return $this->success(ExamRoutineResource::collection($routines));
    }

    /**
     * Get routines structured as a weekly grid (days × time slots) with batch grouping.
     * This is the primary endpoint for the ERP-style exam routine dashboard.
     */
    public function getGrid(Request $request, string $examId): JsonResponse
    {
        $filters = [];
        if ($request->filled('batch_id')) $filters['batch_id'] = $request->input('batch_id');
        if ($request->filled('class_id')) $filters['class_id'] = $request->input('class_id');
        if ($request->filled('course_id')) $filters['course_id'] = $request->input('course_id');
        if ($request->filled('exam_type_id')) $filters['exam_type_id'] = $request->input('exam_type_id');
        if ($request->filled('status')) $filters['status'] = $request->input('status');
        if ($request->filled('delivery_channel')) {
            $channel = $request->input('delivery_channel');
            if (in_array($channel, ['offline', 'online'], true)) {
                $filters['delivery_channel'] = $channel;
            }
        }

        $routines = $this->repository->getByExam($examId, $filters);

        $days = ExamRoutineGridBuilder::WEEKDAYS;
        $includeLunch = $request->boolean('include_lunch', false);
        $timeSlots = $this->gridBuilder->buildTimeSlots($routines, $includeLunch);

        $batchMaps = $this->gridBuilder->buildBatchColorMaps($routines);
        $batchColorMap = $batchMaps['bg'];
        $batchTextColorMap = $batchMaps['text'];
        $batches = $batchMaps['batches'];

        $firstDate = null;
        $lastDate = null;
        foreach ($routines as $routine) {
            $d = $routine->exam_date;
            if (!$d) {
                continue;
            }
            $dateStr = $d->format('Y-m-d');
            if ($firstDate === null || $dateStr < $firstDate) {
                $firstDate = $dateStr;
            }
            if ($lastDate === null || $dateStr > $lastDate) {
                $lastDate = $dateStr;
            }
        }

        $exam = \Modules\Exam\app\Models\Exam::with(['examType', 'batch', 'course', 'class'])->find($examId);

        if ($exam?->is_practice) {
            return $this->validationError([], 'Practice exams are not shown on the exam routine grid.');
        }

        [$month, $year] = $this->gridBuilder->resolveMonthYear(
            $request->filled('month') ? (int) $request->input('month') : null,
            $request->filled('year') ? (int) $request->input('year') : null,
            $firstDate,
            $exam?->start_date?->format('Y-m-d'),
        );

        $weeks = $this->gridBuilder->buildMonthWeeks($year, $month, $timeSlots);
        $resultCounts = $this->gridBuilder->resultCountsForRoutines($routines);
        $this->gridBuilder->populateWeekGrids($weeks, $routines, $timeSlots, $batchColorMap, $batchTextColorMap, $resultCounts);

        // Legacy single-week grid (first week of month) for backward compatibility
        $dayDates = [];
        $grid = [];
        if (!empty($weeks)) {
            $firstWeek = $weeks[0];
            foreach ($firstWeek['columns'] as $col) {
                $dayDates[$col['day_name']] = $col['date'];
                $grid[$col['day_name']] = [];
                foreach ($firstWeek['grid'][$col['date']] ?? [] as $slotKey => $cell) {
                    $grid[$col['day_name']][$slotKey] = $cell;
                }
            }
        }

        // Collect unique classes, courses, exam_types from routines for filter dropdowns
        // Use direct relationships on ExamRoutine (course, class) which are eager-loaded,
        // and also traverse batch->course as a fallback for routines that may not have
        // direct course_id/class_id set.
        $filterClasses = [];
        $filterCourses = [];
        $filterExamTypes = [];
        $seenClassIds = [];
        $seenCourseIds = [];
        $seenExamTypeIds = [];
        foreach ($routines as $routine) {
            // Collect course — try direct relationship first, then via batch
            $course = $routine->course ?? ($routine->batch?->course ?? null);
            if ($course && !in_array($course->id, $seenCourseIds)) {
                $seenCourseIds[] = $course->id;
                $filterCourses[] = ['id' => $course->id, 'name' => $course->name];
            }

            // Collect class — try direct relationship first, then via course->class, then via batch->course->class
            $class = $routine->class
                ?? ($course?->class ?? null)
                ?? ($routine->batch?->course?->class ?? null);
            if ($class && !in_array($class->id, $seenClassIds)) {
                $seenClassIds[] = $class->id;
                $filterClasses[] = ['id' => $class->id, 'name' => $class->name];
            }

            // Collect exam type
            if ($routine->examType && !in_array($routine->examType->id, $seenExamTypeIds)) {
                $seenExamTypeIds[] = $routine->examType->id;
                $filterExamTypes[] = ['id' => $routine->examType->id, 'name' => $routine->examType->name];
            }
        }

        $monthLabel = \Carbon\Carbon::create($year, $month, 1)->format('F Y');

        $activeChannel = $filters['delivery_channel'] ?? null;

        return $this->success([
            'grid' => $grid,
            'days' => $days,
            'day_dates' => $dayDates,
            'weeks' => $weeks,
            'month' => $month,
            'year' => $year,
            'month_label' => $monthLabel,
            'delivery_channel' => $activeChannel,
            'include_lunch' => $includeLunch,
            'time_slots' => $timeSlots,
            'batches' => $batches,
            'batch_colors' => $batchColorMap,
            'batch_text_colors' => $batchTextColorMap,
            'filter_classes' => $filterClasses,
            'filter_courses' => $filterCourses,
            'filter_exam_types' => $filterExamTypes,
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
                'result_status' => $exam->result_status,
                'result_publish_at' => $exam->result_publish_at?->toIso8601String(),
            ] : null,
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
            'math' => '#EF4444',
            'mathematics' => '#EF4444',
            'biology' => '#8B5CF6',
            'english' => '#F97316',
            'bangla' => '#78716C',
            'ict' => '#06B6D4',
            'higher math' => '#EC4899',
            'accounting' => '#14B8A6',
            'finance' => '#F59E0B',
            'economics' => '#84CC16',
            'history' => '#6366F1',
            'geography' => '#0EA5E9',
            'social' => '#A855F7',
            'islam' => '#059669',
        ];

        $name = strtolower(trim($subjectName));
        foreach ($colors as $key => $color) {
            if (str_contains($name, $key)) return $color;
        }

        $palette = ['#3B82F6', '#10B981', '#EF4444', '#8B5CF6', '#F97316', '#78716C', '#06B6D4', '#EC4899'];
        return $palette[abs(crc32($name)) % count($palette)];
    }

    /**
     * Get routines by batch.
     */
    public function getByBatch(Request $request, string $batchId): JsonResponse
    {
        $filters = $request->only(['exam_id', 'status', 'from_date', 'to_date']);
        $routines = $this->repository->getByLevel('batch', $batchId, $filters);
        return $this->success($routines);
    }

    /**
     * Get routines by course.
     */
    public function getByCourse(Request $request, string $courseId): JsonResponse
    {
        $filters = $request->only(['exam_id', 'status', 'from_date', 'to_date']);
        $routines = $this->repository->getByLevel('course', $courseId, $filters);
        return $this->success($routines);
    }

    /**
     * Get routines by class.
     */
    public function getByClass(Request $request, string $classId): JsonResponse
    {
        $filters = $request->only(['exam_id', 'status', 'from_date', 'to_date']);
        $routines = $this->repository->getByLevel('class', $classId, $filters);
        return $this->success($routines);
    }

    /**
     * Get calendar data for an exam.
     */
    public function calendar(string $examId, Request $request): JsonResponse
    {
        $month = $request->query('month');
        $year = $request->query('year');
        $data = $this->repository->getCalendarData($examId, $month, $year);
        return $this->success($data);
    }

    /**
     * Bulk store exam routines.
     */
    public function bulkStore(BulkStoreExamRoutineRequest $request): JsonResponse
    {
        $routines = $request->input('routines');
        $examId = $request->input('exam_id');
        $examTypeId = $request->input('exam_type_id');
        $created = [];
        $errors = [];

        foreach ($routines as $i => $data) {
            $data['created_by'] = auth()->id();
            $data['status'] = 'draft';
            $data['exam_id'] = $examId;
            if ($examTypeId) {
                $data['exam_type_id'] = $examTypeId;
            }

            // Normalize times
            if (isset($data['start_time']) && preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
                $data['start_time'] .= ':00';
            }
            if (isset($data['end_time']) && preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
                $data['end_time'] .= ':00';
            }

            $batchConflicts = $this->repository->findBatchTimeConflict($data);
            if ($batchConflicts->isNotEmpty()) {
                $c = $batchConflicts->first();
                $errors[] = "Row {$i}: Batch already has "
                    . ($c->exam?->name ?? 'an exam')
                    . ' (' . ($c->subject?->name ?? 'subject') . ') at an overlapping time';
                continue;
            }

            $conflicts = $this->repository->findConflicts($data);
            if ($conflicts->isNotEmpty()) {
                $errors[] = "Row {$i}: Teacher or room conflict detected";
                continue;
            }

            try {
                $created[] = $this->repository->create($data);
            } catch (\Exception $e) {
                $errors[] = "Row {$i}: {$e->getMessage()}";
            }
        }

        return $this->success([
            'created' => ExamRoutineResource::collection(collect($created)),
            'errors' => $errors,
            'total_created' => count($created),
            'total_errors' => count($errors),
        ], 'Bulk store completed');
    }

    /**
     * Export exam routine as PDF.
     */
    public function exportPdf(string $examId): \Illuminate\Http\Response
    {
        try {
            $pdfService = app(ExamRoutinePdfService::class);
            $pdfContent = $pdfService->generateRoutinePdf($examId);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="exam-routine-' . $examId . '.pdf"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List questions attached to a routine.
     */
    public function questions(Request $request, string $id): JsonResponse
    {
        $routine = ExamRoutine::find($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        $includeAnswers = $request->boolean('include_answers')
            || in_array($request->input('variant'), ['answer_sheet', 'invigilator'], true);

        $user = auth()->user();
        $isStudent = $user && $user->hasRole('student') && !$user->hasAnyRole(['super-admin', 'admin', 'teacher']);

        if ($isStudent) {
            $includeAnswers = false;
        }

        $questions = $this->paperService->getAttachedQuestions(
            $routine,
            $includeAnswers,
            false
        );

        return $this->success([
            'routine_id' => $routine->id,
            'randomize_questions' => $routine->randomize_questions,
            'count' => count($questions),
            'questions' => $questions,
        ]);
    }

    /**
     * Sync questions onto a routine.
     */
    public function syncQuestions(Request $request, string $id): JsonResponse
    {
        $routine = ExamRoutine::find($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        $validated = $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*.question_id' => 'required|string|exists:questions,id',
            'questions.*.sort_order' => 'sometimes|integer|min:0',
            'questions.*.marks_override' => 'nullable|numeric|min:0',
            'randomize_questions' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->paperService->syncQuestions(
                $routine,
                $validated['questions'],
                (bool) ($validated['randomize_questions'] ?? false)
            );

            return $this->success($result, 'Questions attached successfully');
        } catch (\Throwable $e) {
            return $this->validationError(['questions' => $e->getMessage()], $e->getMessage());
        }
    }

    /**
     * Export question paper PDF for a single routine.
     */
    public function exportQuestionPaper(Request $request, string $id): \Illuminate\Http\Response|JsonResponse
    {
        $routine = ExamRoutine::find($id);
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        $variant = $request->input('variant', 'student');
        if (!in_array($variant, ['student', 'answer_sheet', 'invigilator'], true)) {
            return $this->validationError(['variant' => 'Invalid variant'], 'Variant must be student, answer_sheet, or invigilator');
        }

        try {
            $pdfContent = $this->paperService->generateQuestionPaperPdf($id, $variant);
            $filename = 'question-paper-' . $variant . '-' . $id . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate question paper: ' . $e->getMessage(),
            ], 500);
        }
    }
}
