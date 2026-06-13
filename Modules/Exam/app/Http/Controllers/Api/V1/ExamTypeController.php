<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Exam\app\Models\ExamType;
use Modules\Core\app\Http\Controllers\BaseApiController;

class ExamTypeController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->collectionResponse(ExamType::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:exam_types,code',
            'description' => 'nullable|string',
        ]);

        return $this->created(ExamType::create($validated));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $type = ExamType::find($id);
        if (!$type) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:exam_types,code,' . $id,
            'description' => 'nullable|string',
        ]);

        $type->update($validated);
        return $this->success($type->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $type = ExamType::find($id);
        if (!$type) return $this->notFound();
        $type->delete();
        return $this->noContent();
    }
}
