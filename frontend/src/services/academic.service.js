import apiClient from './api.service'

export default {
  // ========== Academic Groups ==========
  groups: {
    list(params = {}) {
      return apiClient.get('/academic-groups', { params })
    },
    get(id) {
      return apiClient.get(`/academic-groups/${id}`)
    },
    create(data) {
      return apiClient.post('/academic-groups', data)
    },
    update(id, data) {
      return apiClient.put(`/academic-groups/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/academic-groups/${id}`)
    },
    listAll() {
      return apiClient.get('/academic-groups/list/all')
    },
    byClass(classId) {
      return apiClient.get(`/academic-groups/by-class/${classId}`)
    },
  },

  // ========== Academic Sessions ==========
  sessions: {
    list(params = {}) {
      return apiClient.get('/academic-sessions', { params })
    },
    get(id) {
      return apiClient.get(`/academic-sessions/${id}`)
    },
    create(data) {
      return apiClient.post('/academic-sessions', data)
    },
    update(id, data) {
      return apiClient.put(`/academic-sessions/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/academic-sessions/${id}`)
    },
    current() {
      return apiClient.get('/academic-sessions/current')
    },
  },

  // ========== Classes ==========
  classes: {
    list(params = {}) {
      return apiClient.get('/classes', { params })
    },
    get(id) {
      return apiClient.get(`/classes/${id}`)
    },
    create(data) {
      return apiClient.post('/classes', data)
    },
    update(id, data) {
      return apiClient.put(`/classes/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/classes/${id}`)
    },
    listAll() {
      return apiClient.get('/classes/list/all')
    },
    assignSubjects(id, data) {
      return apiClient.post(`/classes/${id}/assign-subjects`, data)
    },
  },

  // ========== Sections ==========
  sections: {
    list(params = {}) {
      return apiClient.get('/sections', { params })
    },
    get(id) {
      return apiClient.get(`/sections/${id}`)
    },
    create(data) {
      return apiClient.post('/sections', data)
    },
    update(id, data) {
      return apiClient.put(`/sections/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/sections/${id}`)
    },
    byClass(classId) {
      return apiClient.get(`/sections/by-class/${classId}`)
    },
  },

  // ========== Subjects ==========
  subjects: {
    list(params = {}) {
      return apiClient.get('/subjects', { params })
    },
    get(id) {
      return apiClient.get(`/subjects/${id}`)
    },
    create(data) {
      return apiClient.post('/subjects', data)
    },
    update(id, data) {
      return apiClient.put(`/subjects/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/subjects/${id}`)
    },
    listAll() {
      return apiClient.get('/subjects/list/all')
    },
    byClass(classId, params = {}) {
      return apiClient.get(`/subjects/by-class/${classId}`, { params })
    },
    byCourse(courseId, params = {}) {
      return apiClient.get(`/subjects/by-course/${courseId}`, { params })
    },
    assignGroups(id, data) {
      return apiClient.post(`/subjects/${id}/assign-groups`, data)
    },
  },

  // ========== Rooms ==========
  rooms: {
    list(params = {}) {
      return apiClient.get('/rooms', { params })
    },
    get(id) {
      return apiClient.get(`/rooms/${id}`)
    },
    create(data) {
      return apiClient.post('/rooms', data)
    },
    update(id, data) {
      return apiClient.put(`/rooms/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/rooms/${id}`)
    },
    listAll() {
      return apiClient.get('/rooms/list/all')
    },
  },

  // ========== Routine Periods ==========
  periods: {
    list(params = {}) {
      return apiClient.get('/routine-periods', { params })
    },
    get(id) {
      return apiClient.get(`/routine-periods/${id}`)
    },
    create(data) {
      return apiClient.post('/routine-periods', data)
    },
    update(id, data) {
      return apiClient.put(`/routine-periods/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/routine-periods/${id}`)
    },
    listAll() {
      return apiClient.get('/routine-periods/list/all')
    },
  },

  // ========== Class Routines ==========
  routines: {
    list(params = {}) {
      return apiClient.get('/class-routines', { params })
    },
    get(id) {
      return apiClient.get(`/class-routines/${id}`)
    },
    create(data) {
      return apiClient.post('/class-routines', data)
    },
    update(id, data) {
      return apiClient.put(`/class-routines/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/class-routines/${id}`)
    },
    byBatch(batchId, params = {}) {
      return apiClient.get(`/class-routines/by-batch/${batchId}`, { params })
    },
    byClass(params) {
      return apiClient.get('/class-routines/by-class', { params })
    },
    byTeacher(params) {
      return apiClient.get('/class-routines/by-teacher', { params })
    },
    bulkStore(data) {
      return apiClient.post('/class-routines/bulk', data)
    },
  },

  // ========== FLAT ALIASES (for backward compat) ==========
  getClasses(params = {}) {
    return this.classes.list(params)
  },
  getGroups(params = {}) {
    return this.groups.list(params)
  },
  getSessions(params = {}) {
    return this.sessions.list(params)
  },
  getRooms(params = {}) {
    return this.rooms.list(params)
  },
  getSubjects(params = {}) {
    return this.subjects.list(params)
  },
  getPeriods(params = {}) {
    return this.periods.list(params)
  },
  getAllClasses() {
    return this.classes.listAll()
  },
  getAllSubjects() {
    return this.subjects.listAll()
  },
}
