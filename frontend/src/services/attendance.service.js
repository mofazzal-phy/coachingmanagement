import apiClient from './api.service'

export default {
  // ========== Legacy endpoints (backward compatibility) ==========
  list(params = {}) {
    return apiClient.get('/attendance', { params })
  },
  get(id) {
    return apiClient.get(`/attendance/${id}`)
  },
  getByClass(params) {
    return apiClient.get('/attendance/by-class', { params })
  },
  create(data) {
    return apiClient.post('/attendance', data)
  },
  update(id, data) {
    return apiClient.put(`/attendance/${id}`, data)
  },
  delete(id) {
    return apiClient.delete(`/attendance/${id}`)
  },
  markBulk(data) {
    return apiClient.post('/attendance/bulk', data)
  },
  summary(params) {
    return apiClient.get('/attendance/summary', { params })
  },

  // ========== Own Attendance (portal) ==========
  getMyAttendance() {
    return apiClient.get('/attendance/my')
  },

  // ========== Student Attendance ==========
  getStudents(params) {
    return apiClient.get('/attendance/students', { params })
  },
  markStudentAttendance(data) {
    return apiClient.post('/attendance/students/mark', data)
  },
  bulkMarkStudentAttendance(data) {
    return apiClient.post('/attendance/students/bulk-mark', data)
  },
  getStudentSummary(params) {
    return apiClient.get('/attendance/students/summary', { params })
  },
  getSubjectWiseAttendance(params) {
    return apiClient.get('/attendance/students/subject-wise', { params })
  },
  getStudentBatchReport(params) {
    return apiClient.get('/attendance/students/batch-report', { params })
  },

  // ========== Teacher Attendance ==========
  getTeachers(params) {
    return apiClient.get('/attendance/teachers', { params })
  },
  markTeacherAttendance(data) {
    return apiClient.post('/attendance/teachers/mark', data)
  },
  bulkMarkTeacherAttendance(data) {
    return apiClient.post('/attendance/teachers/bulk-mark', data)
  },
  getTeacherClassLedger(params) {
    return apiClient.get('/attendance/teachers/class-ledger', { params })
  },
  bulkMarkTeacherClassLedger(data) {
    return apiClient.post('/attendance/teachers/class-ledger/mark', data)
  },
  getTeacherSummary(params) {
    return apiClient.get('/attendance/teachers/summary', { params })
  },

  // ========== Employee Attendance ==========
  getEmployees(params) {
    return apiClient.get('/attendance/employees', { params })
  },
  markEmployeeAttendance(data) {
    return apiClient.post('/attendance/employees/mark', data)
  },
  bulkMarkEmployeeAttendance(data) {
    return apiClient.post('/attendance/employees/bulk-mark', data)
  },
  getEmployeeSummary(params) {
    return apiClient.get('/attendance/employees/summary', { params })
  },

  // ========== Attendance Sessions ==========
  getSessions(params) {
    return apiClient.get('/attendance/sessions', { params })
  },
  getSession(id) {
    return apiClient.get(`/attendance/sessions/${id}`)
  },
  createSession(data) {
    return apiClient.post('/attendance/sessions', data)
  },
  getClassSessions(params) {
    return apiClient.get('/attendance/class-sessions', { params })
  },
  syncClassSessions(data) {
    return apiClient.post('/attendance/class-sessions/sync', data)
  },
  sessionFromRoutine(data) {
    return apiClient.post('/attendance/sessions/from-routine', data)
  },
  updateSession(id, data) {
    return apiClient.put(`/attendance/sessions/${id}`, data)
  },
  closeSession(id) {
    return apiClient.post(`/attendance/sessions/${id}/close`)
  },
  cancelSession(id) {
    return apiClient.post(`/attendance/sessions/${id}/cancel`)
  },
  detectCurrentSession(params) {
    return apiClient.get('/attendance/sessions/detect/current', { params })
  },

  // ========== Biometric Devices ==========
  getDevices(params) {
    return apiClient.get('/attendance/devices', { params })
  },
  getDevice(id) {
    return apiClient.get(`/attendance/devices/${id}`)
  },
  getDeviceDrivers() {
    return apiClient.get('/attendance/devices/drivers/list')
  },
  createDevice(data) {
    return apiClient.post('/attendance/devices', data)
  },
  updateDevice(id, data) {
    return apiClient.put(`/attendance/devices/${id}`, data)
  },
  deleteDevice(id) {
    return apiClient.delete(`/attendance/devices/${id}`)
  },
  testDeviceConnection(id) {
    return apiClient.post(`/attendance/devices/${id}/test-connection`)
  },
  pullAttendanceFromDevice(id) {
    return apiClient.post(`/attendance/devices/${id}/pull-attendance`)
  },

  // ========== Dashboard ==========
  getTodayOverview() {
    return apiClient.get('/attendance/dashboard/today')
  },
  getRealtimeData(params) {
    return apiClient.get('/attendance/dashboard/realtime', { params })
  },
  getMonthlyTrend(params) {
    return apiClient.get('/attendance/dashboard/monthly-trend', { params })
  },
  getLowAttendanceAlerts(params) {
    return apiClient.get('/attendance/dashboard/low-attendance-alerts', { params })
  },
  getConsecutiveAbsentAlerts(params) {
    return apiClient.get('/attendance/dashboard/consecutive-absent-alerts', { params })
  },
  getDeviceStatus() {
    return apiClient.get('/attendance/dashboard/device-status')
  },

  // ========== Reports ==========
  getDailyReport(params) {
    return apiClient.get('/attendance/reports/daily', { params })
  },
  getMonthlyReport(params) {
    return apiClient.get('/attendance/reports/monthly', { params })
  },
  getBatchReport(params) {
    return apiClient.get('/attendance/reports/batch', { params })
  },
  getSubjectReport(params) {
    return apiClient.get('/attendance/reports/subject', { params })
  },
  getTeacherReport(params) {
    return apiClient.get('/attendance/reports/teacher', { params })
  },
  getEmployeeReport(params) {
    return apiClient.get('/attendance/reports/employee', { params })
  },
  getSessionReport(params) {
    return apiClient.get('/attendance/reports/session', { params })
  },

  // ========== Device Simulator ==========
  getSimulatorDevices() {
    return apiClient.get('/attendance/simulator/devices')
  },
  getSimulatorUsers(params) {
    return apiClient.get('/attendance/simulator/users', { params })
  },
  simulateScan(data) {
    return apiClient.post('/attendance/simulator/scan', data)
  },
  generateRandomEvents(data) {
    return apiClient.post('/attendance/simulator/generate-random', data)
  },
  getRecentSimulatorScans(params) {
    return apiClient.get('/attendance/simulator/recent-scans', { params })
  },
}
