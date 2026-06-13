<?php

namespace Modules\Finance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\app\Models\Expense;
use Modules\Finance\app\Models\ExpenseCategory;
use Modules\Core\app\Http\Controllers\BaseApiController;

class ExpenseController extends BaseApiController
{
    // === Expense Categories ===
    public function categories(): JsonResponse
    {
        return $this->collectionResponse(ExpenseCategory::withCount('expenses')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:expense_categories,code',
            'description' => 'nullable|string',
        ]);
        return $this->created(ExpenseCategory::create($validated));
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $cat = ExpenseCategory::find($id);
        if (!$cat) return $this->notFound();
        $cat->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:expense_categories,code,' . $id,
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($cat->fresh());
    }

    public function destroyCategory(string $id): JsonResponse
    {
        $cat = ExpenseCategory::find($id);
        if (!$cat) return $this->notFound();
        $cat->delete();
        return $this->noContent();
    }

    // === Expenses ===
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $expenses = Expense::with('category')
            ->search($request->search)
            ->filter($request->only(['expense_category_id', 'payment_method']))
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|string|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'voucher_no' => 'nullable|string',
            'payment_method' => 'sometimes|in:cash,bank,mobile_banking',
        ]);

        $validated['created_by'] = auth()->id();
        return $this->created(Expense::create($validated)->load('category'));
    }

    public function show(string $id): JsonResponse
    {
        $expense = Expense::with('category')->find($id);
        if (!$expense) return $this->notFound();
        return $this->success($expense);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (!$expense) return $this->notFound();

        $expense->update($request->validate([
            'expense_category_id' => 'sometimes|string|exists:expense_categories,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'expense_date' => 'sometimes|date',
            'voucher_no' => 'nullable|string',
            'payment_method' => 'sometimes|in:cash,bank,mobile_banking',
        ]));

        return $this->success($expense->fresh('category'));
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (!$expense) return $this->notFound();
        $expense->delete();
        return $this->noContent();
    }
}
