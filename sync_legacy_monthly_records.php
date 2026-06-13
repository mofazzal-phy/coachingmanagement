<?php
/**
 * One-time sync script to update legacy MonthlyFeeRecord records
 * from their corresponding smart StudentFeeAssignment records.
 * 
 * This fixes existing records that were paid via the combined
 * exam+monthly fee payment flow BEFORE the sync code was added
 * to allocateToSpecificAssignments().
 * 
 * Run: php sync_legacy_monthly_records.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Finance\app\Models\StudentFeeAssignment;

$enrollmentId = '187f8420-79f6-425c-967e-7915846a26ca';

echo "=== Syncing Legacy MonthlyFeeRecords from Smart StudentFeeAssignments ===\n\n";

// Get all smart assignments with period_month that are paid
$smartAssignments = StudentFeeAssignment::where('enrollment_id', $enrollmentId)
    ->whereNotNull('period_month')
    ->whereIn('status', ['paid', 'partial'])
    ->get();

echo "Found " . $smartAssignments->count() . " smart assignments with period_month:\n";
foreach ($smartAssignments as $sa) {
    echo "  Smart: {$sa->id} | month: {$sa->period_month} | status: {$sa->status} | paid: {$sa->paid_amount}\n";
}

echo "\n";

$syncedCount = 0;
$skippedCount = 0;

foreach ($smartAssignments as $assignment) {
    // Find the corresponding legacy record
    $legacyRecord = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
        ->where('month', $assignment->period_month)
        ->first();

    if (!$legacyRecord) {
        echo "  ⚠ No legacy record found for month {$assignment->period_month}\n";
        $skippedCount++;
        continue;
    }

    // Check if legacy record needs updating
    if ($legacyRecord->payment_status === 'paid') {
        echo "  ✓ Month {$assignment->period_month}: Already synced (legacy=paid)\n";
        $skippedCount++;
        continue;
    }

    // Update legacy record to match smart assignment
    $newPaidAmount = $assignment->paid_amount;
    $newStatus = $assignment->status;
    $totalDue = $legacyRecord->due_amount;
    $remainingDue = max(0, $totalDue - $newPaidAmount);

    $legacyRecord->update([
        'paid_amount' => $newPaidAmount,
        'due_amount' => $totalDue, // Keep original discounted amount
        'payment_status' => $newStatus === 'paid' ? 'paid' : ($remainingDue <= 0 ? 'paid' : 'partial'),
        'paid_at' => $newStatus === 'paid' ? now() : null,
    ]);

    echo "  ✅ Month {$assignment->period_month}: Synced! Legacy status: {$legacyRecord->fresh()->payment_status}, paid_amount: {$legacyRecord->fresh()->paid_amount}\n";
    $syncedCount++;
}

echo "\n=== Summary ===\n";
echo "Synced: {$syncedCount}\n";
echo "Skipped: {$skippedCount}\n";

// Also sync enrollment totals
echo "\n=== Syncing Enrollment Payment Totals ===\n";
$monthlyFeeService = app(\Modules\Enrollment\app\Services\MonthlyFeeService::class);
$monthlyFeeService->syncEnrollmentPaymentTotals($enrollmentId);
echo "Enrollment totals synced.\n";

echo "\nDone!\n";
