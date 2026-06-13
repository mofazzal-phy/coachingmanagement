<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\Classes;
use Modules\Core\app\Http\Controllers\BaseApiController;

class ClassController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $classes = Classes::with(['sections', 'subjects'])
            ->search($request->search)
            ->filter($request->only(['status']))
            ->orderBy('numeric_value')
            ->paginate($perPage);
        return $this->paginatedResponse($classes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:classes,code',
            'numeric_value' => 'required|integer|min:1|max:12',
            'type' => 'sometimes|in:boys,girls,common',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $class = Classes::create($validated);
        return $this->created($class->load(['sections', 'subjects']));
    }

    public function show(string $id): JsonResponse
    {
        $class = Classes::with(['sections', 'subjects', 'teachers'])->find($id);
        if (!$class) return $this->notFound();
        return $this->success($class);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $class = Classes::find($id);
        if (!$class) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:classes,code,' . $id,
            'numeric_value' => 'sometimes|integer|min:1|max:12',
            'type' => 'sometimes|in:boys,girls,common',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $class->update($validated);
        return $this->success($class->fresh(['sections', 'subjects']));
    }

    public function destroy(string $id): JsonResponse
    {
        $class = Classes::find($id);
        if (!$class) return $this->notFound();
        $class->delete();
        return $this->noContent();
    }

    public function assignSubjects(Request $request, string $id): JsonResponse
    {
        $class = Classes::find($id);
        if (!$class) return $this->notFound();

        $validated = $request->validate([
            'subjects' => 'required|array',
            'subjects.*.id' => 'required|string|exists:subjects,id',
            'subjects.*.total_marks' => 'sometimes|integer|min:1',
            'subjects.*.pass_marks' => 'sometimes|integer|min:1',
        ]);

        $syncData = [];
        foreach ($validated['subjects'] as $subject) {
            $syncData[$subject['id']] = [
                'total_marks' => $subject['total_marks'] ?? 100,
                'pass_marks' => $subject['pass_marks'] ?? 33,
            ];
        }

        $class->subjects()->sync($syncData);
        return $this->success($class->load('subjects'), 'Subjects assigned successfully');
    }

    public function listAll(): JsonResponse
    {
        $classes = Classes::where('status', 'active')
            ->with('sections')
            ->orderBy('numeric_value')
            ->get();
        return $this->collectionResponse($classes);
    }
}
