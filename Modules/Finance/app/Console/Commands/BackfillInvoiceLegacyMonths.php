<?php

namespace Modules\Finance\app\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\app\Models\SmartPaymentInvoice;
use Modules\Finance\app\Models\PaymentTransaction;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;

class BackfillInvoiceLegacyMonths extends Command
{
    protected $signature = 'fee:backfill-invoice-legacy-months
                            {--dry-run : Preview changes without applying them}';

    protected $description = 'Backfill legacy_months metadata into existing invoices that were generated for legacy MonthlyFeeRecord payments';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        // Get all invoices that have paymentTransaction with remarks containing "Legacy months:"
        $invoices = SmartPaymentInvoice::whereHas('paymentTransaction', function ($q) {
            $q->where('remarks', 'like', '%Legacy months:%');
        })->with('paymentTransaction')->get();

        $this->info("Found {$invoices->count()} invoices with legacy month remarks in transactions.");

        foreach ($invoices as $invoice) {
            $transaction = $invoice->paymentTransaction;
            $metadata = $invoice->metadata ?? [];

            // Skip if legacy_months already exists and is not empty
            if (!empty($metadata['legacy_months'])) {
                $this->line("  [SKIP] Invoice {$invoice->invoice_no} already has legacy_months: " . implode(',', $metadata['legacy_months']));
                $skipped++;
                continue;
            }

            // Extract legacy months from transaction remarks
            $legacyMonths = [];
            if ($transaction->remarks && preg_match('/Legacy months:\s*([\d,\-]+)/', $transaction->remarks, $m)) {
                $legacyMonths = explode(',', $m[1]);
            }

            if (empty($legacyMonths)) {
                $this->line("  [SKIP] Invoice {$invoice->invoice_no} has no legacy months in remarks.");
                $skipped++;
                continue;
            }

            // Also try to resolve period_description from legacy months
            $monthLabels = array_map(function ($ym) {
                if (preg_match('/^\d{4}-\d{2}$/', $ym)) {
                    return \Carbon\Carbon::createFromFormat('Y-m', $ym)?->format('F Y') ?? $ym;
                }
                return $ym;
            }, $legacyMonths);

            $enrollment = $transaction->enrollment;
            $courseName = $enrollment?->batch?->course?->name ?? 'Fee';
            $periodDescription = $courseName . ' - ' . implode(', ', $monthLabels);

            // Update metadata
            $metadata['legacy_months'] = $legacyMonths;
            $metadata['period_description'] = $periodDescription;

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would update Invoice {$invoice->invoice_no} with legacy_months: " . implode(',', $legacyMonths));
                $updated++;
            } else {
                $invoice->updateQuietly(['metadata' => $metadata]);
                $this->line("  [OK] Updated Invoice {$invoice->invoice_no} with legacy_months: " . implode(',', $legacyMonths));
                $updated++;
            }
        }

        // Also check for invoices where transaction has NO allocations but paid for legacy monthly fees
        // (transactions without "Legacy months:" in remarks but clearly for legacy fees)
        $this->info("Checking invoices without explicit legacy month remarks...");

        $allInvoices = SmartPaymentInvoice::with('paymentTransaction.allocations')->get();

        foreach ($allInvoices as $invoice) {
            $metadata = $invoice->metadata ?? [];
            if (!empty($metadata['legacy_months'])) {
                continue; // Already has legacy_months
            }

            $transaction = $invoice->paymentTransaction;
            if (!$transaction) {
                continue;
            }

            // Skip if transaction has allocations (smart fee system)
            if ($transaction->allocations->isNotEmpty()) {
                continue;
            }

            // This transaction has no allocations - likely a legacy monthly fee payment
            // Try to find which months were paid by looking at MonthlyFeeRecord updates
            $enrollmentId = $transaction->enrollment_id;
            $transactionDate = $transaction->created_at;

            // Find MonthlyFeeRecords that were paid around this transaction time
            // We look for records where paid_at is close to the transaction date
            $paidRecords = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
                ->where('payment_status', 'paid')
                ->whereNotNull('paid_at')
                ->orderBy('paid_at', 'desc')
                ->get();

            $matchedMonths = [];
            foreach ($paidRecords as $record) {
                // Check if the record was paid within 5 minutes of this transaction
                if ($record->paid_at && abs($record->paid_at->diffInMinutes($transactionDate)) <= 5) {
                    if ($record->month) {
                        $matchedMonths[] = $record->month;
                    }
                }
            }

            if (!empty($matchedMonths)) {
                $matchedMonths = array_unique($matchedMonths);
                $monthLabels = array_map(function ($ym) {
                    if (preg_match('/^\d{4}-\d{2}$/', $ym)) {
                        return \Carbon\Carbon::createFromFormat('Y-m', $ym)?->format('F Y') ?? $ym;
                    }
                    return $ym;
                }, $matchedMonths);

                $enrollment = $transaction->enrollment;
                $courseName = $enrollment?->batch?->course?->name ?? 'Fee';
                $periodDescription = $courseName . ' - ' . implode(', ', $monthLabels);

                $metadata['legacy_months'] = array_values($matchedMonths);
                $metadata['period_description'] = $periodDescription;

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Would update Invoice {$invoice->invoice_no} with inferred legacy_months: " . implode(',', $matchedMonths));
                    $updated++;
                } else {
                    $invoice->updateQuietly(['metadata' => $metadata]);
                    $this->line("  [OK] Updated Invoice {$invoice->invoice_no} with inferred legacy_months: " . implode(',', $matchedMonths));
                    $updated++;
                }
            } else {
                $this->line("  [SKIP] Invoice {$invoice->invoice_no} - could not infer legacy months.");
                $skipped++;
            }
        }

        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
