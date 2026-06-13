<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\AcademicGroup;
use Modules\Core\app\Http\Controllers\BaseApiController;

class AcademicGroupController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $groups = AcademicGroup::search($request->search)
            ->filter($request->only(['status']))
            ->orderBy('name')
            ->paginate($perPage);
        return $this->paginatedResponse($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:academic_groups,slug',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $group = AcademicGroup::create($validated);
        return $this->created($group);
    }

    public function show(string $id): JsonResponse
    {
        $group = AcademicGroup::find($id);
        if (!$group) return $this->notFound();
        return $this->success($group);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $group = AcademicGroup::find($id);
        if (!$group) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:academic_groups,slug,' . $id,
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $group->update($validated);
        return $this->success($group->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $group = AcademicGroup::find($id);
        if (!$group) return $this->notFound();
        $group->delete();
        return $this->noContent();
    }

    public function listAll(): JsonResponse
    {
        $groups = AcademicGroup::with('subjects')
            ->where('status', 'active')
            ->orderBy('name')
            ->distinct()
            ->get();
        return $this->collectionResponse($groups);
    }

    public function byClass(string $classId): JsonResponse
    {
        $groups = AcademicGroup::whereHas('subjects', function ($q) use ($classId) {
            $q->whereHas('classes', function ($cq) use ($classId) {
                $cq->where('class_id', $classId);
            });
        })
        ->orWhereHas('classSubjects', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        })
        ->where('status', 'active')
        ->orderBy('name')
        ->distinct()
        ->get();

        return $this->collectionResponse($groups);
    }
}
