<?php

namespace Modules\Enrollment\app\Services;

use Modules\Enrollment\app\Models\Enrollment;
use Modules\Communication\app\Models\Notification as NotificationModel;

class NotificationService
{
    /**
     * Send enrollment confirmation via email + SMS.
     */
    public function sendEnrollmentConfirmation(Enrollment $enrollment): array
    {
        $results = [
            'email_sent' => false,
            'sms_sent' => false,
        ];

        $student = $enrollment->student;
        $batch = $enrollment->batch;
        $course = $batch?->course;

        // Build message data
        $data = [
            'student_name' => $student?->full_name ?? $student?->first_name ?? 'Student',
            'enrollment_no' => $enrollment->enrollment_no,
            'course_name' => $course?->name ?? 'N/A',
            'batch_name' => $batch?->name ?? 'N/A',
            'batch_mode' => $batch?->mode ?? 'N/A',
            'schedule' => $batch?->days ? implode(', ', $batch->days) : 'N/A',
            'start_time' => $batch?->start_time ?? 'N/A',
            'end_time' => $batch?->end_time ?? 'N/A',
            'teacher_name' => $batch?->teacher
                ? ($batch->teacher->first_name . ' ' . $batch->teacher->last_name)
                : 'N/A',
            'total_fee' => $enrollment->total_fee,
            'paid_amount' => $enrollment->paid_amount,
            'due_amount' => $enrollment->due_amount,
            'status' => $enrollment->status,
        ];

        // Send email to student
        if ($student?->email) {
            try {
                $this->sendEmail(
                    $student->email,
                    "Enrollment Confirmed - {$enrollment->enrollment_no}",
                    $this->buildStudentEmailBody($data),
                    $data
                );
                $results['email_sent'] = true;
            } catch (\Exception $e) {
                \Log::error("Failed to send enrollment email to student: " . $e->getMessage());
            }
        }

        // Send email to guardian
        $guardianEmail = $enrollment->guardian_email
            ?? $student?->guardian?->guardian_email
            ?? $student?->guardian?->father_email
            ?? null;

        if ($guardianEmail) {
            try {
                $this->sendEmail(
                    $guardianEmail,
                    "Enrollment Confirmation — Guardian Copy — {$enrollment->enrollment_no}",
                    $this->buildGuardianEmailBody($data),
                    $data
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send enrollment email to guardian: " . $e->getMessage());
            }
        }

        // Send SMS to student
        $studentPhone = $student?->phone;
        if ($studentPhone) {
            try {
                $this->sendSms(
                    $studentPhone,
                    $this->buildStudentSms($data)
                );
                $results['sms_sent'] = true;
            } catch (\Exception $e) {
                \Log::error("Failed to send enrollment SMS to student: " . $e->getMessage());
            }
        }

        // Send SMS to guardian
        $guardianPhone = $enrollment->guardian_phone
            ?? $student?->guardian?->guardian_phone
            ?? $student?->guardian?->father_phone
            ?? null;

        if ($guardianPhone && $guardianPhone !== $studentPhone) {
            try {
                $this->sendSms(
                    $guardianPhone,
                    $this->buildGuardianSms($data)
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send enrollment SMS to guardian: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send payment confirmation notification via email + SMS.
     * Used for both enrollment fee payments and monthly fee payments.
     */
    public function sendPaymentConfirmation(
        Enrollment $enrollment,
        float $amount,
        string $paymentMethod,
        ?string $transactionId = null,
        ?string $monthlyFeeMonth = null
    ): array {
        $results = [
            'email_sent' => false,
            'sms_sent' => false,
        ];

        $student = $enrollment->student;
        $batch = $enrollment->batch;
        $course = $batch?->course;

        $paymentType = $monthlyFeeMonth
            ? "Monthly Fee ({$monthlyFeeMonth})"
            : 'Enrollment Fee';

        $data = [
            'student_name' => $student?->full_name ?? $student?->first_name ?? 'Student',
            'enrollment_no' => $enrollment->enrollment_no,
            'course_name' => $course?->name ?? 'N/A',
            'batch_name' => $batch?->name ?? 'N/A',
            'payment_type' => $paymentType,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId ?? 'N/A',
            'total_paid' => $enrollment->paid_amount,
            'total_due' => $enrollment->due_amount,
            'status' => $enrollment->status,
        ];

        // Send email to student
        if ($student?->email) {
            try {
                $this->sendEmail(
                    $student->email,
                    "Payment Confirmed - {$paymentType} - {$enrollment->enrollment_no}",
                    $this->buildPaymentEmailBody($data),
                    $data
                );
                $results['email_sent'] = true;
            } catch (\Exception $e) {
                \Log::error("Failed to send payment email to student: " . $e->getMessage());
            }
        }

        // Send email to guardian
        $guardianEmail = $enrollment->guardian_email
            ?? $student?->guardian?->guardian_email
            ?? $student?->guardian?->father_email
            ?? null;

        if ($guardianEmail) {
            try {
                $this->sendEmail(
                    $guardianEmail,
                    "Payment Receipt — Guardian Copy — {$enrollment->enrollment_no}",
                    $this->buildPaymentEmailBody($data),
                    $data
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send payment email to guardian: " . $e->getMessage());
            }
        }

        // Send SMS to student
        $studentPhone = $student?->phone;
        if ($studentPhone) {
            try {
                $this->sendSms(
                    $studentPhone,
                    $this->buildPaymentSms($data)
                );
                $results['sms_sent'] = true;
            } catch (\Exception $e) {
                \Log::error("Failed to send payment SMS to student: " . $e->getMessage());
            }
        }

        // Send SMS to guardian
        $guardianPhone = $enrollment->guardian_phone
            ?? $student?->guardian?->guardian_phone
            ?? $student?->guardian?->father_phone
            ?? null;

        if ($guardianPhone && $guardianPhone !== $studentPhone) {
            try {
                $this->sendSms(
                    $guardianPhone,
                    $this->buildPaymentSms($data)
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send payment SMS to guardian: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send bulk notifications to pending enrollments.
     */
    public function sendBulkReminders(array $enrollmentIds): array
    {
        $results = ['total' => count($enrollmentIds), 'sent' => 0, 'failed' => 0];

        foreach ($enrollmentIds as $id) {
            try {
                $enrollment = Enrollment::with(['student', 'batch.course'])->find($id);
                if ($enrollment) {
                    $this->sendEnrollmentConfirmation($enrollment);
                    $results['sent']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                \Log::error("Failed bulk notification for enrollment {$id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send email (uses Laravel Mail facade).
     */
    private function sendEmail(string $to, string $subject, string $body, array $data = []): void
    {
        // For now, log the notification and use Laravel's mail later
        // In production, dispatch a Mailable job

        NotificationModel::create([
            'title' => $subject,
            'message' => strip_tags($body),
            'type' => 'email',
            'audience' => 'custom',
            'audience_ids' => [$to],
            'sent_by' => auth()->id() ?? 1,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send SMS (uses sms_logs table + gateway).
     */
    private function sendSms(string $to, string $message): void
    {
        \Modules\Communication\app\Models\SmsLog::create([
            'recipient' => $to,
            'message' => $message,
            'gateway' => config('services.sms.gateway', 'log'),
            'status' => 'sent',
        ]);
    }

    /**
     * Build student email body.
     */
    private function buildStudentEmailBody(array $data): string
    {
        $status = $data['status'] === 'active' ? '✅ Confirmed' : '⚠️ Pending (Payment Due)';

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #4a90d9;">🎓 Enrollment {$status}</h2>
            <p>Dear <strong>{$data['student_name']}</strong>,</p>
            <p>Your enrollment has been processed at <strong>CMS Coaching</strong>.</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Enrollment No</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['enrollment_no']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Course</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['course_name']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Batch</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['batch_name']} ({$data['batch_mode']})</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Schedule</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['schedule']} ({$data['start_time']} - {$data['end_time']})</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Teacher</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['teacher_name']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Total Fee</strong></td><td style="padding: 8px; border: 1px solid #ddd;">৳{$data['total_fee']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Paid</strong></td><td style="padding: 8px; border: 1px solid #ddd;">৳{$data['paid_amount']}</td></tr>
            </table>

            <p style="color: #e74c3c;"><strong>Due: ৳{$data['due_amount']}</strong></p>
            <p>Please complete your payment to confirm enrollment.</p>
            <hr>
            <p style="color: #888; font-size: 12px;">CMS Coaching Management System</p>
        </div>
        HTML;
    }

    /**
     * Build guardian email body.
     */
    private function buildGuardianEmailBody(array $data): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #4a90d9;">📋 Enrollment Receipt — Guardian Copy</h2>
            <p>Dear Guardian of <strong>{$data['student_name']}</strong>,</p>
            <p>Your ward has been enrolled at <strong>CMS Coaching</strong>.</p>
            <p><strong>Enrollment No:</strong> {$data['enrollment_no']}</p>
            <p><strong>Course:</strong> {$data['course_name']}</p>
            <p><strong>Batch:</strong> {$data['batch_name']} ({$data['batch_mode']})</p>
            <p><strong>Total Fee:</strong> ৳{$data['total_fee']} | <strong>Paid:</strong> ৳{$data['paid_amount']}</p>
            <hr>
            <p style="color: #888; font-size: 12px;">CMS Coaching Management System</p>
        </div>
        HTML;
    }

    /**
     * Build student SMS message.
     */
    private function buildStudentSms(array $data): string
    {
        $status = $data['status'] === 'active' ? 'Confirmed' : 'Pending';

        return "CMS Coaching: Enrollment {$status}!\n"
            . "No: {$data['enrollment_no']}\n"
            . "Course: {$data['course_name']}\n"
            . "Batch: {$data['batch_name']} ({$data['batch_mode']})\n"
            . "Fee: ৳{$data['total_fee']} | Paid: ৳{$data['paid_amount']}\n"
            . ($data['due_amount'] > 0 ? "Due: ৳{$data['due_amount']}. Please pay soon.\n" : '')
            . "Thank you!";
    }

    /**
     * Build guardian SMS message.
     */
    private function buildGuardianSms(array $data): string
    {
        return "CMS Coaching: {$data['student_name']}'s Enrollment\n"
            . "No: {$data['enrollment_no']}\n"
            . "Course: {$data['course_name']}\n"
            . "Fee: ৳{$data['total_fee']} | Paid: ৳{$data['paid_amount']}\n"
            . ($data['due_amount'] > 0 ? "Due: ৳{$data['due_amount']}\n" : '')
            . "Thank you!";
    }

    /**
     * Build payment confirmation email body.
     */
    private function buildPaymentEmailBody(array $data): string
    {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #27ae60;">✅ Payment Confirmed</h2>
            <p>Dear <strong>{$data['student_name']}</strong>,</p>
            <p>Your payment has been received successfully.</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Enrollment No</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['enrollment_no']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Course</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['course_name']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Payment Type</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['payment_type']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Amount</strong></td><td style="padding: 8px; border: 1px solid #ddd;">৳{$data['amount']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Method</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['payment_method']}</td></tr>
                <tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Transaction ID</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{$data['transaction_id']}</td></tr>
            </table>

            <p><strong>Total Paid:</strong> ৳{$data['total_paid']} | <strong>Due:</strong> ৳{$data['total_due']}</p>
            <hr>
            <p style="color: #888; font-size: 12px;">CMS Coaching Management System</p>
        </div>
        HTML;
    }

    /**
     * Build payment confirmation SMS message.
     */
    private function buildPaymentSms(array $data): string
    {
        return "CMS Coaching: Payment Confirmed!\n"
            . "Type: {$data['payment_type']}\n"
            . "Amount: ৳{$data['amount']}\n"
            . "Method: {$data['payment_method']}\n"
            . "TrxID: {$data['transaction_id']}\n"
            . ($data['total_due'] > 0 ? "Due: ৳{$data['total_due']}\n" : "Fully Paid! ✅\n")
            . "Thank you!";
    }
}
