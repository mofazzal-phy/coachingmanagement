<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Services\PaymentGatewayService;
use Modules\Enrollment\app\Services\MonthlyFeeService;
use Modules\Enrollment\app\Services\NotificationService;

class PaymentGatewayController extends BaseApiController
{
    protected PaymentGatewayService $gatewayService;
    protected MonthlyFeeService $monthlyFeeService;
    protected NotificationService $notificationService;

    public function __construct(
        PaymentGatewayService $gatewayService,
        MonthlyFeeService $monthlyFeeService,
        NotificationService $notificationService
    ) {
        $this->gatewayService = $gatewayService;
        $this->monthlyFeeService = $monthlyFeeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get list of enabled payment gateways.
     */
    public function gateways()
    {
        return $this->success([
            'gateways' => $this->gatewayService->getEnabledGateways(),
            'manual_methods' => [
                ['key' => 'cash', 'name' => 'Cash', 'type' => 'manual'],
                ['key' => 'bank', 'name' => 'Bank Transfer', 'type' => 'manual'],
                ['key' => 'manual', 'name' => 'Manual', 'type' => 'manual'],
            ],
        ]);
    }

    /**
     * Initiate a payment through a gateway.
     * Used for student self-payment from portal.
     */
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:bkash,nagad,rocket,stripe',
            'amount' => 'required|numeric|min:1',
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'monthly_fee_record_id' => 'nullable|string|exists:monthly_fee_records,id',
            'callback_url' => 'nullable|url',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->gatewayService->initiatePayment($validated['gateway'], [
                'amount' => $validated['amount'],
                'reference' => $validated['reference'] ?? 'PAY-' . uniqid(),
                'callback_url' => $validated['callback_url'] ?? url('/api/v1/payment/callback'),
                'enrollment_id' => $validated['enrollment_id'],
                'invoice_no' => 'INV-' . time(),
                'description' => 'Fee Payment - Enrollment',
            ]);

            if (!$result['success']) {
                return $this->error($result['message'] ?? 'Payment initiation failed', 400);
            }

            // Store the pending payment info in session/cache for verification
            cache()->put(
                "payment_pending_{$result['transaction_id']}",
                [
                    'gateway' => $validated['gateway'],
                    'enrollment_id' => $validated['enrollment_id'],
                    'monthly_fee_record_id' => $validated['monthly_fee_record_id'] ?? null,
                    'amount' => $validated['amount'],
                ],
                now()->addHours(2)
            );

            // In dev mode, auto-record the payment so it shows in the ledger immediately
            if ($this->gatewayService->isDevMockMode() && $validated['monthly_fee_record_id']) {
                $this->monthlyFeeService->recordPayment(
                    $validated['monthly_fee_record_id'],
                    $validated['amount'],
                    $validated['gateway'],
                    $result['transaction_id'],
                    'DEV: ' . ($validated['reference'] ?? 'Mock payment'),
                    'Auto-recorded in dev mode',
                    null,
                    null,
                    null,
                    auth()->id()
                );
            }

            return $this->success($result, 'Payment initiated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Handle payment gateway callback (after user completes payment on gateway page).
     */
    public function handleCallback(Request $request, string $gateway)
    {
        try {
            $paymentId = $request->input('paymentID')
                ?? $request->input('payment_id')
                ?? $request->input('session_id')
                ?? $request->input('trxID');

            if (!$paymentId) {
                return $this->error('Invalid callback: missing payment ID', 400);
            }

            // Verify the payment with the gateway
            $verification = $this->gatewayService->verifyPayment($gateway, $paymentId);

            if (!$verification['success']) {
                return $this->error('Payment verification failed', 400);
            }

            // Get pending payment info from cache
            $pendingInfo = cache()->pull("payment_pending_{$paymentId}");

            if (!$pendingInfo) {
                // Try to find by transaction ID
                return $this->error('Payment session expired or not found', 404);
            }

            // Record the payment
            $enrollment = Enrollment::findOrFail($pendingInfo['enrollment_id']);

            if ($pendingInfo['monthly_fee_record_id']) {
                // Monthly fee payment
                $record = $this->monthlyFeeService->recordPayment(
                    $pendingInfo['monthly_fee_record_id'],
                    $pendingInfo['amount'],
                    $gateway,
                    $paymentId,
                    'Gateway callback: ' . $gateway
                );

                // Send notification
                $this->notificationService->sendPaymentConfirmation(
                    $enrollment,
                    $pendingInfo['amount'],
                    $gateway,
                    $paymentId,
                    $record->month
                );
            } else {
                // Enrollment fee payment - update enrollment directly
                $enrollment->increment('paid_amount', $pendingInfo['amount']);
                $enrollment->decrement('due_amount', $pendingInfo['amount']);
                $enrollment->payment_status = $enrollment->due_amount <= 0 ? 'paid' : 'partial';
                $enrollment->status = $enrollment->due_amount <= 0 ? 'active' : $enrollment->status;
                $enrollment->save();

                // Send notification
                $this->notificationService->sendPaymentConfirmation(
                    $enrollment,
                    $pendingInfo['amount'],
                    $gateway,
                    $paymentId
                );
            }

            return $this->success([
                'gateway' => $gateway,
                'transaction_id' => $paymentId,
                'enrollment_id' => $enrollment->id,
            ], 'Payment completed successfully');
        } catch (\Exception $e) {
            \Log::error("Payment callback error ({$gateway}): " . $e->getMessage());
            return $this->error('Payment processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify a payment transaction status.
     */
    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        try {
            $result = $this->gatewayService->verifyPayment(
                $validated['gateway'],
                $validated['transaction_id']
            );

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Process a manual payment (student self-payment or admin).
     *
     * For cash/bank: immediately confirmed.
     * For bkash/nagad/rocket: goes to awaiting_confirmation (admin must verify).
     */
    public function manualPayment(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bkash,nagad,rocket,bank,card,manual',
            'transaction_id' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
            'monthly_fee_record_id' => 'nullable|string|exists:monthly_fee_records,id',
        ]);

        try {
            $enrollment = Enrollment::findOrFail($validated['enrollment_id']);

            // Get the authenticated user's ID for tracking who recorded the payment
            $confirmedBy = auth()->id();

            if ($validated['monthly_fee_record_id']) {
                // Monthly fee payment
                $record = $this->monthlyFeeService->recordPayment(
                    $validated['monthly_fee_record_id'],
                    $validated['amount'],
                    $validated['payment_method'],
                    $validated['transaction_id'] ?? null,
                    $validated['reference'] ?? null,
                    $validated['note'] ?? null,
                    null,  // paymentId
                    null,  // senderNumber
                    null,  // bankName
                    $confirmedBy  // confirmedBy - tracks who submitted the payment
                );

                $this->notificationService->sendPaymentConfirmation(
                    $enrollment,
                    $validated['amount'],
                    $validated['payment_method'],
                    $validated['transaction_id'] ?? null,
                    $record->month
                );

                return $this->success($record, 'Monthly fee payment recorded successfully');
            }

            // Direct enrollment payment
            $newPaid = $enrollment->paid_amount + $validated['amount'];
            $newDue = max(0, $enrollment->payable_fee - $newPaid);
            $paymentStatus = $newDue <= 0 ? 'paid' : 'partial';
            $status = $paymentStatus === 'paid' ? 'active' : $enrollment->status;

            $enrollment->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'payment_status' => $paymentStatus,
                'status' => $status,
                'enrolled_at' => $status === 'active' && !$enrollment->enrolled_at ? now() : $enrollment->enrolled_at,
            ]);

            $this->notificationService->sendPaymentConfirmation(
                $enrollment,
                $validated['amount'],
                $validated['payment_method'],
                $validated['transaction_id'] ?? null
            );

            return $this->success($enrollment->fresh(), 'Payment recorded successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
