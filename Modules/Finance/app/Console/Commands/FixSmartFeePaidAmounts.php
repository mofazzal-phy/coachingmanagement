<?php

namespace Modules\Finance\app\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\app\Models\PaymentAllocation;
use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Enrollment\app\Models\Enrollment;

class FixSmartFeePaidAmounts extends Command
{
    protected $signature = 'fee:fix-paid-amounts
                            {--enrollment= : Specific enrollment ID to fix (optional, fixes all if omitted)}
                            {--dry-run : Preview changes without applying them}';

    protected $description = 'Recalculate paid_amount and status for smart fee assignments based on actual payment allocations';

    public function handle(): int
    {
        $specificEnrollmentId = $this->option('enrollment');
        $dryRun = $this->option('dry-run');

        if ($specificEnrollmentId) {
            $enrollments = Enrollment::where('id', $specificEnrollmentId)->get();
            if ($enrollments->isEmpty()) {
                $this->error("Enrollment not found: {$specificEnrollmentId}");
                return Command::FAILURE;
            }
        } else {
            // Get all enrollments that have smart fee assignments
            $enrollmentIds = StudentFeeAssignment::distinct()->pluck('enrollment_id');
            $enrollments = Enrollment::whereIn('id', $enrollmentIds)->get();
        }

        $totalFixed = 0;
        $totalErrors = 0;

        foreach ($enrollments as $enrollment) {
            $this->info("Processing enrollment: {$enrollment->id}");

            $assignments = StudentFeeAssignment::where('enrollment_id', $enrollment->id)->get();

            foreach ($assignments as $assignment) {
                // Calculate total paid from actual allocations linked to confirmed transactions
                $totalAllocated = (float) PaymentAllocation::where('fee_assignment_id', $assignment->id)
                    ->whereHas('transaction', function ($q) {
                        $q->where('status', 'confirmed');
                    })
                    ->sum('amount');

                $finalAmount = (float) ($assignment->final_amount + ($assignment->late_fee_applied ?? 0));
                $currentPaid = (float) $assignment->paid_amount;

                // Determine correct status based on allocated amount
                if ($totalAllocated >= $finalAmount && $finalAmount > 0) {
                    $correctStatus = 'paid';
                } elseif ($totalAllocated > 0) {
                    $correctStatus = 'partial';
                } else {
                    $correctStatus = 'pending';
                }

                // Determine current status (what it would be if we recalculated)
                $currentCorrectStatus = $assignment->status;

                if (abs($currentPaid - $totalAllocated) > 0.01 || $currentCorrectStatus !== $correctStatus) {
                    $this->line("  Assignment [{$assignment->id}] {$assignment->period_month}:");
                    $this->line("    paid_amount: {$currentPaid} -> {$totalAllocated}");
                    $this->line("    status: {$assignment->status} -> {$correctStatus}");

                    if (!$dryRun) {
                        try {
                            $assignment->update([
                                'paid_amount' => $totalAllocated,
                                'status' => $correctStatus,
                            ]);
                            $totalFixed++;
                            $this->line("    ✓ Fixed");
                        } catch (\Exception $e) {
                            $this->error("    ✗ Error: {$e->getMessage()}");
                            $totalErrors++;
                        }
                    } else {
                        $totalFixed++;
                        $this->line("    [DRY-RUN] Would fix");
                    }
                }
            }
        }

        $mode = $dryRun ? 'DRY-RUN: ' : '';
        $this->info("{$mode}Processed. {$totalFixed} assignments would be/were fixed. {$totalErrors} errors.");

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
