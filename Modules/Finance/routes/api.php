<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\app\Http\Controllers\Api\V1\FeeController;
use Modules\Finance\app\Http\Controllers\Api\V1\ExpenseController;
use Modules\Finance\app\Http\Controllers\Api\V1\FeeManagementController;
use Modules\Finance\app\Http\Controllers\Api\V1\ExamFeeNotificationController;

// Read-only routes - teachers, employees, students can view finance data
Route::middleware(['api.auth'])->prefix('v1')->group(function () {

    // Fee Types - view
    Route::get('fee-types', [FeeController::class, 'types']);
    Route::get('fee-types/{id}', [FeeController::class, 'showType']);

    // Fee Structures - view
    Route::get('fee-structures', [FeeController::class, 'structures']);
    Route::get('fee-structures/{id}', [FeeController::class, 'showStructure']);

    // Fee Collections - view
    Route::get('fee-collections', [FeeController::class, 'collections']);
    Route::get('fee-collections/{id}', [FeeController::class, 'showCollection']);
    Route::get('fee-collections/student/{studentId}/due', [FeeController::class, 'studentDue']);
    Route::get('fee-summary', [FeeController::class, 'summary']);

    // Expense Categories - view
    Route::get('expense-categories', [ExpenseController::class, 'categories']);

    // Expenses - view
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::get('expenses/{id}', [ExpenseController::class, 'show']);

    // Discount Rules - read-only (accessible by any authenticated user, needed for enrollment wizard)
    Route::get('fee/admin/discount-rules', [FeeManagementController::class, 'discountRules']);

    // ==================== SMART FEE MANAGEMENT - STUDENT APIs ====================

    // Student Fee Dashboard
    Route::get('fee/student/dashboard', [FeeManagementController::class, 'studentDashboard']);

    // Student Fee Ledger
    Route::get('fee/student/ledger/{enrollmentId}', [FeeManagementController::class, 'studentLedger']);

    // Student Fee Assignments
    Route::get('fee/student/assignments/{enrollmentId}', [FeeManagementController::class, 'studentAssignments']);

    // Student Payment History
    Route::get('fee/student/payments/{enrollmentId}', [FeeManagementController::class, 'studentPayments']);

    // Student Notification Preferences
    Route::get('fee/student/notification-preferences/{studentId}', [FeeManagementController::class, 'getNotificationPreferences']);

    // Student Submit Payment
    Route::post('fee/student/pay', [FeeManagementController::class, 'studentPay']);

    // Student Update Notification Preferences
    Route::put('fee/student/notification-preferences/{studentId}', [FeeManagementController::class, 'updateNotificationPreferences']);

    // Student Invoice - Download invoice PDF (MUST be before the wildcard route)
    Route::get('fee/student/invoice/{invoiceId}/download', [FeeManagementController::class, 'downloadInvoice']);

    // Student Invoice - Get invoice by transaction
    Route::get('fee/student/invoice/{transactionId}', [FeeManagementController::class, 'getInvoiceByTransaction']);

    // Student Invoices by Enrollment (with allocations eager-loaded for invoice lookup)
    Route::get('fee/student/invoices/{enrollmentId}', [FeeManagementController::class, 'studentInvoices']);
    // Student Fee Notifications
    Route::get('fee/student/notifications', [ExamFeeNotificationController::class, 'getNotifications']);
    Route::get('fee/student/notifications/count', [ExamFeeNotificationController::class, 'getNotificationCount']);
    Route::put('fee/student/notifications/{id}/read', [ExamFeeNotificationController::class, 'markAsRead']);
    Route::put('fee/student/notifications/read-all', [ExamFeeNotificationController::class, 'markAllAsRead']);
    Route::get('fee/student/notified-fees', [ExamFeeNotificationController::class, 'getNotifiedFees']);

    // Student Bulk Payment
    Route::post('fee/student/bulk-pay', [ExamFeeNotificationController::class, 'bulkPay']);
});

// Admin/Employee write routes
Route::middleware(['api.auth', 'role:super-admin,admin,employee'])->prefix('v1')->group(function () {

    // Fee Types - CRUD
    Route::post('fee-types', [FeeController::class, 'storeType']);
    Route::put('fee-types/{id}', [FeeController::class, 'updateType']);
    Route::delete('fee-types/{id}', [FeeController::class, 'destroyType']);

    // Fee Structures - CRUD
    Route::post('fee-structures', [FeeController::class, 'storeStructure']);
    Route::put('fee-structures/{id}', [FeeController::class, 'updateStructure']);
    Route::delete('fee-structures/{id}', [FeeController::class, 'destroyStructure']);

    // Fee Collections - CRUD
    Route::post('fee-collections', [FeeController::class, 'collectFee']);
    Route::put('fee-collections/{id}', [FeeController::class, 'updateCollection']);
    Route::delete('fee-collections/{id}', [FeeController::class, 'destroyCollection']);

    // Expense Categories - CRUD
    Route::post('expense-categories', [ExpenseController::class, 'storeCategory']);
    Route::put('expense-categories/{id}', [ExpenseController::class, 'updateCategory']);
    Route::delete('expense-categories/{id}', [ExpenseController::class, 'destroyCategory']);

    // Expenses - CRUD
    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::put('expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('expenses/{id}', [ExpenseController::class, 'destroy']);

    // ==================== SMART FEE MANAGEMENT - ADMIN APIs ====================

    // Admin Dashboard
    Route::get('fee/admin/dashboard', [FeeManagementController::class, 'adminDashboard']);

    // Pending Payments
    Route::get('fee/admin/pending-payments', [FeeManagementController::class, 'pendingPayments']);

    // Confirmed Payments
    Route::get('fee/admin/confirmed-payments', [FeeManagementController::class, 'confirmedPayments']);

    // Rejected Payments
    Route::get('fee/admin/rejected-payments', [FeeManagementController::class, 'rejectedPayments']);

    // All Fee Assignments
    Route::get('fee/admin/assignments', [FeeManagementController::class, 'allAssignments']);

    // Audit Logs
    Route::get('fee/admin/audit-logs', [FeeManagementController::class, 'auditLogs']);

    // Confirm Payment
    Route::post('fee/admin/confirm-payment/{transactionId}', [FeeManagementController::class, 'confirmPayment']);

    // Reject Payment
    Route::post('fee/admin/reject-payment/{transactionId}', [FeeManagementController::class, 'rejectPayment']);

    // Manual Payment Recording
    Route::post('fee/admin/manual-payment', [FeeManagementController::class, 'manualPayment']);

    // Manual Payment with Specific Fee Allocations
    Route::post('fee/admin/manual-payment-with-allocations', [FeeManagementController::class, 'manualPaymentWithAllocations']);

    // Student Pending Fees (grouped by fee type with category)
    Route::get('fee/admin/student-pending-fees/{studentId}', [FeeManagementController::class, 'studentPendingFees']);

    // Assign Fees to Enrollment
    Route::post('fee/admin/assign-fees', [FeeManagementController::class, 'assignFees']);

    // Discount Rules CRUD
    Route::post('fee/admin/discount-rules', [FeeManagementController::class, 'storeDiscountRule']);
    Route::put('fee/admin/discount-rules/{id}', [FeeManagementController::class, 'updateDiscountRule']);
    Route::delete('fee/admin/discount-rules/{id}', [FeeManagementController::class, 'destroyDiscountRule']);

    // Late Fee Rules CRUD
    Route::get('fee/admin/late-fee-rules', [FeeManagementController::class, 'lateFeeRules']);
    Route::post('fee/admin/late-fee-rules', [FeeManagementController::class, 'storeLateFeeRule']);
    Route::put('fee/admin/late-fee-rules/{id}', [FeeManagementController::class, 'updateLateFeeRule']);
    Route::delete('fee/admin/late-fee-rules/{id}', [FeeManagementController::class, 'destroyLateFeeRule']);

    // Installment Plans CRUD
    Route::get('fee/admin/installment-plans', [FeeManagementController::class, 'installmentPlans']);
    Route::post('fee/admin/installment-plans', [FeeManagementController::class, 'storeInstallmentPlan']);
    Route::put('fee/admin/installment-plans/{id}', [FeeManagementController::class, 'updateInstallmentPlan']);
    Route::delete('fee/admin/installment-plans/{id}', [FeeManagementController::class, 'destroyInstallmentPlan']);

    // Admin Invoices
    Route::get('fee/admin/invoices', [FeeManagementController::class, 'allInvoices']);
    Route::post('fee/admin/generate-invoice/{transactionId}', [FeeManagementController::class, 'generateInvoice']);
    // Exam Fee Creation with Notification Dispatch
    Route::post('fee/admin/exam-fee/create', [ExamFeeNotificationController::class, 'createExamFee']);
    Route::get('fee/admin/exam-fee/scope/{examId}', [ExamFeeNotificationController::class, 'examFeeScope']);
    Route::post('fee/admin/exam-fee/bulk-create', [ExamFeeNotificationController::class, 'bulkCreateExamFee']);
    Route::post('fee/admin/exam-fee/dispatch/{examId}', [ExamFeeNotificationController::class, 'dispatchExamFeeNotifications']);

    // Exam Fee Notifications - Admin view & collection
    Route::get('fee/admin/exam-fee/notifications', [ExamFeeNotificationController::class, 'adminNotifications']);
    Route::post('fee/admin/exam-fee/collect', [ExamFeeNotificationController::class, 'adminCollectExamFee']);
});
