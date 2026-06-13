<?php

namespace Modules\Finance\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Finance\app\Models\SmartPaymentInvoice;

class SmartInvoiceService
{
    /**
     * Generate and save an invoice PDF for a confirmed PaymentTransaction.
     *
     * @param PaymentTransaction $transaction
     * @return SmartPaymentInvoice|null
     */
    public function generateInvoice(PaymentTransaction $transaction): ?SmartPaymentInvoice
    {
        if ($transaction->status !== 'confirmed') {
            throw new \Exception('Invoice can only be generated for confirmed payments.');
        }

        // Check if invoice already exists for this transaction
        $existingInvoice = SmartPaymentInvoice::where('payment_transaction_id', $transaction->id)->first();
        if ($existingInvoice) {
            return $existingInvoice;
        }

        // Generate invoice number
        $invoiceNo = $this->generateInvoiceNo();

        $enrollment = $transaction->enrollment;
        $student = $transaction->student;
        $course = $enrollment?->batch?->course;

        // Resolve month/period description from PaymentAllocations
        $periodDescription = $this->resolvePeriodDescription($transaction);

        // Extract legacy month info from transaction remarks (set by autoAllocateToOldestFees)
        $legacyMonths = [];
        if ($transaction->remarks && preg_match('/Legacy months:\s*([\d,\-]+)/', $transaction->remarks, $m)) {
            $legacyMonths = explode(',', $m[1]);
        }

        // Prepare invoice data
        $metadata = [
            'amount' => $transaction->amount,
            'payment_method' => $transaction->payment_method,
            'transaction_no' => $transaction->transaction_no,
            'gateway_trx_id' => $transaction->gateway_trx_id,
            'reference_no' => $transaction->reference_no,
            'confirmed_by' => $transaction->confirmed_by,
            'confirmed_at' => $transaction->confirmed_at?->toDateTimeString(),
            'is_manual' => $transaction->is_manual,
            'enrollment_id' => $transaction->enrollment_id,
            'student_id' => $transaction->student_id,
            'period_description' => $periodDescription,
            'legacy_months' => $legacyMonths,
        ];

        // Create invoice record
        $invoice = SmartPaymentInvoice::create([
            'payment_transaction_id' => $transaction->id,
            'invoice_no' => $invoiceNo,
            'invoice_type' => 'payment',
            'generated_at' => now(),
            'generated_by' => $transaction->confirmed_by ?? auth()->id(),
            'metadata' => $metadata,
        ]);

        // Generate PDF
        try {
            $pdfContent = $this->renderPdf($transaction, $invoice, $student, $course, $enrollment);

            // Save PDF to storage
            $filePath = 'invoices/smart/' . $invoiceNo . '.pdf';
            Storage::disk('local')->put($filePath, $pdfContent);

            // Update invoice with file path
            $invoice->updateQuietly(['file_path' => $filePath]);
        } catch (\Exception $e) {
            Log::error('Smart invoice PDF generation failed: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'trace' => $e->getTraceAsString(),
            ]);
            // Invoice record still created even if PDF fails
        }

        return $invoice->fresh();
    }

    /**
     * Render the invoice PDF.
     */
    private function renderPdf(
        PaymentTransaction $transaction,
        SmartPaymentInvoice $invoice,
        $student,
        $course,
        $enrollment
    ): string {
        $paymentMethodLabels = [
            'cash' => 'Cash',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
            'check' => 'Cheque',
        ];

        $methodLabel = $paymentMethodLabels[$transaction->payment_method] ?? ucfirst($transaction->payment_method);
        $confirmedByName = $transaction->confirmedBy?->name ?? 'System';
        $confirmedAt = $transaction->confirmed_at?->format('d M, Y h:i A') ?? 'N/A';
        $gatewayTrxId = $transaction->gateway_trx_id ?? 'N/A';
        $referenceNo = $transaction->reference_no ?? 'N/A';

        // Get period description from metadata or resolve it
        $periodDescription = $invoice->metadata['period_description'] ?? $this->resolvePeriodDescription($transaction);

        $data = [
            'invoice' => $invoice,
            'transaction' => $transaction,
            'student' => $student,
            'course' => $course,
            'enrollment' => $enrollment,
            'method_label' => $methodLabel,
            'confirmed_by_name' => $confirmedByName,
            'confirmed_at' => $confirmedAt,
            'gateway_trx_id' => $gatewayTrxId,
            'reference_no' => $referenceNo,
            'period_description' => $periodDescription,
            'institute_name' => config('app.name', 'Coaching Management System'),
            'institute_address' => config('app.address', ''),
            'institute_phone' => config('app.phone', ''),
            'institute_email' => config('app.email', ''),
        ];

        $html = $this->getInvoiceHtml($data);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Get the invoice HTML template.
     */
    private function getInvoiceHtml(array $data): string
    {
        extract($data);

        $transactionId = $transaction->transaction_no ?? 'N/A';
        $paymentDate = $transaction->created_at->format('d M, Y h:i A');
        $studentName = $student?->full_name ?? $student?->name ?? 'N/A';
        $studentId = $student?->student_id ?? 'N/A';
        $courseName = $course?->name ?? ($enrollment?->batch?->course?->name ?? 'N/A');
        $batchName = $enrollment?->batch?->name ?? 'N/A';
        $periodDescription = $period_description ?? 'N/A';

        // Build fee breakdown from allocations
        $allocations = \Modules\Finance\app\Models\PaymentAllocation::with([
            'feeAssignment.feeStructure.feeType'
        ])
            ->where('transaction_id', $transaction->id)
            ->get();

        $feeBreakdownRows = '';
        $categoryLabels = [
            'one_time' => 'One-Time',
            'monthly' => 'Monthly',
            'event_based' => 'Event',
        ];

        if ($allocations->isNotEmpty()) {
            foreach ($allocations as $alloc) {
                $assignment = $alloc->feeAssignment;
                if (!$assignment) continue;

                $feeType = $assignment->feeStructure?->feeType;
                $feeTypeName = $feeType?->name ?? 'Fee';
                $category = $feeType?->category ?? 'monthly';
                $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
                $installment = $assignment->installment_number ? " (Installment {$assignment->installment_number})" : '';

                // Resolve period label
                $periodLabel = '';
                if ($assignment->period_month) {
                    $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $assignment->period_month)?->format('F Y') ?? $assignment->period_month;
                } else {
                    $periodLabel = $assignment->due_date?->format('M Y') ?? '';
                }

                $description = htmlspecialchars("{$feeTypeName}{$installment} - {$periodLabel}");
                $amount = number_format($alloc->amount, 2);

                $feeBreakdownRows .= <<<ROW
                <tr>
                    <td><span style="display:inline-block;background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold;margin-right:4px;">{$categoryLabel}</span> {$description}</td>
                    <td style="text-align:right;">৳ {$amount}</td>
                </tr>
ROW;
            }
        }

        // If no allocations, show a single row with the period description
        if (empty($feeBreakdownRows)) {
            $feeBreakdownRows = <<<ROW
                <tr>
                    <td>Payment for {$courseName} ({$batchName})<br><small style="color: #666;">Period: {$periodDescription}</small></td>
                    <td style="text-align: right;">৳ {$transaction->amount}</td>
                </tr>
ROW;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .invoice-box {
            max-width: 700px;
            margin: 0 auto;
            padding: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1a56db;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        .header .institute-info {
            color: #777;
            font-size: 11px;
            margin-top: 5px;
        }
        .invoice-title {
            text-align: center;
            margin: 20px 0;
        }
        .invoice-title h3 {
            font-size: 16px;
            color: #1a56db;
            border: 1px solid #1a56db;
            display: inline-block;
            padding: 8px 30px;
            border-radius: 4px;
            margin: 0;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-section td {
            padding: 4px 8px;
            font-size: 12px;
        }
        .info-section .label {
            font-weight: bold;
            color: #555;
            width: 140px;
        }
        .info-section .value {
            color: #333;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .details-table th {
            background-color: #1a56db;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
        }
        .details-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
        }
        .details-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .total-row td {
            font-weight: bold;
            border-top: 2px solid #1a56db;
            font-size: 14px;
        }
        .payment-info {
            background-color: #f0f7ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .payment-info h4 {
            color: #1a56db;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .payment-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-info td {
            padding: 3px 8px;
            font-size: 11px;
        }
        .payment-info .label {
            font-weight: bold;
            color: #555;
            width: 140px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #999;
        }
        .footer p {
            margin: 2px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            background-color: #d1fae5;
            color: #065f46;
        }
        .amount-in-words {
            font-style: italic;
            color: #555;
            font-size: 11px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <h1>{$institute_name}</h1>
            <div class="institute-info">
                {$institute_address}<br>
                Phone: {$institute_phone} | Email: {$institute_email}
            </div>
        </div>

        <div class="invoice-title">
            <h3>PAYMENT RECEIPT</h3>
        </div>

        <div class="info-section">
            <table>
                <tr>
                    <td class="label">Invoice No:</td>
                    <td class="value"><strong>{$invoice->invoice_no}</strong></td>
                    <td class="label">Date:</td>
                    <td class="value">{$invoice->generated_at->format('d M, Y')}</td>
                </tr>
                <tr>
                    <td class="label">Student Name:</td>
                    <td class="value">{$studentName}</td>
                    <td class="label">Student ID:</td>
                    <td class="value">{$studentId}</td>
                </tr>
                <tr>
                    <td class="label">Course:</td>
                    <td class="value">{$courseName}</td>
                    <td class="label">Batch:</td>
                    <td class="value">{$batchName}</td>
                </tr>
                <tr>
                    <td class="label">Status:</td>
                    <td class="value" colspan="3"><span class="status-badge">PAID</span></td>
                </tr>
            </table>
        </div>

        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Description</th>
                    <th style="width: 40%; text-align: right;">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                {$feeBreakdownRows}
                <tr class="total-row">
                    <td><strong>Total Paid</strong></td>
                    <td style="text-align: right;"><strong>৳ {$transaction->amount}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="payment-info">
            <h4>Payment Details</h4>
            <table>
                <tr>
                    <td class="label">Payment Method:</td>
                    <td>{$method_label}</td>
                </tr>
                <tr>
                    <td class="label">Transaction No:</td>
                    <td>{$transactionId}</td>
                </tr>
                <tr>
                    <td class="label">Gateway Trx ID:</td>
                    <td>{$gateway_trx_id}</td>
                </tr>
                <tr>
                    <td class="label">Reference No:</td>
                    <td>{$reference_no}</td>
                </tr>
                <tr>
                    <td class="label">Payment Date:</td>
                    <td>{$paymentDate}</td>
                </tr>
                <tr>
                    <td class="label">Confirmed By:</td>
                    <td>{$confirmed_by_name}</td>
                </tr>
                <tr>
                    <td class="label">Confirmed At:</td>
                    <td>{$confirmed_at}</td>
                </tr>
            </table>
        </div>

        <div class="amount-in-words">
            Amount in words: {$this->numberToWords($transaction->amount)} Taka only.
        </div>

        <div class="footer">
            <p>This is a computer-generated receipt. No signature is required.</p>
            <p>{$institute_name} | {$institute_address}</p>
            <p>Thank you for your payment!</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Download an invoice PDF by invoice ID.
     *
     * @param string $invoiceId
     * @return \Barryvdh\DomPDF\PDF|null
     */
    public function downloadInvoice(string $invoiceId)
    {
        $invoice = SmartPaymentInvoice::with([
            'paymentTransaction.enrollment.student',
            'paymentTransaction.enrollment.batch.course',
            'paymentTransaction.confirmedBy',
        ])->findOrFail($invoiceId);

        $transaction = $invoice->paymentTransaction;
        $enrollment = $transaction->enrollment;
        $student = $transaction->student;
        $course = $enrollment?->batch?->course;

        // If file exists on disk, return it
        if ($invoice->file_path && Storage::disk('local')->exists($invoice->file_path)) {
            return Storage::disk('local')->download($invoice->file_path, $invoice->invoice_no . '.pdf');
        }

        // Otherwise generate and return
        $html = $this->getInvoiceHtml([
            'invoice' => $invoice,
            'transaction' => $transaction,
            'student' => $student,
            'course' => $course,
            'enrollment' => $enrollment,
            'method_label' => $this->getMethodLabel($transaction->payment_method),
            'confirmed_by_name' => $transaction->confirmedBy?->name ?? 'System',
            'confirmed_at' => $transaction->confirmed_at?->format('d M, Y h:i A') ?? 'N/A',
            'gateway_trx_id' => $transaction->gateway_trx_id ?? 'N/A',
            'reference_no' => $transaction->reference_no ?? 'N/A',
            'institute_name' => config('app.name', 'Coaching Management System'),
            'institute_address' => config('app.address', ''),
            'institute_phone' => config('app.phone', ''),
            'institute_email' => config('app.email', ''),
        ]);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download($invoice->invoice_no . '.pdf');
    }

    /**
     * Get invoice by transaction ID.
     */
    public function getInvoiceByTransaction(string $transactionId): ?SmartPaymentInvoice
    {
        return SmartPaymentInvoice::where('payment_transaction_id', $transactionId)->first();
    }

    /**
     * Generate a unique invoice number.
     * Format: SINV-YYYYMM-XXXXX
     */
    public function generateInvoiceNo(): string
    {
        $prefix = 'SINV-' . now()->format('Ym') . '-';
        $lastInvoice = SmartPaymentInvoice::where('invoice_no', 'like', $prefix . '%')
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
     * Resolve the month/period description for a payment transaction.
     * Looks up PaymentAllocations to find which fee assignments were paid,
     * then extracts the fee type name and due date to build a period description.
     */
    private function resolvePeriodDescription(PaymentTransaction $transaction): string
    {
        // Try to get from allocations (Smart Fee system)
        $allocations = \Modules\Finance\app\Models\PaymentAllocation::with([
            'feeAssignment.feeStructure.feeType'
        ])
            ->where('transaction_id', $transaction->id)
            ->get();

        if ($allocations->isNotEmpty()) {
            $periods = $allocations->map(function ($alloc) {
                $assignment = $alloc->feeAssignment;
                if (!$assignment) return null;
                $feeTypeName = $assignment->feeStructure?->feeType?->name ?? 'Fee';
                $installment = $assignment->installment_number ? " (Installment {$assignment->installment_number})" : '';

                // Use period_month if available for proper calendar month name
                if ($assignment->period_month) {
                    $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $assignment->period_month)?->format('F Y') ?? $assignment->period_month;
                    return $feeTypeName . $installment . " - {$periodLabel}";
                }

                // Fallback to due date
                $dueDate = $assignment->due_date?->format('M Y') ?? '';
                return $feeTypeName . $installment . ($dueDate ? " - {$dueDate}" : '');
            })->filter()->unique()->values()->implode(', ');

            if (!empty($periods)) {
                return $periods;
            }
        }

        // Check transaction remarks for legacy month info (legacy MonthlyFeeRecord payments)
        $enrollment = $transaction->enrollment;
        $courseName = $enrollment?->batch?->course?->name ?? 'Fee';

        if ($transaction->remarks && preg_match('/Legacy months:\s*([\d,\-]+)/', $transaction->remarks, $m)) {
            $legacyMonths = explode(',', $m[1]);
            $monthLabels = array_map(function ($ym) {
                if (preg_match('/^\d{4}-\d{2}$/', $ym)) {
                    return \Carbon\Carbon::createFromFormat('Y-m', $ym)?->format('F Y') ?? $ym;
                }
                return $ym;
            }, $legacyMonths);
            return $courseName . ' - ' . implode(', ', $monthLabels);
        }

        // Fallback: use the enrollment's batch course name and transaction date
        $paymentMonth = $transaction->created_at->format('F Y');

        return "{$courseName} - {$paymentMonth}";
    }

    /**
     * Get payment method label.
     */
    private function getMethodLabel(string $method): string
    {
        $labels = [
            'cash' => 'Cash',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
            'check' => 'Cheque',
        ];

        return $labels[$method] ?? ucfirst($method);
    }

    /**
     * Convert number to words.
     */
    private function numberToWords($num): string
    {
        $num = (int) $num;
        if ($num == 0) return 'Zero';

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'
        ];
        $tens = [
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
        ];

        if ($num < 20) return $ones[$num];
        if ($num < 100) return $tens[intval($num / 10)] . ($num % 10 > 0 ? ' ' . $ones[$num % 10] : '');
        if ($num < 1000) return $ones[intval($num / 100)] . ' Hundred' . ($num % 100 > 0 ? ' ' . $this->numberToWords($num % 100) : '');
        if ($num < 100000) return $this->numberToWords(intval($num / 1000)) . ' Thousand' . ($num % 1000 > 0 ? ' ' . $this->numberToWords($num % 1000) : '');
        if ($num < 10000000) return $this->numberToWords(intval($num / 100000)) . ' Lakh' . ($num % 100000 > 0 ? ' ' . $this->numberToWords($num % 100000) : '');

        return $this->numberToWords(intval($num / 10000000)) . ' Crore' . ($num % 10000000 > 0 ? ' ' . $this->numberToWords($num % 10000000) : '');
    }
}
