<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Apply 10% discount to all existing monthly enrollments that don't have a discount.
     *
     * The user wants:
     * - Total fee: 3100/month × 13 months = 40300
     * - 10% discount → discount amount = 310/month
     * - Discounted monthly fee (due_amount) = 3100 - 310 = 2790
     * - Total after discount = 2790 × 13 = 36270
     * - Total discount = 40300 - 36270 = 4030
     *
     * For records that already have partial payments, the paid_amount stays the same,
     * but the due_amount is reduced so the remaining balance is correct.
     */
    public function up(): void
    {
        // Find all monthly enrollments with no discount applied
        // (discount_percent = 0 AND all records have due_amount == total_monthly_fee)
        $enrollments = DB::table('enrollments')
            ->where('fee_type', 'monthly')
            ->where('discount_percent', 0)
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

            // Check if ANY record already has a discount applied
            $hasDiscount = $records->contains(function ($r) {
                return (float) $r->due_amount < (float) $r->total_monthly_fee;
            });

            if ($hasDiscount) {
                // Skip — already has discount applied
                continue;
            }

            // Apply 10% discount to each record
            foreach ($records as $record) {
                $totalMonthlyFee = (float) $record->total_monthly_fee;
                $discountPercent = 10;
                $discountAmount = $totalMonthlyFee * $discountPercent / 100;
                $newDueAmount = max(0, $totalMonthlyFee - $discountAmount);

                DB::table('monthly_fee_records')
                    ->where('id', $record->id)
                    ->update([
                        'due_amount' => $newDueAmount,
                        'updated_at' => now(),
                    ]);

                $fixedCount++;
            }

            // Update the enrollment's discount_percent and discount_reason
            DB::table('enrollments')
                ->where('id', $enrollment->id)
                ->update([
                    'discount_percent' => 10,
                    'discount_reason' => '10% Standard Discount',
                    'updated_at' => now(),
                ]);
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
            $totalDue = $monthlyRecords->sum(function ($r) {
                return max(0, $r->due_amount - $r->paid_amount);
            });
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

        echo "Applied 10% discount to {$fixedCount} monthly fee records across " . $enrollments->count() . " enrollments.\n";
    }

    public function down(): void
    {
        // This migration is a data fix and cannot be easily reversed.
        // To reverse, you would need to restore due_amount = total_monthly_fee for all records.
    }
};
