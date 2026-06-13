<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Models\ClassSession;
use Modules\Attendance\app\Services\ClassSessionService;

class ClassSessionController extends BaseApiController
{
    public function __construct(
        protected ClassSessionService $classSessionService
    ) {}

    /**
     * List class sessions (scheduled classes).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassSession::with([
            'batch:id,name',
            'subject:id,name',
            'teacher:id,first_name,last_name',
            'room:id,name',
            'attendanceSession:id,status,scheduled_status',
        ]);

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

        if ($request->filled('date')) {
            $query->where('session_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->where('session_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('session_date', '<=', $request->date_to);
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('start_time')
            ->paginate($this->getPerPage($request));

        return $this->paginatedResponse($sessions);
    }

    /**
     * Show a class session.
     */
    public function show(string $id): JsonResponse
    {
        $session = ClassSession::with([
            'batch:id,name',
            'subject:id,name',
            'teacher:id,first_name,last_name',
            'room:id,name',
            'routine',
            'attendanceSession',
        ])->findOrFail($id);

        return $this->success($session);
    }

    /**
     * Sync class sessions from published routines for a date.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|array',
            'batch_ids.*' => 'string|exists:batches,id',
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString());
        $batchIds = $validated['batch_ids'] ?? null;

        if (!empty($validated['batch_id'])) {
            $batchIds = array_merge($batchIds ?? [], [$validated['batch_id']]);
        }

        $result = $this->classSessionService->syncForDate($date, $batchIds);

        return $this->success([
            'date' => $date->toDateString(),
            'created' => $result['created'],
            'updated' => $result['updated'],
            'total' => $result['total'],
        ], 'Class sessions synced from routine');
    }

    /**
     * Update class session status (complete, cancel, in_progress).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,in_progress,completed,cancelled,rescheduled',
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $session = ClassSession::findOrFail($id);
        $session = $this->classSessionService->updateStatus(
            $session,
            $validated['status'],
            $validated['cancel_reason'] ?? null
        );

        if ($session->attendance_session_id) {
            $session->attendanceSession?->update([
                'scheduled_status' => $validated['status'],
                'status' => $validated['status'] === 'completed' ? 'completed'
                    : ($validated['status'] === 'cancelled' ? 'cancelled' : 'active'),
                'cancel_reason' => $validated['cancel_reason'] ?? null,
            ]);
        }

        return $this->success($session->load(['batch', 'subject', 'teacher']), 'Class session status updated');
    }
}
