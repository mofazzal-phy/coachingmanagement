<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Permissions</h1>
        <span class="badge-count">{{ totalPermissions }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadPermissions" :disabled="loading">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openCreateDialog" v-if="authStore.hasPermission('create permissions')">
          + Add Permission
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading permissions...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="loadPermissions">Try Again</button>
    </div>

    <!-- Empty -->
    <div v-else-if="Object.keys(groupedPermissions).length === 0" class="empty-state">
      <div class="empty-icon">🛡️</div>
      <h3>No Permissions Found</h3>
      <p>Create your first permission to get started</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Permission</button>
    </div>

    <!-- Permissions by Group -->
    <div v-else class="permissions-container">
      <div v-for="(perms, group) in groupedPermissions" :key="group" class="perm-group-card">
        <div class="perm-group-header">
          <h3>{{ group }}</h3>
          <span class="perm-count">{{ perms.length }} permissions</span>
        </div>
        <div class="perm-list">
          <div v-for="perm in perms" :key="perm.id" class="perm-item">
            <div class="perm-info">
              <span class="perm-name">{{ perm.name }}</span>
              <span class="perm-guard">{{ perm.guard_name }}</span>
            </div>
            <button 
              class="btn-icon danger" 
              title="Delete" 
              @click="confirmDelete(perm)"
              v-if="authStore.hasPermission('delete permissions')"
            >🗑️</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Dialog -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog modal-sm">
        <div class="modal-header">
          <h3>Create Permission</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>

          <div class="form-group">
            <label>Permission Name <span class="required">*</span></label>
            <input 
              v-model="dialogForm.name" 
              type="text" 
              class="form-control" 
              placeholder="e.g., create users, edit roles"
            />
            <span class="field-hint">Format: "action resource" (e.g., "create users")</span>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button 
            class="btn btn-primary" 
            @click="createPermission" 
            :disabled="!dialogForm.name.trim() || dialogLoading"
          >
            {{ dialogLoading ? 'Creating...' : 'Create Permission' }}
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
          <p>Are you sure you want to delete permission <strong>{{ selectedPerm?.name }}</strong>?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deletePermission" :disabled="deleteLoading">
            {{ deleteLoading ? 'Deleting...' : 'Delete Permission' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import permissionService from '@/services/permission.service'

const authStore = useAuthStore()

const groupedPermissions = ref({})
const loading = ref(false)
const error = ref(null)

// Dialog
const showDialog = ref(false)
const dialogLoading = ref(false)
const dialogError = ref(null)
const dialogForm = ref({ name: '' })

// Delete
const showDeleteDialog = ref(false)
const selectedPerm = ref(null)
const deleteLoading = ref(false)

const totalPermissions = computed(() => {
  let count = 0
  Object.values(groupedPermissions.value).forEach(perms => {
    count += perms.length
  })
  return count
})

const loadPermissions = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await permissionService.list()
    groupedPermissions.value = response.data.data || {}
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load permissions'
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  dialogForm.value = { name: '' }
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => {
  showDialog.value = false
}

const createPermission = async () => {
  dialogLoading.value = true
  dialogError.value = null
  try {
    await permissionService.create({ name: dialogForm.value.name })
    closeDialog()
    loadPermissions()
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Failed to create permission'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = (perm) => {
  selectedPerm.value = perm
  showDeleteDialog.value = true
}

const deletePermission = async () => {
  if (!selectedPerm.value) return
  deleteLoading.value = true
  try {
    await permissionService.delete(selectedPerm.value.id)
    showDeleteDialog.value = false
    loadPermissions()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete permission'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
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

/* Permissions Container */
.permissions-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1rem;
}

.perm-group-card {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.perm-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
}

.perm-group-header h3 {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-primary);
  text-transform: capitalize;
}

.perm-count {
  font-size: 0.75rem;
  color: var(--text-muted);
  background: #e5e7eb;
  padding: 0.15rem 0.5rem;
  border-radius: 10px;
}

.perm-list {
  padding: 0.5rem;
}

.perm-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  border-radius: 6px;
  transition: background 0.2s;
}

.perm-item:hover {
  background: var(--bg-accent);
}

.perm-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.perm-name {
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.perm-guard {
  font-size: 0.7rem;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  background: #f3f4f6;
  color: var(--text-muted);
}

.btn-icon {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  opacity: 0;
}

.perm-item:hover .btn-icon {
  opacity: 1;
}

.btn-icon:hover { background: #f3f4f6; }
.btn-icon.danger:hover { background: #fef2f2; }

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

.field-hint {
  display: block;
  color: var(--text-muted);
  font-size: 0.75rem;
  margin-top: 0.25rem;
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
