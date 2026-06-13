<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeePayment;
use Modules\Enrollment\app\Services\MonthlyFeeService;
use Modules\Enrollment\app\Services\InvoiceService;
use Modules\Enrollment\app\Services\NotificationService;

class MonthlyFeeController extends BaseApiController
{
    protected MonthlyFeeService $monthlyFeeService;
    protected NotificationService $notificationService;
    protected InvoiceService $invoiceService;

    public function __construct(
        MonthlyFeeService $monthlyFeeService,
        NotificationService $notificationService,
        InvoiceService $invoiceService
    ) {
        $this->monthlyFeeService = $monthlyFeeService;
        $this->notificationService = $notificationService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * List all monthly fee records (admin view).
     */
    public function index(Request $request)
    {
        $filters = $request->only(['enrollment_id', 'month', 'payment_status', 'search', 'per_page']);

        if ($request->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        $records = $this->monthlyFeeService->getAllRecords($filters);
        return $this->paginatedResponse($records);
    }

    /**
     * Get monthly fee records for a specific enrollment.
     */
    public function enrollmentRecords(Request $request, string $enrollmentId)
    {
        $enrollment = Enrollment::with(['student:id,first_name,last_name,student_id', 'batch.course:id,name'])
            ->findOrFail($enrollmentId);

        $records = $this->monthlyFeeService->getRecords(
            $enrollmentId,
            $request->month
        );

        $summary = $this->monthlyFeeService->getSummary($enrollmentId);

        return $this->success([
            'enrollment' => $enrollment,
            'records' => $records,
            'summary' => $summary,
        ]);
    }

    /**
     * Get a single monthly fee record with payments.
     */
    public function show(string $id)
    {
        $record = $this->monthlyFeeService->getRecord($id);
        return $this->success($record);
    }

    /**
     * Record a payment against a monthly fee record (admin/manual).
     * Cash/offline payments are immediately confirmed.
     * bKash/Nagad/online payments go to awaiting_confirmation.
     */
    public function recordPayment(Request $request, string $recordId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bkash,nagad,rocket,bank,card,manual,bank_transfer,online',
            'transaction_id' => 'nullable|string|max:255',
            'sender_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $record = $this->monthlyFeeService->recordPayment(
                $recordId,
                $validated['amount'],
                $validated['payment_method'],
                $validated['transaction_id'] ?? null,
                $validated['reference'] ?? null,
                $validated['note'] ?? null,
                null,
                $validated['sender_number'] ?? null,
                $validated['bank_name'] ?? null,
                $request->user()?->id
            );

            // Send notification for cash payments (immediately confirmed)
            $enrollment = $record->enrollment;
            if ($enrollment && in_array($validated['payment_method'], ['cash', 'bank', 'card', 'manual'])) {
                $this->notificationService->sendPaymentConfirmation(
                    $enrollment,
                    $validated['amount'],
                    $validated['payment_method'],
                    $validated['transaction_id'] ?? null,
                    $record->month
                );
            }

            $message = in_array($validated['payment_method'], ['bkash', 'nagad', 'rocket', 'online', 'bank_transfer'])
                ? 'Payment submitted. Awaiting admin confirmation.'
                : 'Payment recorded successfully';

            return $this->success($record, $message);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get all payments awaiting admin confirmation.
     */
    public function pendingConfirmations(Request $request)
    {
        $filters = $request->only(['payment_method', 'search', 'date_from', 'date_to', 'per_page']);
        $payments = $this->monthlyFeeService->getUnconfirmedPayments($filters);
        return $this->paginatedResponse($payments);
    }

    /**
     * Confirm a pending payment (admin approves student's self-payment).
     * Generates invoice automatically.
     */
    public function confirmPayment(Request $request, string $paymentId)
    {
        $request->validate([
            'confirmed_by' => 'nullable|exists:users,id',
        ]);

        try {
            $confirmedBy = $request->input('confirmed_by', $request->user()?->id);

            if (!$confirmedBy) {
                return $this->error('Confirmation user not identified.', 400);
            }

            $payment = $this->monthlyFeeService->confirmPayment($paymentId, $confirmedBy);

            // Generate invoice
            $invoice = $this->invoiceService->generateInvoice($payment->id);

            // Send notification
            $enrollment = $payment->monthlyFeeRecord->enrollment;
            if ($enrollment) {
                $this->notificationService->sendPaymentConfirmation(
                    $enrollment,
                    $payment->amount,
                    $payment->payment_method,
                    $payment->transaction_id,
                    $payment->monthlyFeeRecord->month
                );
            }

            return $this->success([
                'payment' => $payment->load(['invoice', 'confirmer']),
                'invoice' => $invoice,
            ], 'Payment confirmed and invoice generated successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Reject a pending payment (admin rejects student's self-payment).
     */
    public function rejectPayment(Request $request, string $paymentId)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'rejected_by' => 'nullable|exists:users,id',
        ]);

        try {
            $rejectedBy = $validated['rejected_by'] ?? $request->user()?->id;

            if (!$rejectedBy) {
                return $this->error('Rejection user not identified.', 400);
            }

            $payment = $this->monthlyFeeService->rejectPayment(
                $paymentId,
                $rejectedBy,
                $validated['rejection_reason']
            );

            return $this->success($payment, 'Payment rejected successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get confirmed payments list.
     */
    public function confirmedPayments(Request $request)
    {
        $filters = $request->only(['payment_method', 'search', 'per_page']);
        $payments = $this->monthlyFeeService->getConfirmedPayments($filters);
        return $this->paginatedResponse($payments);
    }

    /**
     * Download invoice for a confirmed payment.
     */
    public function downloadInvoice(string $paymentId)
    {
        try {
            $payment = MonthlyFeePayment::findOrFail($paymentId);

            if ($payment->payment_status !== 'confirmed') {
                return $this->error('Invoice is only available for confirmed payments.', 400);
            }

            // Check if invoice exists, if not generate it
            $invoice = $this->invoiceService->getInvoiceByPayment($paymentId);
            if (!$invoice) {
                $invoice = $this->invoiceService->generateInvoice($paymentId);
            }

            $pdf = $this->invoiceService->downloadInvoice($invoice->id);

            $filename = 'invoice-' . $invoice->invoice_no . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get overdue monthly fee records.
     */
    public function overdue(Request $request)
    {
        $limit = $request->input('per_page', 50);
        $records = $this->monthlyFeeService->getOverdueRecords($limit);
        return $this->success($records);
    }

    /**
     * Get summary for a specific enrollment.
     */
    public function summary(string $enrollmentId)
    {
        $enrollment = Enrollment::with(['student:id,first_name,last_name,student_id', 'batch.course:id,name'])
            ->findOrFail($enrollmentId);

        $summary = $this->monthlyFeeService->getSummary($enrollmentId);

        return $this->success([
            'enrollment' => $enrollment,
            'summary' => $summary,
        ]);
    }

    /**
     * Get current month pending fees.
     */
    public function currentMonthPending()
    {
        $records = $this->monthlyFeeService->getCurrentMonthPending();
        return $this->success($records);
    }
}
