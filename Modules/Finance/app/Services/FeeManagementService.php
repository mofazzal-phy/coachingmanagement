<?php

namespace Modules\Finance\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Finance\app\Models\DiscountRule;
use Modules\Finance\app\Models\LateFeeRule;
use Modules\Finance\app\Models\InstallmentPlan;
use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Finance\app\Models\PaymentAllocation;
use Modules\Finance\app\Models\FeeAuditLog;
use Modules\Finance\app\Models\FeeStructure;
use Modules\Finance\app\Models\NotificationPreference;
use Modules\Student\app\Models\Student;

class FeeManagementService
{
    /**
     * Smart Fee Engine: Assign fee structures to an enrollment.
     * Applies discounts, late fee rules, and installment plans automatically.
     * Supports 3 fee categories:
     *   - one_time: Single assignment at enrollment
     *   - monthly: One assignment per month of course duration
     *   - event_based: Single assignment with absolute due_date from FeeStructure
     */
    public function assignFeesToEnrollment(Enrollment $enrollment, ?string $installmentPlanId = null): array
    {
        $feeStructures = FeeStructure::where('class_id', $enrollment->enrolled_class_id)
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('status', 'active')
            ->with('feeType')
            ->get();

        if ($feeStructures->isEmpty()) {
            return ['success' => false, 'message' => 'No fee structures found for this class/session.'];
        }

        $assignments = [];
        $student = $enrollment->student;

        DB::beginTransaction();
        try {
            foreach ($feeStructures as $feeStructure) {
                $feeType = $feeStructure->feeType;
                $category = $feeType?->category ?? 'monthly';

                $newAssignments = match ($category) {
                    'one_time' => $this->generateOneTimeAssignment($feeStructure, $enrollment, $student, $installmentPlanId),
                    'monthly' => $this->generateMonthlyAssignments($feeStructure, $enrollment, $student, $installmentPlanId),
                    'event_based' => $this->generateEventBasedAssignment($feeStructure, $enrollment, $student, $installmentPlanId),
                    default => $this->generateOneTimeAssignment($feeStructure, $enrollment, $student, $installmentPlanId),
                };

                foreach ($newAssignments as $assignment) {
                    $assignments[] = $assignment;
                }
            }

            // If installment plan, generate installment records from all assignments
            if ($installmentPlanId && !empty($assignments)) {
                $this->generateInstallments($enrollment, $assignments, $installmentPlanId);
            }

            DB::commit();
            return ['success' => true, 'assignments' => $assignments];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fee assignment failed: ' . $e->getMessage(), [
                'enrollment_id' => $enrollment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Fee assignment failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate a single one-time fee assignment (e.g., admission fee, registration fee).
     */
    private function generateOneTimeAssignment(FeeStructure $feeStructure, Enrollment $enrollment, Student $student, ?string $installmentPlanId): array
    {
        $originalAmount = (float) $feeStructure->amount;
        $discountResult = $this->applyDiscounts($student, $enrollment, $feeStructure, $originalAmount);
        $discountedAmount = $discountResult['discounted_amount'];
        $appliedDiscounts = $discountResult['applied_discounts'];

        $dueDate = $this->calculateDueDate($feeStructure, $enrollment, 0);
        $periodMonth = $enrollment->start_date
            ? \Carbon\Carbon::parse($enrollment->start_date)->format('Y-m')
            : $dueDate->format('Y-m');

        $assignment = $this->createAssignment(
            $enrollment, $feeStructure, $originalAmount, $discountedAmount,
            $dueDate, $periodMonth, $installmentPlanId, $appliedDiscounts
        );

        return [$assignment];
    }

    /**
     * Generate monthly fee assignments for the full course duration.
     * Creates one assignment per month from enrollment start_date.
     */
    private function generateMonthlyAssignments(FeeStructure $feeStructure, Enrollment $enrollment, Student $student, ?string $installmentPlanId): array
    {
        $originalAmount = (float) $feeStructure->amount;
        $discountResult = $this->applyDiscounts($student, $enrollment, $feeStructure, $originalAmount);
        $discountedAmount = $discountResult['discounted_amount'];
        $appliedDiscounts = $discountResult['applied_discounts'];

        $startDate = $enrollment->start_date
            ? \Carbon\Carbon::parse($enrollment->start_date)
            : now();

        $totalMonths = (int) ($enrollment->total_months ?: 12);
        $assignments = [];

        for ($i = 0; $i < $totalMonths; $i++) {
            $periodDate = (clone $startDate)->addMonths($i);
            $periodMonth = $periodDate->format('Y-m');

            $dueDate = $this->calculateDueDate($feeStructure, $enrollment, $i);

            $assignment = $this->createAssignment(
                $enrollment, $feeStructure, $originalAmount, $discountedAmount,
                $dueDate, $periodMonth, $installmentPlanId, $appliedDiscounts
            );

            $assignments[] = $assignment;
        }

        return $assignments;
    }

    /**
     * Generate an event-based fee assignment (e.g., exam fee with deadline).
     * Uses the absolute due_date from FeeStructure.
     */
    private function generateEventBasedAssignment(FeeStructure $feeStructure, Enrollment $enrollment, Student $student, ?string $installmentPlanId): array
    {
        $originalAmount = (float) $feeStructure->amount;
        $discountResult = $this->applyDiscounts($student, $enrollment, $feeStructure, $originalAmount);
        $discountedAmount = $discountResult['discounted_amount'];
        $appliedDiscounts = $discountResult['applied_discounts'];

        // Use absolute due_date from FeeStructure, fallback to calculated
        $dueDate = $feeStructure->due_date
            ? \Carbon\Carbon::parse($feeStructure->due_date)
            : $this->calculateDueDate($feeStructure, $enrollment, 0);

        $periodMonth = $dueDate->format('Y-m');

        $assignment = $this->createAssignment(
            $enrollment, $feeStructure, $originalAmount, $discountedAmount,
            $dueDate, $periodMonth, $installmentPlanId, $appliedDiscounts
        );

        return [$assignment];
    }

    /**
     * Create a single StudentFeeAssignment record.
     */
    private function createAssignment(
        Enrollment $enrollment,
        FeeStructure $feeStructure,
        float $originalAmount,
        float $finalAmount,
        \Carbon\Carbon $dueDate,
        string $periodMonth,
        ?string $installmentPlanId,
        array $appliedDiscounts = []
    ): StudentFeeAssignment {
        $assignment = StudentFeeAssignment::create([
            'enrollment_id' => $enrollment->id,
            'fee_structure_id' => $feeStructure->id,
            'original_amount' => $originalAmount,
            'discounted_amount' => $finalAmount,
            'final_amount' => $finalAmount,
            'due_date' => $dueDate,
            'period_month' => $periodMonth,
            'status' => 'pending',
            'late_fee_applied' => 0,
            'paid_amount' => 0,
            'installment_plan_id' => $installmentPlanId,
            'remarks' => !empty($appliedDiscounts)
                ? 'Discounts applied: ' . implode(', ', array_column($appliedDiscounts, 'name'))
                : null,
        ]);

        // Log audit
        $this->logAudit('StudentFeeAssignment', $assignment->id, 'created', [
            'enrollment_id' => $enrollment->id,
            'fee_structure_id' => $feeStructure->id,
            'original_amount' => $originalAmount,
            'final_amount' => $finalAmount,
            'due_date' => $dueDate->format('Y-m-d'),
            'period_month' => $periodMonth,
            'applied_discounts' => $appliedDiscounts,
        ]);

        return $assignment;
    }

    /**
     * Calculate due date based on fee structure's due_day, category, and period offset.
     * For monthly fees: due_day of each month in the course duration.
     * For event-based fees: absolute due_date from FeeStructure.
     * For one-time fees: due_day of enrollment month.
     */
    public function calculateDueDate(FeeStructure $feeStructure, Enrollment $enrollment, int $monthOffset = 0): \Carbon\Carbon
    {
        $feeType = $feeStructure->feeType;

        // Event-based: use absolute due_date from FeeStructure
        if ($feeType && $feeType->category === 'event_based' && $feeStructure->due_date) {
            return \Carbon\Carbon::parse($feeStructure->due_date);
        }

        $dueDay = $feeStructure->due_day ?? 10;
        $startDate = $enrollment->start_date
            ? \Carbon\Carbon::parse($enrollment->start_date)
            : ($enrollment->created_at ?? now());

        // Calculate due date: start_date + monthOffset months, set to due_day
        $dueDate = (clone $startDate)->addMonths($monthOffset)->day($dueDay);

        // If the calculated date is in the past and we're at offset 0, push to next month
        if ($monthOffset === 0 && $dueDate->isPast()) {
            $dueDate->addMonth();
        }

        return $dueDate;
    }

    /**
     * Smart Discount Engine: Apply all eligible discounts to an amount.
     * Supports 7 condition types with priority and stackability.
     */
    public function applyDiscounts(Student $student, Enrollment $enrollment, FeeStructure $feeStructure, float $amount): array
    {
        $eligibleRules = DiscountRule::active()
            ->orderBy('priority')
            ->get();

        $appliedDiscounts = [];
        $discountedAmount = $amount;

        foreach ($eligibleRules as $rule) {
            $eligible = $this->evaluateDiscountCondition($rule, $student, $enrollment);

            if (!$eligible) {
                continue;
            }

            // Check stackability
            if (!$rule->stackable && !empty($appliedDiscounts)) {
                continue; // Non-stackable, skip if already applied
            }

            $discountValue = $this->calculateDiscountValue($rule, $discountedAmount);
            $discountedAmount -= $discountValue;

            // Apply max cap
            if ($rule->max_cap && $discountValue > $rule->max_cap) {
                $discountValue = $rule->max_cap;
                $discountedAmount = $amount - $discountValue;
            }

            // Ensure not negative
            if ($discountedAmount < 0) {
                $discountedAmount = 0;
            }

            $appliedDiscounts[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'condition_type' => $rule->condition_type,
                'discount_value' => $discountValue,
                'remaining' => $discountedAmount,
            ];
        }

        return [
            'original_amount' => $amount,
            'discounted_amount' => $discountedAmount,
            'total_discount' => $amount - $discountedAmount,
            'applied_discounts' => $appliedDiscounts,
        ];
    }

    /**
     * Evaluate a single discount rule condition against student/enrollment data.
     */
    public function evaluateDiscountCondition(DiscountRule $rule, Student $student, Enrollment $enrollment): bool
    {
        $config = $rule->condition_config ?? [];

        return match ($rule->condition_type) {
            'early_bird' => $this->evaluateEarlyBird($enrollment, $config),
            'sibling' => $this->evaluateSibling($student, $config),
            'loyalty' => $this->evaluateLoyalty($student, $config),
            'merit' => $this->evaluateMerit($student, $config),
            'bulk' => $this->evaluateBulk($enrollment, $config),
            'need_based' => $this->evaluateNeedBased($student, $config),
            'custom' => $this->evaluateCustom($student, $enrollment, $config),
            default => false,
        };
    }

    /**
     * Early Bird: Enrollment before a cutoff date.
     */
    private function evaluateEarlyBird(Enrollment $enrollment, array $config): bool
    {
        $cutoffDays = $config['cutoff_days'] ?? 30;
        $batchStart = $enrollment->batch?->start_date ?? $enrollment->created_at;
        $daysBeforeStart = now()->diffInDays($batchStart, false); // negative if after start

        // Enrolled at least $cutoffDays before batch start
        return $daysBeforeStart >= $cutoffDays;
    }

    /**
     * Sibling: Student has siblings enrolled in the same institution.
     */
    private function evaluateSibling(Student $student, array $config): bool
    {
        $requiredSiblings = $config['min_siblings'] ?? 1;
        $siblingCount = Enrollment::whereIn('student_id', function ($q) use ($student) {
            $q->select('id')->from('students')
              ->where('guardian_phone', $student->guardian_phone)
              ->where('id', '!=', $student->id);
        })->where('status', 'active')->count();

        return $siblingCount >= $requiredSiblings;
    }

    /**
     * Loyalty: Student has previous enrollments (re-enrollment).
     */
    private function evaluateLoyalty(Student $student, array $config): bool
    {
        $minEnrollments = $config['min_enrollments'] ?? 1;
        $enrollmentCount = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->count();

        return $enrollmentCount > $minEnrollments;
    }

    /**
     * Merit: Based on student's academic performance.
     */
    private function evaluateMerit(Student $student, array $config): bool
    {
        $minGpa = $config['min_gpa'] ?? 5.0;
        $lastExamGpa = $student->last_exam_gpa ?? 0;

        return $lastExamGpa >= $minGpa;
    }

    /**
     * Bulk: Multiple subjects/courses enrolled at once.
     */
    private function evaluateBulk(Enrollment $enrollment, array $config): bool
    {
        $minSubjects = $config['min_subjects'] ?? 3;
        $subjectCount = $enrollment->subjects()->count();

        return $subjectCount >= $minSubjects;
    }

    /**
     * Need-based: Financial need assessment (requires manual flag or scholarship data).
     */
    private function evaluateNeedBased(Student $student, array $config): bool
    {
        return (bool) ($student->financial_aid ?? false);
    }

    /**
     * Custom: Evaluates a custom condition stored in condition_config.
     */
    private function evaluateCustom(Student $student, Enrollment $enrollment, array $config): bool
    {
        $customField = $config['field'] ?? null;
        $customValue = $config['value'] ?? null;
        $customOperator = $config['operator'] ?? '=';

        if (!$customField || !$student->$customField) {
            return false;
        }

        return match ($customOperator) {
            '=' => $student->$customField == $customValue,
            '>' => $student->$customField > $customValue,
            '<' => $student->$customField < $customValue,
            '>=' => $student->$customField >= $customValue,
            '<=' => $student->$customField <= $customValue,
            'in' => in_array($student->$customField, (array) $customValue),
            'contains' => str_contains($student->$customField, $customValue),
            default => false,
        };
    }

    /**
     * Calculate the discount value based on rule type and current amount.
     */
    private function calculateDiscountValue(DiscountRule $rule, float $currentAmount): float
    {
        return match ($rule->discount_type) {
            'percentage' => ($rule->discount_value / 100) * $currentAmount,
            'fixed' => $rule->discount_value,
            'waive' => $currentAmount, // Full waiver
            default => 0,
        };
    }

    /**
     * Smart Late Fee Engine: Calculate late fee for overdue assignments.
     */
    public function calculateLateFee(StudentFeeAssignment $assignment): float
    {
        if ($assignment->status === 'paid') {
            return 0;
        }

        $daysOverdue = now()->diffInDays($assignment->due_date, false);
        if ($daysOverdue <= 0) {
            return 0;
        }

        // Find applicable late fee rule
        $lateFeeRule = LateFeeRule::active()->first();

        if (!$lateFeeRule) {
            return 0;
        }

        // Apply grace period
        $chargeableDays = max(0, $daysOverdue - $lateFeeRule->grace_period_days);
        if ($chargeableDays <= 0) {
            return 0;
        }

        $lateFee = match ($lateFeeRule->calculation_type) {
            'flat_per_day' => $lateFeeRule->flat_rate * $chargeableDays,
            'percentage_per_day' => ($lateFeeRule->percentage_rate / 100) * $assignment->final_amount * $chargeableDays,
            'tiered' => $this->calculateTieredLateFee($lateFeeRule, $chargeableDays, $assignment->final_amount),
            default => 0,
        };

        // Apply max cap
        if ($lateFeeRule->max_cap && $lateFee > $lateFeeRule->max_cap) {
            $lateFee = $lateFeeRule->max_cap;
        }

        return round($lateFee, 2);
    }

    /**
     * Tiered late fee calculation based on config.
     */
    private function calculateTieredLateFee(LateFeeRule $rule, int $daysOverdue, float $amount): float
    {
        $tiers = $rule->tier_config ?? [];
        $totalFee = 0;

        foreach ($tiers as $tier) {
            $tierStart = $tier['from_days'] ?? 0;
            $tierEnd = $tier['to_days'] ?? PHP_INT_MAX;

            if ($daysOverdue >= $tierStart) {
                $applicableDays = min($daysOverdue, $tierEnd) - $tierStart + 1;
                $tierRate = $tier['rate'] ?? 0;
                $tierType = $tier['type'] ?? 'flat';

                if ($tierType === 'percentage') {
                    $totalFee += ($tierRate / 100) * $amount * $applicableDays;
                } else {
                    $totalFee += $tierRate * $applicableDays;
                }
            }
        }

        return $totalFee;
    }

    /**
     * Generate installment records for a fee assignment.
     */
    public function generateInstallments(Enrollment $enrollment, array $assignments, string $installmentPlanId): array
    {
        $plan = InstallmentPlan::find($installmentPlanId);
        if (!$plan) {
            return ['success' => false, 'message' => 'Installment plan not found.'];
        }

        $installments = [];
        $totalAmount = array_sum(array_map(fn($a) => $a->final_amount, $assignments));
        $perInstallment = round($totalAmount / $plan->total_installments, 2);

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $plan->total_installments; $i++) {
                $dueDate = now()->addDays($plan->frequency_days * $i);

                $amount = ($i === $plan->total_installments)
                    ? $totalAmount - ($perInstallment * ($plan->total_installments - 1)) // Last installment = remainder
                    : $perInstallment;

                $periodMonth = $dueDate->format('Y-m');

                $installment = StudentFeeAssignment::create([
                    'enrollment_id' => $enrollment->id,
                    'original_amount' => $amount,
                    'discounted_amount' => $amount,
                    'final_amount' => $amount,
                    'due_date' => $dueDate,
                    'period_month' => $periodMonth,
                    'status' => 'pending',
                    'late_fee_applied' => 0,
                    'paid_amount' => 0,
                    'installment_plan_id' => $plan->id,
                    'installment_number' => $i,
                    'remarks' => "Installment {$i} of {$plan->total_installments}",
                ]);

                $installments[] = $installment;
            }

            DB::commit();
            return ['success' => true, 'installments' => $installments];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Installment generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Installment generation failed.'];
        }
    }

    /**
     * Process a student payment (online/gateway or cash).
     *
     * - Online gateways (bkash, nagad, rocket, card): Auto-confirmed, invoice auto-generated
     * - Cash/Manual: Status remains 'pending' for admin confirmation
     *
     * If 'assignment_ids' is provided, allocates to those specific assignments.
     * Otherwise, auto-allocates to oldest unpaid fees (FIFO).
     */
    public function processPayment(array $data): array
    {
        $enrollment = Enrollment::find($data['enrollment_id']);
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found.'];
        }

        $studentId = $data['student_id'] ?? $enrollment->student_id;
        $paymentMethod = $data['payment_method'];

        // Guard: Check for existing pending payments on the same assignment(s)
        if (!empty($data['assignment_ids'])) {
            $existingPending = PaymentTransaction::where('enrollment_id', $enrollment->id)
                ->where('status', 'pending')
                ->whereHas('allocations', fn($q) => $q->whereIn('fee_assignment_id', $data['assignment_ids']))
                ->exists();
            if ($existingPending) {
                return ['success' => false, 'message' => 'One or more of these fees already has a payment awaiting admin confirmation. Please wait until it is approved or rejected.'];
            }
        }

        // Online gateways that auto-confirm
        $autoConfirmMethods = ['bkash', 'nagad', 'rocket', 'card', 'bank'];
        $isAutoConfirm = in_array($paymentMethod, $autoConfirmMethods);
        $status = $isAutoConfirm ? 'confirmed' : 'pending';

        DB::beginTransaction();
        try {
            // Generate unique transaction number
            $transactionNo = $this->generateTransactionNo();

            // Create transaction
            $transaction = PaymentTransaction::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $studentId,
                'transaction_no' => $transactionNo,
                'amount' => $data['amount'],
                'payment_method' => $paymentMethod,
                'gateway_trx_id' => $data['gateway_trx_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'status' => $status,
                'confirmed_by' => $isAutoConfirm ? ($data['confirmed_by'] ?? auth()->id()) : null,
                'confirmed_at' => $isAutoConfirm ? now() : null,
                'remarks' => $data['remarks'] ?? null,
                'is_manual' => false,
            ]);

            // If auto-confirmed, allocate immediately and generate invoice
            if ($isAutoConfirm) {
                // If specific assignment IDs are provided, allocate to those
                if (!empty($data['assignment_ids'])) {
                    $assignments = StudentFeeAssignment::whereIn('id', $data['assignment_ids'])
                        ->where('enrollment_id', $enrollment->id)
                        ->whereIn('status', ['pending', 'partial'])
                        ->get();
                    \Log::info('[processPayment] Found assignments for allocation', [
                        'assignment_ids' => $data['assignment_ids'],
                        'found_count' => $assignments->count(),
                        'found_ids' => $assignments->pluck('id')->toArray(),
                        'found_statuses' => $assignments->pluck('status')->toArray(),
                        'found_amounts' => $assignments->map(fn($a) => ['id' => $a->id, 'final_amount' => $a->final_amount, 'paid_amount' => $a->paid_amount, 'status' => $a->status])->toArray(),
                    ]);
                    if ($assignments->isNotEmpty()) {
                        $this->allocateToSpecificAssignments($transaction, $assignments);
                    } else {
                        \Log::warning('[processPayment] No pending/partial assignments found for IDs, falling back to auto-allocate', [
                            'assignment_ids' => $data['assignment_ids'],
                        ]);
                        $this->autoAllocateToOldestFees($transaction);
                    }
                } else {
                    $this->autoAllocateToOldestFees($transaction);
                }
                
                // Auto-generate invoice for online payments
                try {
                    $invoiceService = app(\Modules\Finance\app\Services\SmartInvoiceService::class);
                    $invoiceService->generateInvoice($transaction);
                } catch (\Exception $invEx) {
                    Log::warning('Auto invoice generation failed: ' . $invEx->getMessage(), [
                        'transaction_id' => $transaction->id,
                    ]);
                }
            } elseif (!empty($data['assignment_ids'])) {
                // Pending cash/check: reserve allocations for the selected fees without
                // updating assignment balances until admin confirms the payment.
                $assignments = StudentFeeAssignment::whereIn('id', $data['assignment_ids'])
                    ->where('enrollment_id', $enrollment->id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('due_date')
                    ->get();

                if ($assignments->isNotEmpty()) {
                    $this->reserveAllocationsForAssignments($transaction, $assignments);
                }
            }

            // Log audit
            $this->logAudit('PaymentTransaction', $transaction->id, 'payment_initiated', [
                'enrollment_id' => $enrollment->id,
                'amount' => $data['amount'],
                'method' => $paymentMethod,
                'transaction_no' => $transactionNo,
                'auto_confirmed' => $isAutoConfirm,
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'auto_confirmed' => $isAutoConfirm,
                'message' => $isAutoConfirm
                    ? 'Payment via ' . ucfirst($paymentMethod) . ' completed successfully. Invoice generated.'
                    : 'Payment submitted successfully. Awaiting admin confirmation.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Admin confirms a pending payment.
     * Auto-allocates to oldest unpaid fees and generates invoice.
     */
    public function confirmPayment(string $transactionId, string $confirmedBy): array
    {
        $transaction = PaymentTransaction::find($transactionId);
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }

        if ($transaction->status !== 'pending') {
            return ['success' => false, 'message' => 'Transaction is not in pending status.'];
        }

        DB::beginTransaction();
        try {
            $oldStatus = $transaction->status;

            $transaction->update([
                'status' => 'confirmed',
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now(),
            ]);

            $existingAllocations = PaymentAllocation::where('transaction_id', $transaction->id)->count();
            if ($existingAllocations === 0) {
                $this->autoAllocateToOldestFees($transaction);
            } else {
                $this->fulfillExistingAllocations($transaction);
            }

            // Auto-generate invoice for confirmed payment
            try {
                $invoiceService = app(\Modules\Finance\app\Services\SmartInvoiceService::class);
                $invoiceService->generateInvoice($transaction->fresh());
            } catch (\Exception $invEx) {
                Log::warning('Invoice generation on confirm failed: ' . $invEx->getMessage(), [
                    'transaction_id' => $transaction->id,
                ]);
            }

            // Log audit
            $this->logAudit('PaymentTransaction', $transaction->id, 'payment_confirmed', [
                'old_status' => $oldStatus,
                'new_status' => 'confirmed',
                'confirmed_by' => $confirmedBy,
                'amount' => $transaction->amount,
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'message' => 'Payment confirmed, allocated, and invoice generated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment confirmation failed: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Payment confirmation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Admin rejects a pending payment.
     */
    public function rejectPayment(string $transactionId, string $rejectedBy, string $reason): array
    {
        $transaction = PaymentTransaction::find($transactionId);
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }

        if ($transaction->status !== 'pending') {
            return ['success' => false, 'message' => 'Transaction is not in pending status.'];
        }

        DB::beginTransaction();
        try {
            $oldStatus = $transaction->status;

            $transaction->update([
                'status' => 'rejected',
                'confirmed_by' => $rejectedBy,
                'confirmed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->logAudit('PaymentTransaction', $transaction->id, 'payment_rejected', [
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'reason' => $reason,
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'message' => 'Payment rejected.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment rejection failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment rejection failed.'];
        }
    }

    /**
     * Admin records a manual payment (cash, check, bank transfer).
     * Cash payments are auto-confirmed; check/bank are pending.
     * Auto-generates invoice for confirmed payments.
     */
    public function recordManualPayment(array $data, string $recordedBy): array
    {
        $enrollment = Enrollment::find($data['enrollment_id']);
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found.'];
        }

        $studentId = $data['student_id'] ?? $enrollment->student_id;
        $paymentMethod = $data['payment_method'];

        // Cash payments are auto-confirmed, others need confirmation
        $autoConfirm = in_array($paymentMethod, ['cash']);
        $status = $autoConfirm ? 'confirmed' : 'pending';

        DB::beginTransaction();
        try {
            $transactionNo = $this->generateTransactionNo();

            $transaction = PaymentTransaction::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $studentId,
                'transaction_no' => $transactionNo,
                'amount' => $data['amount'],
                'payment_method' => $paymentMethod,
                'reference_no' => $data['reference_no'] ?? null,
                'status' => $status,
                'confirmed_by' => $autoConfirm ? $recordedBy : null,
                'confirmed_at' => $autoConfirm ? now() : null,
                'remarks' => $data['remarks'] ?? 'Manual payment by admin',
                'is_manual' => true,
            ]);

            // If auto-confirmed, allocate immediately and generate invoice
            if ($autoConfirm) {
                $this->autoAllocateToOldestFees($transaction);
                
                // Auto-generate invoice for confirmed manual payments
                try {
                    $invoiceService = app(\Modules\Finance\app\Services\SmartInvoiceService::class);
                    $invoiceService->generateInvoice($transaction->fresh());
                } catch (\Exception $invEx) {
                    Log::warning('Invoice generation for manual payment failed: ' . $invEx->getMessage(), [
                        'transaction_id' => $transaction->id,
                    ]);
                }
            }

            $this->logAudit('PaymentTransaction', $transaction->id, 'manual_payment_recorded', [
                'amount' => $data['amount'],
                'method' => $paymentMethod,
                'auto_confirmed' => $autoConfirm,
                'recorded_by' => $recordedBy,
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'auto_confirmed' => $autoConfirm,
                'message' => $autoConfirm
                    ? 'Manual payment recorded, confirmed, and invoice generated successfully.'
                    : 'Manual payment recorded. Awaiting confirmation.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual payment recording failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Manual payment recording failed.'];
        }
    }

    /**
     * Auto-allocate payment amount to oldest unpaid fee assignments first.
     * Uses FIFO (First In, First Out) allocation strategy.
     */
    public function autoAllocateToOldestFees(PaymentTransaction $transaction): void
    {
        $remainingAmount = $transaction->amount;

        // First, try to allocate to Smart Fee assignments
        $assignments = StudentFeeAssignment::where('enrollment_id', $transaction->enrollment_id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->get();

        if ($assignments->isNotEmpty()) {
            foreach ($assignments as $assignment) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $currentDue = $assignment->final_amount + ($assignment->late_fee_applied ?? 0) - $assignment->paid_amount;

                if ($currentDue <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingAmount, $currentDue);

                // Create allocation record
                PaymentAllocation::create([
                    'transaction_id' => $transaction->id,
                    'fee_assignment_id' => $assignment->id,
                    'amount' => $allocationAmount,
                ]);

                // Update assignment
                $newPaidAmount = $assignment->paid_amount + $allocationAmount;
                $totalDue = $assignment->final_amount + ($assignment->late_fee_applied ?? 0);
                $newStatus = $newPaidAmount >= $totalDue ? 'paid' : 'partial';

                $assignment->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus,
                ]);

                $remainingAmount -= $allocationAmount;

                $this->logAudit('StudentFeeAssignment', $assignment->id, 'payment_allocated', [
                    'transaction_id' => $transaction->id,
                    'allocated_amount' => $allocationAmount,
                    'new_paid_amount' => $newPaidAmount,
                    'new_status' => $newStatus,
                ]);
            }
        } else {
            // Fallback: No Smart Fee assignments exist, allocate to MonthlyFeeRecord records
            $monthlyRecords = \Modules\Enrollment\app\Models\MonthlyFeeRecord::where('enrollment_id', $transaction->enrollment_id)
                ->whereIn('payment_status', ['pending', 'partial'])
                ->orderBy('due_date')
                ->get();

            $allocatedMonths = [];

            foreach ($monthlyRecords as $record) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $currentDue = max(0, $record->due_amount - $record->paid_amount);

                if ($currentDue <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingAmount, $currentDue);

                // Update the monthly fee record
                $newPaidAmount = $record->paid_amount + $allocationAmount;
                $newStatus = $newPaidAmount >= $record->due_amount ? 'paid' : 'partial';

                $record->update([
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $newStatus,
                    'paid_at' => $newStatus === 'paid' ? now() : $record->paid_at,
                ]);

                // Track which months were paid (for invoice metadata matching)
                if ($record->month) {
                    $allocatedMonths[] = $record->month;
                }

                $remainingAmount -= $allocationAmount;

                $this->logAudit('MonthlyFeeRecord', $record->id, 'payment_allocated', [
                    'transaction_id' => $transaction->id,
                    'allocated_amount' => $allocationAmount,
                    'new_paid_amount' => $newPaidAmount,
                    'new_status' => $newStatus,
                ]);
            }

            // Store allocated month info in transaction remarks for invoice matching
            if (!empty($allocatedMonths)) {
                $monthsStr = implode(',', array_unique($allocatedMonths));
                $existingRemarks = $transaction->remarks ?? '';
                $remarksSuffix = " | Legacy months: {$monthsStr}";
                if (!str_contains($existingRemarks, 'Legacy months:')) {
                    $transaction->updateQuietly(['remarks' => $existingRemarks . $remarksSuffix]);
                }
            }

            // Fallback: one_time enrollment course fee (no smart assignments / monthly records)
            if ($remainingAmount > 0) {
                $enrollment = Enrollment::find($transaction->enrollment_id);
                if ($enrollment && $enrollment->fee_type === 'one_time') {
                    $courseDue = max(0, (float) ($enrollment->payable_fee ?? 0) - (float) ($enrollment->paid_amount ?? 0));
                    if ($courseDue > 0) {
                        $allocationAmount = min($remainingAmount, $courseDue);
                        $newCoursePaid = (float) ($enrollment->paid_amount ?? 0) + $allocationAmount;
                        $newDue = max(0, (float) ($enrollment->payable_fee ?? 0) - $newCoursePaid);
                        $enrollment->update([
                            'paid_amount' => $newCoursePaid,
                            'due_amount' => $newDue,
                            'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                        ]);
                        $remainingAmount -= $allocationAmount;
                    }
                }
            }
        }

        // If remaining amount after all allocations, add as prepayment/credit
        if ($remainingAmount > 0) {
            $transaction->update(['remarks' => ($transaction->remarks ?? '') . " | Prepayment balance: {$remainingAmount}"]);
        }
    }

    /**
     * Get complete student ledger with debit/credit/balance.
     */
    public function getStudentLedger(string $enrollmentId): array
    {
        $enrollment = Enrollment::with('student')->find($enrollmentId);
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found.'];
        }

        $assignments = StudentFeeAssignment::where('enrollment_id', $enrollmentId)
            ->orderBy('due_date')
            ->get();

        $transactions = PaymentTransaction::where('enrollment_id', $enrollmentId)
            ->where('status', 'confirmed')
            ->orderBy('created_at')
            ->get();

        $ledger = [];
        $runningBalance = 0;

        // Add fee assignments as debit entries
        foreach ($assignments as $assignment) {
            $totalDue = $assignment->final_amount + ($assignment->late_fee_applied ?? 0);
            $runningBalance += $totalDue;

            $ledger[] = [
                'date' => $assignment->due_date->format('Y-m-d'),
                'type' => 'debit',
                'description' => $assignment->feeStructure?->feeType?->name ?? 'Fee'
                    . ($assignment->installment_number ? " (Installment {$assignment->installment_number})" : ''),
                'amount' => $totalDue,
                'balance' => $runningBalance,
                'status' => $assignment->status,
                'reference' => $assignment->id,
            ];
        }

        // Add payments as credit entries
        foreach ($transactions as $transaction) {
            $runningBalance -= $transaction->amount;

            $ledger[] = [
                'date' => $transaction->confirmed_at->format('Y-m-d'),
                'type' => 'credit',
                'description' => "Payment via {$transaction->payment_method}"
                    . ($transaction->is_manual ? ' (Manual)' : ''),
                'amount' => $transaction->amount,
                'balance' => max(0, $runningBalance),
                'status' => 'confirmed',
                'reference' => $transaction->transaction_no,
            ];
        }

        // Sort by date
        usort($ledger, fn($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'success' => true,
            'student' => $enrollment->student,
            'enrollment' => $enrollment,
            'ledger' => $ledger,
            'summary' => [
                'total_fees' => $assignments->sum(fn($a) => $a->original_amount + ($a->late_fee_applied ?? 0)),
                'total_paid' => $transactions->sum('amount'),
                'total_discount' => $assignments->sum(fn($a) => max(0, $a->original_amount - $a->final_amount)),
                'total_due' => max(0, $assignments->sum(fn($a) => $a->final_amount + ($a->late_fee_applied ?? 0)) - $transactions->sum('amount')),
                'pending_count' => $assignments->whereIn('status', ['pending', 'partial'])->count(),
                'paid_count' => $assignments->where('status', 'paid')->count(),
            ],
        ];
    }

    /**
     * Get student's fee dashboard data.
     * Queries Smart Fee system first; falls back to Monthly Fee system if no Smart Fee data exists.
     */
    public function getStudentDashboard(string $studentId): array
    {
        $enrollments = Enrollment::where('student_id', $studentId)
            ->with('batch.course')
            ->get();

        $dashboard = [];

        foreach ($enrollments as $enrollment) {
            $assignments = StudentFeeAssignment::where('enrollment_id', $enrollment->id)->get();
            $transactions = PaymentTransaction::where('enrollment_id', $enrollment->id)
                ->where('status', 'confirmed')
                ->get();

            // Fetch legacy monthly fee records alongside smart assignments
            $monthlyRecords = MonthlyFeeRecord::where('enrollment_id', $enrollment->id)->get();

            // Fetch fee notifications (exam fees, event-based fees) that don't yet
            // have a corresponding StudentFeeAssignment record.
            $notifications = \Modules\Finance\app\Models\StudentFeeNotification::where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['unread', 'read'])
                ->get();

            // Collect period_month values covered by smart assignments (for dedup)
            $coveredPeriodMonths = $assignments
                ->filter(fn($a) => !empty($a->period_month))
                ->pluck('period_month')
                ->toArray();

            // Calculate totals from smart assignments
            $smartTotalOriginal = $assignments->sum(fn($a) => $a->original_amount + ($a->late_fee_applied ?? 0));
            $smartTotalDiscounted = $assignments->sum(fn($a) => $a->final_amount + ($a->late_fee_applied ?? 0));
            $smartTotalPaid = $transactions->sum('amount');
            $smartTotalDiscount = $assignments->sum(fn($a) => max(0, $a->original_amount - $a->final_amount));
            $smartOverdueCount = $assignments->whereIn('status', ['pending', 'partial'])
                ->filter(fn($a) => $a->due_date < now())
                ->count();

            // Calculate totals from legacy monthly records (skip months covered by smart assignments)
            $legacyTotalFees = 0;
            $legacyTotalPaid = 0;
            $legacyTotalDiscount = 0;
            $legacyTotalDue = 0;
            $legacyOverdueCount = 0;

            foreach ($monthlyRecords as $r) {
                $monthDate = \Carbon\Carbon::parse($r->month);
                $periodKey = $monthDate->format('Y-m');
                if (in_array($periodKey, $coveredPeriodMonths)) {
                    continue; // Skip - already covered by smart assignment
                }
                $legacyTotalFees += (float) ($r->total_monthly_fee ?? 0);
                $legacyTotalPaid += (float) ($r->paid_amount ?? 0);
                $legacyTotalDiscount += max(0, (float) ($r->total_monthly_fee ?? 0) - (float) ($r->due_amount ?? 0));
                $legacyTotalDue += max(0, (float) ($r->due_amount ?? 0) - (float) ($r->paid_amount ?? 0));
                if ($r->payment_status !== 'paid' && $r->due_date && $r->due_date < now()) {
                    $legacyOverdueCount++;
                }
            }

            // Calculate totals from fee notifications (skip if already covered by smart assignment)
            $notificationTotalDue = 0;
            $notificationOverdueCount = 0;
            foreach ($notifications as $notif) {
                // Skip if a StudentFeeAssignment already exists for this fee_structure + enrollment
                $existingAssignment = StudentFeeAssignment::where('enrollment_id', $enrollment->id)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->exists();

                if ($existingAssignment) {
                    continue;
                }

                $amount = (float) ($notif->amount ?? 0);
                $notificationTotalDue += $amount;

                if ($notif->due_date && $notif->due_date < now()) {
                    $notificationOverdueCount++;
                }
            }

            // Merge: smart + legacy + notification totals
            $totalFees = $smartTotalOriginal + $legacyTotalFees + $notificationTotalDue;
            $totalPaid = $smartTotalPaid + $legacyTotalPaid;
            $totalDue = max(0, $smartTotalDiscounted - $smartTotalPaid) + $legacyTotalDue + $notificationTotalDue;
            $totalDiscount = $smartTotalDiscount + $legacyTotalDiscount;
            $overdueCount = $smartOverdueCount + $legacyOverdueCount + $notificationOverdueCount;

            // Determine status from combined data
            $combinedDiscounted = $smartTotalDiscounted + ($legacyTotalFees - $legacyTotalDiscount);
            $status = $totalPaid >= $combinedDiscounted ? 'clear' : ($overdueCount > 0 ? 'overdue' : 'pending');

            $dashboard[] = [
                'enrollment_id' => $enrollment->id,
                'course_name' => $enrollment->batch?->course?->name ?? 'N/A',
                'batch_name' => $enrollment->batch?->name ?? 'N/A',
                'total_fees' => $totalFees,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
                'total_discount' => $totalDiscount,
                'overdue_count' => $overdueCount,
                'status' => $status,
            ];
        }

        return [
            'success' => true,
            'enrollments' => $dashboard,
            'overall' => [
                'total_fees' => array_sum(array_column($dashboard, 'total_fees')),
                'total_paid' => array_sum(array_column($dashboard, 'total_paid')),
                'total_due' => array_sum(array_column($dashboard, 'total_due')),
                'total_discount' => array_sum(array_column($dashboard, 'total_discount')),
                'overdue_count' => array_sum(array_column($dashboard, 'overdue_count')),
            ],
        ];
    }

    /**
     * Get pending fee assignments for a student grouped by fee type with category info.
     * Used by admin collection form and student portal to show what's due.
     * Includes both Smart Fee (StudentFeeAssignment) and Legacy (MonthlyFeeRecord) data.
     */
    public function getStudentPendingFees(string $studentId, ?string $enrollmentId = null): array
    {
        // StudentFeeAssignment does not have a student_id column directly;
        // it links through enrollment. So we query via enrollment relationship.
        $query = StudentFeeAssignment::with(['feeStructure.feeType', 'enrollment.batch.course'])
            ->whereHas('enrollment', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            })
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date');

        if ($enrollmentId) {
            $query->where('enrollment_id', $enrollmentId);
        }

        $assignments = $query->get();

        $grouped = [];
        foreach ($assignments as $assignment) {
            $feeType = $assignment->feeStructure?->feeType;
            $category = $feeType?->category ?? 'monthly';
            $feeTypeName = $feeType?->name ?? 'Unknown Fee';

            // Resolve period label
            $periodLabel = '';
            if ($assignment->period_month) {
                $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $assignment->period_month)?->format('F Y') ?? $assignment->period_month;
            }

            $dueAmount = ($assignment->final_amount + ($assignment->late_fee_applied ?? 0)) - $assignment->paid_amount;

            $key = $category . '|' . $feeTypeName;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'fee_type_name' => $feeTypeName,
                    'category' => $category,
                    'category_label' => $feeType?->category_label ?? ucfirst($category),
                    'total_due' => 0,
                    'items' => [],
                ];
            }

            $grouped[$key]['total_due'] += max(0, $dueAmount);
            $grouped[$key]['items'][] = [
                'id' => $assignment->id,
                'enrollment_id' => $assignment->enrollment_id,
                'course_name' => $assignment->enrollment?->batch?->course?->name ?? 'N/A',
                'batch_name' => $assignment->enrollment?->batch?->name ?? 'N/A',
                'original_amount' => (float) $assignment->original_amount,
                'final_amount' => (float) $assignment->final_amount,
                'late_fee' => (float) ($assignment->late_fee_applied ?? 0),
                'paid_amount' => (float) $assignment->paid_amount,
                'due_amount' => max(0, $dueAmount),
                'due_date' => $assignment->due_date?->format('Y-m-d'),
                'period_label' => $periodLabel,
                'period_month' => $assignment->period_month,
                'status' => $assignment->status,
                'installment_number' => $assignment->installment_number,
            ];
        }

        // Also include legacy MonthlyFeeRecord data for students whose fees
        // exist only in the legacy system (not yet migrated to Smart Fee).
        $enrollmentsQuery = Enrollment::where('student_id', $studentId);
        if ($enrollmentId) {
            $enrollmentsQuery->where('id', $enrollmentId);
        }
        $enrollments = $enrollmentsQuery->pluck('id');

        if ($enrollments->isNotEmpty()) {
            // Query legacy records that are unpaid or partially paid.
            // We check both payment_status and the actual balance to be robust
            // against different status conventions in legacy data.
            $legacyRecords = MonthlyFeeRecord::whereIn('enrollment_id', $enrollments)
                ->where(function ($q) {
                    $q->whereIn('payment_status', ['pending', 'partial', 'unpaid', 'due', 'overdue'])
                      ->orWhere(function ($q2) {
                          $q2->where('payment_status', '!=', 'paid')
                             ->whereColumn('due_amount', '>', 'paid_amount');
                      });
                })
                ->with('enrollment.batch.course')
                ->orderBy('month')
                ->get();

            foreach ($legacyRecords as $record) {
                $dueAmount = (float) ($record->due_amount - $record->paid_amount);
                if ($dueAmount <= 0) {
                    continue;
                }

                $monthLabel = '';
                if ($record->month) {
                    $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $record->month)?->format('F Y') ?? $record->month;
                }

                $key = 'monthly|Monthly Fee';

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'fee_type_name' => 'Monthly Fee',
                        'category' => 'monthly',
                        'category_label' => 'Monthly Fee',
                        'total_due' => 0,
                        'items' => [],
                    ];
                }

                $grouped[$key]['total_due'] += max(0, $dueAmount);
                $grouped[$key]['items'][] = [
                    'id' => $record->id,
                    'enrollment_id' => $record->enrollment_id,
                    'course_name' => $record->enrollment?->batch?->course?->name ?? 'N/A',
                    'batch_name' => $record->enrollment?->batch?->name ?? 'N/A',
                    'original_amount' => (float) $record->total_monthly_fee,
                    'final_amount' => (float) $record->total_monthly_fee,
                    'late_fee' => (float) ($record->fine_amount ?? 0),
                    'paid_amount' => (float) $record->paid_amount,
                    'due_amount' => max(0, $dueAmount),
                    'due_date' => $record->due_date?->format('Y-m-d'),
                    'period_label' => $monthLabel,
                    'period_month' => $record->month,
                    'status' => $record->payment_status === 'partial' ? 'partial' : 'pending',
                    'installment_number' => null,
                    'is_legacy' => true,
                ];
            }
        }

        // Also include fee notifications (exam fees, event-based fees) that don't yet
        // have a corresponding StudentFeeAssignment record. These are fees notified to
        // the student via the notification system but not yet assigned as a smart fee.
        $notificationQuery = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
            ->whereIn('status', ['unread', 'read'])
            ->with(['feeStructure.feeType', 'enrollment.batch.course']);

        if ($enrollmentId) {
            $notificationQuery->where('enrollment_id', $enrollmentId);
        }

        $notifications = $notificationQuery->get();

        foreach ($notifications as $notif) {
            // Skip if a StudentFeeAssignment already exists for this fee_structure + enrollment
            $existingAssignment = StudentFeeAssignment::where('enrollment_id', $notif->enrollment_id)
                ->where('fee_structure_id', $notif->fee_structure_id)
                ->whereIn('status', ['pending', 'partial'])
                ->exists();

            if ($existingAssignment) {
                continue; // Already covered by the smart fee query above
            }

            $feeType = $notif->feeStructure?->feeType;
            $category = $feeType?->category ?? 'event_based';
            $feeTypeName = $feeType?->name ?? ($notif->title ?? 'Exam Fee');

            $key = $category . '|' . $feeTypeName;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'fee_type_name' => $feeTypeName,
                    'category' => $category,
                    'category_label' => $feeType?->category_label ?? ucfirst($category),
                    'total_due' => 0,
                    'items' => [],
                ];
            }

            $dueAmount = (float) ($notif->amount ?? 0);

            $grouped[$key]['total_due'] += max(0, $dueAmount);
            $grouped[$key]['items'][] = [
                'id' => 'notif_' . $notif->id, // Unique prefix to avoid collision with real assignment IDs
                'enrollment_id' => $notif->enrollment_id,
                'course_name' => $notif->enrollment?->batch?->course?->name ?? 'N/A',
                'batch_name' => $notif->enrollment?->batch?->name ?? 'N/A',
                'original_amount' => $dueAmount,
                'final_amount' => $dueAmount,
                'late_fee' => 0,
                'paid_amount' => 0,
                'due_amount' => $dueAmount,
                'due_date' => $notif->due_date?->format('Y-m-d'),
                'period_label' => $notif->title ?? $feeTypeName,
                'period_month' => null,
                'status' => 'pending',
                'installment_number' => null,
                'is_notification' => true, // Flag to indicate this is from a notification
                'notification_id' => $notif->id, // Original notification ID for status sync
                'fee_structure_id' => $notif->fee_structure_id, // Fee structure ID for creating assignment
            ];
        }

        return [
            'success' => true,
            'grouped' => array_values($grouped),
            'total_due' => array_sum(array_column($grouped, 'total_due')),
        ];
    }

    /**
     * Record a manual payment with specific fee assignment allocations.
     * Unlike recordManualPayment() which uses FIFO auto-allocation,
     * this allows admin to specify exactly which assignments to pay.
     * Supports both Smart Fee (StudentFeeAssignment) and Legacy (MonthlyFeeRecord) items.
     */
    public function recordManualPaymentWithAllocations(array $data, string $recordedBy): array
    {
        $enrollment = Enrollment::find($data['enrollment_id']);
        if (!$enrollment) {
            return ['success' => false, 'message' => 'Enrollment not found.'];
        }

        $studentId = $data['student_id'] ?? $enrollment->student_id;
        $paymentMethod = $data['payment_method'];
        $feeAssignmentIds = $data['fee_assignment_ids'] ?? [];

        // Separate smart fee IDs from legacy monthly fee record IDs and notification IDs
        // IMPORTANT: MonthlyFeeRecord also uses UUIDs as primary keys (not auto-increment integers).
        // So we cannot simply assume UUIDs are StudentFeeAssignment IDs. We need to check
        // the actual table to determine where each ID belongs.
        $smartIds = [];
        $legacyIds = [];
        $notificationIds = []; // IDs from notifications (prefixed with 'notif_')
        if (!empty($feeAssignmentIds)) {
            foreach ($feeAssignmentIds as $id) {
                // Check for notification prefix first
                if (is_string($id) && str_starts_with($id, 'notif_')) {
                    $notificationIds[] = str_replace('notif_', '', $id);
                }
                // Legacy MonthlyFeeRecord IDs are UUIDs (not auto-increment integers).
                // Smart Fee StudentFeeAssignment IDs are also UUIDs.
                // We need to check which table the ID belongs to.
                elseif (is_string($id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
                    // Check if this UUID exists in StudentFeeAssignment first
                    $existsInSmart = StudentFeeAssignment::where('id', $id)
                        ->whereIn('status', ['pending', 'partial'])
                        ->exists();
                    
                    if ($existsInSmart) {
                        $smartIds[] = $id;
                    } else {
                        // If not in StudentFeeAssignment, it might be a MonthlyFeeRecord UUID
                        $legacyIds[] = $id;
                    }
                } else {
                    $legacyIds[] = $id;
                }
            }
        }

        // Validate smart fee assignments if provided
        $assignments = collect();
        $legacyRecords = collect();
        $totalDue = 0;

        if (!empty($smartIds)) {
            // First try with enrollment_id filter
            $assignments = StudentFeeAssignment::whereIn('id', $smartIds)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['pending', 'partial'])
                ->get();

            // If no assignments found with enrollment_id filter, try without it
            // This handles cases where the enrollment_id in the payload doesn't match
            // the assignments' enrollment_id (e.g., when items come from different enrollments)
            if ($assignments->isEmpty()) {
                $assignments = StudentFeeAssignment::whereIn('id', $smartIds)
                    ->whereIn('status', ['pending', 'partial'])
                    ->get();

            }

            foreach ($assignments as $a) {
                $totalDue += ($a->final_amount + ($a->late_fee_applied ?? 0)) - $a->paid_amount;
            }
        }

        if (!empty($legacyIds)) {
            $legacyRecords = MonthlyFeeRecord::whereIn('id', $legacyIds)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('payment_status', ['pending', 'partial'])
                ->get();

            foreach ($legacyRecords as $r) {
                $totalDue += max(0, $r->due_amount - $r->paid_amount);
            }
        }

        // Check if any of the smartIds are actually fee_structure_ids from notifications
        // (i.e., they are valid UUIDs but not found in StudentFeeAssignment table).
        // This happens when admin selects exam fee items that only exist as notifications.
        // IMPORTANT: We first check if the unmatched UUIDs exist as fee_structure_id in
        // StudentFeeNotification before moving them out of smartIds. This prevents
        // legitimate StudentFeeAssignment UUIDs from being incorrectly treated as
        // fee_structure_ids when the enrollment_id filter doesn't match.
        $notificationFeeStructureIds = [];
        if (!empty($smartIds) && $assignments->isEmpty()) {
            // All smartIds failed to match StudentFeeAssignment - check if they might be fee_structure_ids
            // by looking them up in StudentFeeNotification table
            $possibleNotifIds = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('fee_structure_id', $smartIds)
                ->whereIn('status', ['unread', 'read'])
                ->pluck('fee_structure_id')
                ->toArray();
            
            if (!empty($possibleNotifIds)) {
                // Only move the ones that actually exist as fee_structure_id in notifications
                $notificationFeeStructureIds = $possibleNotifIds;
                $smartIds = array_values(array_diff($smartIds, $possibleNotifIds));
            }
            // If none matched notifications either, keep smartIds as-is (they'll fail validation naturally)
        } elseif (!empty($smartIds) && $assignments->count() < count($smartIds)) {
            // Some matched, some didn't - find the unmatched ones
            $foundIds = $assignments->pluck('id')->toArray();
            $unmatchedIds = [];
            foreach ($smartIds as $sid) {
                if (!in_array($sid, $foundIds)) {
                    $unmatchedIds[] = $sid;
                }
            }
            
            // Check if unmatched IDs are fee_structure_ids in notifications
            if (!empty($unmatchedIds)) {
                $possibleNotifIds = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                    ->where('enrollment_id', $enrollment->id)
                    ->whereIn('fee_structure_id', $unmatchedIds)
                    ->whereIn('status', ['unread', 'read'])
                    ->pluck('fee_structure_id')
                    ->toArray();
                
                if (!empty($possibleNotifIds)) {
                    $notificationFeeStructureIds = $possibleNotifIds;
                    // Remove notification fee_structure_ids from unmatchedIds
                    $unmatchedIds = array_values(array_diff($unmatchedIds, $possibleNotifIds));
                }
            }
            
            $smartIds = $foundIds;
        }

        // If we have notification fee structure IDs (from old format), create StudentFeeAssignment records
        // on-the-fly (similar to how ExamFeeNotificationController::adminCollectExamFee works)
        $notificationIdsFromStructure = [];
        if (!empty($notificationFeeStructureIds)) {
            $notifications = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('fee_structure_id', $notificationFeeStructureIds)
                ->whereIn('status', ['unread', 'read'])
                ->with('feeStructure.feeType')
                ->get();

            foreach ($notifications as $notif) {
                $feeStructure = $notif->feeStructure;
                $amount = $notif->amount ?? ($feeStructure ? $feeStructure->amount : 0);

                // Check if an assignment already exists for this fee structure
                $existingAssn = StudentFeeAssignment::where('enrollment_id', $enrollment->id)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->first();

                if ($existingAssn) {
                    $assignments->push($existingAssn);
                    $smartIds[] = $existingAssn->id;
                } else {
                    // Create a new StudentFeeAssignment for this notified fee
                    $newAssn = StudentFeeAssignment::create([
                        'enrollment_id' => $enrollment->id,
                        'fee_structure_id' => $notif->fee_structure_id,
                        'original_amount' => $amount,
                        'discounted_amount' => $amount,
                        'final_amount' => $amount,
                        'due_date' => $notif->due_date ?? now()->addDays(30),
                        'period_month' => now()->format('Y-m'),
                        'status' => 'pending',
                        'late_fee_applied' => 0,
                        'paid_amount' => 0,
                        'remarks' => 'Auto-created from fee notification (admin collect): ' . ($notif->title ?? ''),
                    ]);

                    $assignments->push($newAssn);
                    $smartIds[] = $newAssn->id;
                }

                $notificationIdsFromStructure[] = $notif->id;
                $totalDue += $amount;
            }
        }

        // Process notification IDs from the 'notif_' prefix format.
        // These are notification IDs that were sent directly from the frontend
        // (as opposed to fee_structure_ids that were sent as smart IDs).
        // We need to look up the notifications, create StudentFeeAssignment records,
        // and add them to the assignments collection.
        if (!empty($notificationIds)) {
            $notifRecords = \Modules\Finance\app\Models\StudentFeeNotification::whereIn('id', $notificationIds)
                ->where('student_id', $studentId)
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['unread', 'read'])
                ->with('feeStructure.feeType')
                ->get();

            foreach ($notifRecords as $notif) {
                $feeStructure = $notif->feeStructure;
                $amount = $notif->amount ?? ($feeStructure ? $feeStructure->amount : 0);

                // Check if an assignment already exists for this fee structure
                $existingAssn = StudentFeeAssignment::where('enrollment_id', $enrollment->id)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->first();

                if ($existingAssn) {
                    $assignments->push($existingAssn);
                    $smartIds[] = $existingAssn->id;
                } else {
                    // Create a new StudentFeeAssignment for this notified fee
                    $newAssn = StudentFeeAssignment::create([
                        'enrollment_id' => $enrollment->id,
                        'fee_structure_id' => $notif->fee_structure_id,
                        'original_amount' => $amount,
                        'discounted_amount' => $amount,
                        'final_amount' => $amount,
                        'due_date' => $notif->due_date ?? now()->addDays(30),
                        'period_month' => now()->format('Y-m'),
                        'status' => 'pending',
                        'late_fee_applied' => 0,
                        'paid_amount' => 0,
                        'remarks' => 'Auto-created from fee notification (notif_ prefix): ' . ($notif->title ?? ''),
                    ]);

                    $assignments->push($newAssn);
                    $smartIds[] = $newAssn->id;
                }

                $totalDue += $amount;
            }
        }

        // Merge notification IDs from both sources (prefixed and fee_structure_id based)
        $allNotificationIds = array_unique(array_merge($notificationIds, $notificationIdsFromStructure));

        // Check if we found ANY valid items across all categories
        // Use $smartIds/$legacyIds/$notificationIds instead of $feeAssignmentIds because
        // $feeAssignmentIds includes the original input which may contain IDs that were
        // moved between categories during processing (e.g., smart UUIDs that turned out
        // to be fee_structure_ids from notifications).
        $hasValidSmartItems = !empty($smartIds) && $assignments->isNotEmpty();
        $hasValidLegacyItems = !empty($legacyIds) && $legacyRecords->isNotEmpty();
        $hasValidNotificationItems = !empty($notificationIds) || !empty($notificationFeeStructureIds) || !empty($notificationIdsFromStructure);
        
        if (!empty($feeAssignmentIds) && !$hasValidSmartItems && !$hasValidLegacyItems && !$hasValidNotificationItems) {
            return ['success' => false, 'message' => 'No valid pending fee items found for the specified IDs.'];
        }

        if ($totalDue > 0 && $data['amount'] > $totalDue) {
            return ['success' => false, 'message' => "Payment amount ({$data['amount']}) exceeds total due ({$totalDue}) for selected items."];
        }

        // Cash payments are auto-confirmed, others need confirmation
        $autoConfirm = in_array($paymentMethod, ['cash']);
        $status = $autoConfirm ? 'confirmed' : 'pending';

        DB::beginTransaction();
        try {
            $transactionNo = $this->generateTransactionNo();

            $transaction = PaymentTransaction::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $studentId,
                'transaction_no' => $transactionNo,
                'amount' => $data['amount'],
                'payment_method' => $paymentMethod,
                'reference_no' => $data['reference_no'] ?? null,
                'status' => $status,
                'confirmed_by' => $autoConfirm ? $recordedBy : null,
                'confirmed_at' => $autoConfirm ? now() : null,
                'remarks' => $data['remarks'] ?? 'Manual payment by admin',
                'is_manual' => true,
            ]);

            // Auto-confirm any existing pending student payments for this enrollment
            // (student self-paid earlier, admin is now collecting officially via CollectFeePage)
            // Use enrollment-level matching to cover payments without allocations too
            $existingPendingTxns = PaymentTransaction::where('enrollment_id', $enrollment->id)
                ->where('status', 'pending')
                ->get();
            foreach ($existingPendingTxns as $pendingTxn) {
                $pendingTxn->update([
                    'status' => 'confirmed',
                    'confirmed_by' => $recordedBy,
                    'confirmed_at' => now(),
                    'remarks' => ($pendingTxn->remarks ?? '') . ' [Auto-confirmed: admin collected fee for same enrollment]',
                ]);
                Log::info('Auto-confirmed pending student payment via admin collect', [
                    'transaction_id' => $pendingTxn->id,
                    'transaction_no' => $pendingTxn->transaction_no,
                    'admin_id' => $recordedBy,
                ]);
            }

            // If auto-confirmed, allocate to specified assignments or use FIFO
            if ($autoConfirm) {
                if (!empty($smartIds) && $assignments->isNotEmpty()) {
                    $this->allocateToSpecificAssignments($transaction, $assignments);
                }

                // Handle legacy MonthlyFeeRecord payments
                if ($legacyRecords->isNotEmpty()) {
                    $this->allocateToLegacyRecords($transaction, $legacyRecords);
                }

                if (empty($smartIds) && empty($legacyIds)) {
                    $this->autoAllocateToOldestFees($transaction);
                }

                // Mark fee notifications as paid ONLY after successful allocation
                // This ensures notifications are only marked as paid when the payment
                // is actually confirmed (cash payments are auto-confirmed).
                if (!empty($allNotificationIds)) {
                    \Modules\Finance\app\Models\StudentFeeNotification::whereIn('id', $allNotificationIds)
                        ->update(['status' => 'paid', 'read_at' => now()]);

                }

                // Auto-generate invoice
                try {
                    $invoiceService = app(\Modules\Finance\app\Services\SmartInvoiceService::class);
                    $invoiceService->generateInvoice($transaction->fresh());
                } catch (\Exception $invEx) {
                    Log::warning('Invoice generation for manual payment failed: ' . $invEx->getMessage(), [
                        'transaction_id' => $transaction->id,
                    ]);
                }
            }

            $this->logAudit('PaymentTransaction', $transaction->id, 'manual_payment_recorded', [
                'amount' => $data['amount'],
                'method' => $paymentMethod,
                'auto_confirmed' => $autoConfirm,
                'specific_assignments' => !empty($feeAssignmentIds),
                'recorded_by' => $recordedBy,
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'auto_confirmed' => $autoConfirm,
                'message' => $autoConfirm
                    ? 'Manual payment recorded, confirmed, and invoice generated successfully.'
                    : 'Manual payment recorded. Awaiting confirmation.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual payment with allocations failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Manual payment recording failed.'];
        }
    }

    /**
     * Reserve payment allocations for pending transactions without updating assignments.
     */
    private function reserveAllocationsForAssignments(PaymentTransaction $transaction, $assignments): void
    {
        $remainingAmount = $transaction->amount;

        foreach ($assignments as $assignment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $currentDue = ($assignment->final_amount + ($assignment->late_fee_applied ?? 0)) - $assignment->paid_amount;
            if ($currentDue <= 0) {
                continue;
            }

            $allocationAmount = min($remainingAmount, $currentDue);

            PaymentAllocation::create([
                'transaction_id' => $transaction->id,
                'fee_assignment_id' => $assignment->id,
                'amount' => $allocationAmount,
            ]);

            $remainingAmount -= $allocationAmount;
        }

        if ($remainingAmount > 0) {
            $transaction->update(['remarks' => ($transaction->remarks ?? '') . " | Prepayment balance: {$remainingAmount}"]);
        }
    }

    /**
     * Apply reserved allocations when a pending payment is confirmed.
     */
    private function fulfillExistingAllocations(PaymentTransaction $transaction): void
    {
        $allocations = PaymentAllocation::where('transaction_id', $transaction->id)
            ->with('feeAssignment')
            ->get();

        foreach ($allocations as $allocation) {
            $assignment = $allocation->feeAssignment;
            if (!$assignment) {
                continue;
            }

            $allocationAmount = (float) $allocation->amount;
            if ($allocationAmount <= 0) {
                continue;
            }

            $newPaidAmount = $assignment->paid_amount + $allocationAmount;
            $totalDue = $assignment->final_amount + ($assignment->late_fee_applied ?? 0);
            $newStatus = $newPaidAmount >= $totalDue ? 'paid' : 'partial';

            $assignment->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            $this->logAudit('StudentFeeAssignment', $assignment->id, 'payment_allocated', [
                'transaction_id' => $transaction->id,
                'allocated_amount' => $allocationAmount,
                'new_paid_amount' => $newPaidAmount,
                'new_status' => $newStatus,
            ]);

            $this->syncAssignmentPaymentSideEffects($assignment, $newPaidAmount, $newStatus);
        }
    }

    private function syncAssignmentPaymentSideEffects(
        StudentFeeAssignment $assignment,
        float $newPaidAmount,
        string $newStatus
    ): void {
        if ($assignment->period_month && $newStatus === 'paid') {
            try {
                $legacyRecord = \Modules\Enrollment\app\Models\MonthlyFeeRecord::where('enrollment_id', $assignment->enrollment_id)
                    ->where('month', $assignment->period_month)
                    ->first();

                if ($legacyRecord) {
                    $legacyRecord->update([
                        'paid_amount' => $newPaidAmount,
                        'due_amount' => 0,
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('[syncAssignmentPaymentSideEffects] Failed to sync legacy MonthlyFeeRecord', [
                    'period_month' => $assignment->period_month,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($assignment->fee_structure_id) {
            try {
                $notifStatus = ($newStatus === 'paid') ? 'paid' : 'read';
                \Modules\Finance\app\Models\StudentFeeNotification::where('enrollment_id', $assignment->enrollment_id)
                    ->where('fee_structure_id', $assignment->fee_structure_id)
                    ->whereIn('status', ['unread', 'read'])
                    ->update(['status' => $notifStatus, 'read_at' => now()]);
            } catch (\Exception $e) {
                \Log::warning('[syncAssignmentPaymentSideEffects] Failed to sync StudentFeeNotification', [
                    'fee_structure_id' => $assignment->fee_structure_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Allocate payment to specific fee assignments.
     */
    private function allocateToSpecificAssignments(PaymentTransaction $transaction, $assignments): void
    {
        $remainingAmount = $transaction->amount;

        \Log::info('[allocateToSpecificAssignments] Starting allocation', [
            'transaction_id' => $transaction->id,
            'transaction_amount' => $transaction->amount,
            'assignment_count' => $assignments->count(),
        ]);

        foreach ($assignments as $assignment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $currentDue = ($assignment->final_amount + ($assignment->late_fee_applied ?? 0)) - $assignment->paid_amount;

            if ($currentDue <= 0) {
                continue;
            }

            $allocationAmount = min($remainingAmount, $currentDue);

            PaymentAllocation::create([
                'transaction_id' => $transaction->id,
                'fee_assignment_id' => $assignment->id,
                'amount' => $allocationAmount,
            ]);

            $newPaidAmount = $assignment->paid_amount + $allocationAmount;
            $totalDue = $assignment->final_amount + ($assignment->late_fee_applied ?? 0);
            $newStatus = $newPaidAmount >= $totalDue ? 'paid' : 'partial';

            $assignment->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            $remainingAmount -= $allocationAmount;

            $this->logAudit('StudentFeeAssignment', $assignment->id, 'payment_allocated', [
                'transaction_id' => $transaction->id,
                'allocated_amount' => $allocationAmount,
                'new_paid_amount' => $newPaidAmount,
                'new_status' => $newStatus,
            ]);

            $this->syncAssignmentPaymentSideEffects($assignment, $newPaidAmount, $newStatus);
        }

        if ($remainingAmount > 0) {
            $transaction->update(['remarks' => ($transaction->remarks ?? '') . " | Prepayment balance: {$remainingAmount}"]);
        }
    }

    /**
     * Allocate payment to legacy MonthlyFeeRecord items.
     * Updates the record's paid_amount and payment_status directly.
     */
    private function allocateToLegacyRecords(PaymentTransaction $transaction, $legacyRecords): void
    {
        $remainingAmount = $transaction->amount;

        foreach ($legacyRecords as $record) {
            if ($remainingAmount <= 0) {
                break;
            }

            $currentDue = max(0, $record->due_amount - $record->paid_amount);
            if ($currentDue <= 0) {
                continue;
            }

            $allocationAmount = min($remainingAmount, $currentDue);
            $newPaidAmount = $record->paid_amount + $allocationAmount;
            $newStatus = $newPaidAmount >= $record->due_amount ? 'paid' : 'partial';

            $record->update([
                'paid_amount' => $newPaidAmount,
                'due_amount' => max(0, $record->due_amount - $allocationAmount),
                'payment_status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : $record->paid_at,
            ]);

            $remainingAmount -= $allocationAmount;

            $this->logAudit('MonthlyFeeRecord', $record->id, 'legacy_payment_allocated', [
                'transaction_id' => $transaction->id,
                'allocated_amount' => $allocationAmount,
                'new_paid_amount' => $newPaidAmount,
                'new_status' => $newStatus,
            ]);
        }

        if ($remainingAmount > 0) {
            $transaction->update(['remarks' => ($transaction->remarks ?? '') . " | Legacy prepayment balance: {$remainingAmount}"]);
        }
    }

    /**
     * Get pending payments for admin confirmation.
     */
    public function getPendingPayments(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = PaymentTransaction::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                  ->orWhere('gateway_trx_id', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Get all fee assignments with optional filters.
     */
    public function getFeeAssignments(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = StudentFeeAssignment::with(['enrollment.student', 'enrollment.batch.course', 'feeStructure.feeType'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['enrollment_id'])) {
            $query->where('enrollment_id', $filters['enrollment_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['overdue'])) {
            $query->whereIn('status', ['pending', 'partial'])
                ->where('due_date', '<', now());
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Get admin dashboard summary.
     */
    public function getAdminDashboard(): array
    {
        $totalPendingPayments = PaymentTransaction::where('status', 'pending')->count();
        $totalPendingAmount = PaymentTransaction::where('status', 'pending')->sum('amount');

        $totalOverdueAssignments = StudentFeeAssignment::whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now())
            ->count();

        $totalOverdueAmount = StudentFeeAssignment::whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now())
            ->get()
            ->sum(fn($a) => ($a->final_amount + ($a->late_fee_applied ?? 0)) - $a->paid_amount);

        $todayCollection = PaymentTransaction::where('status', 'confirmed')
            ->whereDate('confirmed_at', today())
            ->sum('amount');

        $thisMonthCollection = PaymentTransaction::where('status', 'confirmed')
            ->whereMonth('confirmed_at', now()->month)
            ->whereYear('confirmed_at', now()->year)
            ->sum('amount');

        $totalCollected = PaymentTransaction::where('status', 'confirmed')->sum('amount');

        return [
            'pending_confirmation' => [
                'count' => $totalPendingPayments,
                'amount' => $totalPendingAmount,
            ],
            'overdue' => [
                'count' => $totalOverdueAssignments,
                'amount' => round($totalOverdueAmount, 2),
            ],
            'collection' => [
                'today' => $todayCollection,
                'this_month' => $thisMonthCollection,
                'total' => $totalCollected,
            ],
        ];
    }

    /**
     * Generate a unique transaction number.
     * Format: FEE-YYYYMMDD-XXXXXX (random 6 alphanumeric)
     */
    public function generateTransactionNo(): string
    {
        $prefix = 'FEE-' . now()->format('Ymd') . '-';
        $random = strtoupper(Str::random(6));

        // Ensure uniqueness
        $exists = PaymentTransaction::where('transaction_no', $prefix . $random)->exists();
        if ($exists) {
            return $this->generateTransactionNo(); // Recursive retry
        }

        return $prefix . $random;
    }

    /**
     * Log an audit trail entry.
     */
    private function logAudit(string $entityType, string $entityId, string $action, array $newValues = [], ?array $oldValues = null): void
    {
        try {
            FeeAuditLog::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'performed_by' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }
}