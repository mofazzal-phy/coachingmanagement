<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Roles</h1>
        <span class="badge-count">{{ roles.length }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadRoles" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog" v-if="authStore.hasPermission('create roles')">
          + Add Role
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading roles...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="loadRoles">Try Again</button>
    </div>

    <!-- Empty -->
    <div v-else-if="roles.length === 0" class="empty-state">
      <div class="empty-icon">🔑</div>
      <h3>No Roles Found</h3>
      <p>Create your first role to get started</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Role</button>
    </div>

    <!-- Roles Grid -->
    <div v-else class="roles-grid">
      <div v-for="role in roles" :key="role.id" class="role-card">
        <div class="role-card-header">
          <div class="role-name-section">
            <h3>{{ role.name }}</h3>
            <span class="guard-badge">{{ role.guard_name }}</span>
          </div>
          <div class="role-actions">
            <button class="btn-icon" title="Edit" @click="openEditDialog(role)" v-if="authStore.hasPermission('edit roles')">✏️</button>
            <button 
              class="btn-icon danger" 
              title="Delete" 
              @click="confirmDelete(role)"
              v-if="authStore.hasPermission('delete roles') && role.name !== 'super-admin'"
            >🗑️</button>
          </div>
        </div>

        <div class="role-meta">
          <span class="meta-item">👥 {{ role.users_count || 0 }} users</span>
          <span class="meta-item">📅 {{ formatDate(role.created_at) }}</span>
        </div>

        <div class="permissions-section">
          <div class="section-label">Permissions ({{ role.permissions?.length || 0 }})</div>
          <div class="permission-tags">
            <span 
              v-for="perm in (role.permissions || [])" 
              :key="perm" 
              class="perm-tag"
            >{{ perm }}</span>
            <span v-if="!role.permissions?.length" class="no-perms">No permissions assigned</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>{{ editingRole ? 'Edit Role' : 'Create Role' }}</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>

          <div class="form-group">
            <label>Role Name <span class="required">*</span></label>
            <input 
              v-model="dialogForm.name" 
              type="text" 
              class="form-control" 
              placeholder="e.g., editor, manager"
            />
          </div>

          <div class="form-group">
            <label>Permissions</label>
            <div class="permission-groups">
              <div v-for="(perms, group) in groupedPermissions" :key="group" class="perm-group">
                <div class="perm-group-header">
                  <label class="checkbox-label">
                    <input 
                      type="checkbox" 
                      :checked="isGroupSelected(group)"
                      @change="toggleGroup(group)"
                    />
                    <span class="group-name">{{ group }}</span>
                  </label>
                </div>
                <div class="perm-items">
                  <label v-for="perm in perms" :key="perm.id" class="checkbox-label perm-item">
                    <input 
                      type="checkbox" 
                      :value="perm.name"
                      v-model="dialogForm.permissions"
                    />
                    <span>{{ perm.name }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button 
            class="btn btn-primary" 
            @click="saveRole" 
            :disabled="!dialogForm.name.trim() || dialogLoading"
          >
            {{ dialogLoading ? 'Saving...' : (editingRole ? 'Update Role' : 'Create Role') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button class="modal-close" @click="showDeleteDialog = false">✕</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete role <strong>{{ selectedRole?.name }}</strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteRole" :disabled="deleteLoading">
            {{ deleteLoading ? 'Deleting...' : 'Delete Role' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import roleService from '@/services/role.service'
import permissionService from '@/services/permission.service'

const authStore = useAuthStore()

const roles = ref([])
const allPermissions = ref({})
const loading = ref(false)
const error = ref(null)

// Dialog
const showDialog = ref(false)
const editingRole = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const dialogForm = ref({ name: '', permissions: [] })

// Delete
const showDeleteDialog = ref(false)
const selectedRole = ref(null)
const deleteLoading = ref(false)

const groupedPermissions = computed(() => allPermissions.value)

const isGroupSelected = (group) => {
  const perms = allPermissions.value[group] || []
  return perms.length > 0 && perms.every(p => dialogForm.value.permissions.includes(p.name))
}

const toggleGroup = (group) => {
  const perms = allPermissions.value[group] || []
  const allSelected = perms.every(p => dialogForm.value.permissions.includes(p.name))
  
  if (allSelected) {
    dialogForm.value.permissions = dialogForm.value.permissions.filter(
      p => !perms.map(pp => pp.name).includes(p)
    )
  } else {
    perms.forEach(p => {
      if (!dialogForm.value.permissions.includes(p.name)) {
        dialogForm.value.permissions.push(p.name)
      }
    })
  }
}

const loadRoles = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await roleService.list()
    roles.value = response.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load roles'
  } finally {
    loading.value = false
  }
}

const loadPermissions = async () => {
  try {
    const response = await permissionService.list()
    allPermissions.value = response.data.data || {}
  } catch {
    // Silently fail
  }
}

const openCreateDialog = () => {
  editingRole.value = null
  dialogForm.value = { name: '', permissions: [] }
  dialogError.value = null
  showDialog.value = true
}

const openEditDialog = (role) => {
  editingRole.value = role
  dialogForm.value = {
    name: role.name,
    permissions: [...(role.permissions || [])],
  }
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => {
  showDialog.value = false
  editingRole.value = null
}

const saveRole = async () => {
  dialogLoading.value = true
  dialogError.value = null
  try {
    if (editingRole.value) {
      await roleService.update(editingRole.value.id, {
        name: dialogForm.value.name,
        permissions: dialogForm.value.permissions,
      })
    } else {
      await roleService.create({
        name: dialogForm.value.name,
        permissions: dialogForm.value.permissions,
      })
    }
    closeDialog()
    loadRoles()
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Failed to save role'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = (role) => {
  selectedRole.value = role
  showDeleteDialog.value = true
}

const deleteRole = async () => {
  if (!selectedRole.value) return
  deleteLoading.value = true
  try {
    await roleService.delete(selectedRole.value.id)
    showDeleteDialog.value = false
    loadRoles()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete role'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
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
  loadRoles()
  loadPermissions()
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

.btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-outline:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }

/* Loading */
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

.error-state {
  text-align: center;
  padding: 2rem;
  background: #fef2f2;
  border-radius: 12px;
  color: #dc2626;
}

.error-state .btn { margin-top: 0.75rem; }

.empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
}

.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0 0 1.25rem; }

/* Roles Grid */
.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 1rem;
}

.role-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: var(--shadow-sm);
  transition: box-shadow 0.2s;
}

.role-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.role-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.role-name-section {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.role-name-section h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--text-primary);
  text-transform: capitalize;
}

.guard-badge {
  font-size: 0.7rem;
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  background: #f3f4f6;
  color: var(--text-muted);
}

.role-actions {
  display: flex;
  gap: 0.25rem;
}

.btn-icon {
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-icon:hover { background: #f3f4f6; }
.btn-icon.danger:hover { background: #fef2f2; }

.role-meta {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.meta-item {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.permissions-section {
  border-top: 1px solid #f3f4f6;
  padding-top: 0.75rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.permission-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.perm-tag {
  font-size: 0.75rem;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  background: #e8eaf6;
  color: #4f46e5;
}

.no-perms {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-style: italic;
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
  max-width: 600px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-sm { max-width: 400px; }

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }

.modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
}

.modal-close:hover { color: var(--text-secondary); }

.modal-body { padding: 1.5rem; }

.modal-body p { margin: 0 0 1rem; color: var(--text-secondary); }

.form-group { margin-bottom: 1rem; }

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.4rem;
}

.required { color: #ef4444; }

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

/* Permission Groups */
.permission-groups {
  max-height: 350px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.5rem;
}

.perm-group {
  margin-bottom: 0.5rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border-light);
}

.perm-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

.perm-group-header {
  margin-bottom: 0.35rem;
}

.group-name {
  font-weight: 700;
  text-transform: capitalize;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.perm-items {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding-left: 1.5rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.perm-item {
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  background: var(--bg-accent);
}

.perm-item:hover {
  background: #f3f4f6;
}

.alert {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.85rem;
}

.alert p { margin: 0; }

.alert-danger {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.text-danger { color: #dc2626; font-size: 0.85rem; }

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}
</style>
