import apiClient from './api.service'

export default {
  // Get paginated users list
  list(params = {}) {
    return apiClient.get('/users', { params })
  },

  // Get single user
  get(id) {
    return apiClient.get(`/users/${id}`)
  },

  // Create new user
  create(data) {
    return apiClient.post('/users', data)
  },

  // Update user
  update(id, data) {
    return apiClient.put(`/users/${id}`, data)
  },

  // Delete user
  delete(id) {
    return apiClient.delete(`/users/${id}`)
  },

  // Get users by role
  byRole(role, params = {}) {
    return apiClient.get(`/users/role/${role}`, { params })
  },

  // Get dashboard stats
  stats() {
    return apiClient.get('/users/stats')
  },
}
