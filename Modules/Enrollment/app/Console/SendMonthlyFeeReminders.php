<?php

namespace Modules\Enrollment\app\Console;

use Illuminate\Console\Command;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Services\NotificationService;

class SendMonthlyFeeReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'enrollment:send-monthly-fee-reminders
                            {--date= : Specific date to check (Y-m-d format, defaults to today)}
                            {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send monthly fee reminders to students with pending fees (runs on 25th of each month)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $isDryRun = $this->option('dry-run');
        $currentMonth = $date->format('Y-m');

        $this->info("Monthly Fee Reminder Check - {$currentMonth}");
        $this->newLine();

        // Get all pending monthly fee records for the current month
        $pendingRecords = MonthlyFeeRecord::with([
            'enrollment.student',
            'enrollment.batch.course',
        ])
            ->where('month', $currentMonth)
            ->where('payment_status', '!=', 'paid')
            ->get();

        if ($pendingRecords->isEmpty()) {
            $this->info('No pending monthly fee records found for this month.');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingRecords->count()} pending monthly fee records.");
        $this->newLine();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingRecords as $record) {
            $enrollment = $record->enrollment;

            if (!$enrollment || !$enrollment->student) {
                $this->warn("Skipping record {$record->id}: No enrollment or student found.");
                $skipped++;
                continue;
            }

            $student = $enrollment->student;
            $studentName = $student->first_name . ' ' . $student->last_name;
            $amount = $record->due_amount;
            $dueDate = $record->due_date?->format('d M Y') ?? $date->day(25)->format('d M Y');

            $this->line("  [{$record->id}] {$studentName} - {$enrollment->enrollment_no}");
            $this->line("         Month: {$record->month} | Due: ৳{$amount} | Due Date: {$dueDate}");

            if ($isDryRun) {
                $this->line("         [DRY-RUN] Would send reminder");
                $this->newLine();
                continue;
            }

            try {
                // Send SMS reminder
                $phone = $student->phone;
                if ($phone) {
                    $message = "CMS Coaching: Monthly Fee Reminder\n"
                        . "Dear {$studentName},\n"
                        . "Your monthly fee for {$record->month} is due.\n"
                        . "Amount: ৳{$amount}\n"
                        . "Due Date: {$dueDate}\n"
                        . "Please pay to avoid late fees.\n"
                        . "Thank you!";

                    try {
                        \Modules\Communication\app\Models\SmsLog::create([
                            'recipient' => $phone,
                            'message' => $message,
                            'gateway' => config('services.sms.gateway', 'log'),
                            'status' => 'sent',
                        ]);
                        $this->info("         ✓ SMS sent to {$phone}");
                    } catch (\Exception $e) {
                        $this->error("         ✗ SMS failed: " . $e->getMessage());
                    }
                }

                // Send email reminder
                $email = $student->email;
                if ($email) {
                    try {
                        \Modules\Communication\app\Models\Notification::create([
                            'title' => "Monthly Fee Reminder - {$record->month}",
                            'message' => "Dear {$studentName},\n\nYour monthly fee of ৳{$amount} for {$record->month} is due on {$dueDate}. Please complete your payment to avoid any disruption.\n\nThank you,\nCMS Coaching",
                            'type' => 'email',
                            'audience' => 'custom',
                            'audience_ids' => [$email],
                            'sent_by' => 1,
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);
                        $this->info("         ✓ Email sent to {$email}");
                    } catch (\Exception $e) {
                        $this->error("         ✗ Email failed: " . $e->getMessage());
                    }
                }

                // Also send to guardian
                $guardianPhone = $enrollment->guardian_phone
                    ?? $student->guardian?->guardian_phone
                    ?? $student->guardian?->father_phone
                    ?? null;

                if ($guardianPhone && $guardianPhone !== $phone) {
                    try {
                        \Modules\Communication\app\Models\SmsLog::create([
                            'recipient' => $guardianPhone,
                            'message' => "CMS Coaching: Monthly Fee Reminder\n"
                                . "Your ward {$studentName}'s fee for {$record->month} is due.\n"
                                . "Amount: ৳{$amount}\nDue: {$dueDate}\nThank you!",
                            'gateway' => config('services.sms.gateway', 'log'),
                            'status' => 'sent',
                        ]);
                        $this->info("         ✓ SMS sent to guardian {$guardianPhone}");
                    } catch (\Exception $e) {
                        $this->error("         ✗ Guardian SMS failed: " . $e->getMessage());
                    }
                }

                $sent++;
            } catch (\Exception $e) {
                $this->error("         ✗ Failed: " . $e->getMessage());
                $failed++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Sent', $sent],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', $pendingRecords->count()],
            ]
        );

        return Command::SUCCESS;
    }
}
