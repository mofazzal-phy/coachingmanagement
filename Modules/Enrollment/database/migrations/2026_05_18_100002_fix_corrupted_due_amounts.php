<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix corrupted due_amount values in monthly_fee_records.
     *
     * The old applyPaymentToRecord() method was overwriting due_amount with the
     * remaining balance after payment (e.g., setting due_amount=0 after full payment).
     * This destroyed the original discounted amount, causing the discount calculation
     * (total_monthly_fee - due_amount) to be wrong.
     *
     * The fix: due_amount should ALWAYS store the original discounted amount.
     * Remaining balance should be calculated as: due_amount - paid_amount.
     *
     * Recovery formula: restored_due_amount = current_due_amount + current_paid_amount
     * This works because:
     * - Before any payment: due_amount = discounted, paid = 0 => restored = discounted
     * - After full payment (old bug): due_amount = 0, paid = discounted => restored = discounted
     * - After partial payment (old bug): due_amount = remaining, paid = paid => restored = discounted
     * - After this fix (due_amount no longer modified): due_amount + paid = discounted
     */
    public function up(): void
    {
        // Fix monthly_fee_records where due_amount was corrupted by payments
        $records = DB::table('monthly_fee_records')->get();

        $fixedCount = 0;
        foreach ($records as $record) {
            // Restore the original discounted due_amount
            $restoredDueAmount = $record->due_amount + $record->paid_amount;

            // Only update if the restored value differs from current
            if (abs((float) $restoredDueAmount - (float) $record->due_amount) > 0.01) {
                DB::table('monthly_fee_records')
                    ->where('id', $record->id)
                    ->update(['due_amount' => $restoredDueAmount]);
                $fixedCount++;
            }
        }

        // Also fix enrollments table: recalculate due_amount from monthly records
        $enrollments = DB::table('enrollments')
            ->where('fee_type', 'monthly')
            ->get();

        foreach ($enrollments as $enrollment) {
            $monthlyRecords = DB::table('monthly_fee_records')
                ->where('enrollment_id', $enrollment->id)
                ->get();

            $totalPaid = $monthlyRecords->sum('paid_amount');
            $totalDue = $monthlyRecords->sum(fn($r) => max(0, $r->due_amount - $r->paid_amount));
            $totalPayable = $monthlyRecords->sum('due_amount');
            $allPaid = $monthlyRecords->every(fn($r) => $r->payment_status === 'paid');

            DB::table('enrollments')
                ->where('id', $enrollment->id)
                ->update([
                    'paid_amount' => $totalPaid,
                    'due_amount' => $totalDue,
                    'total_fee' => $totalPayable,
                    'payable_fee' => $totalPayable,
                    'payment_status' => $allPaid ? 'paid' : ($totalPaid > 0 ? 'partial' : 'pending'),
                ]);
        }
    }

    public function down(): void
    {
        // This migration is a data fix and cannot be reversed.
        // The original corrupted values are lost forever, which is the whole point of the fix.
    }
};
