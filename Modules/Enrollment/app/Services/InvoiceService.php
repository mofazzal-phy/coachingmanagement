<?php

namespace Modules\Enrollment\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\app\Models\MonthlyFeePayment;
use Modules\Enrollment\app\Models\PaymentInvoice;

class InvoiceService
{
    /**
     * Generate and save an invoice PDF for a confirmed payment.
     *
     * @param string $paymentId The MonthlyFeePayment UUID
     * @return PaymentInvoice
     */
    public function generateInvoice(string $paymentId): PaymentInvoice
    {
        $payment = MonthlyFeePayment::with([
            'monthlyFeeRecord.enrollment.student',
            'monthlyFeeRecord.enrollment.batch.course',
            'confirmer',
        ])->findOrFail($paymentId);

        if ($payment->payment_status !== 'confirmed') {
            throw new \Exception('Invoice can only be generated for confirmed payments.');
        }

        // Check if invoice already exists
        $existingInvoice = PaymentInvoice::where('monthly_fee_payment_id', $paymentId)->first();
        if ($existingInvoice) {
            return $existingInvoice;
        }

        // Generate invoice number if not set
        $invoiceNo = $payment->invoice_no;
        if (empty($invoiceNo)) {
            $invoiceNo = $this->generateInvoiceNo();
            $payment->updateQuietly(['invoice_no' => $invoiceNo]);
        }

        $enrollment = $payment->monthlyFeeRecord->enrollment;
        $student = $enrollment->student;
        $course = $enrollment->batch?->course;
        $record = $payment->monthlyFeeRecord;

        // Prepare invoice data
        $data = [
            'invoice_no' => $invoiceNo,
            'invoice_type' => 'monthly_fee',
            'generated_at' => now(),
            'generated_by' => $payment->confirmed_by,
            'metadata' => [
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'sender_number' => $payment->sender_number,
                'bank_name' => $payment->bank_name,
                'confirmed_by' => $payment->confirmed_by,
                'confirmed_at' => $payment->confirmed_at?->toDateTimeString(),
            ],
        ];

        // Create invoice record
        $invoice = PaymentInvoice::create($data);

        // Generate PDF
        $pdfContent = $this->renderPdf($payment, $invoice, $student, $course, $record, $enrollment);

        // Save PDF to storage
        $filePath = 'invoices/' . $invoiceNo . '.pdf';
        Storage::disk('local')->put($filePath, $pdfContent);

        // Update invoice with file path
        $invoice->updateQuietly(['file_path' => $filePath]);

        return $invoice->fresh();
    }

    /**
     * Render the invoice PDF.
     */
    private function renderPdf(
        MonthlyFeePayment $payment,
        PaymentInvoice $invoice,
        $student,
        $course,
        $record,
        $enrollment
    ): string {
        $monthName = \Carbon\Carbon::parse($record->month . '-01')->format('F Y');

        $data = [
            'invoice' => $invoice,
            'payment' => $payment,
            'student' => $student,
            'course' => $course,
            'record' => $record,
            'enrollment' => $enrollment,
            'month_name' => $monthName,
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

        $paymentMethodLabels = [
            'cash' => 'Cash',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online Payment',
            'cheque' => 'Cheque',
        ];

        $methodLabel = $paymentMethodLabels[$payment->payment_method] ?? ucfirst($payment->payment_method);

        // Pre-compute values that use ?? operator (not supported inside heredoc {})
        $transactionId = $payment->transaction_id ?? 'N/A';
        $senderNumber = $payment->sender_number ?? 'N/A';
        $paymentDate = $payment->payment_date->format('d M, Y h:i A');
        $confirmedByName = $payment->confirmer?->name ?? 'System';
        $confirmedAt = $payment->confirmed_at?->format('d M, Y h:i A') ?? 'N/A';
        $discountAmount = (float) $record->total_monthly_fee - (float) $record->due_amount;
        $discountDisplay = $discountAmount > 0 ? '৳ ' . number_format($discountAmount, 2) : '৳ 0.00';

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
        .header h2 {
            color: #555;
            font-size: 18px;
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
            <h3>MONTHLY FEE RECEIPT</h3>
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
                    <td class="value">{$student->full_name}</td>
                    <td class="label">Student ID:</td>
                    <td class="value">{$student->student_id}</td>
                </tr>
                <tr>
                    <td class="label">Course:</td>
                    <td class="value">{$course->name}</td>
                    <td class="label">Month:</td>
                    <td class="value">{$month_name}</td>
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
                <tr>
                    <td>Monthly Fee for {$month_name}</td>
                    <td style="text-align: right;">৳ {$record->total_monthly_fee}</td>
                </tr>
                <tr>
                    <td>Discount / Adjustment</td>
                    <td style="text-align: right;">{$discountDisplay}</td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total Paid</strong></td>
                    <td style="text-align: right;"><strong>৳ {$payment->amount}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="payment-info">
            <h4>Payment Details</h4>
            <table>
                <tr>
                    <td class="label">Payment Method:</td>
                    <td>{$methodLabel}</td>
                </tr>
                <tr>
                    <td class="label">Transaction ID:</td>
                    <td>{$transactionId}</td>
                </tr>
                <tr>
                    <td class="label">Sender Number:</td>
                    <td>{$senderNumber}</td>
                </tr>
                <tr>
                    <td class="label">Payment Date:</td>
                    <td>{$paymentDate}</td>
                </tr>
                <tr>
                    <td class="label">Confirmed By:</td>
                    <td>{$confirmedByName}</td>
                </tr>
                <tr>
                    <td class="label">Confirmed At:</td>
                    <td>{$confirmedAt}</td>
                </tr>
            </table>
        </div>

        <div class="amount-in-words">
            Amount in words: {$this->numberToWords($payment->amount)} Taka only.
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
     * Download an invoice PDF.
     *
     * @param string $invoiceId
     * @return \Barryvdh\DomPDF\PDF
     */
    public function downloadInvoice(string $invoiceId)
    {
        $invoice = PaymentInvoice::with([
            'monthlyFeePayment.monthlyFeeRecord.enrollment.student',
            'monthlyFeePayment.monthlyFeeRecord.enrollment.batch.course',
            'monthlyFeePayment.confirmer',
        ])->findOrFail($invoiceId);

        $payment = $invoice->monthlyFeePayment;
        $enrollment = $payment->monthlyFeeRecord->enrollment;
        $student = $enrollment->student;
        $course = $enrollment->batch?->course;
        $record = $payment->monthlyFeeRecord;

        $html = $this->getInvoiceHtml([
            'invoice' => $invoice,
            'payment' => $payment,
            'student' => $student,
            'course' => $course,
            'record' => $record,
            'enrollment' => $enrollment,
            'month_name' => \Carbon\Carbon::parse($record->month . '-01')->format('F Y'),
            'institute_name' => config('app.name', 'Coaching Management System'),
            'institute_address' => config('app.address', ''),
            'institute_phone' => config('app.phone', ''),
            'institute_email' => config('app.email', ''),
        ]);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * Generate a unique invoice number.
     * Format: INV-YYYYMM-XXXXX
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
     * Get invoice by payment ID.
     */
    public function getInvoiceByPayment(string $paymentId): ?PaymentInvoice
    {
        return PaymentInvoice::where('monthly_fee_payment_id', $paymentId)->first();
    }

    /**
     * Convert number to words (Bengali/English).
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
