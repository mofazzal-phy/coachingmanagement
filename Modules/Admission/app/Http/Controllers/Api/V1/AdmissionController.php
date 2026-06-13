<?php

namespace Modules\Admission\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admission\app\Models\Admission;
use Modules\Core\app\Http\Controllers\BaseApiController;

class AdmissionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $admissions = Admission::with(['applyingClass', 'applyingSession'])
            ->search($request->search)
            ->filter($request->only(['status', 'applying_class_id']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($admissions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'applying_class_id' => 'required|string|exists:classes,id',
            'applying_session_id' => 'required|string|exists:academic_sessions,id',
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'father_name' => 'required|string|max:255',
            'father_phone' => 'required|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_name' => 'required|string|max:255',
            'mother_phone' => 'required|string|max:20',
            'mother_occupation' => 'nullable|string|max:255',
            'documents' => 'nullable|array',
            'remarks' => 'nullable|string',
        ]);

        $validated['admission_no'] = 'ADM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        return $this->created(Admission::create($validated));
    }

    public function show(string $id): JsonResponse
    {
        $admission = Admission::with(['applyingClass', 'applyingSession'])->find($id);
        if (!$admission) return $this->notFound();
        return $this->success($admission);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $admission = Admission::find($id);
        if (!$admission) return $this->notFound();

        $admission->update($request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date',
            'gender' => 'sometimes|in:male,female,other',
            'email' => 'nullable|email',
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'applying_class_id' => 'sometimes|string|exists:classes,id',
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'father_name' => 'sometimes|string|max:255',
            'father_phone' => 'sometimes|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_name' => 'sometimes|string|max:255',
            'mother_phone' => 'sometimes|string|max:20',
            'mother_occupation' => 'nullable|string|max:255',
            'documents' => 'nullable|array',
            'remarks' => 'nullable|string',
        ]));

        return $this->success($admission->fresh(['applyingClass', 'applyingSession']));
    }

    public function destroy(string $id): JsonResponse
    {
        $admission = Admission::find($id);
        if (!$admission) return $this->notFound();
        $admission->delete();
        return $this->noContent();
    }

    public function approve(string $id): JsonResponse
    {
        $admission = Admission::find($id);
        if (!$admission) return $this->notFound();
        $admission->update(['status' => 'approved', 'reviewed_by' => auth()->id()]);
        return $this->success($admission->fresh(), 'Admission approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $admission = Admission::find($id);
        if (!$admission) return $this->notFound();
        $admission->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $request->input('reason', ''),
        ]);
        return $this->success($admission->fresh(), 'Admission rejected');
    }

    public function enroll(string $id): JsonResponse
    {
        $admission = Admission::find($id);
        if (!$admission) return $this->notFound();
        $admission->update(['status' => 'enrolled', 'reviewed_by' => auth()->id()]);
        return $this->success($admission->fresh(), 'Student enrolled successfully');
    }
}
