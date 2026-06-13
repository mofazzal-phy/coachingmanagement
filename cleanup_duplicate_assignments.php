<?php
/**
 * Cleanup duplicate exam fee StudentFeeAssignment records.
 * 
 * This script finds and removes duplicate StudentFeeAssignment records
 * that were created by the bug where every admin collection of exam fee
 * created a new assignment instead of reusing existing ones.
 * 
 * It keeps only the most recent assignment for each unique combination
 * of (enrollment_id, fee_structure_id) and removes older duplicates.
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Finance\app\Models\StudentFeeAssignment;
use Modules\Finance\app\Models\StudentFeeNotification;
use Modules\Finance\app\Models\PaymentAllocation;

echo "=== Cleaning up duplicate exam fee assignments ===\n\n";

// Find all assignments grouped by enrollment_id + fee_structure_id
$allAssignments = StudentFeeAssignment::whereNotNull('fee_structure_id')
    ->orderBy('created_at', 'asc')
    ->get()
    ->groupBy(function ($a) {
        return $a->enrollment_id . '|' . $a->fee_structure_id;
    });

$totalRemoved = 0;
$totalKept = 0;

foreach ($allAssignments as $key => $group) {
    if ($group->count() <= 1) {
        continue; // No duplicates for this group
    }
    
    echo "Group: {$key} ({$group->count()} records)\n";
    
    // Keep the most recent one (last in the sorted group)
    $toKeep = $group->last();
    $toRemove = $group->slice(0, -1); // All except the last
    
    echo "  Keeping: {$toKeep->id} (status: {$toKeep->status}, paid: {$toKeep->paid_amount}, created: {$toKeep->created_at})\n";
    
    foreach ($toRemove as $remove) {
        // Check if this assignment has payment allocations
        $allocations = PaymentAllocation::where('fee_assignment_id', $remove->id)->count();
        
        echo "  Removing: {$remove->id} (status: {$remove->status}, paid: {$remove->paid_amount}, allocations: {$allocations}, created: {$remove->created_at})";
        
        if ($allocations > 0) {
            echo " - HAS ALLOCATIONS, will keep anyway\n";
            // Don't remove if it has allocations - just log it
            $totalKept++;
            continue;
        }
        
        // Delete the duplicate assignment
        $remove->delete();
        echo " - DELETED\n";
        $totalRemoved++;
    }
}

echo "\n=== Summary ===\n";
echo "Total duplicate groups found: " . $allAssignments->filter(fn($g) => $g->count() > 1)->count() . "\n";
echo "Total removed: {$totalRemoved}\n";
echo "Total kept (had allocations): {$totalKept}\n";

// Also check for any orphaned notifications that should be cleaned up
echo "\n=== Checking notification status consistency ===\n";

$notifications = StudentFeeNotification::where('status', 'paid')->get();
echo "Total paid notifications: " . $notifications->count() . "\n";

$unreadNotifications = StudentFeeNotification::whereIn('status', ['unread', 'read'])->get();
echo "Total unread/read notifications: " . $unreadNotifications->count() . "\n";

echo "\nDone.\n";
