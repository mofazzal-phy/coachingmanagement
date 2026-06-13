import apiClient from './api.service'

export default {
  list(params = {}) {
    return apiClient.get('/students', { params })
  },
  get(id) {
    return apiClient.get(`/students/${id}`)
  },
  getStudents(params = {}) {
    return apiClient.get('/students', { params })
  },
  create(data) {
    const config = data instanceof FormData
      ? { headers: { 'Content-Type': 'multipart/form-data' } }
      : {}
    return apiClient.post('/students', data, config)
  },
  createStudent(data) {
    return this.create(data)
  },
  update(id, data) {
    const config = data instanceof FormData
      ? { headers: { 'Content-Type': 'multipart/form-data' } }
      : {}
    return apiClient.put(`/students/${id}`, data, config)
  },
  downloadIdCard(id, enrollmentId = null) {
    const params = enrollmentId ? { enrollment_id: enrollmentId } : {}
    return apiClient.get(`/students/${id}/id-card/download`, {
      params,
      responseType: 'blob',
    })
  },
  delete(id) {
    return apiClient.delete(`/students/${id}`)
  },
  getClasses() {
    return apiClient.get('/classes/list/all')
  },
  getFeeSummary(id) {
    return apiClient.get(`/students/${id}/fee-summary`)
  },
  recordPayment(id, data) {
    return apiClient.post(`/students/${id}/pay`, data)
  },
}
