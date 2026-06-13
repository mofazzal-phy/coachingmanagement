<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix monthly fee records where discount was only applied to the first month
     * (old enrollments created before the discount-in-monthly-records fix).
     *
     * These enrollments have:
     * - First month: due_amount = total_monthly_fee - discount (correct, fixed by previous migration)
     * - Remaining months: due_amount = total_monthly_fee (no discount applied)
     *
     * We detect this by finding enrollments where at least one record has
     * due_amount < total_monthly_fee (discount applied) and other records
     * have due_amount == total_monthly_fee (no discount).
     *
     * The discount amount is calculated as: total_monthly_fee - due_amount
     * from the first discounted record.
     */
    public function up(): void
    {
        // Find all enrollments with mixed discount state
        $enrollments = DB::table('enrollments')
            ->where('fee_type', 'monthly')
            ->get();

        $fixedCount = 0;

        foreach ($enrollments as $enrollment) {
            $records = DB::table('monthly_fee_records')
                ->where('enrollment_id', $enrollment->id)
                ->orderBy('month', 'asc')
                ->get();

            if ($records->isEmpty()) {
                continue;
            }

            // Find the first record that has a discount applied (due_amount < total_monthly_fee)
            $discountedRecord = $records->first(function ($r) {
                return (float) $r->due_amount < (float) $r->total_monthly_fee;
            });

            if (!$discountedRecord) {
                // No discount applied to any record — skip
                continue;
            }

            // Calculate the discount amount from the discounted record
            $discountAmount = (float) $discountedRecord->total_monthly_fee - (float) $discountedRecord->due_amount;

            // Find records that DON'T have the discount applied
            $undiscountedRecords = $records->filter(function ($r) use ($discountAmount) {
                $expectedDue = (float) $r->total_monthly_fee - $discountAmount;
                return abs((float) $r->due_amount - (float) $r->total_monthly_fee) < 0.01
                    && abs((float) $r->due_amount - $expectedDue) > 0.01;
            });

            foreach ($undiscountedRecords as $record) {
                $newDueAmount = max(0, (float) $record->total_monthly_fee - $discountAmount);

                DB::table('monthly_fee_records')
                    ->where('id', $record->id)
                    ->update(['due_amount' => $newDueAmount]);

                $fixedCount++;
            }
        }

        // Now recalculate enrollment-level totals for all monthly enrollments
        $monthlyEnrollments = DB::table('enrollments')
            ->where('fee_type', 'monthly')
            ->get();

        foreach ($monthlyEnrollments as $enrollment) {
            $monthlyRecords = DB::table('monthly_fee_records')
                ->where('enrollment_id', $enrollment->id)
                ->get();

            if ($monthlyRecords->isEmpty()) {
                continue;
            }

            $totalPaid = $monthlyRecords->sum('paid_amount');
            $totalDue = $monthlyRecords->sum(fn($r) => max(0, $r->due_amount - $r->paid_amount));
            $totalPayable = $monthlyRecords->sum('due_amount');
            $allPaid = $monthlyRecords->every(fn($r) => $r->payment_status === 'paid');
            $paidMonthsCount = $monthlyRecords->where('payment_status', 'paid')->count();

            DB::table('enrollments')
                ->where('id', $enrollment->id)
                ->update([
                    'paid_amount' => $totalPaid,
                    'due_amount' => $totalDue,
                    'total_fee' => $totalPayable,
                    'payable_fee' => $totalPayable,
                    'paid_months' => $paidMonthsCount,
                    'payment_status' => $allPaid ? 'paid' : ($totalPaid > 0 ? 'partial' : 'pending'),
                ]);
        }

        echo "Fixed {$fixedCount} monthly fee records across " . $enrollments->count() . " enrollments.\n";
    }

    public function down(): void
    {
        // This migration is a data fix and cannot be reversed.
    }
};
