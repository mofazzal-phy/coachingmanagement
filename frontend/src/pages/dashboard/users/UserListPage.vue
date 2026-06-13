<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Users</h1>
        <span class="badge-count">{{ totalRecords }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadUsers" :disabled="loading">
          🔄 Refresh
        </button>
        <router-link 
          to="/dashboard/users/create" 
          class="btn btn-primary"
          v-if="authStore.hasPermission('create users')"
        >
          + Add User
        </router-link>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input 
          v-model="filters.search" 
          type="text" 
          placeholder="Search by name, email, phone..."
          @input="debouncedSearch"
        />
      </div>
      <select v-model="filters.role" class="filter-select" @change="loadUsers">
        <option value="">All Roles</option>
        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-select" @change="loadUsers">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading users...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="loadUsers">Try Again</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="users.length === 0" class="empty-state">
      <div class="empty-icon">👤</div>
      <h3>No Users Found</h3>
      <p v-if="filters.search || filters.role">Try adjusting your search or filters</p>
      <p v-else>Get started by creating your first user</p>
      <router-link v-if="!filters.search && !filters.role" to="/dashboard/users/create" class="btn btn-primary">
        + Add User
      </router-link>
    </div>

    <!-- Users Table -->
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td>
              <div class="user-cell">
                <img 
                  :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=4f46e5&color=fff`" 
                  :alt="user.name"
                  class="user-avatar"
                />
                <div class="user-info">
                  <span class="user-name">{{ user.name }}</span>
                  <span class="user-id">#{{ user.id?.substring(0, 8) || user.id }}</span>
                </div>
              </div>
            </td>
            <td>{{ user.email }}</td>
            <td>{{ user.phone || '—' }}</td>
            <td>
              <span class="role-badge" :class="getRoleClass(user)">
                {{ getRoleName(user) }}
              </span>
            </td>
            <td>
              <span class="status-badge" :class="getStatusClass(user)">
                {{ getStatusLabel(user) }}
              </span>
            </td>
            <td class="date-cell">{{ formatDate(user.created_at) }}</td>
            <td>
              <div class="action-buttons">
                <button class="btn-icon" title="Edit" @click="editUser(user)" v-if="authStore.hasPermission('edit users')">
                  ✏️
                </button>
                <button class="btn-icon" title="Assign Role" @click="openRoleDialog(user)" v-if="authStore.hasPermission('edit users')">
                  🔑
                </button>
                <button 
                  class="btn-icon danger" 
                  title="Delete" 
                  @click="confirmDelete(user)"
                  v-if="authStore.hasPermission('delete users') && user.role !== 'super-admin'"
                >
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination" v-if="meta.last_page > 1">
        <button 
          class="btn btn-outline btn-sm" 
          :disabled="meta.current_page <= 1"
          @click="changePage(meta.current_page - 1)"
        >
          ← Previous
        </button>
        <div class="page-info">
          Page {{ meta.current_page }} of {{ meta.last_page }}
          ({{ meta.from }}–{{ meta.to }} of {{ meta.total }})
        </div>
        <button 
          class="btn btn-outline btn-sm" 
          :disabled="meta.current_page >= meta.last_page"
          @click="changePage(meta.current_page + 1)"
        >
          Next →
        </button>
      </div>
    </div>

    <!-- Assign Role Dialog -->
    <div class="modal-overlay" v-if="showRoleDialog" @click.self="showRoleDialog = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>Assign Role</h3>
          <button class="modal-close" @click="showRoleDialog = false">✕</button>
        </div>
        <div class="modal-body">
          <p>Assign role to <strong>{{ selectedUser?.name }}</strong></p>
          <div class="form-group">
            <label>Select Role</label>
            <select v-model="selectedRole" class="form-control">
              <option value="">Choose a role...</option>
              <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
            </select>
          </div>
          <div v-if="roleError" class="form-error">{{ roleError }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRoleDialog = false">Cancel</button>
          <button class="btn btn-primary" @click="assignRole" :disabled="!selectedRole || roleLoading">
            {{ roleLoading ? 'Assigning...' : 'Assign Role' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button class="modal-close" @click="showDeleteDialog = false">✕</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong>{{ selectedUser?.name }}</strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteUser" :disabled="deleteLoading">
            {{ deleteLoading ? 'Deleting...' : 'Delete User' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import userService from '@/services/user.service'
import roleService from '@/services/role.service'

const router = useRouter()
const authStore = useAuthStore()

// State
const users = ref([])
const roles = ref([])
const loading = ref(false)
const error = ref(null)
const totalRecords = ref(0)

const meta = ref({
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0,
})

const filters = ref({
  search: '',
  role: '',
  status: '',
})

// Role dialog
const showRoleDialog = ref(false)
const selectedUser = ref(null)
const selectedRole = ref('')
const roleLoading = ref(false)
const roleError = ref('')

// Delete dialog
const showDeleteDialog = ref(false)
const deleteLoading = ref(false)

// Debounce timer
let searchTimer = null

const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    meta.value.current_page = 1
    loadUsers()
  }, 400)
}

const loadUsers = async () => {
  loading.value = true
  error.value = null
  try {
    const params = {
      page: meta.value.current_page,
      per_page: 15,
      search: filters.value.search || undefined,
      role: filters.value.role || undefined,
      status: filters.value.status || undefined,
    }
    const response = await userService.list(params)
    const result = response.data
    
    users.value = result.data || []
    if (result.meta) {
      meta.value = result.meta
      totalRecords.value = result.meta.total
    } else {
      totalRecords.value = users.value.length
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load users'
    users.value = []
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const response = await roleService.list()
    roles.value = response.data.data || []
  } catch {
    // Silently fail - roles are optional for the list
  }
}

const changePage = (page) => {
  meta.value.current_page = page
  loadUsers()
}

const editUser = (user) => {
  router.push(`/dashboard/users/${user.id}/edit`)
}

const openRoleDialog = (user) => {
  selectedUser.value = user
  selectedRole.value = user.role || user.roles?.[0]?.name || ''
  roleError.value = ''
  showRoleDialog.value = true
}

const assignRole = async () => {
  if (!selectedRole.value || !selectedUser.value) return
  roleLoading.value = true
  roleError.value = ''
  try {
    await roleService.assignToUser({
      user_id: selectedUser.value.id,
      role: selectedRole.value,
    })
    showRoleDialog.value = false
    loadUsers()
  } catch (err) {
    roleError.value = err.response?.data?.message || 'Failed to assign role'
  } finally {
    roleLoading.value = false
  }
}

const confirmDelete = (user) => {
  selectedUser.value = user
  showDeleteDialog.value = true
}

const deleteUser = async () => {
  if (!selectedUser.value) return
  deleteLoading.value = true
  try {
    await userService.delete(selectedUser.value.id)
    showDeleteDialog.value = false
    loadUsers()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete user'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
}

const getRoleName = (user) => {
  // Prioritize Spatie role (user.roles) over the database 'role' column
  return (user.roles?.[0]?.name || user.role || 'N/A')
}

const getRoleClass = (user) => {
  const role = getRoleName(user).toLowerCase()
  if (role.includes('super') || role === 'admin') return 'role-admin'
  if (role === 'teacher') return 'role-teacher'
  if (role === 'student') return 'role-student'
  return ''
}

const getStatusLabel = (user) => {
  // API returns 'status' field (active/inactive), but frontend also uses 'is_active'
  if (user.is_active === true) return 'Active'
  if (user.is_active === false) return 'Inactive'
  if (user.status === 'active') return 'Active'
  if (user.status === 'inactive') return 'Inactive'
  return 'Inactive'
}

const getStatusClass = (user) => {
  const label = getStatusLabel(user)
  return label === 'Active' ? 'active' : 'inactive'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

onMounted(() => {
  loadUsers()
  loadRoles()
})
</script>

<style scoped>
.page-container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-left h1 {
  font-size: 1.5rem;
  color: var(--text-primary);
  margin: 0;
}

.badge-count {
  background: #e8eaf6;
  color: #4f46e5;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
}

/* Buttons */
.btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}
.btn-primary:hover { background: #4338ca; }

.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-outline:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-danger {
  background: #ef4444;
  color: white;
}
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.8rem;
}

/* Filter Bar */
.filter-bar {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  min-width: 200px;
  position: relative;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.9rem;
}

.search-box input {
  width: 100%;
  padding: 0.6rem 0.6rem 0.6rem 2.2rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.85rem;
  background: var(--bg-card);
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.search-box input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.filter-select {
  padding: 0.6rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.85rem;
  background: var(--bg-card);
  min-width: 140px;
  cursor: pointer;
}

.filter-select:focus {
  outline: none;
  border-color: #4f46e5;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 3rem;
  color: var(--text-muted);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Error State */
.error-state {
  text-align: center;
  padding: 2rem;
  background: #fef2f2;
  border-radius: 12px;
  color: #dc2626;
}

.error-state .btn { margin-top: 0.75rem; }

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--text-primary);
  margin: 0 0 0.5rem;
}

.empty-state p {
  color: var(--text-muted);
  margin: 0 0 1.25rem;
}

/* Table */
.table-container {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: var(--bg-accent);
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border-color);
}

.data-table td {
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-light);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr:hover td {
  background: var(--bg-accent);
}

/* User Cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: var(--text-primary);
}

.user-id {
  font-size: 0.75rem;
  color: var(--text-muted);
}

/* Role Badge */
.role-badge {
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f3f4f6;
  color: var(--text-secondary);
}

.role-badge.role-admin {
  background: #e8eaf6;
  color: #4f46e5;
}

.role-badge.role-teacher {
  background: #e3f2fd;
  color: #2563eb;
}

.role-badge.role-student {
  background: #e8f5e9;
  color: #059669;
}

/* Status Badge */
.status-badge {
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.active {
  background: #e8f5e9;
  color: #059669;
}

.status-badge.inactive {
  background: #fef2f2;
  color: #dc2626;
}

.date-cell {
  white-space: nowrap;
  color: var(--text-muted);
  font-size: 0.8rem;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #f3f4f6;
}

.btn-icon.danger:hover {
  background: #fef2f2;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--border-color);
  background: var(--bg-accent);
}

.page-info {
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.modal-dialog {
  background: var(--bg-card);
  border-radius: 16px;
  width: 90%;
  max-width: 480px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-sm {
  max-width: 400px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: var(--text-primary);
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
}

.modal-close:hover { color: var(--text-secondary); }

.modal-body {
  padding: 1.5rem;
}

.modal-body p {
  margin: 0 0 1rem;
  color: var(--text-secondary);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.4rem;
}

.form-control {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.85rem;
  background: var(--bg-card);
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-error {
  color: #dc2626;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.text-danger {
  color: #dc2626;
  font-size: 0.85rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}
</style>
