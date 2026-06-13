<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Core\app\Http\Controllers\BaseApiController;

class AcademicSessionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $sessions = AcademicSession::search($request->search)
            ->filter($request->only(['status', 'is_current']))
            ->paginate($perPage);
        return $this->paginatedResponse($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validated['is_current'] ?? false) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
        }

        $session = AcademicSession::create($validated);
        return $this->created($session);
    }

    public function show(string $id): JsonResponse
    {
        $session = AcademicSession::find($id);
        if (!$session) return $this->notFound();
        return $this->success($session);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $session = AcademicSession::find($id);
        if (!$session) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_current' => 'boolean',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if (($validated['is_current'] ?? false) && !$session->is_current) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
        }

        $session->update($validated);
        return $this->success($session->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $session = AcademicSession::find($id);
        if (!$session) return $this->notFound();
        $session->delete();
        return $this->noContent();
    }

    public function current(): JsonResponse
    {
        $session = AcademicSession::where('is_current', true)->first();
        if (!$session) return $this->notFound('No current session set');
        return $this->success($session);
    }
}
