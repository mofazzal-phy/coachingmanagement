import apiClient from './api.service'

export default {
  // ===== Admin APIs =====
  getRoutines(params = {}) {
    return apiClient.get('/class-routines', { params })
  },
  getRoutine(id) {
    return apiClient.get(`/class-routines/${id}`)
  },
  createRoutine(data) {
    return apiClient.post('/class-routines', data)
  },
  updateRoutine(id, data) {
    return apiClient.put(`/class-routines/${id}`, data)
  },
  deleteRoutine(id) {
    return apiClient.delete(`/class-routines/${id}`)
  },
  bulkStore(data) {
    return apiClient.post('/class-routines/bulk', data)
  },

  // Advanced admin endpoints
  generate(data) {
    return apiClient.post('/class-routines/generate', data)
  },
  swap(data) {
    return apiClient.post('/class-routines/swap', data)
  },
  publish(data) {
    return apiClient.post('/class-routines/publish', data)
  },
  publishMultiBatch(data) {
    return apiClient.post('/class-routines/publish/multi-batch', data)
  },
  archive(data) {
    return apiClient.post('/class-routines/archive', data)
  },
  archiveMultiBatch(data) {
    return apiClient.post('/class-routines/archive/multi-batch', data)
  },
  getConflicts(params) {
    return apiClient.get('/class-routines/conflicts', { params })
  },
  getByBatch(batchId, params = {}) {
    return apiClient.get(`/class-routines/by-batch/${batchId}`, { params })
  },
  getByCourse(courseId, params = {}) {
    return apiClient.get(`/class-routines/by-course/${courseId}`, { params })
  },
  getByClass(classId, params = {}) {
    return apiClient.get(`/class-routines/by-class/${classId}`, { params })
  },

  // ===== Multi-Batch Enterprise APIs =====
  getMultiBatchGrid(params) {
    return apiClient.get('/class-routines/multi-batch/grid', { params })
  },
  getMultiBatchStats(params) {
    return apiClient.get('/class-routines/multi-batch/stats', { params })
  },
  getMultiBatchConflicts(params) {
    return apiClient.get('/class-routines/multi-batch/conflicts', { params })
  },
  getTeacherLoad(params) {
    return apiClient.get('/class-routines/teacher-load', { params })
  },
  getRoomUtilization(params) {
    return apiClient.get('/class-routines/room-utilization', { params })
  },
  setLunchBreak(data) {
    return apiClient.post('/class-routines/lunch-break', data)
  },
  setOffDay(data) {
    return apiClient.post('/class-routines/off-day', data)
  },

  // ===== Teacher APIs =====
  getTeacherSchedule(date) {
    return apiClient.get('/teacher/my-schedule', { params: date ? { date } : {} })
  },
  getTeacherTodayClasses() {
    return apiClient.get('/teacher/my-schedule/today')
  },

  // ===== Student APIs =====
  getStudentRoutine() {
    return apiClient.get('/student/my-routine')
  },
  getStudentTodayClasses() {
    return apiClient.get('/student/my-routine/today')
  },
}
