<?php

namespace Modules\Enrollment\app\Services;

use Modules\Enrollment\app\Models\Enrollment;

class PaymentService
{
    /**
     * Record a payment and generate receipt number.
     */
    public function recordPayment(Enrollment $enrollment, float $amount, string $method = 'cash', ?string $reference = null, ?string $transactionId = null): \Modules\Enrollment\app\Models\Payment
    {
        $receiptNo = $this->generateReceiptNo();

        return \Modules\Enrollment\app\Models\Payment::create([
            'enrollment_id' => $enrollment->id,
            'receipt_no' => $receiptNo,
            'payment_method' => $method,
            'amount' => $amount,
            'received_amount' => $amount,
            'transaction_id' => $transactionId,
            'reference' => $reference,
            'payment_date' => now(),
            'payment_status' => 'paid',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
    }

    /**
     * Refund the latest payment for an enrollment.
     */
    public function refundPayment(\Modules\Enrollment\app\Models\Enrollment $enrollment): ?\Modules\Enrollment\app\Models\Payment
    {
        $payment = \Modules\Enrollment\app\Models\Payment::where('enrollment_id', $enrollment->id)
            ->where('payment_status', 'paid')
            ->latest()->first();

        if (!$payment) return null;

        $payment->update(['payment_status' => 'refunded']);

        $enrollment->update([
            'paid_amount' => $enrollment->paid_amount - $payment->amount,
            'due_amount' => $enrollment->due_amount + $payment->amount,
            'payment_status' => $enrollment->paid_amount - $payment->amount <= 0 ? 'pending' : 'partial',
            'status' => $enrollment->paid_amount - $payment->amount <= 0 ? 'pending' : $enrollment->status,
        ]);

        return $payment->fresh();
    }

    /**
     * Generate sequential receipt number: RCP-{YEAR}-{6-DIGIT-SEQ}
     */
    public function generateReceiptNo(): string
    {
        $year = now()->format('Y');
        $last = \Modules\Enrollment\app\Models\Payment::whereYear('created_at', $year)
            ->whereNotNull('receipt_no')
            ->orderBy('receipt_no', 'desc')
            ->first();

        $seq = $last ? (int) substr($last->receipt_no, -6) + 1 : 1;
        return 'RCP-' . $year . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get payment history for an enrollment.
     */
    public function getPaymentHistory(string $enrollmentId): array
    {
        return \Modules\Enrollment\app\Models\Payment::where('enrollment_id', $enrollmentId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
