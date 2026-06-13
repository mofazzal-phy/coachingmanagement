import apiClient from './api.service'

export default {
  // Profile
  profile() {
    return apiClient.get('/student-portal/profile')
  },

  // Dashboard
  dashboard() {
    return apiClient.get('/student-portal/dashboard')
  },

  // Enrollments
  enrollments() {
    return apiClient.get('/student-portal/enrollments')
  },

  // Fee Management
  feeDashboard() {
    return apiClient.get('/student-portal/fee-dashboard')
  },
  feeLedger(enrollmentId) {
    return apiClient.get(`/student-portal/fee-ledger/${enrollmentId}`)
  },
  feeRecords(enrollmentId) {
    return apiClient.get(`/student-portal/fee-records/${enrollmentId}`)
  },

  // Exams
  exams() {
    return apiClient.get('/student-portal/exams')
  },
  examRoutines() {
    return apiClient.get('/student-portal/exam-routines')
  },
  examResults() {
    return apiClient.get('/student-portal/exam-results')
  },

  // Class Routines
  classRoutines() {
    return apiClient.get('/student-portal/class-routines')
  },

  // Notices
  notices() {
    return apiClient.get('/student-portal/notices')
  },

  // Leave Management
  leaveApplications() {
    return apiClient.get('/student-portal/leave-applications')
  },
  applyLeave(data) {
    return apiClient.post('/student-portal/apply-leave', data)
  },

  // Attendance
  attendance() {
    return apiClient.get('/student-portal/attendance')
  },

  // Enrollment Details (subjects, teachers)
  enrollmentDetails(enrollmentId) {
    return apiClient.get(`/student-portal/enrollment-details/${enrollmentId}`)
  },

  // Study Materials & Downloads
  studyMaterials() {
    return apiClient.get('/student-portal/study-materials')
  },
  downloads() {
    return apiClient.get('/student-portal/downloads')
  },
}
