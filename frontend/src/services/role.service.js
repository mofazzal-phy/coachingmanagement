import apiClient from './api.service'

export default {
  // Get all roles
  list() {
    return apiClient.get('/roles')
  },

  // Get single role
  get(id) {
    return apiClient.get(`/roles/${id}`)
  },

  // Create new role
  create(data) {
    return apiClient.post('/roles', data)
  },

  // Update role
  update(id, data) {
    return apiClient.put(`/roles/${id}`, data)
  },

  // Delete role
  delete(id) {
    return apiClient.delete(`/roles/${id}`)
  },

  // Assign role to user
  assignToUser(data) {
    return apiClient.post('/roles/assign', data)
  },
}
