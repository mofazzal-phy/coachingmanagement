import apiClient from './api.service'

export default {
  examReport(examId) {
    return apiClient.get(`/reports/exam/${examId}`)
  },
  examMerit(examId, params = {}) {
    return apiClient.get(`/reports/exam/${examId}/merit`, { params })
  },
  examAnalytics(examId, params = {}) {
    return apiClient.get(`/reports/exam/${examId}/analytics`, { params })
  },
  examSubjectAnalysis(examId, params = {}) {
    return apiClient.get(`/reports/exam/${examId}/subject-analysis`, { params })
  },
  exportExam(examId, params = {}) {
    return apiClient.get(`/reports/exam/${examId}/export`, {
      params,
      responseType: 'blob',
    })
  },
}
