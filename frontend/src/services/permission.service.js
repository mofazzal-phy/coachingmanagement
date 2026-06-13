import apiClient from './api.service'

export default {
  // Get all permissions (grouped by resource)
  list() {
    return apiClient.get('/permissions')
  },

  // Create new permission
  create(data) {
    return apiClient.post('/permissions', data)
  },

  // Delete permission
  delete(id) {
    return apiClient.delete(`/permissions/${id}`)
  },

  // Sync permissions for a role
  syncRolePermissions(data) {
    return apiClient.post('/permissions/sync-role', data)
  },
}
