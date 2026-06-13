<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix old monthly fee records where discount was applied at payment time
     * but never baked into due_amount.
     *
     * PROBLEM:
     * Old enrollments (created before the discount-in-monthly-records fix) have
     * monthly fee records where due_amount == total_monthly_fee (no discount baked in).
     * When a student paid a discounted amount (e.g., 2600 instead of 3100),
     * the old applyPaymentToRecord() would set due_amount = max(0, 3100 - 2600) = 500.
     * Then the previous migration (2026_05_18_100002) restored due_amount to
     * 500 + 2600 = 3100 (the original undiscounted amount).
     *
     * This means the discount is lost — the system shows:
     *   total=3100, due=3100, paid=2600, discount=0, balance=500, status=partial
     *
     * But the user expects:
     *   total=3100, due=2600, paid=2600, discount=500, balance=0, status=paid
     *
     * FIX:
     * For records where due_amount == total_monthly_fee AND paid_amount > 0
     * AND paid_amount < total_monthly_fee, the difference (total - paid) was
     * a discount that was never baked into due_amount. We fix by setting
     * due_amount = paid_amount (the actual amount that was due after discount).
     *
     * This is safe because:
     * - If the student paid the full amount (paid == total), no fix needed
     * - If the student paid less AND due_amount < total, discount was already baked in
     * - If the student paid less AND due_amount == total, the difference IS the discount
     *   (because the old code always set due_amount = total for new records)
     */
    public function up(): void
    {
        $fixed = 0;
        $records = DB::table('monthly_fee_records')->get();

        foreach ($records as $record) {
            // Detect: due_amount == total_monthly_fee (no discount baked in)
            // AND paid_amount > 0 AND paid_amount < total_monthly_fee
            if ((float) $record->due_amount == (float) $record->total_monthly_fee
                && (float) $record->paid_amount > 0
                && (float) $record->paid_amount < (float) $record->total_monthly_fee) {

                // The discount amount = total_monthly_fee - paid_amount
                // Set due_amount = paid_amount (the actual discounted amount)
                $newDueAmount = (float) $record->paid_amount;

                DB::table('monthly_fee_records')
                    ->where('id', $record->id)
                    ->update([
                        'due_amount' => $newDueAmount,
                        'payment_status' => 'paid',
                    ]);

                $fixed++;
            }
        }

        // Also fix enrollments table: recalculate totals from fixed monthly records
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

        echo "Fixed {$fixed} monthly fee records with missing discount.\n";
    }

    public function down(): void
    {
        // This migration is a data fix and cannot be reversed.
    }
};
