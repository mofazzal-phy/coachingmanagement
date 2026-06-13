import apiClient from './api.service'

export default {
  list(params = {}) {
    return apiClient.get('/settings', { params })
  },
  get(key) {
    return apiClient.get(`/settings/${key}`)
  },
  update(settings) {
    return apiClient.put('/settings', { settings })
  },
  getGradingRules() {
    return apiClient.get('/settings/grading-rules')
  },
  updateGradingRules(rules) {
    return apiClient.put('/settings/grading-rules', { rules })
  },
}
