<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Http\Requests\GenerateClassRoutineRequest;
use Modules\Academic\app\Http\Requests\StoreClassRoutineRequest;
use Modules\Academic\app\Http\Requests\UpdateClassRoutineRequest;
use Modules\Academic\app\Http\Resources\ClassRoutineCollection;
use Modules\Academic\app\Http\Resources\ClassRoutineResource;
use Modules\Academic\app\Repositories\ClassRoutineRepository;
use Modules\Academic\app\Services\ClassRoutineService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class ClassRoutineController extends BaseApiController
{
    public function __construct(
        private ClassRoutineRepository $repository,
        private ClassRoutineService $routineService,
    ) {}

    /**
     * Paginated list of routines with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'batch_id', 'batch_ids', 'course_id', 'class_id', 'section_id', 'group_id',
            'subject_id', 'teacher_id', 'room_id', 'day_of_week', 'status', 'search',
            'is_lunch_break', 'is_off_day',
        ]);
        $perPage = $this->getPerPage($request);
        $routines = $this->repository->paginate($filters, $perPage);

        return $this->paginatedResponse($routines);
    }

    /**
     * Store a new routine.
     */
    public function store(StoreClassRoutineRequest $request): JsonResponse
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

        // Calculate duration if not provided
        if (empty($data['duration']) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $start = \Carbon\Carbon::createFromFormat('H:i:s', $data['start_time']);
            $end = \Carbon\Carbon::createFromFormat('H:i:s', $data['end_time']);
            $data['duration'] = $start->diffInMinutes($end);
        }

        // Check for conflicts
        $conflicts = $this->repository->findConflicts($data);
        if ($conflicts->isNotEmpty()) {
            return $this->validationError(
                ['conflict' => 'Time slot conflicts detected'],
                'Cannot create routine: teacher, room, or batch has a conflict at this time'
            );
        }

        $routine = $this->repository->create($data);

        return $this->created(
            new ClassRoutineResource($routine->load([
                'subject', 'teacher.user', 'room', 'batch', 'course', 'class',
            ])),
            'Routine created successfully'
        );
    }

    /**
     * Show a single routine.
     */
    public function show(string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Routine not found');
        }

        return $this->success(new ClassRoutineResource($routine));
    }

    /**
     * Update a routine.
     */
    public function update(UpdateClassRoutineRequest $request, string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Routine not found');
        }

        $data = $request->validated();

        // Normalize time to H:i:s format for DB storage
        if (isset($data['start_time']) && preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
            $data['start_time'] .= ':00';
        }
        if (isset($data['end_time']) && preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
            $data['end_time'] .= ':00';
        }

        // Recalculate duration if times changed
        if ((isset($data['start_time']) || isset($data['end_time'])) && empty($data['duration'])) {
            $startTime = $data['start_time'] ?? $routine->start_time;
            $endTime = $data['end_time'] ?? $routine->end_time;
            $start = \Carbon\Carbon::createFromFormat('H:i:s', $startTime);
            $end = \Carbon\Carbon::createFromFormat('H:i:s', $endTime);
            $data['duration'] = $start->diffInMinutes($end);
        }

        // Check for conflicts (exclude current routine)
        $conflictData = array_merge($routine->toArray(), $data);
        $conflicts = $this->repository->findConflicts($conflictData, $id);
        if ($conflicts->isNotEmpty()) {
            return $this->validationError(
                ['conflict' => 'Time slot conflicts detected'],
                'Cannot update routine: teacher, room, or batch has a conflict at this time'
            );
        }

        $routine = $this->repository->update($routine, $data);

        return $this->success(
            new ClassRoutineResource($routine),
            'Routine updated successfully'
        );
    }

    /**
     * Delete a routine (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $routine = $this->repository->findById($id);
        if (!$routine) {
            return $this->notFound('Routine not found');
        }

        $this->repository->delete($routine);

        return $this->noContent('Routine deleted successfully');
    }

    /**
     * Auto-generate routines.
     */
    public function generate(GenerateClassRoutineRequest $request): JsonResponse
    {
        $result = $this->routineService->generate($request->validated());

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Routine generated successfully');
    }

    /**
     * Swap two routine slots.
     */
    public function swap(Request $request): JsonResponse
    {
        $request->validate([
            'slot1_id' => 'required|string',
            'slot2_id' => 'required|string',
        ]);

        $result = $this->routineService->swapSlots(
            $request->input('slot1_id'),
            $request->input('slot2_id')
        );

        if (isset($result['error'])) {
            return $this->notFound($result['error']);
        }

        return $this->success($result, 'Slots swapped successfully');
    }

    /**
     * Publish routines for a level.
     */
    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:batch,course,class',
            'level_id' => 'required|string',
        ]);

        $result = $this->routineService->publish(
            $request->input('level'),
            $request->input('level_id')
        );

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Routines published successfully');
    }

    /**
     * Publish routines for multiple batches.
     */
    public function publishMultiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
        ]);

        $result = $this->routineService->publishMultiBatch(
            $request->input('batch_ids')
        );

        return $this->success($result, 'Routines published successfully');
    }

    /**
     * Archive routines for a level.
     */
    public function archive(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:batch,course,class',
            'level_id' => 'required|string',
        ]);

        $result = $this->routineService->archive(
            $request->input('level'),
            $request->input('level_id')
        );

        return $this->success($result, 'Routines archived successfully');
    }

    /**
     * Archive routines for multiple batches.
     */
    public function archiveMultiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
        ]);

        $result = $this->routineService->archiveMultiBatch(
            $request->input('batch_ids')
        );

        return $this->success($result, 'Routines archived successfully');
    }

    /**
     * Get conflicts for a level.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:batch,course,class',
            'level_id' => 'required|string',
        ]);

        $conflicts = $this->routineService->getConflicts(
            $request->input('level'),
            $request->input('level_id')
        );

        return $this->success([
            'conflicts' => $conflicts,
            'total' => count($conflicts),
        ]);
    }

    /**
     * Get conflicts across multiple batches.
     */
    public function multiBatchConflicts(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
        ]);

        $conflicts = $this->routineService->getMultiBatchConflicts(
            $request->input('batch_ids')
        );

        return $this->success([
            'conflicts' => $conflicts,
            'total' => count($conflicts),
        ]);
    }

    /**
     * Get multi-batch grid data.
     */
    public function multiBatchGrid(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
        ]);

        $filters = $request->only([
            'status', 'teacher_id', 'subject_id', 'room_id', 'day_of_week',
        ]);

        $grid = $this->routineService->getMultiBatchGrid(
            $request->input('batch_ids'),
            $filters
        );

        return $this->success($grid);
    }

    /**
     * Get dashboard stats for multiple batches.
     */
    public function multiBatchStats(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
        ]);

        $stats = $this->routineService->getMultiBatchStats(
            $request->input('batch_ids')
        );

        return $this->success($stats);
    }

    /**
     * Get teacher workload analysis.
     */
    public function teacherLoad(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => 'required|string',
        ]);

        $load = $this->routineService->getTeacherLoad(
            $request->input('teacher_id')
        );

        return $this->success($load);
    }

    /**
     * Get room utilization statistics.
     */
    public function roomUtilization(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|string',
        ]);

        $utilization = $this->routineService->getRoomUtilization(
            $request->input('room_id')
        );

        return $this->success($utilization);
    }

    /**
     * Set lunch break for batches.
     */
    public function setLunchBreak(Request $request): JsonResponse
    {
        $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'required|string',
            'day' => 'required|in:sat,sun,mon,tue,wed,thu,fri',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
        ]);

        $result = $this->routineService->setLunchBreak(
            $request->input('batch_ids'),
            $request->input('day'),
            $request->input('start_time'),
            $request->input('end_time')
        );

        return $this->success($result, 'Lunch break set successfully');
    }

    /**
     * Set off day for a batch.
     */
    public function setOffDay(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id' => 'required|string',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $offDay = $this->routineService->setOffDay(
            $request->input('batch_id'),
            $request->input('date'),
            $request->input('reason')
        );

        return $this->success($offDay, 'Off day set successfully');
    }

    /**
     * Get routines by batch.
     */
    public function getByBatch(Request $request, string $batchId): JsonResponse
    {
        $sessionId = $request->query('session_id');
        $status = $request->query('status', 'all');
        $routines = $this->routineService->getByLevel('batch', $batchId, $sessionId, $status);
        return $this->success($routines);
    }

    /**
     * Get routines by course.
     */
    public function getByCourse(Request $request, string $courseId): JsonResponse
    {
        $sessionId = $request->query('session_id');
        $status = $request->query('status', 'all');
        $routines = $this->routineService->getByLevel('course', $courseId, $sessionId, $status);
        return $this->success($routines);
    }

    /**
     * Get routines by class.
     */
    public function getByClass(Request $request, string $classId): JsonResponse
    {
        $sessionId = $request->query('session_id');
        $status = $request->query('status', 'all');
        $routines = $this->routineService->getByLevel('class', $classId, $sessionId, $status);
        return $this->success($routines);
    }

    /**
     * Bulk store routines.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'routines' => 'required|array|min:1',
            'routines.*.subject_id' => 'required|string|exists:subjects,id',
            'routines.*.teacher_id' => 'required|string|exists:teachers,id',
            'routines.*.day_of_week' => 'required|in:sat,sun,mon,tue,wed,thu,fri',
            'routines.*.start_time' => 'required|date_format:H:i:s',
            'routines.*.end_time' => 'required|date_format:H:i:s',
        ]);

        $routines = $request->input('routines');
        foreach ($routines as &$slot) {
            if (isset($slot['start_time']) && preg_match('/^\d{2}:\d{2}$/', $slot['start_time'])) {
                $slot['start_time'] .= ':00';
            }
            if (isset($slot['end_time']) && preg_match('/^\d{2}:\d{2}$/', $slot['end_time'])) {
                $slot['end_time'] .= ':00';
            }
            // Calculate duration
            if (empty($slot['duration']) && !empty($slot['start_time']) && !empty($slot['end_time'])) {
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $slot['start_time']);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $slot['end_time']);
                $slot['duration'] = $start->diffInMinutes($end);
            }
        }
        unset($slot);

        $created = [];
        $errors = [];

        foreach ($request->input('routines') as $i => $data) {
            $data['created_by'] = auth()->id();
            $data['status'] = 'draft';

            $conflicts = $this->repository->findConflicts($data);
            if ($conflicts->isNotEmpty()) {
                $errors[] = "Row {$i}: Time conflict detected";
                continue;
            }

            try {
                $created[] = $this->repository->create($data);
            } catch (\Exception $e) {
                $errors[] = "Row {$i}: {$e->getMessage()}";
            }
        }

        return $this->success([
            'created' => ClassRoutineResource::collection(collect($created)),
            'errors' => $errors,
            'total_created' => count($created),
            'total_errors' => count($errors),
        ], 'Bulk store completed');
    }
}
