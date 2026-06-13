import apiClient from './api.service'

export default {
  login(credentials) {
    return apiClient.post('/auth/login', credentials)
  },
  register(data) {
    return apiClient.post('/auth/register', data)
  },
  me() {
    return apiClient.get('/auth/me')
  },
  refresh() {
    return apiClient.post('/auth/refresh', null, { _skipAuthRefresh: true })
  },
  logout() {
    return apiClient.post('/auth/logout')
  },
}