import apiClient from './api.service'

const extractList = (res) => {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

const extractData = (res) => res?.data?.data ?? res?.data ?? null

export default {
  extractList,
  extractData,

  home() {
    return apiClient.get('/cms/public/home')
  },

  sliders() {
    return apiClient.get('/cms/public/sliders')
  },

  blog(params = {}) {
    return apiClient.get('/cms/public/blog', { params })
  },

  blogBySlug(slug) {
    return apiClient.get(`/cms/public/blog/${slug}`)
  },

  pageBySlug(slug) {
    return apiClient.get(`/cms/public/pages/${slug}`)
  },

  testimonials() {
    return apiClient.get('/cms/public/testimonials')
  },

  galleries(params = {}) {
    return apiClient.get('/cms/public/galleries', { params })
  },

  successStories() {
    return apiClient.get('/cms/public/success-stories')
  },

  successStoryBySlug(slug) {
    return apiClient.get(`/cms/public/success-stories/${slug}`)
  },

  downloads() {
    return apiClient.get('/cms/public/downloads')
  },

  downloadFile(id) {
    return apiClient.get(`/cms/public/downloads/${id}/file`)
  },

  events() {
    return apiClient.get('/cms/public/events')
  },

  notices() {
    return apiClient.get('/cms/public/notices')
  },

  track(payload) {
    return apiClient.post('/cms/public/track', payload)
  },

  teachers(params = {}) {
    return apiClient.get('/cms/public/teachers', { params })
  },

  teacher(id) {
    return apiClient.get(`/cms/public/teachers/${id}`)
  },

  siteSettings() {
    return apiClient.get('/cms/public/site-settings')
  },

  contact(payload) {
    return apiClient.post('/cms/public/contact', payload)
  },
}
