import apiClient from './api.service'

export default {
  // ========== Exams ==========
  exams: {
    list(params = {}) {
      return apiClient.get('/exams', { params })
    },
    nameSuggestions() {
      return apiClient.get('/exams/name-suggestions')
    },
    get(id) {
      return apiClient.get(`/exams/${id}`)
    },
    create(data) {
      return apiClient.post('/exams', data)
    },
    update(id, data) {
      return apiClient.put(`/exams/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/exams/${id}`)
    },
    publish(id, params = {}) {
      return apiClient.post(`/exams/${id}/publish`, params)
    },
    publishPreview(id, params = {}) {
      return apiClient.get(`/exams/${id}/publish-preview`, { params })
    },
    publishResults(id, data = {}) {
      return apiClient.post(`/exams/${id}/publish-results`, data)
    },
    results(id) {
      return apiClient.get(`/exams/${id}/results`)
    },
    byBatch(batchId) {
      return apiClient.get(`/exams/by-batch/${batchId}`)
    },
    byCourse(courseId) {
      return apiClient.get(`/exams/by-course/${courseId}`)
    },
    byClass(classId) {
      return apiClient.get(`/exams/by-class/${classId}`)
    },
    downloadMarksheet(examId, studentId, params = {}) {
      return apiClient.get(`/exams/${examId}/students/${studentId}/marksheet`, {
        params,
        responseType: 'blob',
      })
    },
    eligibility(examId, params = {}) {
      return apiClient.get(`/exams/${examId}/eligibility`, { params })
    },
    eligibilitySummary(examId, params = {}) {
      return apiClient.get(`/exams/${examId}/eligibility`, { params: { summary_only: 1, ...params } })
    },
    syncEligibility(examId, params = {}) {
      return apiClient.post(`/exams/${examId}/eligibility/sync`, null, { params })
    },
    overrideEligibility(examId, data) {
      return apiClient.post(`/exams/${examId}/eligibility/override`, data)
    },
    channelPolicies(examId, params = {}) {
      return apiClient.get(`/exams/${examId}/channel-policies`, { params })
    },
    saveChannelPolicies(examId, data) {
      return apiClient.put(`/exams/${examId}/channel-policies`, data)
    },
  },

  // ========== Exam Types ==========
  types: {
    list(params = {}) {
      return apiClient.get('/exam-types', { params })
    },
    get(id) {
      return apiClient.get(`/exam-types/${id}`)
    },
    create(data) {
      return apiClient.post('/exam-types', data)
    },
    update(id, data) {
      return apiClient.put(`/exam-types/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/exam-types/${id}`)
    },
  },

  // ========== Exam Routines ==========
  routines: {
    list(params = {}) {
      return apiClient.get('/exam-routines', { params })
    },
    get(id) {
      return apiClient.get(`/exam-routines/${id}`)
    },
    create(data) {
      return apiClient.post('/exam-routines', data)
    },
    update(id, data) {
      return apiClient.put(`/exam-routines/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/exam-routines/${id}`)
    },
    bulkStore(data) {
      return apiClient.post('/exam-routines/bulk', data)
    },
    generate(data) {
      return apiClient.post('/exam-routines/generate', data)
    },
    publish(examId, params = {}) {
      return apiClient.post(`/exam-routines/publish/${examId}`, params)
    },
    complete(examId) {
      return apiClient.post(`/exam-routines/complete/${examId}`)
    },
    cancel(examId) {
      return apiClient.post(`/exam-routines/cancel/${examId}`)
    },
    conflicts(examId, params = {}) {
      return apiClient.get(`/exam-routines/conflicts/${examId}`, { params })
    },
    byExam(examId, params = {}) {
      return apiClient.get(`/exam-routines/by-exam/${examId}`, { params })
    },
    pruneSubjects(examId, subjectIds, deliveryMode = null) {
      const payload = { subject_ids: subjectIds }
      if (deliveryMode) payload.delivery_mode = deliveryMode
      return apiClient.post(`/exam-routines/prune/${examId}`, payload)
    },
    grid(examId, params = {}) {
      return apiClient.get(`/exam-routines/grid/${examId}`, { params })
    },
    byBatch(batchId, params = {}) {
      return apiClient.get(`/exam-routines/by-batch/${batchId}`, { params })
    },
    byCourse(courseId, params = {}) {
      return apiClient.get(`/exam-routines/by-course/${courseId}`, { params })
    },
    byClass(classId, params = {}) {
      return apiClient.get(`/exam-routines/by-class/${classId}`, { params })
    },
    calendar(examId, params = {}) {
      return apiClient.get(`/exam-routines/calendar/${examId}`, { params })
    },
    exportPdf(examId) {
      return apiClient.get(`/exam-routines/export-pdf/${examId}`, {
        responseType: 'blob',
      })
    },
    getQuestions(routineId, params = {}) {
      return apiClient.get(`/exam-routines/${routineId}/questions`, { params })
    },
    syncQuestions(routineId, data) {
      return apiClient.put(`/exam-routines/${routineId}/questions`, data)
    },
    exportQuestionPaper(routineId, variant = 'student') {
      return apiClient.get(`/exam-routines/${routineId}/paper/pdf`, {
        params: { variant },
        responseType: 'blob',
      })
    },
  },

  // ========== Exam Results ==========
  results: {
    list(params = {}) {
      return apiClient.get('/exam-results', { params })
    },
    get(id) {
      return apiClient.get(`/exam-results/${id}`)
    },
    create(data) {
      return apiClient.post('/exam-results', data)
    },
    update(id, data) {
      return apiClient.put(`/exam-results/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/exam-results/${id}`)
    },
    markBulk(data) {
      return apiClient.post('/exam-results/bulk', data)
    },
    summary(params = {}) {
      return apiClient.get('/exam-results/summary', { params })
    },
    subjectsSummary(params = {}) {
      return apiClient.get('/exam-results/subjects-summary', { params })
    },
    publishBulk(data) {
      return apiClient.post('/exam-results/publish-bulk', data)
    },
    publish(id) {
      return apiClient.post(`/exam-results/${id}/publish`)
    },
    unpublish(id) {
      return apiClient.post(`/exam-results/${id}/unpublish`)
    },
  },

  // ========== Teacher Exam APIs ==========
  teacher: {
    schedule(date) {
      return apiClient.get('/teacher/exam-schedule', { params: date ? { date } : {} })
    },
    todayDuties() {
      return apiClient.get('/teacher/exam-today')
    },
    upcomingDuties() {
      return apiClient.get('/teacher/exam-upcoming')
    },
    markAttendance(routineId, data) {
      return apiClient.post(`/teacher/exam-attendance/${routineId}`, data)
    },
    // Exam routines for subjects the teacher teaches (not just duties)
    myRoutines() {
      return apiClient.get('/teacher/exam-routines')
    },
    leaderboardContext() {
      return apiClient.get('/teacher/exam-leaderboard/context')
    },
    // Structured grid data for teacher's exam routines (same format as admin getGrid)
    myGrid() {
      return apiClient.get('/teacher/exam-routines/grid')
    },
  },

  // ========== Question Bank ==========
  questions: {
    list(params = {}) {
      return apiClient.get('/questions', { params })
    },
    get(id) {
      return apiClient.get(`/questions/${id}`)
    },
    bulkCreate(payload) {
      return apiClient.post('/questions/bulk', payload)
    },
    bulkSubmit(payload = {}) {
      return apiClient.post('/questions/bulk-submit', payload)
    },
    create(data) {
      if (data instanceof FormData) {
        return apiClient.post('/questions', data, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
      return apiClient.post('/questions', data)
    },
    update(id, data) {
      if (data instanceof FormData) {
        return apiClient.put(`/questions/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
      return apiClient.put(`/questions/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/questions/${id}`)
    },
    submit(id, payload = {}) {
      return apiClient.post(`/questions/${id}/submit`, payload)
    },
    approve(id, payload = {}) {
      return apiClient.post(`/questions/${id}/approve`, payload)
    },
    reject(id, payload = {}) {
      return apiClient.post(`/questions/${id}/reject`, payload)
    },
    sendBack(id, payload = {}) {
      return apiClient.post(`/questions/${id}/send-back`, payload)
    },
    reviewLogs(id) {
      return apiClient.get(`/questions/${id}/review-logs`)
    },
  },

  // ========== Student Exam APIs ==========
  student: {
    routines() {
      return apiClient.get('/student/exam-routines')
    },
    upcoming() {
      return apiClient.get('/student/exam-upcoming')
    },
    nextExam() {
      return apiClient.get('/student/exam-next')
    },
    admitCard(examId) {
      return apiClient.get(`/student/exam-admit-card/${examId}`)
    },
    downloadAdmitCard(examId) {
      return apiClient.get(`/student/exam-admit-card/${examId}/download`, {
        responseType: 'blob',
      })
    },
    eligibilityMe(examId) {
      return apiClient.get(`/exams/${examId}/eligibility/me`)
    },
    setReminder(data) {
      return apiClient.post('/student/exam-reminder', data)
    },
    results() {
      return apiClient.get('/student/exam-results')
    },
    leaderboard(examId, params = {}) {
      return apiClient.get(`/student/exam-results/${examId}/leaderboard`, { params })
    },
    provisionalLeaderboard(examId, params = {}) {
      return apiClient.get(`/student/exam-results/${examId}/provisional-leaderboard`, { params })
    },
    downloadMarksheet(examId, params = {}) {
      return apiClient.get(`/student/exam-results/${examId}/marksheet`, {
        params,
        responseType: 'blob',
      })
    },
    practiceRoutines() {
      return apiClient.get('/student/practice-routines')
    },
    liveExams() {
      return apiClient.get('/student/exams/live')
    },
  },

  attempts: {
    start(examRoutineId) {
      return apiClient.post('/exam-attempts/start', { exam_routine_id: examRoutineId })
    },
    save(id, data) {
      return apiClient.put(`/exam-attempts/${id}`, data)
    },
    submit(id, data = {}) {
      return apiClient.post(`/exam-attempts/${id}/submit`, data)
    },
    my(params = {}) {
      return apiClient.get('/exam-attempts/my', { params })
    },
    get(id) {
      return apiClient.get(`/exam-attempts/${id}`)
    },
  },
}
