import apiClient from './api.service'

const extractList = (res) => {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

export default {
  notices: {
    list(params = {}) {
      return apiClient.get('/notices', { params })
    },
    get(id) {
      return apiClient.get(`/notices/${id}`)
    },
    create(data) {
      return apiClient.post('/notices', data)
    },
    update(id, data) {
      return apiClient.put(`/notices/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/notices/${id}`)
    },
    published() {
      return apiClient.get('/notices/published')
    },
    uploadAttachment(file, type = 'pdf') {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', type)
      return apiClient.post('/notices/attachments/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/notices/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/notices/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/notices/${id}/reject`, { reason, comment })
    },
    publish(id) {
      return apiClient.post(`/notices/${id}/publish`)
    },
    unpublish(id) {
      return apiClient.post(`/notices/${id}/unpublish`)
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/notices/${id}/audit-logs`, { params })
    },
  },

  extractList,
}
