<?php

namespace Modules\Finance\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Models\MonthlyFeePayment;
use Modules\Finance\app\Models\FeeType;
use Modules\Finance\app\Models\FeeStructure;
use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Finance\app\Models\PaymentAllocation;
use Modules\Finance\app\Models\FeeGenerationLog;

class MigrateMonthlyToSmartFee extends Command
{
    protected $signature = 'fee:migrate-monthly-to-smart
        {--dry-run : Preview what would be migrated without making changes}
        {--enrollment= : Migrate only a specific enrollment ID}
        {--force : Skip confirmation prompt}';

    protected $description = 'Migrate legacy MonthlyFeeRecord/MonthlyFeePayment data to Smart Fee system (StudentFeeAssignment/PaymentTransaction)';

    /**
     * Cache for fee type and structure lookups per enrollment.
     */
    private array $feeStructureCache = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificEnrollment = $this->option('enrollment');
        $force = $this->option('force');

        if (!$force && !$dryRun) {
            $this->info('⚠️  This will migrate legacy monthly fee data to the Smart Fee system.');
            $this->info('   Existing data in monthly_fee_records and monthly_fee_payments will NOT be deleted.');
            $this->info('   New records will be created in:');
            $this->info('     - student_fee_assignments');
            $this->info('     - payment_transactions');
            $this->info('     - payment_allocations');
            $this->info('     - fee_generation_logs');
            $this->newLine();

            if (!$this->confirm('Do you wish to continue?', false)) {
                $this->warn('Migration cancelled.');
                return Command::FAILURE;
            }
        }

        // Build query for enrollments with monthly fee records
        $query = Enrollment::whereHas('monthlyFeeRecords');

        if ($specificEnrollment) {
            $query->where('id', $specificEnrollment);
        }

        $enrollments = $query->get();
        $totalEnrollments = $enrollments->count();

        if ($totalEnrollments === 0) {
            $this->warn('No enrollments with monthly fee records found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalEnrollments} enrollment(s) with monthly fee records.");

        $stats = [
            'enrollments_processed' => 0,
            'assignments_created' => 0,
            'transactions_created' => 0,
            'allocations_created' => 0,
            'errors' => 0,
            'skipped_already_migrated' => 0,
        ];

        $bar = $this->output->createProgressBar($totalEnrollments);
        $bar->start();

        foreach ($enrollments as $enrollment) {
            try {
                $result = $this->migrateEnrollment($enrollment, $dryRun);
                foreach ($stats as $key => &$value) {
                    if (isset($result[$key])) {
                        $value += $result[$key];
                    }
                }
                unset($value);
            } catch (\Exception $e) {
                $this->error("Failed for enrollment {$enrollment->id}: {$e->getMessage()}");
                Log::error('Monthly fee migration failed for enrollment', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $stats['errors']++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info('=== DRY RUN COMPLETE ===');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Enrollments to process', $stats['enrollments_processed']],
                    ['Assignments to create', $stats['assignments_created']],
                    ['Transactions to create', $stats['transactions_created']],
                    ['Allocations to create', $stats['allocations_created']],
                    ['Already migrated (skipped)', $stats['skipped_already_migrated']],
                    ['Errors', $stats['errors']],
                ]
            );
        } else {
            $this->info('=== MIGRATION COMPLETE ===');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Enrollments processed', $stats['enrollments_processed']],
                    ['Assignments created', $stats['assignments_created']],
                    ['Transactions created', $stats['transactions_created']],
                    ['Allocations created', $stats['allocations_created']],
                    ['Already migrated (skipped)', $stats['skipped_already_migrated']],
                    ['Errors', $stats['errors']],
                ]
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Migrate a single enrollment's monthly fee data.
     */
    private function migrateEnrollment(Enrollment $enrollment, bool $dryRun): array
    {
        $stats = [
            'enrollments_processed' => 0,
            'assignments_created' => 0,
            'transactions_created' => 0,
            'allocations_created' => 0,
            'skipped_already_migrated' => 0,
        ];

        // Check if already migrated by looking for fee_generation_logs with migration type
        $alreadyMigrated = FeeGenerationLog::where('enrollment_id', $enrollment->id)
            ->where('generation_type', 'migration')
            ->exists();

        if ($alreadyMigrated) {
            $this->warn("Enrollment {$enrollment->id} already migrated. Skipping.");
            $stats['skipped_already_migrated'] = 1;
            return $stats;
        }

        // Get or create the monthly fee type and structure for this enrollment
        $feeStructure = $this->resolveFeeStructure($enrollment);
        if (!$feeStructure) {
            $this->error("Could not resolve fee structure for enrollment {$enrollment->id}");
            return $stats;
        }

        // Get all monthly fee records for this enrollment
        $records = MonthlyFeeRecord::where('enrollment_id', $enrollment->id)
            ->orderBy('month')
            ->get();

        if ($records->isEmpty()) {
            return $stats;
        }

        $assignments = [];
        $studentId = $enrollment->student_id;

        DB::beginTransaction();
        try {
            // Step 1: Create StudentFeeAssignment for each monthly fee record
            foreach ($records as $record) {
                $finalAmount = (float) $record->due_amount;
                $paidAmount = (float) $record->paid_amount;
                $status = $this->resolveAssignmentStatus($record->payment_status, $paidAmount, $finalAmount);

                $assignment = StudentFeeAssignment::create([
                    'enrollment_id' => $enrollment->id,
                    'fee_structure_id' => $feeStructure->id,
                    'original_amount' => (float) $record->total_monthly_fee,
                    'discounted_amount' => $finalAmount,
                    'final_amount' => $finalAmount,
                    'due_date' => $record->due_date ?? $this->parseMonthDueDate($record->month),
                    'period_month' => $record->month,
                    'status' => $status,
                    'late_fee_applied' => (float) ($record->fine_amount ?? 0),
                    'paid_amount' => $paidAmount,
                ]);

                $assignments[$record->id] = $assignment;
                $stats['assignments_created']++;
            }

            // Step 2: Create PaymentTransaction + PaymentAllocation for confirmed payments
            $payments = MonthlyFeePayment::whereIn('monthly_fee_record_id', $records->pluck('id'))
                ->where('payment_status', 'confirmed')
                ->get();

            foreach ($payments as $payment) {
                $recordId = $payment->monthly_fee_record_id;
                $assignment = $assignments[$recordId] ?? null;

                if (!$assignment) {
                    continue;
                }

                $transaction = PaymentTransaction::create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $studentId,
                    'transaction_no' => $payment->invoice_no ?? ('MIG-' . strtoupper(substr($payment->id, 0, 8))),
                    'amount' => (float) $payment->amount,
                    'payment_method' => $this->mapPaymentMethod($payment->payment_method),
                    'gateway_trx_id' => $payment->transaction_id,
                    'reference_no' => $payment->reference,
                    'status' => 'confirmed',
                    'confirmed_by' => $payment->confirmed_by,
                    'confirmed_at' => $payment->confirmed_at ?? $payment->payment_date ?? $payment->created_at,
                    'remarks' => 'Migrated from legacy monthly fee payment',
                    'is_manual' => true,
                ]);

                $stats['transactions_created']++;

                // Create allocation linking transaction to assignment
                PaymentAllocation::create([
                    'transaction_id' => $transaction->id,
                    'fee_assignment_id' => $assignment->id,
                    'amount' => (float) $payment->amount,
                ]);

                $stats['allocations_created']++;
            }

            // Step 3: Log the migration
            if (!$dryRun) {
                FeeGenerationLog::create([
                    'enrollment_id' => $enrollment->id,
                    'generation_type' => 'migration',
                    'summary' => [
                        'total_records' => $records->count(),
                        'assignments_created' => $stats['assignments_created'],
                        'payments_migrated' => $payments->count(),
                        'transactions_created' => $stats['transactions_created'],
                    ],
                    'notes' => 'Migrated from legacy MonthlyFeeRecord system to Smart Fee system',
                ]);
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $stats['enrollments_processed'] = 1;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * Resolve or create a FeeStructure for the monthly fee migration.
     * Uses a generic "Monthly Tuition Fee" type if no specific monthly fee structure exists.
     */
    private function resolveFeeStructure(Enrollment $enrollment): ?FeeStructure
    {
        $cacheKey = $enrollment->enrolled_class_id . '_' . $enrollment->academic_session_id;

        if (isset($this->feeStructureCache[$cacheKey])) {
            return $this->feeStructureCache[$cacheKey];
        }

        // First, try to find an existing monthly fee structure for this class/session
        $feeStructure = FeeStructure::where('class_id', $enrollment->enrolled_class_id)
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->whereHas('feeType', function ($q) {
                $q->where('category', 'monthly');
            })
            ->first();

        if ($feeStructure) {
            $this->feeStructureCache[$cacheKey] = $feeStructure;
            return $feeStructure;
        }

        // If no monthly fee structure exists, find or create a generic "Monthly Tuition" fee type
        $feeType = FeeType::where('category', 'monthly')
            ->where('name', 'like', '%Monthly%')
            ->first();

        if (!$feeType) {
            $feeType = FeeType::where('category', 'monthly')->first();
        }

        if (!$feeType) {
            // Create a generic monthly fee type
            $feeType = FeeType::create([
                'name' => 'Monthly Tuition Fee',
                'category' => 'monthly',
                'description' => 'Generic monthly tuition fee (migrated from legacy system)',
                'status' => 'active',
            ]);
        }

        // Create a fee structure for this class/session using the monthly fee type
        $feeStructure = FeeStructure::create([
            'academic_session_id' => $enrollment->academic_session_id,
            'class_id' => $enrollment->enrolled_class_id,
            'fee_type_id' => $feeType->id,
            'amount' => 0, // Will be overridden per assignment
            'due_day' => 10,
            'description' => 'Monthly fee (migrated from legacy system)',
            'status' => 'active',
        ]);

        $this->feeStructureCache[$cacheKey] = $feeStructure;
        return $feeStructure;
    }

    /**
     * Resolve assignment status based on payment status and amounts.
     */
    private function resolveAssignmentStatus(string $paymentStatus, float $paidAmount, float $finalAmount): string
    {
        if ($paymentStatus === 'paid') {
            return 'paid';
        }

        if ($paidAmount > 0 && $paidAmount < $finalAmount) {
            return 'partial';
        }

        if ($paidAmount >= $finalAmount) {
            return 'paid';
        }

        return 'pending';
    }

    /**
     * Map legacy payment methods to smart fee payment methods.
     */
    private function mapPaymentMethod(?string $method): string
    {
        return match (strtolower($method ?? '')) {
            'bkash', 'bKash' => 'bkash',
            'nagad' => 'nagad',
            'rocket' => 'rocket',
            'bank', 'bank_transfer' => 'bank',
            'cash' => 'cash',
            'card', 'credit_card', 'debit_card' => 'card',
            'check' => 'check',
            default => 'cash',
        };
    }

    /**
     * Parse a month string (Y-m) into a Carbon due date (25th of that month).
     */
    private function parseMonthDueDate(string $month): \Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromFormat('Y-m', $month)->day(25);
        } catch (\Exception $e) {
            return now()->addDays(30);
        }
    }
}
