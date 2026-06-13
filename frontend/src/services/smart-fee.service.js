import apiClient from './api.service'

export default {
  // ========== Student APIs ==========
  student: {
    dashboard(params = {}) {
      return apiClient.get('/fee/student/dashboard', { params })
    },
    ledger(enrollmentId) {
      return apiClient.get(`/fee/student/ledger/${enrollmentId}`)
    },
    assignments(enrollmentId) {
      return apiClient.get(`/fee/student/assignments/${enrollmentId}`)
    },
    payments(enrollmentId) {
      return apiClient.get(`/fee/student/payments/${enrollmentId}`)
    },
    pay(data) {
      return apiClient.post('/fee/student/pay', data)
    },
    notificationPreferences(studentId) {
      return apiClient.get(`/fee/student/notification-preferences/${studentId}`)
    },
    updateNotificationPreferences(studentId, data) {
      return apiClient.put(`/fee/student/notification-preferences/${studentId}`, data)
    },
    // Fee Notifications
    notifications(params = {}) {
      return apiClient.get('/fee/student/notifications', { params })
    },
    notificationCount(params = {}) {
      return apiClient.get('/fee/student/notifications/count', { params })
    },
    markNotificationRead(id) {
      return apiClient.put(`/fee/student/notifications/${id}/read`)
    },
    markAllNotificationsRead(data) {
      return apiClient.put('/fee/student/notifications/read-all', data)
    },
    notifiedFees(params = {}) {
      return apiClient.get('/fee/student/notified-fees', { params })
    },
    bulkPay(data) {
      return apiClient.post('/fee/student/bulk-pay', data)
    },
  },

  // ========== Admin APIs ==========
  admin: {
    dashboard() {
      return apiClient.get('/fee/admin/dashboard')
    },
    pendingPayments(params = {}) {
      return apiClient.get('/fee/admin/pending-payments', { params })
    },
    confirmedPayments(params = {}) {
      return apiClient.get('/fee/admin/confirmed-payments', { params })
    },
    rejectedPayments(params = {}) {
      return apiClient.get('/fee/admin/rejected-payments', { params })
    },
    assignments(params = {}) {
      return apiClient.get('/fee/admin/assignments', { params })
    },
    auditLogs(params = {}) {
      return apiClient.get('/fee/admin/audit-logs', { params })
    },
    confirmPayment(transactionId) {
      return apiClient.post(`/fee/admin/confirm-payment/${transactionId}`)
    },
    rejectPayment(transactionId, data) {
      return apiClient.post(`/fee/admin/reject-payment/${transactionId}`, data)
    },
    manualPayment(data) {
      return apiClient.post('/fee/admin/manual-payment', data)
    },
    manualPaymentWithAllocations(data) {
      return apiClient.post('/fee/admin/manual-payment-with-allocations', data)
    },
    studentPendingFees(studentId, params = {}) {
      return apiClient.get(`/fee/admin/student-pending-fees/${studentId}`, { params })
    },
    assignFees(data) {
      return apiClient.post('/fee/admin/assign-fees', data)
    },
    invoices(params = {}) {
      return apiClient.get('/fee/admin/invoices', { params })
    },
    generateInvoice(transactionId) {
      return apiClient.post(`/fee/admin/generate-invoice/${transactionId}`)
    },
    // Exam Fee Creation with Notifications
    createExamFee(data) {
      return apiClient.post('/fee/admin/exam-fee/create', data)
    },
    examFeeScope(examId) {
      return apiClient.get(`/fee/admin/exam-fee/scope/${examId}`)
    },
    bulkCreateExamFee(data) {
      return apiClient.post('/fee/admin/exam-fee/bulk-create', data)
    },
    dispatchExamFeeNotifications(examId, data = {}) {
      return apiClient.post(`/fee/admin/exam-fee/dispatch/${examId}`, data)
    },
    // Exam Fee Notifications - Admin view
    examFeeNotifications(params = {}) {
      return apiClient.get('/fee/admin/exam-fee/notifications', { params })
    },
    // Exam Fee Collection - Admin collect on behalf of student
    collectExamFee(data) {
      return apiClient.post('/fee/admin/exam-fee/collect', data)
    },
  },

  // ========== Invoice APIs ==========
  invoices: {
    getByTransaction(transactionId) {
      return apiClient.get(`/fee/student/invoice/${transactionId}`)
    },
    download(invoiceId) {
      return apiClient.get(`/fee/student/invoice/${invoiceId}/download`, { responseType: 'blob' })
    },
    // Get all invoices for an enrollment (with allocations eager-loaded)
    byEnrollment(enrollmentId) {
      return apiClient.get(`/fee/student/invoices/${enrollmentId}`)
    },
  },

  // ========== Discount Rules ==========
  discountRules: {
    list() {
      return apiClient.get('/fee/admin/discount-rules')
    },
    create(data) {
      return apiClient.post('/fee/admin/discount-rules', data)
    },
    update(id, data) {
      return apiClient.put(`/fee/admin/discount-rules/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee/admin/discount-rules/${id}`)
    },
  },

  // ========== Late Fee Rules ==========
  lateFeeRules: {
    list() {
      return apiClient.get('/fee/admin/late-fee-rules')
    },
    create(data) {
      return apiClient.post('/fee/admin/late-fee-rules', data)
    },
    update(id, data) {
      return apiClient.put(`/fee/admin/late-fee-rules/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee/admin/late-fee-rules/${id}`)
    },
  },

  // ========== Installment Plans ==========
  installmentPlans: {
    list() {
      return apiClient.get('/fee/admin/installment-plans')
    },
    create(data) {
      return apiClient.post('/fee/admin/installment-plans', data)
    },
    update(id, data) {
      return apiClient.put(`/fee/admin/installment-plans/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee/admin/installment-plans/${id}`)
    },
  },
}
