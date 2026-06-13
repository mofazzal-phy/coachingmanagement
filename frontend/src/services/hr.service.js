import apiClient from './api.service'

export default {
  // ========== Employees ==========
  employees: {
    list(params = {}) {
      return apiClient.get('/employees', { params })
    },
    get(id) {
      return apiClient.get(`/employees/${id}`)
    },
    create(data) {
      return apiClient.post('/employees', data)
    },
    update(id, data) {
      return apiClient.put(`/employees/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/employees/${id}`)
    },
  },

  // ========== Departments ==========
  departments: {
    list(params = {}) {
      return apiClient.get('/departments', { params })
    },
    get(id) {
      return apiClient.get(`/departments/${id}`)
    },
    create(data) {
      return apiClient.post('/departments', data)
    },
    update(id, data) {
      return apiClient.put(`/departments/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/departments/${id}`)
    },
  },

  // ========== Designations ==========
  designations: {
    list(params = {}) {
      return apiClient.get('/designations', { params })
    },
    get(id) {
      return apiClient.get(`/designations/${id}`)
    },
    create(data) {
      return apiClient.post('/designations', data)
    },
    update(id, data) {
      return apiClient.put(`/designations/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/designations/${id}`)
    },
  },

  // ========== Staff Attendance ==========
  staffAttendance: {
    list(params = {}) {
      return apiClient.get('/staff-attendance', { params })
    },
    create(data) {
      return apiClient.post('/staff-attendance', data)
    },
    update(id, data) {
      return apiClient.put(`/staff-attendance/${id}`, data)
    },
    markBulk(data) {
      return apiClient.post('/staff-attendance/bulk', data)
    },
  },

  // ========== Leave Requests ==========
  leaveRequests: {
    list(params = {}) {
      return apiClient.get('/leave-requests', { params })
    },
    get(id) {
      return apiClient.get(`/leave-requests/${id}`)
    },
    create(data) {
      return apiClient.post('/leave-requests', data)
    },
    update(id, data) {
      return apiClient.put(`/leave-requests/${id}`, data)
    },
    approve(id) {
      return apiClient.post(`/leave-requests/${id}/approve`)
    },
    reject(id, reason = '') {
      return apiClient.post(`/leave-requests/${id}/reject`, { reason })
    },
  },

  // ========== Leave Types ==========
  leaveTypes: {
    list(params = {}) {
      return apiClient.get('/leave-types', { params })
    },
    create(data) {
      return apiClient.post('/leave-types', data)
    },
    update(id, data) {
      return apiClient.put(`/leave-types/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/leave-types/${id}`)
    },
  },

  // ========== Payroll ==========
  payroll: {
    list(params = {}) {
      return apiClient.get('/payrolls', { params })
    },
    get(id) {
      return apiClient.get(`/payrolls/${id}`)
    },
    create(data) {
      return apiClient.post('/payrolls', data)
    },
    update(id, data) {
      return apiClient.put(`/payrolls/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/payrolls/${id}`)
    },
    process(id) {
      return apiClient.post(`/payrolls/${id}/process`)
    },
    markPaid(id) {
      return apiClient.post(`/payrolls/${id}/paid`)
    },
  },
}
