<?php

namespace Modules\Finance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\app\Models\FeeType;
use Modules\Finance\app\Models\FeeStructure;
use Modules\Finance\app\Models\FeeCollection;
use Modules\Core\app\Http\Controllers\BaseApiController;

class FeeController extends BaseApiController
{
    // === Fee Types ===
    public function types(): JsonResponse
    {
        return $this->collectionResponse(FeeType::all());
    }

    public function showType(string $id): JsonResponse
    {
        $type = FeeType::find($id);
        if (!$type) return $this->notFound();
        return $this->success($type);
    }

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:fee_types,code',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:one_time,monthly,event_based',
        ]);
        return $this->created(FeeType::create($validated));
    }

    public function updateType(Request $request, string $id): JsonResponse
    {
        $type = FeeType::find($id);
        if (!$type) return $this->notFound();
        $type->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:fee_types,code,' . $id,
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:one_time,monthly,event_based',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($type->fresh());
    }

    public function destroyType(string $id): JsonResponse
    {
        $type = FeeType::find($id);
        if (!$type) return $this->notFound();
        $type->delete();
        return $this->noContent();
    }

    // === Fee Structures ===
    public function structures(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $structures = FeeStructure::with(['feeType', 'class'])
            ->filter($request->only(['class_id', 'academic_session_id', 'fee_type_id']))
            ->paginate($perPage);
        return $this->paginatedResponse($structures);
    }

    public function showStructure(string $id): JsonResponse
    {
        $structure = FeeStructure::with(['feeType', 'class'])->find($id);
        if (!$structure) return $this->notFound();
        return $this->success($structure);
    }

    public function storeStructure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'class_id' => 'required|string|exists:classes,id',
            'fee_type_id' => 'required|string|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'due_date' => 'nullable|date',
            'event_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        // Check if a fee structure already exists for this session/class/fee_type combination
        // The unique constraint (fee_structure_unique) prevents duplicates, so we find or update.
        $structure = FeeStructure::where([
            'academic_session_id' => $validated['academic_session_id'],
            'class_id' => $validated['class_id'],
            'fee_type_id' => $validated['fee_type_id'],
        ])->first();

        if ($structure) {
            $structure->update($validated);
            return $this->success($structure->fresh(['feeType', 'class']));
        }

        return $this->created(FeeStructure::create($validated)->load(['feeType', 'class']));
    }

    public function updateStructure(Request $request, string $id): JsonResponse
    {
        $structure = FeeStructure::find($id);
        if (!$structure) return $this->notFound();
        $structure->update($request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'due_date' => 'nullable|date',
            'event_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($structure->fresh(['feeType', 'class']));
    }

    public function destroyStructure(string $id): JsonResponse
    {
        $structure = FeeStructure::find($id);
        if (!$structure) return $this->notFound();
        $structure->delete();
        return $this->noContent();
    }

    // === Fee Collections ===
    public function collections(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $collections = FeeCollection::with(['student', 'feeType'])
            ->filter($request->only(['status', 'student_id', 'fee_type_id', 'payment_method']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($collections);
    }

    public function collectFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'fee_type_id' => 'required|string|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'paid_date' => 'nullable|date',
            'payment_method' => 'sometimes|in:cash,bank,mobile_banking,card,online',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $validated['invoice_no'] = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $validated['due_amount'] = $validated['amount'] - $validated['discount'] - $validated['paid_amount'];
        $validated['status'] = $validated['due_amount'] <= 0 ? 'paid' : ($validated['paid_amount'] > 0 ? 'partial' : 'unpaid');
        $validated['collected_by'] = auth()->id();

        return $this->created(FeeCollection::create($validated)->load(['student', 'feeType']));
    }

    public function showCollection(string $id): JsonResponse
    {
        $collection = FeeCollection::with(['student', 'feeType'])->find($id);
        if (!$collection) return $this->notFound();
        return $this->success($collection);
    }

    public function updateCollection(Request $request, string $id): JsonResponse
    {
        $collection = FeeCollection::find($id);
        if (!$collection) return $this->notFound();
        $collection->update($request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'paid_amount' => 'sometimes|numeric|min:0',
            'paid_date' => 'nullable|date',
            'payment_method' => 'sometimes|in:cash,bank,mobile_banking,card,online',
            'transaction_id' => 'nullable|string',
            'status' => 'sometimes|in:paid,partial,pending,overdue',
            'remarks' => 'nullable|string',
        ]));
        return $this->success($collection->fresh(['student', 'feeType']));
    }

    public function destroyCollection(string $id): JsonResponse
    {
        $collection = FeeCollection::find($id);
        if (!$collection) return $this->notFound();
        $collection->delete();
        return $this->noContent();
    }

    public function studentDue(string $studentId): JsonResponse
    {
        $dues = FeeCollection::where('student_id', $studentId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->with('feeType')
            ->get();
        return $this->collectionResponse($dues);
    }

    public function summary(): JsonResponse
    {
        $totalCollected = FeeCollection::whereIn('status', ['paid', 'partial'])->sum('paid_amount');
        $totalDue = FeeCollection::whereIn('status', ['unpaid', 'partial', 'overdue'])->sum('due_amount');
        $totalDiscount = FeeCollection::sum('discount');

        return $this->success([
            'total_collected' => (float) $totalCollected,
            'total_due' => (float) $totalDue,
            'total_discount' => (float) $totalDiscount,
            'paid_count' => FeeCollection::where('status', 'paid')->count(),
            'partial_count' => FeeCollection::where('status', 'partial')->count(),
            'unpaid_count' => FeeCollection::where('status', 'unpaid')->count(),
        ]);
    }
}
