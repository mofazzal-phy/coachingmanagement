import apiClient from './api.service';

export default {
    // ========== COURSES ==========
    getCourses(params = {}) {
        return apiClient.get('/courses', { params });
    },

    getCourse(id) {
        return apiClient.get(`/courses/${id}`);
    },

    createCourse(data) {
        const config = data instanceof FormData
            ? { headers: { 'Content-Type': 'multipart/form-data' } }
            : {};
        return apiClient.post('/courses', data, config);
    },

    updateCourse(id, data) {
        const config = data instanceof FormData
            ? { headers: { 'Content-Type': 'multipart/form-data' } }
            : {};
        return apiClient.put(`/courses/${id}`, data, config);
    },

    deleteCourse(id) {
        return apiClient.delete(`/courses/${id}`);
    },

    assignSubjects(id, subjects) {
        return apiClient.post(`/courses/${id}/assign-subjects`, { subjects });
    },

    getCourseAnalytics(id) {
        return apiClient.get(`/courses/${id}/analytics`);
    },

    listAllCourses() {
        return apiClient.get('/courses/list-all');
    },

    getCoursesByCategory(category, params = {}) {
        return apiClient.get(`/courses/by-category/${category}`, { params });
    },

    getCourseStatistics() {
        return apiClient.get('/courses/statistics');
    },

    bulkActionCourses(data) {
        return apiClient.post('/courses/bulk-action', data);
    },

    exportCourses() {
        return apiClient.get('/courses/export', { responseType: 'blob' });
    },

    restoreCourse(id) {
        return apiClient.post(`/courses/${id}/restore`);
    },

    duplicateCourse(id) {
        return apiClient.post(`/courses/${id}/duplicate`);
    },

    // ========== BATCHES ==========
    getBatches(params = {}) {
        return apiClient.get('/batches', { params });
    },

    getBatch(id) {
        return apiClient.get(`/batches/${id}`);
    },

    createBatch(data) {
        return apiClient.post('/batches', data);
    },

    updateBatch(id, data) {
        return apiClient.put(`/batches/${id}`, data);
    },

    deleteBatch(id) {
        return apiClient.delete(`/batches/${id}`);
    },

    getBatchStatistics() {
        return apiClient.get('/batches/statistics');
    },

    bulkActionBatches(data) {
        return apiClient.post('/batches/bulk-action', data);
    },

    exportBatches() {
        return apiClient.get('/batches/export', { responseType: 'blob' });
    },

    restoreBatch(id) {
        return apiClient.post(`/batches/${id}/restore`);
    },

    duplicateBatch(id) {
        return apiClient.post(`/batches/${id}/duplicate`);
    },

    getBatchesByCourse(courseId) {
        return apiClient.get(`/batches/by-course/${courseId}`);
    },

    getBatchStudents(id) {
        return apiClient.get(`/batches/${id}/students`);
    },

    // ========== ENROLLMENTS ==========
    getEnrollments(params = {}) {
        return apiClient.get('/enrollments', { params });
    },

    getEnrollment(id) {
        return apiClient.get(`/enrollments/${id}`);
    },

    updateEnrollment(id, data) {
        return apiClient.put(`/enrollments/${id}`, data);
    },

    deleteEnrollment(id) {
        return apiClient.delete(`/enrollments/${id}`);
    },

    exportEnrollments(params = {}) {
        return apiClient.get('/enrollments/export', { params, responseType: 'blob' });
    },

    enrollStudent(data) {
        return apiClient.post('/enrollments/enroll', data);
    },

    renewEnrollment(data) {
        return apiClient.post('/enrollments/renew', data);
    },

    confirmEnrollment(id) {
        return apiClient.post(`/enrollments/${id}/confirm`);
    },

    changeBatch(id, batchId) {
        return apiClient.post(`/enrollments/${id}/change-batch`, { batch_id: batchId });
    },

    recordPayment(id, data) {
        return apiClient.post(`/enrollments/${id}/payment`, data);
    },

    dropoutStudent(id, reason = '') {
        return apiClient.post(`/enrollments/${id}/dropout`, { reason });
    },

    calculateFee(data) {
        return apiClient.post('/enrollments/calculate-fee', data);
    },

    createStudent(data) {
        return apiClient.post('/students', data);
    },

    // ========== STUDENT (via enrollment module) ==========
    createStudentRecord(data) {
        const config = data instanceof FormData
            ? { headers: { 'Content-Type': 'multipart/form-data' } }
            : {};
        return apiClient.post('/students', data, config);
    },

    createGuardian(studentId, data) {
        return apiClient.post(`/students/${studentId}/guardian`, data);
    },

    // ========== SMART ENROLLMENT ==========
    searchStudent(query) {
        return apiClient.get('/enrollments/search-student', { params: { q: query } });
    },

    getSuggestedCourses(params = {}) {
        return apiClient.get('/enrollments/suggested-courses', { params });
    },

    getSuggestedBatches(courseId, mode = null) {
        return apiClient.get('/enrollments/suggested-batches', {
            params: { course_id: courseId, ...(mode ? { mode } : {}) }
        });
    },

    checkSiblingDiscount(studentId) {
        return apiClient.get(`/enrollments/sibling-discount/${studentId}`);
    },

    getPendingPayments(params = {}) {
        return apiClient.get('/enrollments/pending-payments', { params });
    },

    checkDuplicate(studentId, courseId) {
        return apiClient.get('/enrollments/check-duplicate', { params: { student_id: studentId, course_id: courseId } });
    },

    generateStudentId() {
        return apiClient.get('/enrollments/generate-student-id');
    },

    getWaitingList(params = {}) {
        return apiClient.get('/enrollments/waiting-list', { params });
    },

    addToWaitingList(data) {
        return apiClient.post('/enrollments/waiting-list/add', data);
    },

    approveFromWaitingList(id) {
        return apiClient.post(`/enrollments/waiting-list/${id}/approve`);
    },

    getWaitingListStats() {
        return apiClient.get('/enrollments/waiting-list/stats');
    },

    bulkApproveWaitingList(ids) {
        return apiClient.post('/enrollments/waiting-list/bulk-approve', { ids });
    },

    // ========== SESSION WIZARD ==========
    initSession(data) {
        return apiClient.post('/enrollment/initiate', data);
    },

    getSession(sessionId) {
        return apiClient.get(`/enrollment/session/${sessionId}`);
    },

    saveSessionStep(sessionId, step, data) {
        return apiClient.post(`/enrollment/session/${sessionId}/${step}`, data);
    },

    finalizeSession(sessionId, data) {
        return apiClient.post(`/enrollment/session/${sessionId}/payment`, data);
    },

    abandonSession(sessionId) {
        return apiClient.delete(`/enrollment/session/${sessionId}`);
    },

    getReceipt(enrollmentId) {
        return apiClient.get(`/enrollments/${enrollmentId}/receipt`);
    },

    getTimeline(enrollmentId) {
        return apiClient.get(`/enrollments/${enrollmentId}/timeline`);
    },

    refundPayment(enrollmentId) {
        return apiClient.post(`/enrollments/${enrollmentId}/refund`);
    },

    verifyPayment(enrollmentId) {
        return apiClient.post(`/enrollments/${enrollmentId}/verify-payment`);
    },

    downloadInvoice(enrollmentId) {
        return apiClient.get(`/enrollments/${enrollmentId}/invoice/download`, { responseType: 'blob' });
    },

    // ========== PUBLIC ==========
    getPublicCourses(params = {}) {
        return apiClient.get('/enrollment/public/courses', { params });
    },

    getPublicCourseDetails(id) {
        return apiClient.get(`/enrollment/public/courses/${id}`);
    },

    getPublicBatches(courseId) {
        return apiClient.get(`/enrollment/public/courses/${courseId}/batches`);
    },

    calculatePublicFee(data) {
        return apiClient.post('/enrollment/public/calculate-fee', data);
    },

    applyOnline(data) {
        return apiClient.post('/enrollment/public/apply', data);
    },

    trackEnrollment(enrollmentNo) {
        return apiClient.get(`/enrollment/public/track/${enrollmentNo}`);
    },

    // ========== REPORTS ==========
    getEnrollmentSummary() {
        return apiClient.get('/reports/enrollment/summary');
    },

    getBatchWiseReport() {
        return apiClient.get('/reports/enrollment/batch-wise');
    },

    getCourseWiseReport() {
        return apiClient.get('/reports/enrollment/course-wise');
    },

    getModeWiseReport() {
        return apiClient.get('/reports/enrollment/mode-wise');
    },

    getDailyEnrollment(days = 30) {
        return apiClient.get('/reports/enrollment/daily', { params: { days } });
    },

    getRevenueProjection() {
        return apiClient.get('/reports/enrollment/revenue-projection');
    },

    getReportOverview() {
        return apiClient.get('/reports/enrollment/overview');
    },
    getReportRevenueTrend(months = 6) {
        return apiClient.get('/reports/enrollment/revenue-trend', { params: { months } });
    },
    getReportOccupancy() {
        return apiClient.get('/reports/enrollment/occupancy');
    },
    getReportTeacherPerformance(params) {
        return apiClient.get('/reports/enrollment/teacher-performance', { params });
    },
    getReportBatchPerformance(params) {
        return apiClient.get('/reports/enrollment/batch-performance', { params });
    },
    getReportCoursePerformance(params) {
        return apiClient.get('/reports/enrollment/course-performance', { params });
    },

    getAuditLogs(params) {
        return apiClient.get('/audit-logs', { params });
    },

    // ========== DISCOUNT RULES (for enrollment wizard) ==========
    getDiscountRules() {
        return apiClient.get('/fee/admin/discount-rules');
    },
};
