<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\AcademicGroup;
use Modules\Core\app\Http\Controllers\BaseApiController;

class SubjectController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $subjects = Subject::with(['classes', 'groups'])
            ->search($request->search)
            ->filter($request->only(['status', 'type']))
            ->paginate($perPage);
        return $this->paginatedResponse($subjects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:subjects,code',
            'type' => 'sometimes|in:core,elective,optional',
            'credit_hours' => 'sometimes|integer|min:0|max:20',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'group_ids' => 'sometimes|array',
            'group_ids.*' => 'exists:academic_groups,id',
        ]);

        $subject = Subject::create($validated);

        if ($request->has('group_ids')) {
            $subject->groups()->sync($request->group_ids);
        }

        return $this->created($subject->load(['classes', 'groups']));
    }

    public function show(string $id): JsonResponse
    {
        $subject = Subject::with(['classes', 'groups'])->find($id);
        if (!$subject) return $this->notFound();
        return $this->success($subject);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $subject = Subject::find($id);
        if (!$subject) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:subjects,code,' . $id,
            'type' => 'sometimes|in:core,elective,optional',
            'credit_hours' => 'sometimes|integer|min:0|max:20',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'group_ids' => 'sometimes|array',
            'group_ids.*' => 'exists:academic_groups,id',
        ]);

        $subject->update($validated);

        if ($request->has('group_ids')) {
            $subject->groups()->sync($request->group_ids);
        }

        return $this->success($subject->fresh(['classes', 'groups']));
    }

    public function destroy(string $id): JsonResponse
    {
        $subject = Subject::find($id);
        if (!$subject) return $this->notFound();
        $subject->delete();
        return $this->noContent();
    }

    public function listAll(): JsonResponse
    {
        $subjects = Subject::where('status', 'active')
            ->with('classes')
            ->get();
        return $this->collectionResponse($subjects);
    }

    public function byClass(string $classId, Request $request): JsonResponse
    {
        $query = Subject::whereHas('classes', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });

        // Filter by group if provided
        if ($request->filled('group_id')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('academic_groups.id', $request->group_id);
            });
        }

        $subjects = $query->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $this->collectionResponse($subjects);
    }

    public function byCourse(string $courseId, Request $request): JsonResponse
    {
        $query = Subject::whereHas('courses', function ($q) use ($courseId) {
            $q->where('course_id', $courseId);
        });

        // Filter by group if provided
        if ($request->filled('group_id')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('academic_groups.id', $request->group_id);
            });
        }

        $subjects = $query->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $this->collectionResponse($subjects);
    }

    public function assignGroups(Request $request, string $id): JsonResponse
    {
        $subject = Subject::find($id);
        if (!$subject) return $this->notFound();

        $validated = $request->validate([
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:academic_groups,id',
        ]);

        $subject->groups()->sync($validated['group_ids']);

        return $this->success($subject->load('groups'), 'Groups assigned successfully');
    }
}
