import apiClient from './api.service';

export default {
    // ========== MONTHLY FEE RECORDS ==========

    /**
     * Get all monthly fee records (admin).
     */
    getRecords(params = {}) {
        return apiClient.get('/monthly-fees', { params });
    },

    /**
     * Get a single monthly fee record with payments.
     */
    getRecord(id) {
        return apiClient.get(`/monthly-fees/${id}`);
    },

    /**
     * Get monthly fee records for a specific enrollment.
     */
    getEnrollmentRecords(enrollmentId, params = {}) {
        return apiClient.get(`/monthly-fees/enrollment/${enrollmentId}`, { params });
    },

    /**
     * Get monthly fee summary for an enrollment.
     */
    getEnrollmentSummary(enrollmentId) {
        return apiClient.get(`/monthly-fees/enrollment/${enrollmentId}/summary`);
    },

    /**
     * Get overdue monthly fee records.
     */
    getOverdueRecords(params = {}) {
        return apiClient.get('/monthly-fees/overdue', { params });
    },

    /**
     * Get current month pending fees.
     */
    getCurrentMonthPending() {
        return apiClient.get('/monthly-fees/current-month-pending');
    },

    /**
     * Record a payment against a monthly fee record (admin).
     * For cash/offline: immediately confirmed.
     * For bKash/Nagad/online: goes to awaiting_confirmation.
     */
    recordPayment(recordId, data) {
        return apiClient.post(`/monthly-fees/${recordId}/pay`, data);
    },

    // ========== PAYMENT CONFIRMATION WORKFLOW ==========

    /**
     * Get all payments awaiting admin confirmation.
     */
    getPendingConfirmations(params = {}) {
        return apiClient.get('/monthly-fees/pending-confirmations', { params });
    },

    /**
     * Get all confirmed payments.
     */
    getConfirmedPayments(params = {}) {
        return apiClient.get('/monthly-fees/confirmed-payments', { params });
    },

    /**
     * Confirm a pending payment (admin approves).
     * Auto-generates invoice.
     */
    confirmPayment(paymentId, data = {}) {
        return apiClient.post(`/monthly-fees/${paymentId}/confirm`, data);
    },

    /**
     * Reject a pending payment (admin rejects).
     */
    rejectPayment(paymentId, data) {
        return apiClient.post(`/monthly-fees/${paymentId}/reject`, data);
    },

    /**
     * Download invoice for a confirmed payment.
     */
    downloadInvoice(paymentId) {
        return apiClient.get(`/monthly-fees/${paymentId}/invoice/download`, {
            responseType: 'blob',
        });
    },

    // ========== PAYMENT GATEWAYS ==========

    /**
     * Get list of enabled payment gateways.
     */
    getGateways() {
        return apiClient.get('/payment/gateways');
    },

    /**
     * Initiate a payment through a gateway (student self-payment).
     */
    initiatePayment(data) {
        return apiClient.post('/payment/initiate', data);
    },

    /**
     * Verify a payment transaction.
     */
    verifyPayment(gateway, transactionId) {
        return apiClient.post('/payment/verify', { gateway, transaction_id: transactionId });
    },

    /**
     * Process a manual payment (admin).
     */
    manualPayment(data) {
        return apiClient.post('/payment/manual', data);
    },
};
