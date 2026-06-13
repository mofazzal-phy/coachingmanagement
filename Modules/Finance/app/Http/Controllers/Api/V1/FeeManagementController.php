<?php

namespace Modules\Finance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Finance\app\Models\DiscountRule;
use Modules\Finance\app\Models\LateFeeRule;
use Modules\Finance\app\Models\InstallmentPlan;
use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Finance\app\Models\SmartPaymentInvoice;
use Modules\Finance\app\Models\NotificationPreference;
use Modules\Finance\app\Services\FeeManagementService;
use Modules\Finance\app\Services\SmartInvoiceService;
use Modules\Enrollment\app\Models\Enrollment;

class FeeManagementController extends BaseApiController
{
    protected FeeManagementService $feeService;

    public function __construct(FeeManagementService $feeService)
    {
        $this->feeService = $feeService;
    }

    // ==================== STUDENT APIs ====================

    /**
     * Get student's fee dashboard (all enrollments with fee status).
     * GET /api/v1/fee/student/dashboard
     */
    public function studentDashboard(Request $request): JsonResponse
    {
        $request->validate(['student_id' => 'required|string|exists:students,id']);

        $result = $this->feeService->getStudentDashboard($request->student_id);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result);
    }

    /**
     * Get student's complete fee ledger for an enrollment.
     * GET /api/v1/fee/student/ledger/{enrollmentId}
     */
    public function studentLedger(string $enrollmentId): JsonResponse
    {
        $result = $this->feeService->getStudentLedger($enrollmentId);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result);
    }

    /**
     * Student submits a payment (online/gateway).
     * POST /api/v1/fee/student/pay
     */
    public function studentPay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'student_id' => 'nullable|string|exists:students,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank,card,cash',
            'gateway_trx_id' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        // If student_id not provided, derive it from the enrollment
        if (empty($validated['student_id'])) {
            $enrollment = \Modules\Enrollment\app\Models\Enrollment::find($validated['enrollment_id']);
            if (!$enrollment) {
                return $this->notFound('Enrollment not found.');
            }
            $validated['student_id'] = $enrollment->student_id;
        }

        $result = $this->feeService->processPayment($validated);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, 'Payment submitted successfully.');
    }

    /**
     * Get student's payment history.
     * GET /api/v1/fee/student/payments/{enrollmentId}
     */
    public function studentPayments(string $enrollmentId): JsonResponse
    {
        $payments = PaymentTransaction::with(['confirmedBy', 'allocations.feeAssignment'])
            ->where('enrollment_id', $enrollmentId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->paginatedResponse($payments);
    }

    /**
     * Get student's fee assignments for an enrollment.
     * GET /api/v1/fee/student/assignments/{enrollmentId}
     */
    public function studentAssignments(string $enrollmentId): JsonResponse
    {
        $assignments = StudentFeeAssignment::with(['feeStructure.feeType', 'installmentPlan'])
            ->where('enrollment_id', $enrollmentId)
            ->orderBy('due_date')
            ->get();

        // Calculate late fees for overdue items
        $assignments->each(function ($assignment) {
            $assignment->calculated_late_fee = $this->feeService->calculateLateFee($assignment);
        });

        return $this->success([
            'assignments' => $assignments,
            'summary' => [
                'total' => $assignments->sum('original_amount'),
                'total_discounted' => $assignments->sum('final_amount'),
                'total_discount' => $assignments->sum(fn($a) => max(0, $a->original_amount - $a->final_amount)),
                'paid' => $assignments->sum('paid_amount'),
                'due' => $assignments->sum(fn($a) => $a->final_amount - $a->paid_amount),
                'pending' => $assignments->whereIn('status', ['pending', 'partial'])->count(),
                'overdue' => $assignments->whereIn('status', ['pending', 'partial'])
                    ->filter(fn($a) => $a->due_date < now())->count(),
            ],
        ]);
    }

    // ==================== ADMIN APIs ====================

    /**
     * Get admin dashboard summary.
     * GET /api/v1/fee/admin/dashboard
     */
    public function adminDashboard(): JsonResponse
    {
        $result = $this->feeService->getAdminDashboard();
        return $this->success($result);
    }

    /**
     * Get pending payments for admin confirmation.
     * GET /api/v1/fee/admin/pending-payments
     */
    public function pendingPayments(Request $request): JsonResponse
    {
        $filters = $request->only(['payment_method', 'date_from', 'date_to', 'search', 'per_page']);
        $payments = $this->feeService->getPendingPayments($filters);
        return $this->paginatedResponse($payments);
    }

    /**
     * Admin confirms a pending payment.
     * POST /api/v1/fee/admin/confirm-payment/{transactionId}
     */
    public function confirmPayment(string $transactionId): JsonResponse
    {
        $confirmedBy = auth()->id();
        $result = $this->feeService->confirmPayment($transactionId, $confirmedBy);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, 'Payment confirmed successfully.');
    }

    /**
     * Admin rejects a pending payment.
     * POST /api/v1/fee/admin/reject-payment/{transactionId}
     */
    public function rejectPayment(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $rejectedBy = auth()->id();
        $result = $this->feeService->rejectPayment($transactionId, $rejectedBy, $validated['reason']);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, 'Payment rejected.');
    }

    /**
     * Admin records a manual payment (cash/check/bank).
     * POST /api/v1/fee/admin/manual-payment
     */
    public function manualPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'student_id' => 'nullable|string|exists:students,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:cash,check,bank',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        $recordedBy = auth()->id();
        $result = $this->feeService->recordManualPayment($validated, $recordedBy);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, $result['message']);
    }

    /**
     * Manual payment with specific fee assignment allocations.
     * POST /api/v1/fee/admin/manual-payment-with-allocations
     */
    public function manualPaymentWithAllocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'student_id' => 'nullable|string|exists:students,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:cash,check,bank',
            'fee_assignment_ids' => 'nullable|array',
            'fee_assignment_ids.*' => 'string',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        $recordedBy = auth()->id();
        $result = $this->feeService->recordManualPaymentWithAllocations($validated, $recordedBy);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, $result['message']);
    }

    /**
     * Get pending fees for a student (grouped by fee type with category).
     * GET /api/v1/fee/admin/student-pending-fees/{studentId}
     */
    public function studentPendingFees(string $studentId, Request $request): JsonResponse
    {
        $enrollmentId = $request->query('enrollment_id');
        $result = $this->feeService->getStudentPendingFees($studentId, $enrollmentId);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result);
    }

    /**
     * Assign fees to an enrollment (triggered after enrollment creation).
     * POST /api/v1/fee/admin/assign-fees
     */
    public function assignFees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'installment_plan_id' => 'nullable|string|exists:installment_plans,id',
        ]);

        $enrollment = Enrollment::find($validated['enrollment_id']);
        $result = $this->feeService->assignFeesToEnrollment(
            $enrollment,
            $validated['installment_plan_id'] ?? null
        );

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, 'Fees assigned successfully.');
    }

    /**
     * Get all fee assignments with filters.
     * GET /api/v1/fee/admin/assignments
     */
    public function allAssignments(Request $request): JsonResponse
    {
        $filters = $request->only(['enrollment_id', 'status', 'overdue', 'per_page']);
        $assignments = $this->feeService->getFeeAssignments($filters);
        return $this->paginatedResponse($assignments);
    }

    /**
     * Get all confirmed payments.
     * GET /api/v1/fee/admin/confirmed-payments
     */
    public function confirmedPayments(Request $request): JsonResponse
    {
        $query = PaymentTransaction::with(['enrollment.student', 'confirmedBy'])
            ->where('status', 'confirmed')
            ->orderBy('confirmed_at', 'desc');

        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->date_from) {
            $query->whereDate('confirmed_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('confirmed_at', '<=', $request->date_to);
        }

        $perPage = $request->per_page ?? 15;
        return $this->paginatedResponse($query->paginate($perPage));
    }

    /**
     * Get all rejected payments.
     * GET /api/v1/fee/admin/rejected-payments
     */
    public function rejectedPayments(Request $request): JsonResponse
    {
        $query = PaymentTransaction::with(['enrollment.student', 'confirmedBy'])
            ->where('status', 'rejected')
            ->orderBy('confirmed_at', 'desc');

        $perPage = $request->per_page ?? 15;
        return $this->paginatedResponse($query->paginate($perPage));
    }

    // ==================== DISCOUNT RULE APIs ====================

    /**
     * List all discount rules.
     * GET /api/v1/fee/admin/discount-rules
     */
    public function discountRules(): JsonResponse
    {
        return $this->collectionResponse(
            DiscountRule::orderBy('priority')->get()
        );
    }

    /**
     * Create a discount rule.
     * POST /api/v1/fee/admin/discount-rules
     */
    public function storeDiscountRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'condition_type' => 'required|string|in:early_bird,sibling,loyalty,merit,bulk,need_based,custom',
            'condition_config' => 'nullable|array',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'priority' => 'required|integer|min:0',
            'stackable' => 'boolean',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $validated['condition_config'] = !empty($validated['condition_config'])
            ? json_encode($validated['condition_config'])
            : json_encode([]);
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['created_by'] = auth()->id();

        $rule = DiscountRule::create($validated);
        return $this->created($rule, 'Discount rule created.');
    }

    /**
     * Update a discount rule.
     * PUT /api/v1/fee/admin/discount-rules/{id}
     */
    public function updateDiscountRule(Request $request, string $id): JsonResponse
    {
        $rule = DiscountRule::find($id);
        if (!$rule) {
            return $this->error('Discount rule not found.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'condition_type' => 'sometimes|string|in:early_bird,sibling,loyalty,merit,bulk,need_based,custom',
            'condition_config' => 'nullable|array',
            'discount_type' => 'sometimes|string|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'priority' => 'sometimes|integer|min:0',
            'stackable' => 'boolean',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        if (isset($validated['condition_config'])) {
            $validated['condition_config'] = json_encode($validated['condition_config']);
        }

        $rule->update($validated);
        return $this->success($rule, 'Discount rule updated.');
    }

    /**
     * Delete a discount rule.
     * DELETE /api/v1/fee/admin/discount-rules/{id}
     */
    public function destroyDiscountRule(string $id): JsonResponse
    {
        $rule = DiscountRule::find($id);
        if (!$rule) {
            return $this->error('Discount rule not found.', 404);
        }

        $rule->delete();
        return $this->success(null, 'Discount rule deleted.');
    }

    // ==================== LATE FEE RULE APIs ====================

    /**
     * List all late fee rules.
     * GET /api/v1/fee/admin/late-fee-rules
     */
    public function lateFeeRules(): JsonResponse
    {
        return $this->collectionResponse(LateFeeRule::all());
    }

    /**
     * Create a late fee rule.
     * POST /api/v1/fee/admin/late-fee-rules
     */
    public function storeLateFeeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'calculation_type' => 'required|string|in:flat_per_day,percentage_per_day,tiered',
            'flat_rate' => 'nullable|numeric|min:0',
            'percentage_rate' => 'nullable|numeric|min:0|max:100',
            'tier_config' => 'nullable|array',
            'grace_period_days' => 'required|integer|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'recurring' => 'boolean',
            'is_active' => 'boolean',
            'applies_to' => 'nullable|string',
        ]);

        $rule = LateFeeRule::create($validated);
        return $this->created($rule, 'Late fee rule created.');
    }

    /**
     * Update a late fee rule.
     * PUT /api/v1/fee/admin/late-fee-rules/{id}
     */
    public function updateLateFeeRule(Request $request, string $id): JsonResponse
    {
        $rule = LateFeeRule::find($id);
        if (!$rule) {
            return $this->error('Late fee rule not found.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'calculation_type' => 'sometimes|string|in:flat_per_day,percentage_per_day,tiered',
            'flat_rate' => 'nullable|numeric|min:0',
            'percentage_rate' => 'nullable|numeric|min:0|max:100',
            'tier_config' => 'nullable|array',
            'grace_period_days' => 'sometimes|integer|min:0',
            'max_cap' => 'nullable|numeric|min:0',
            'recurring' => 'boolean',
            'is_active' => 'boolean',
            'applies_to' => 'nullable|string',
        ]);

        $rule->update($validated);
        return $this->success($rule, 'Late fee rule updated.');
    }

    /**
     * Delete a late fee rule.
     * DELETE /api/v1/fee/admin/late-fee-rules/{id}
     */
    public function destroyLateFeeRule(string $id): JsonResponse
    {
        $rule = LateFeeRule::find($id);
        if (!$rule) {
            return $this->error('Late fee rule not found.', 404);
        }

        $rule->delete();
        return $this->success(null, 'Late fee rule deleted.');
    }

    // ==================== INSTALLMENT PLAN APIs ====================

    /**
     * List all installment plans.
     * GET /api/v1/fee/admin/installment-plans
     */
    public function installmentPlans(): JsonResponse
    {
        return $this->collectionResponse(
            InstallmentPlan::with('lateFeeRule')->get()
        );
    }

    /**
     * Create an installment plan.
     * POST /api/v1/fee/admin/installment-plans
     */
    public function storeInstallmentPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'required|string|in:equal,custom,percentage',
            'total_installments' => 'required|integer|min:2|max:24',
            'frequency_days' => 'required|integer|min:7|max:365',
            'config' => 'nullable|array',
            'late_fee_rule_id' => 'nullable|string|exists:late_fee_rules,id',
            'is_active' => 'boolean',
        ]);

        $plan = InstallmentPlan::create($validated);
        return $this->created($plan, 'Installment plan created.');
    }

    /**
     * Update an installment plan.
     * PUT /api/v1/fee/admin/installment-plans/{id}
     */
    public function updateInstallmentPlan(Request $request, string $id): JsonResponse
    {
        $plan = InstallmentPlan::find($id);
        if (!$plan) {
            return $this->error('Installment plan not found.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'sometimes|string|in:equal,custom,percentage',
            'total_installments' => 'sometimes|integer|min:2|max:24',
            'frequency_days' => 'sometimes|integer|min:7|max:365',
            'config' => 'nullable|array',
            'late_fee_rule_id' => 'nullable|string|exists:late_fee_rules,id',
            'is_active' => 'boolean',
        ]);

        $plan->update($validated);
        return $this->success($plan, 'Installment plan updated.');
    }

    /**
     * Delete an installment plan.
     * DELETE /api/v1/fee/admin/installment-plans/{id}
     */
    public function destroyInstallmentPlan(string $id): JsonResponse
    {
        $plan = InstallmentPlan::find($id);
        if (!$plan) {
            return $this->error('Installment plan not found.', 404);
        }

        $plan->delete();
        return $this->success(null, 'Installment plan deleted.');
    }

    // ==================== NOTIFICATION PREFERENCE APIs ====================

    /**
     * Get notification preferences for a student.
     * GET /api/v1/fee/student/notification-preferences/{studentId}
     */
    public function getNotificationPreferences(string $studentId): JsonResponse
    {
        $prefs = NotificationPreference::where('student_id', $studentId)->first();

        if (!$prefs) {
            // Return defaults
            return $this->success([
                'student_id' => $studentId,
                'sms_enabled' => true,
                'email_enabled' => true,
                'due_reminder' => true,
                'overdue_alert' => true,
                'payment_confirmation' => true,
                'payment_rejection' => true,
                'installment_reminder' => true,
            ]);
        }

        return $this->success($prefs);
    }

    /**
     * Update notification preferences.
     * PUT /api/v1/fee/student/notification-preferences/{studentId}
     */
    public function updateNotificationPreferences(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'sms_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'due_reminder' => 'boolean',
            'overdue_alert' => 'boolean',
            'payment_confirmation' => 'boolean',
            'payment_rejection' => 'boolean',
            'installment_reminder' => 'boolean',
            'sms_phone' => 'nullable|string|max:20',
            'email_address' => 'nullable|email|max:255',
        ]);

        $prefs = NotificationPreference::updateOrCreate(
            ['student_id' => $studentId],
            $validated
        );

        return $this->success($prefs, 'Notification preferences updated.');
    }

    // ==================== AUDIT LOG APIs ====================

    /**
     * Get audit logs for a specific entity.
     * GET /api/v1/fee/admin/audit-logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = \Modules\Finance\app\Models\FeeAuditLog::with('performer')
            ->orderBy('created_at', 'desc');

        if ($request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->entity_id) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->action) {
            $query->where('action', $request->action);
        }

        $perPage = $request->per_page ?? 15;
        return $this->paginatedResponse($query->paginate($perPage));
    }

    // ==================== INVOICE APIs ====================

    /**
     * Get invoice by transaction ID.
     * GET /api/v1/fee/student/invoice/{transactionId}
     */
    public function getInvoiceByTransaction(string $transactionId): JsonResponse
    {
        $invoiceService = app(SmartInvoiceService::class);
        $invoice = $invoiceService->getInvoiceByTransaction($transactionId);

        if (!$invoice) {
            return $this->notFound('No invoice found for this transaction.');
        }

        $invoice->load(['paymentTransaction', 'generator']);
        return $this->success($invoice);
    }

    /**
     * Download invoice PDF by invoice ID.
     * GET /api/v1/fee/student/invoice/{invoiceId}/download
     */
    public function downloadInvoice(string $invoiceId)
    {
        $invoiceService = app(SmartInvoiceService::class);
        return $invoiceService->downloadInvoice($invoiceId);
    }

    /**
     * Student: Get invoices for an enrollment, with allocations eager-loaded.
     * GET /api/v1/fee/student/invoices/{enrollmentId}
     */
    public function studentInvoices(string $enrollmentId): JsonResponse
    {
        $invoices = SmartPaymentInvoice::whereHas('paymentTransaction', function ($q) use ($enrollmentId) {
            $q->where('enrollment_id', $enrollmentId)
              ->where('status', 'confirmed');
        })
        ->with([
            'paymentTransaction.allocations.feeAssignment',
            'generator',
        ])
        ->orderBy('generated_at', 'desc')
        ->get();

        return $this->success($invoices);
    }

    /**
     * Admin: Get all invoices with filters.
     * GET /api/v1/fee/admin/invoices
     */
    public function allInvoices(Request $request): JsonResponse
    {
        $query = SmartPaymentInvoice::with([
            'paymentTransaction.enrollment.student',
            'paymentTransaction.confirmedBy',
            'generator',
        ])->orderBy('created_at', 'desc');

        if ($request->invoice_type) {
            $query->where('invoice_type', $request->invoice_type);
        }

        if ($request->date_from) {
            $query->whereDate('generated_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('generated_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('paymentTransaction', function ($sq) use ($request) {
                      $sq->where('transaction_no', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $perPage = $request->per_page ?? 15;
        return $this->paginatedResponse($query->paginate($perPage));
    }

    /**
     * Admin: Generate invoice for a confirmed transaction (re-generate if missing).
     * POST /api/v1/fee/admin/generate-invoice/{transactionId}
     */
    public function generateInvoice(string $transactionId): JsonResponse
    {
        $transaction = PaymentTransaction::find($transactionId);
        if (!$transaction) {
            return $this->notFound('Transaction not found.');
        }

        if ($transaction->status !== 'confirmed') {
            return $this->error('Invoice can only be generated for confirmed payments.', 400);
        }

        $invoiceService = app(SmartInvoiceService::class);
        $invoice = $invoiceService->generateInvoice($transaction);

        if (!$invoice) {
            return $this->error('Failed to generate invoice.');
        }

        $invoice->load(['paymentTransaction', 'generator']);
        return $this->created($invoice, 'Invoice generated successfully.');
    }
}
