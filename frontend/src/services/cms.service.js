import apiClient from './api.service'

const extractList = (res) => {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

export default {
  extractList,

  foundation: {
    approvalQueue(params = {}) {
      return apiClient.get('/cms/approval-queue', { params })
    },
    auditLogs(params = {}) {
      return apiClient.get('/cms/audit-logs', { params })
    },
    entityAuditLogs(entityType, entityId, params = {}) {
      return apiClient.get(`/cms/audit-logs/${entityType}/${entityId}`, { params })
    },
    analyticsDashboard(params = {}) {
      return apiClient.get('/cms/analytics/dashboard', { params })
    },
    analyticsSummary(contentType, contentId) {
      return apiClient.get('/cms/analytics/summary', { params: { content_type: contentType, content_id: contentId } })
    },
  },

  pages: {
    list(params = {}) {
      return apiClient.get('/pages', { params })
    },
    get(id) {
      return apiClient.get(`/pages/detail/${id}`)
    },
    getBySlug(slug) {
      return apiClient.get(`/pages/${slug}`)
    },
    create(data) {
      return apiClient.post('/pages', data)
    },
    update(id, data) {
      return apiClient.put(`/pages/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/pages/${id}`)
    },
    publish(id) {
      return apiClient.post(`/pages/${id}/publish`)
    },
    unpublish(id) {
      return apiClient.post(`/pages/${id}/unpublish`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/pages/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/pages/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/pages/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/pages/${id}/audit-logs`, { params })
    },
    published(params = {}) {
      return apiClient.get('/pages/published', { params })
    },
  },

  blog: {
    list(params = {}) {
      return apiClient.get('/pages', { params: { ...params, content_type: 'blog' } })
    },
    create(data) {
      return apiClient.post('/blog', { ...data, content_type: 'blog' })
    },
    update(id, data) {
      return apiClient.put(`/pages/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/pages/${id}`)
    },
    publish(id) {
      return apiClient.post(`/pages/${id}/publish`)
    },
    unpublish(id) {
      return apiClient.post(`/pages/${id}/unpublish`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/pages/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/pages/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/pages/${id}/reject`, { reason, comment })
    },
    published() {
      return apiClient.get('/blog/published')
    },
    getBySlug(slug) {
      return apiClient.get(`/blog/${slug}`)
    },
  },

  media: {
    upload(file, type = 'image', subfolder = 'general') {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', type)
      formData.append('subfolder', subfolder)
      return apiClient.post('/cms/media/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
  },

  testimonials: {
    list(params = {}) {
      return apiClient.get('/testimonials', { params })
    },
    get(id) {
      return apiClient.get(`/testimonials/${id}`)
    },
    create(data) {
      return apiClient.post('/testimonials', data)
    },
    update(id, data) {
      return apiClient.put(`/testimonials/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/testimonials/${id}`)
    },
    activate(id) {
      return apiClient.post(`/testimonials/${id}/activate`)
    },
    deactivate(id) {
      return apiClient.post(`/testimonials/${id}/deactivate`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/testimonials/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/testimonials/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/testimonials/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/testimonials/${id}/audit-logs`, { params })
    },
    published() {
      return apiClient.get('/testimonials/published')
    },
  },

  galleries: {
    list(params = {}) {
      return apiClient.get('/galleries', { params })
    },
    get(id) {
      return apiClient.get(`/galleries/${id}`)
    },
    create(data) {
      return apiClient.post('/galleries', data)
    },
    update(id, data) {
      return apiClient.put(`/galleries/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/galleries/${id}`)
    },
    activate(id) {
      return apiClient.post(`/galleries/${id}/activate`)
    },
    deactivate(id) {
      return apiClient.post(`/galleries/${id}/deactivate`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/galleries/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/galleries/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/galleries/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/galleries/${id}/audit-logs`, { params })
    },
    published(params = {}) {
      return apiClient.get('/galleries/published', { params })
    },
  },

  successStories: {
    list(params = {}) {
      return apiClient.get('/success-stories', { params })
    },
    get(id) {
      return apiClient.get(`/success-stories/detail/${id}`)
    },
    create(data) {
      return apiClient.post('/success-stories', data)
    },
    update(id, data) {
      return apiClient.put(`/success-stories/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/success-stories/${id}`)
    },
    activate(id) {
      return apiClient.post(`/success-stories/${id}/activate`)
    },
    deactivate(id) {
      return apiClient.post(`/success-stories/${id}/deactivate`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/success-stories/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/success-stories/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/success-stories/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/success-stories/${id}/audit-logs`, { params })
    },
    published() {
      return apiClient.get('/success-stories/published')
    },
  },

  studyMaterials: {
    list(params = {}) {
      return apiClient.get('/study-materials', { params })
    },
    get(id) {
      return apiClient.get(`/study-materials/${id}`)
    },
    create(data) {
      return apiClient.post('/study-materials', data)
    },
    update(id, data) {
      return apiClient.put(`/study-materials/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/study-materials/${id}`)
    },
    activate(id) {
      return apiClient.post(`/study-materials/${id}/activate`)
    },
    deactivate(id) {
      return apiClient.post(`/study-materials/${id}/deactivate`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/study-materials/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/study-materials/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/study-materials/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/study-materials/${id}/audit-logs`, { params })
    },
    download(id) {
      return apiClient.get(`/study-materials/${id}/download`)
    },
    published() {
      return apiClient.get('/study-materials/published')
    },
  },

  downloads: {
    list(params = {}) {
      return apiClient.get('/downloads', { params })
    },
    get(id) {
      return apiClient.get(`/downloads/${id}`)
    },
    create(data) {
      return apiClient.post('/downloads', data)
    },
    update(id, data) {
      return apiClient.put(`/downloads/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/downloads/${id}`)
    },
    activate(id) {
      return apiClient.post(`/downloads/${id}/activate`)
    },
    deactivate(id) {
      return apiClient.post(`/downloads/${id}/deactivate`)
    },
    submitForReview(id, comment = '') {
      return apiClient.post(`/downloads/${id}/submit-for-review`, { comment })
    },
    approve(id, comment = '') {
      return apiClient.post(`/downloads/${id}/approve`, { comment })
    },
    reject(id, reason, comment = '') {
      return apiClient.post(`/downloads/${id}/reject`, { reason, comment })
    },
    auditLogs(id, params = {}) {
      return apiClient.get(`/downloads/${id}/audit-logs`, { params })
    },
    download(id) {
      return apiClient.get(`/downloads/${id}/download`)
    },
    published() {
      return apiClient.get('/downloads/published')
    },
  },
}
