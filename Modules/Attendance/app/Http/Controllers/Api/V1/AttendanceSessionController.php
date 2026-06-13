<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Models\AttendanceSession;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\SessionAttendanceService;
use Carbon\Carbon;

class AttendanceSessionController extends BaseApiController
{
    public function __construct(
        protected AttendanceEngine $engine,
        protected SessionAttendanceService $sessionAttendance,
    ) {}

    /**
     * List attendance sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceSession::with(['batch:id,name', 'subject:id,name', 'teacher:id,first_name,last_name', 'creator:id,name']);

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('session_type')) {
            $query->where('session_type', $request->session_type);
        }

        if ($request->filled('date')) {
            $query->where('attendance_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->where('attendance_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('attendance_date', '<=', $request->date_to);
        }

        $sessions = $query->withCount('logs')
            ->orderBy('created_at', 'desc')
            ->paginate($this->getPerPage($request));

        return $this->paginatedResponse($sessions);
    }

    /**
     * Create a new attendance session.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'nullable|string|exists:classes,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'teacher_id' => 'nullable|string|exists:teachers,id',
            'room_id' => 'nullable|string|exists:rooms,id',
            'slot_id' => 'nullable|string|exists:routine_periods,id',
            'attendance_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'session_type' => 'nullable|in:student,teacher,employee',
            'source' => 'nullable|in:manual,routine,biometric,simulator',
            'status' => 'nullable|in:active,completed,cancelled',
        ]);

        $session = $this->engine->createSession($validated);

        return $this->created($session->load(['batch', 'subject', 'teacher']), 'Attendance session created successfully');
    }

    /**
     * Show an attendance session.
     */
    public function show(string $id): JsonResponse
    {
        $session = AttendanceSession::with([
            'batch:id,name',
            'subject:id,name',
            'teacher:id,first_name,last_name',
            'room:id,name',
            'course:id,name',
            'class:id,name',
            'creator:id,name',
            'logs' => function ($q) {
                $q->with('user')->latest();
            },
        ])->withCount('logs')->findOrFail($id);

        return $this->success($session);
    }

    /**
     * Update an attendance session.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::findOrFail($id);

        $validated = $request->validate([
            'class_id' => 'nullable|string|exists:classes,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'batch_id' => 'nullable|string|exists:batches,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'teacher_id' => 'nullable|string|exists:teachers,id',
            'room_id' => 'nullable|string|exists:rooms,id',
            'slot_id' => 'nullable|string|exists:routine_periods,id',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'status' => 'nullable|in:active,completed,cancelled',
        ]);

        $session->update($validated);

        return $this->success($session, 'Attendance session updated successfully');
    }

    /**
     * Close/complete an attendance session.
     */
    public function close(string $id): JsonResponse
    {
        $session = AttendanceSession::findOrFail($id);
        $session->update(['status' => 'completed']);

        return $this->success($session, 'Attendance session closed successfully');
    }

    /**
     * Cancel an attendance session.
     */
    public function cancel(string $id): JsonResponse
    {
        $session = AttendanceSession::findOrFail($id);
        $session->update(['status' => 'cancelled']);

        return $this->success($session, 'Attendance session cancelled successfully');
    }

    /**
     * Detect current session based on routine for a batch (or first of batch_ids).
     */
    public function detectCurrentSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|string',
        ]);

        // Use first batch_id from batch_ids if batch_id not provided
        $batchId = $validated['batch_id'] ?? null;
        if (!$batchId && !empty($validated['batch_ids'])) {
            $ids = array_map('trim', explode(',', $validated['batch_ids']));
            $batchId = $ids[0] ?? null;
        }

        if (!$batchId) {
            return $this->success(null, 'No batch specified');
        }

        $session = $this->engine->detectCurrentSession($batchId);

        if (!$session) {
            return $this->success(null, 'No active session found for this batch');
        }

        return $this->success($session);
    }

    /**
     * Create or resolve an attendance session from a routine slot.
     */
    public function fromRoutine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'required|string|exists:batches,id',
            'routine_id' => 'nullable|string|exists:class_routines,id',
            'at' => 'nullable|date',
        ]);

        try {
            $result = $this->sessionAttendance->ensureFromRoutine(
                $validated['batch_id'],
                isset($validated['at']) ? Carbon::parse($validated['at']) : null,
                $validated['routine_id'] ?? null
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created($result, 'Attendance session ready from routine');
    }

    /**
     * Create or resolve attendance session from an existing class session.
     */
    public function fromClassSession(string $classSessionId): JsonResponse
    {
        $result = $this->sessionAttendance->ensureFromClassSession($classSessionId);

        return $this->success($result, 'Attendance session linked to class session');
    }
}
