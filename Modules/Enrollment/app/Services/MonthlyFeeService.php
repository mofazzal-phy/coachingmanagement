<?php

namespace Modules\Enrollment\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Models\MonthlyFeePayment;
use Modules\Enrollment\app\Models\PaymentInvoice;
use Modules\Enrollment\app\Models\Payment;

class MonthlyFeeService
{
    /**
     * Generate monthly fee records for a monthly-type enrollment.
     * Called after enrollment is created with fee_type = 'monthly'.
     * Generates records for the full course duration.
     *
     * @param Enrollment $enrollment The enrollment
     * @param float $discountPercent Percentage discount per month (0 = none)
     * @param float $discountFlat Flat amount discount per month (0 = none, preferable for monthly)
     */
    public function generateMonthlyFeeRecords(Enrollment $enrollment, float $discountPercent = 0, float $discountFlat = 0): void
    {
        if ($enrollment->fee_type !== 'monthly') {
            return;
        }

        $course = $enrollment->batch?->course;
        if (!$course) {
            throw new \Exception('Course not found for enrollment batch.');
        }

        $durationMonths = $course->total_months ?: ($course->duration_days ? max(1, round($course->duration_days / 30)) : 12);
        $startMonth = now()->startOfMonth();

        // Calculate monthly fee from subjects (full rate before discount)
        $monthlyFee = $this->calculateMonthlyFee($enrollment);

        // Calculate discounted amount per month
        // Priority: flat discount > percentage discount
        if ($discountFlat > 0) {
            // Flat discount per month (e.g., ৳500 off each month)
            $discountedMonthlyFee = max(0, $monthlyFee - $discountFlat);
        } elseif ($discountPercent > 0) {
            // Percentage discount per month (e.g., 10% off each month)
            $discountedMonthlyFee = $monthlyFee - ($monthlyFee * $discountPercent / 100);
        } else {
            // No discount
            $discountedMonthlyFee = $monthlyFee;
        }

        $records = [];
        for ($i = 0; $i < $durationMonths; $i++) {
            $month = $startMonth->copy()->addMonths($i)->format('Y-m');
            $dueDate = $startMonth->copy()->addMonths($i)->day(25);

            $records[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'enrollment_id' => $enrollment->id,
                'month' => $month,
                'total_monthly_fee' => $monthlyFee,
                'paid_amount' => 0,
                'due_amount' => $discountedMonthlyFee,
                'payment_status' => 'pending',
                'due_date' => $dueDate,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert
        MonthlyFeeRecord::insert($records);

        // Update enrollment total_months
        $enrollment->updateQuietly([
            'total_months' => $durationMonths,
            'paid_months' => 0,
        ]);
    }

    /**
     * Calculate the monthly fee for an enrollment based on its subjects.
     * Sums the monthly_fee from course_subject pivot for each enrolled subject.
     */
    public function calculateMonthlyFee(Enrollment $enrollment): float
    {
        $course = $enrollment->batch?->course;
        if (!$course) {
            return 0;
        }

        $enrolledSubjectIds = $enrollment->subjects()->pluck('subjects.id')->toArray();

        if (empty($enrolledSubjectIds)) {
            // No specific subjects selected — sum all mandatory subjects' monthly_fee
            $subjects = $course->subjects()->wherePivot('is_mandatory', true)->get();
            $total = 0;
            foreach ($subjects as $subject) {
                $total += (float) ($subject->pivot->monthly_fee ?? 0);
            }
            return $total;
        }

        // Sum monthly_fee for selected subjects
        $subjects = $course->subjects()->whereIn('subjects.id', $enrolledSubjectIds)->get();
        $total = 0;
        foreach ($subjects as $subject) {
            $total += (float) ($subject->pivot->monthly_fee ?? 0);
        }
        return $total;
    }

    /**
     * Sync legacy MonthlyFeeRecord payment status from smart StudentFeeAssignment records.
     * This ensures that when a monthly fee is paid via the combined exam+monthly fee flow
     * (which creates smart assignments), the legacy records stay in sync.
     */
    public function syncFromSmartAssignments(string $enrollmentId): void
    {
        try {
            $smartAssignments = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
                ->whereNotNull('period_month')
                ->whereIn('status', ['paid', 'partial'])
                ->get();

            foreach ($smartAssignments as $assignment) {
                $legacyRecord = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
                    ->where('month', $assignment->period_month)
                    ->first();

                if (!$legacyRecord || $legacyRecord->payment_status === 'paid') {
                    continue;
                }

                $newPaidAmount = $assignment->paid_amount;
                $totalDue = $legacyRecord->due_amount;
                $remainingDue = max(0, $totalDue - $newPaidAmount);
                $newStatus = $remainingDue <= 0 ? 'paid' : 'partial';

                $legacyRecord->update([
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $newStatus,
                    'paid_at' => $newStatus === 'paid' ? now() : null,
                ]);
            }

            // Also sync enrollment totals
            $this->syncEnrollmentPaymentTotals($enrollmentId);
        } catch (\Exception $e) {
            Log::warning('[MonthlyFeeService] Failed to sync from smart assignments: ' . $e->getMessage(), [
                'enrollment_id' => $enrollmentId,
            ]);
        }
    }

    /**
     * Get monthly fee records for an enrollment, optionally filtered by month.
     */
    public function getRecords(string $enrollmentId, ?string $month = null)
    {
        // Sync legacy records from smart assignments before returning
        $this->syncFromSmartAssignments($enrollmentId);

        $query = MonthlyFeeRecord::with(['payments', 'confirmedPayments', 'unconfirmedPayments'])
            ->where('enrollment_id', $enrollmentId)
            ->orderBy('month', 'asc');

        if ($month) {
            $query->where('month', $month);
        }

        $records = $query->get();

        // Auto-update fine for overdue unpaid months
        foreach ($records as $record) {
            if ($record->payment_status !== 'paid' && $record->due_date && $record->due_date < now()) {
                $daysOverdue = $record->due_date->diffInDays(now());
                $fine = min($daysOverdue * 50, 500); // ৳50/day cap at ৳500
                if ($record->fine_amount != $fine) {
                    $record->updateQuietly(['fine_amount' => $fine]);
                    $record->fine_amount = $fine;
                }
            }
            // Add computed 'balance' field: remaining amount to pay (original due minus paid, plus fine)
            // We do NOT modify due_amount here because the frontend uses due_amount for display
            // (discount calculation = total_monthly_fee - due_amount) and calculates remaining due
            // as max(0, due_amount - paid_amount) + fine_amount
            $record->balance = max(0, $record->due_amount - $record->paid_amount) + (float) ($record->fine_amount ?? 0);
        }

        return $records;
    }

    /**
     * Get a single monthly fee record by ID.
     */
    public function getRecord(string $recordId): MonthlyFeeRecord
    {
        return MonthlyFeeRecord::with([
            'enrollment.student',
            'enrollment.batch.course',
            'payments',
            'confirmedPayments',
            'unconfirmedPayments',
        ])->findOrFail($recordId);
    }

    /**
     * Get overdue monthly fee records (past due_date and not fully paid).
     */
    public function getOverdueRecords(int $limit = 50)
    {
        return MonthlyFeeRecord::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending monthly fee records due this month.
     */
    public function getCurrentMonthPending()
    {
        $currentMonth = now()->format('Y-m');

        return MonthlyFeeRecord::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('month', $currentMonth)
            ->where('payment_status', '!=', 'paid')
            ->get();
    }

    /**
     * Record a payment against a monthly fee record.
     *
     * For cash/offline payments (admin-recorded), the payment is immediately confirmed.
     * For online/mobile banking payments (student self-payments), the payment is
     * created with 'awaiting_confirmation' status until admin verifies.
     */
    public function recordPayment(
        string $recordId,
        float $amount,
        string $paymentMethod = 'cash',
        ?string $transactionId = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $paymentId = null,
        ?string $senderNumber = null,
        ?string $bankName = null,
        ?string $confirmedBy = null
    ): MonthlyFeeRecord {
        return DB::transaction(function () use (
            $recordId, $amount, $paymentMethod, $transactionId,
            $reference, $note, $paymentId, $senderNumber, $bankName, $confirmedBy
        ) {
            $record = MonthlyFeeRecord::findOrFail($recordId);

            // Guard: Check for existing pending payment on this record
            $existingPending = MonthlyFeePayment::where('monthly_fee_record_id', $recordId)
                ->whereIn('payment_status', ['awaiting_confirmation'])
                ->exists();
            if ($existingPending) {
                throw new \Exception('This month already has a payment awaiting admin confirmation. Please wait until it is approved or rejected before making another payment.');
            }

            // Determine payment status based on method
            // Cash/cheque/bank (admin-manual) → immediately confirmed
            // bKash/Nagad/rocket/online (self-payment) → awaiting confirmation
            $requiresConfirmation = in_array($paymentMethod, [
                'bkash', 'nagad', 'rocket', 'online', 'bank_transfer',
            ]);

            $paymentStatus = $requiresConfirmation ? 'awaiting_confirmation' : 'confirmed';

            // Create monthly fee payment record
            $payment = MonthlyFeePayment::create([
                'monthly_fee_record_id' => $record->id,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'sender_number' => $senderNumber,
                'bank_name' => $bankName,
                'reference' => $reference,
                'payment_date' => now(),
                'note' => $note,
                'payment_status' => $paymentStatus,
                'confirmed_by' => $requiresConfirmation ? null : $confirmedBy,
                'confirmed_at' => $requiresConfirmation ? null : now(),
            ]);

            // If immediately confirmed (cash/offline), update the record totals
            if (!$requiresConfirmation) {
                $this->applyPaymentToRecord($record, $amount);
            }

            return $record->fresh(['payments', 'confirmedPayments', 'unconfirmedPayments']);
        });
    }

    /**
     * Record a pending payment (student self-payment via bKash/Nagad/etc.).
     * Creates the payment with 'awaiting_confirmation' status.
     * Admin must confirm via confirmPayment() before it affects balances.
     */
    public function recordPendingPayment(
        string $recordId,
        float $amount,
        string $paymentMethod,
        ?string $transactionId = null,
        ?string $senderNumber = null,
        ?string $bankName = null,
        ?string $reference = null,
        ?string $note = null
    ): MonthlyFeePayment {
        return DB::transaction(function () use (
            $recordId, $amount, $paymentMethod, $transactionId,
            $senderNumber, $bankName, $reference, $note
        ) {
            $record = MonthlyFeeRecord::findOrFail($recordId);

            $payment = MonthlyFeePayment::create([
                'monthly_fee_record_id' => $record->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'sender_number' => $senderNumber,
                'bank_name' => $bankName,
                'reference' => $reference,
                'payment_date' => now(),
                'note' => $note,
                'payment_status' => 'awaiting_confirmation',
            ]);

            return $payment->load(['monthlyFeeRecord.enrollment.student']);
        });
    }

    /**
     * Confirm a pending payment (admin approves a student's self-payment).
     * Transitions payment_status from 'awaiting_confirmation' to 'confirmed',
     * generates an invoice, and updates the monthly fee record totals.
     */
    public function confirmPayment(string $paymentId, string $confirmedBy): MonthlyFeePayment
    {
        return DB::transaction(function () use ($paymentId, $confirmedBy) {
            $payment = MonthlyFeePayment::findOrFail($paymentId);

            if ($payment->payment_status !== 'awaiting_confirmation') {
                throw new \Exception('Payment is not in awaiting_confirmation status.');
            }

            // Mark as confirmed
            $payment->update([
                'payment_status' => 'confirmed',
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now(),
            ]);

            // Generate invoice number if not already set
            if (empty($payment->invoice_no)) {
                $invoiceNo = $this->generateInvoiceNo();
                $payment->updateQuietly(['invoice_no' => $invoiceNo]);
            }

            // Apply payment to the monthly fee record
            $record = $payment->monthlyFeeRecord;
            $this->applyPaymentToRecord($record, $payment->amount);

            return $payment->fresh([
                'monthlyFeeRecord',
                'invoice',
                'confirmer',
            ]);
        });
    }

    /**
     * Reject a pending payment (admin rejects a student's self-payment).
     * Transitions payment_status from 'awaiting_confirmation' to 'rejected'
     * with a rejection reason. No invoice is generated, no totals updated.
     */
    public function rejectPayment(string $paymentId, string $rejectedBy, string $reason): MonthlyFeePayment
    {
        return DB::transaction(function () use ($paymentId, $rejectedBy, $reason) {
            $payment = MonthlyFeePayment::findOrFail($paymentId);

            if ($payment->payment_status !== 'awaiting_confirmation') {
                throw new \Exception('Payment is not in awaiting_confirmation status.');
            }

            $payment->update([
                'payment_status' => 'rejected',
                'confirmed_by' => $rejectedBy,
                'confirmed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $payment->fresh(['monthlyFeeRecord.enrollment.student']);
        });
    }

    /**
     * Get all payments awaiting admin confirmation.
     */
    public function getUnconfirmedPayments(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = MonthlyFeePayment::with([
            'monthlyFeeRecord.enrollment.student:id,first_name,last_name,student_id,phone',
            'monthlyFeeRecord.enrollment.batch.course:id,name',
        ])->where('payment_status', 'awaiting_confirmation');

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('sender_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('monthlyFeeRecord.enrollment.student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('payment_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get all confirmed payments with optional filters.
     */
    public function getConfirmedPayments(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = MonthlyFeePayment::with([
            'monthlyFeeRecord.enrollment.student:id,first_name,last_name,student_id,phone',
            'monthlyFeeRecord.enrollment.batch.course:id,name',
            'invoice',
            'confirmer:id,name',
        ])->where('payment_status', 'confirmed');

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('monthlyFeeRecord.enrollment.student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('confirmed_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Generate a unique invoice number.
     * Format: INV-YYYYMM-XXXXX (sequential per month)
     */
    public function generateInvoiceNo(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $lastInvoice = PaymentInvoice::where('invoice_no', 'like', $prefix . '%')
            ->orderBy('invoice_no', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_no, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string) $newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Apply a confirmed payment amount to a monthly fee record.
     * Updates paid_amount, due_amount, payment_status, and paid_at.
     */
    private function applyPaymentToRecord(MonthlyFeeRecord $record, float $amount): void
    {
        $newPaid = $record->paid_amount + $amount;
        // CRITICAL: Do NOT modify due_amount here!
        // due_amount stores the ORIGINAL discounted amount (e.g., 2600 after 500 discount).
        // It must remain unchanged so the frontend can calculate:
        //   discount = total_monthly_fee - due_amount
        //   remaining_due = due_amount - paid_amount
        // If we overwrite due_amount here, the discount calculation breaks.
        $remainingDue = max(0, $record->due_amount - $newPaid);
        $paymentStatus = $remainingDue <= 0 ? 'paid' : 'partial';

        $record->update([
            'paid_amount' => $newPaid,
            // due_amount intentionally NOT updated — it stays as the original discounted amount
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
        ]);

        // Sync enrollment totals
        $this->syncEnrollmentPaymentTotals($record->enrollment_id);
    }

    /**
     * Sync the enrollment's overall paid/due amounts based on all monthly fee records.
     * Also updates paid_months count on the enrollment.
     */
    public function syncEnrollmentPaymentTotals(string $enrollmentId): void
    {
        $enrollment = Enrollment::find($enrollmentId);
        if (!$enrollment || $enrollment->fee_type !== 'monthly') {
            return;
        }

        $records = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)->orderBy('month','asc')->get();
        $totalPaid = $records->sum('paid_amount');
        $totalUndiscounted = $records->sum('total_monthly_fee');
        $totalPayable = $records->sum('due_amount');
        $totalRemainingDue = $records->sum(fn($r) => max(0, $r->due_amount - $r->paid_amount));
        $allPaid = $records->every(fn($r) => $r->payment_status === 'paid');
        $paidMonthsCount = $records->where('payment_status', 'paid')->count();

        // For monthly fee type, enrollment is "paid" if current month is paid
        $currentMonthStr = now()->format('Y-m');
        $currentMonthRecord = $records->firstWhere('month', $currentMonthStr);
        $currentMonthPaid = $currentMonthRecord && $currentMonthRecord->payment_status === 'paid';
        // If no current month record exists, check if first unpaid month exists in past
        $firstUnpaid = $records->first(fn($r) => $r->payment_status !== 'paid');
        $isUpToDate = $firstUnpaid ? $firstUnpaid->month > $currentMonthStr : true;

        $enrollment->update([
            'paid_amount' => $totalPaid,
            'due_amount' => max(0, $totalRemainingDue),
            'total_fee' => $totalUndiscounted,
            'payable_fee' => $totalPayable,
            'paid_months' => $paidMonthsCount,
            'payment_status' => $allPaid ? 'paid' : ($currentMonthPaid ? 'paid' : ($totalPaid > 0 ? 'partial' : 'pending')),
            'status' => $totalPaid > 0 ? 'active' : $enrollment->status,
        ]);
    }

    /**
     * Get monthly fee summary for an enrollment.
     */
    public function getSummary(string $enrollmentId): array
    {
        // Sync legacy records from smart assignments before building summary
        $this->syncFromSmartAssignments($enrollmentId);

        $records = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)->orderBy('month', 'asc')->get();
        $enrollment = Enrollment::with('student')->find($enrollmentId);

        // Find current month record
        $currentMonthStr = now()->format('Y-m');
        $currentMonthRecord = $records->firstWhere('month', $currentMonthStr);

        // Find next unpaid month (first record that is not fully paid)
        $nextUnpaidMonth = $records->first(function ($rec) {
            return $rec->payment_status !== 'paid';
        });

        // Find last paid month (most recent record that is fully paid)
        $lastPaidMonth = $records->filter(function ($rec) {
            return $rec->payment_status === 'paid';
        })->last();

        // Format month names
        $currentMonthName = $currentMonthRecord
            ? \Carbon\Carbon::parse($currentMonthRecord->month . '-01')->format('F Y')
            : null;

        $nextMonthName = $nextUnpaidMonth
            ? \Carbon\Carbon::parse($nextUnpaidMonth->month . '-01')->format('F Y')
            : null;

        $lastPaidMonthName = $lastPaidMonth
            ? \Carbon\Carbon::parse($lastPaidMonth->month . '-01')->format('F Y')
            : null;

        $totalUndiscounted = $records->sum('total_monthly_fee');
        $totalDiscounted = $records->sum('due_amount');
        // Calculate remaining due: sum of (due_amount - paid_amount) for each record
        // This correctly accounts for partial payments across multiple months
        $totalRemainingDue = $records->sum(fn($r) => max(0, $r->due_amount - $r->paid_amount));

        // Add computed 'balance' field to each record for frontend consistency
        // (same as getRecords() does, so the frontend always has balance available)
        $recordsArray = $records->map(function ($rec) {
            $data = $rec->toArray();
            $data['balance'] = max(0, $rec->due_amount - $rec->paid_amount) + (float) ($rec->fine_amount ?? 0);
            return $data;
        })->values()->toArray();

        return [
            'total_months' => $records->count(),
            'total_fee' => $totalUndiscounted,
            'total_discount' => $totalUndiscounted - $totalDiscounted,
            'total_paid' => $records->sum('paid_amount'),
            'total_due' => $totalRemainingDue,
            'paid_months' => $records->where('payment_status', 'paid')->count(),
            'pending_months' => $records->where('payment_status', 'pending')->count(),
            'partial_months' => $records->where('payment_status', 'partial')->count(),
            'overdue_months' => $records->where('payment_status', '!=', 'paid')
                ->where('due_date', '<', now())
                ->count(),
            'current_month' => $currentMonthRecord,
            'current_month_name' => $currentMonthName,
            'next_unpaid_month' => $nextUnpaidMonth,
            'next_month_name' => $nextMonthName,
            'next_month_due' => $nextUnpaidMonth ? (float) max(0, $nextUnpaidMonth->due_amount - $nextUnpaidMonth->paid_amount) : 0,
            'next_month_status' => $nextUnpaidMonth ? $nextUnpaidMonth->payment_status : null,
            'last_paid_month' => $lastPaidMonth,
            'last_paid_month_name' => $lastPaidMonthName,
            'last_paid_amount' => $lastPaidMonth ? (float) $lastPaidMonth->paid_amount : 0,
            'records' => $recordsArray,
            'student_name' => $enrollment?->student?->full_name ?? 'N/A',
            'student_id' => $enrollment?->student?->student_id ?? 'N/A',
            'course_name' => $enrollment?->batch?->course?->name ?? 'N/A',
            'enrollment_status' => $enrollment?->status,
            'payment_status' => $enrollment?->payment_status,
        ];
    }

    /**
     * Get all monthly fee records with pagination (admin listing).
     */
    public function getAllRecords(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = MonthlyFeeRecord::with([
            'enrollment.student:id,first_name,last_name,student_id,phone',
            'enrollment.batch.course:id,name',
            'confirmedPayments',
            'unconfirmedPayments',
        ]);

        if (!empty($filters['enrollment_id'])) {
            $query->where('enrollment_id', $filters['enrollment_id']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('payment_status', '!=', 'paid')
                ->where('due_date', '<', now());
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('enrollment.student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}
