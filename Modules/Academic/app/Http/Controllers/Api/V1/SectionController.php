<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\Section;
use Modules\Core\app\Http\Controllers\BaseApiController;

class SectionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $sections = Section::with('class')
            ->search($request->search)
            ->filter($request->only(['status', 'class_id']))
            ->paginate($perPage);
        return $this->paginatedResponse($sections);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|string|exists:classes,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:sections,code',
            'capacity' => 'sometimes|integer|min:1|max:200',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $section = Section::create($validated);
        return $this->created($section->load('class'));
    }

    public function show(string $id): JsonResponse
    {
        $section = Section::with('class')->find($id);
        if (!$section) return $this->notFound();
        return $this->success($section);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $section = Section::find($id);
        if (!$section) return $this->notFound();

        $validated = $request->validate([
            'class_id' => 'sometimes|string|exists:classes,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:sections,code,' . $id,
            'capacity' => 'sometimes|integer|min:1|max:200',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $section->update($validated);
        return $this->success($section->fresh('class'));
    }

    public function destroy(string $id): JsonResponse
    {
        $section = Section::find($id);
        if (!$section) return $this->notFound();
        $section->delete();
        return $this->noContent();
    }

    public function byClass(string $classId): JsonResponse
    {
        $sections = Section::where('class_id', $classId)
            ->where('status', 'active')
            ->get();
        return $this->collectionResponse($sections);
    }
}
