<template>
  <div class="page-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-left">
        <h1>Notice Board</h1>
        <span class="badge-count">{{ items.length }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">
          <span class="btn-icon-hidden">🔄</span>
          <span class="btn-text-visible">Refresh</span>
        </button>
        <button class="btn btn-primary" @click="openCreateDialog">
          <span>+</span>
          <span class="btn-text-visible">New Notice</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <select v-model="filters.status" class="form-control filter-select" @change="loadItems">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="archived">Archived</option>
      </select>
      <select v-model="filters.priority" class="form-control filter-select" @change="loadItems">
        <option value="">All Priority</option>
        <option value="low">Low</option>
        <option value="normal">Normal</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
      </select>
      <select v-model="filters.audience" class="form-control filter-select" @change="loadItems">
        <option value="">All Audience</option>
        <option value="all">Everyone</option>
        <option value="students">Students</option>
        <option value="teachers">Teachers</option>
        <option value="staff">Staff</option>
        <option value="parents">Parents</option>
      </select>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading notices...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon">⚠️</div>
      <p>{{ error }}</p>
      <button class="btn btn-outline" @click="loadItems">Try Again</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">📢</div>
      <h3>No Notices Found</h3>
      <p>Create your first notice to share with students and staff.</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ New Notice</button>
    </div>

    <!-- Data View (Desktop + Mobile) -->
    <template v-else>
      <!-- Desktop Table View -->
      <div class="desktop-view">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Priority</th>
                <th>Audience</th>
                <th>Publish Date</th>
                <th>Status</th>
                <th>Approval</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <strong>{{ item.title }}</strong>
                  <span v-if="item.is_featured" class="featured-tag">Featured</span>
                </td>
                <td><span class="priority-pill" :class="item.priority">{{ item.priority || 'normal' }}</span></td>
                <td>{{ formatAudience(item.audience) }}</td>
                <td>{{ formatDate(item.publish_date) }}</td>
                <td><span class="status-pill" :class="item.status">{{ item.status }}</span></td>
                <td><span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span></td>
                <td class="actions-cell">
                  <div class="dropdown" :class="{ 'dropdown-open': openDropdownId === item.id }">
                    <button class="dots-button" @click.stop="toggleDropdown(item.id)">
                      <span class="dots-icon">⋮</span>
                    </button>
                    
                    <teleport to="body">
                      <div v-if="openDropdownId === item.id" class="dropdown-backdrop" @click="closeDropdown"></div>
                      <div v-if="openDropdownId === item.id" class="dropdown-menu" :style="dropdownStyle" @click.stop>
                        <div class="dropdown-header">
                          <span class="dropdown-title">Actions</span>
                          <button class="dropdown-close" @click="closeDropdown">×</button>
                        </div>
                        <button class="dropdown-item" @click="openEditDialog(item); closeDropdown()">
                          <span class="item-icon">✏️</span>
                          <span>Edit Notice</span>
                        </button>
                        <button v-if="item.status !== 'published'" class="dropdown-item" @click="publishItem(item); closeDropdown()">
                          <span class="item-icon">📢</span>
                          <span>Publish</span>
                        </button>
                        <button v-else class="dropdown-item" @click="unpublishItem(item); closeDropdown()">
                          <span class="item-icon">🔽</span>
                          <span>Unpublish</span>
                        </button>
                        <button v-if="canSubmit(item)" class="dropdown-item" @click="submitItem(item); closeDropdown()">
                          <span class="item-icon">📤</span>
                          <span>Submit for Review</span>
                        </button>
                        <template v-if="item.approval_status === 'pending_review' && canApprove">
                          <button class="dropdown-item" @click="approveItem(item); closeDropdown()">
                            <span class="item-icon">✅</span>
                            <span>Approve</span>
                          </button>
                          <button class="dropdown-item dropdown-item-danger" @click="openReject(item); closeDropdown()">
                            <span class="item-icon">❌</span>
                            <span>Reject</span>
                          </button>
                        </template>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item dropdown-item-danger" @click="confirmDelete(item); closeDropdown()">
                          <span class="item-icon">🗑️</span>
                          <span>Delete Notice</span>
                        </button>
                      </div>
                    </teleport>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile Card View -->
      <div class="mobile-view">
        <div v-for="item in items" :key="item.id" class="notice-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title">{{ item.title }}</h3>
              <div class="card-badges">
                <span v-if="item.is_featured" class="featured-tag">Featured</span>
                <span class="priority-pill" :class="item.priority">{{ item.priority || 'normal' }}</span>
              </div>
            </div>
            <div class="card-actions">
              <div class="dropdown" :class="{ 'dropdown-open': openDropdownId === item.id }">
                <button class="dots-button" @click.stop="toggleDropdown(item.id)">
                  <span class="dots-icon">⋮</span>
                </button>
                
                <teleport to="body">
                  <div v-if="openDropdownId === item.id" class="dropdown-backdrop" @click="closeDropdown"></div>
                  <div v-if="openDropdownId === item.id" class="dropdown-menu" :style="dropdownStyle" @click.stop>
                    <div class="dropdown-header">
                      <span class="dropdown-title">Actions</span>
                      <button class="dropdown-close" @click="closeDropdown">×</button>
                    </div>
                    <button class="dropdown-item" @click="openEditDialog(item); closeDropdown()">
                      <span class="item-icon">✏️</span>
                      <span>Edit Notice</span>
                    </button>
                    <button v-if="item.status !== 'published'" class="dropdown-item" @click="publishItem(item); closeDropdown()">
                      <span class="item-icon">📢</span>
                      <span>Publish</span>
                    </button>
                    <button v-else class="dropdown-item" @click="unpublishItem(item); closeDropdown()">
                      <span class="item-icon">🔽</span>
                      <span>Unpublish</span>
                    </button>
                    <button v-if="canSubmit(item)" class="dropdown-item" @click="submitItem(item); closeDropdown()">
                      <span class="item-icon">📤</span>
                      <span>Submit for Review</span>
                    </button>
                    <template v-if="item.approval_status === 'pending_review' && canApprove">
                      <button class="dropdown-item" @click="approveItem(item); closeDropdown()">
                        <span class="item-icon">✅</span>
                        <span>Approve</span>
                      </button>
                      <button class="dropdown-item dropdown-item-danger" @click="openReject(item); closeDropdown()">
                        <span class="item-icon">❌</span>
                        <span>Reject</span>
                      </button>
                    </template>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item dropdown-item-danger" @click="confirmDelete(item); closeDropdown()">
                      <span class="item-icon">🗑️</span>
                      <span>Delete Notice</span>
                    </button>
                  </div>
                </teleport>
              </div>
            </div>
          </div>
          
          <div class="card-body">
            <div class="card-info-grid">
              <div class="info-item">
                <span class="info-label">Audience</span>
                <span class="info-value">{{ formatAudience(item.audience) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Publish Date</span>
                <span class="info-value">{{ formatDate(item.publish_date) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Status</span>
                <span class="status-pill" :class="item.status">{{ item.status }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Approval</span>
                <span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Create/Edit Modal -->
    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Notice' : 'Create Notice' }}</h3>
          <button class="modal-close" @click="closeDialog">×</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <div class="form-group">
            <label>Title <span class="required">*</span></label>
            <input v-model="form.title" class="form-control" placeholder="Notice title" />
          </div>
          <div class="form-group">
            <label>Content <span class="required">*</span></label>
            <textarea v-model="form.content" class="form-control" rows="5" placeholder="Notice content"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Priority</label>
              <select v-model="form.priority" class="form-control">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="form-group">
              <label>Audience</label>
              <select v-model="form.audience" class="form-control">
                <option value="all">Everyone</option>
                <option value="students">Students</option>
                <option value="teachers">Teachers</option>
                <option value="staff">Staff</option>
                <option value="parents">Parents</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Publish Date <span class="required">*</span></label>
              <input v-model="form.publish_date" type="date" class="form-control" />
            </div>
            <div class="form-group">
              <label>Expiry Date</label>
              <input v-model="form.expiry_date" type="date" class="form-control" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </select>
            </div>
            <div class="form-group checkbox-group">
              <label><input type="checkbox" v-model="form.is_featured" /> Featured notice</label>
            </div>
          </div>
          <div class="form-group">
            <label>Attachment</label>
            <input type="file" class="form-control" @change="onFileSelect" accept=".pdf,image/*,video/*" />
            <div v-if="uploading" class="text-muted">Uploading...</div>
            <div v-if="form.attachments?.length" class="attachment-list">
              <div v-for="(file, idx) in form.attachments" :key="idx" class="attachment-item">
                <a :href="file.url" target="_blank">{{ file.original_name || file.path }}</a>
                <button type="button" class="btn-link danger" @click="removeAttachment(idx)">Remove</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.title || !form.content || !form.publish_date || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">×</button></div>
        <div class="modal-body"><p>Delete notice <strong>{{ selectedItem?.title }}</strong>?</p></div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">{{ deleteLoading ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <CmsRejectModal
      :show="showRejectDialog"
      :title="rejectTarget?.title"
      :reason="rejectReason"
      :loading="rejectLoading"
      :error="rejectError"
      @update:reason="rejectReason = $event"
      @close="closeReject"
      @confirm="confirmReject(() => loadItems())"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import communicationService from '@/services/communication.service'
import CmsRejectModal from '@/components/cms/CmsRejectModal.vue'
import { useCmsApproval } from '@/composables/useCmsApproval'

const {
  canApprove,
  showRejectDialog,
  rejectTarget,
  rejectReason,
  rejectLoading,
  rejectError,
  openReject,
  closeReject,
  confirmReject,
} = useCmsApproval(communicationService.notices)

const items = ref([])
const loading = ref(false)
const error = ref(null)
const filters = ref({ status: '', priority: '', audience: '' })
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const uploading = ref(false)
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)
const openDropdownId = ref(null)
const dropdownStyle = ref({})

const emptyForm = () => ({
  title: '',
  content: '',
  priority: 'normal',
  audience: 'all',
  publish_date: new Date().toISOString().slice(0, 10),
  expiry_date: '',
  status: 'draft',
  is_featured: false,
  attachments: [],
})

const form = ref(emptyForm())

const toggleDropdown = async (id) => {
  if (openDropdownId.value === id) {
    closeDropdown()
    return
  }
  openDropdownId.value = id
  
  await nextTick()
  const button = document.querySelector(`.dropdown-open .dots-button`)
  if (button) {
    const rect = button.getBoundingClientRect()
    const menuWidth = 220
    
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 8}px`,
      right: `${window.innerWidth - rect.right}px`,
      left: 'auto',
    }
    
    if (rect.right - menuWidth < 0) {
      dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
        right: 'auto',
      }
    }
    
    const menuHeight = 300
    if (rect.bottom + menuHeight > window.innerHeight) {
      dropdownStyle.value = {
        ...dropdownStyle.value,
        bottom: `${window.innerHeight - rect.top + 8}px`,
        top: 'auto',
      }
    }
  }
}

const closeDropdown = () => {
  openDropdownId.value = null
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    closeDropdown()
  }
}

onMounted(() => {
  loadItems()
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await communicationService.notices.list({
      ...filters.value,
      per_page: 100,
    })
    items.value = communicationService.extractList(res)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load notices'
  } finally {
    loading.value = false
  }
}

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatAudience = (audience) => {
  const map = { all: 'Everyone', students: 'Students', teachers: 'Teachers', staff: 'Staff', parents: 'Parents', custom: 'Custom' }
  return map[audience] || audience || 'Everyone'
}

const formatApproval = (status) => {
  if (!status) return '—'
  return status.replace('_', ' ')
}

const canSubmit = (item) => !item.approval_status || ['draft', 'rejected'].includes(item.approval_status)

const openCreateDialog = () => {
  editingItem.value = null
  form.value = emptyForm()
  dialogError.value = null
  showDialog.value = true
}

const openEditDialog = (item) => {
  editingItem.value = item
  form.value = {
    title: item.title,
    content: item.content,
    priority: item.priority || 'normal',
    audience: item.audience || 'all',
    publish_date: item.publish_date?.slice?.(0, 10) || item.publish_date,
    expiry_date: item.expiry_date?.slice?.(0, 10) || '',
    status: item.status || 'draft',
    is_featured: !!item.is_featured,
    attachments: Array.isArray(item.attachments) ? [...item.attachments] : [],
  }
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => {
  showDialog.value = false
  editingItem.value = null
}

const onFileSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  uploading.value = true
  dialogError.value = null
  try {
    const type = file.type.startsWith('image/') ? 'image' : file.type.startsWith('video/') ? 'video' : 'pdf'
    const res = await communicationService.notices.uploadAttachment(file, type)
    const payload = res.data?.data || res.data
    form.value.attachments = [...(form.value.attachments || []), payload]
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Attachment upload failed'
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

const removeAttachment = (index) => {
  form.value.attachments.splice(index, 1)
}

const saveItem = async () => {
  dialogLoading.value = true
  dialogError.value = null
  const payload = { ...form.value }
  if (!payload.expiry_date) delete payload.expiry_date
  try {
    if (editingItem.value) {
      await communicationService.notices.update(editingItem.value.id, payload)
    } else {
      await communicationService.notices.create(payload)
    }
    closeDialog()
    loadItems()
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Failed to save notice'
  } finally {
    dialogLoading.value = false
  }
}

const publishItem = async (item) => {
  try {
    await communicationService.notices.publish(item.id)
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to publish'
  }
}

const unpublishItem = async (item) => {
  try {
    await communicationService.notices.unpublish(item.id)
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to unpublish'
  }
}

const submitItem = async (item) => {
  try {
    await communicationService.notices.submitForReview(item.id)
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to submit'
  }
}

const approveItem = async (item) => {
  try {
    await communicationService.notices.approve(item.id)
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to approve'
  }
}

const confirmDelete = (item) => {
  selectedItem.value = item
  showDeleteDialog.value = true
}

const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await communicationService.notices.delete(selectedItem.value.id)
    showDeleteDialog.value = false
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
}
</script>

<style scoped>
/* Base Styles */
.page-container { 
  max-width: 1200px; 
  margin: 0 auto; 
  padding: 1rem;
}

/* Header Styles */
.page-header { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  margin-bottom: 1.5rem; 
  flex-wrap: wrap; 
  gap: 1rem; 
}

.header-left { 
  display: flex; 
  align-items: center; 
  gap: 0.75rem; 
}

.header-left h1 { 
  margin: 0; 
  font-size: clamp(1.25rem, 4vw, 1.75rem);
}

.badge-count { 
  background: var(--bg-subtle, #f3f4f6); 
  color: var(--text-muted, #6b7280); 
  padding: 0.25rem 0.75rem; 
  border-radius: 999px; 
  font-size: 0.8rem; 
  font-weight: 500;
  white-space: nowrap;
}

.header-actions { 
  display: flex; 
  gap: 0.5rem; 
  flex-wrap: wrap;
}

.btn-text-visible {
  display: inline;
}

.btn-icon-hidden {
  display: none;
}

/* Filter Bar */
.filter-bar { 
  display: flex; 
  gap: 0.75rem; 
  margin-bottom: 1.5rem; 
  flex-wrap: wrap; 
}

.filter-select { 
  flex: 1;
  min-width: 150px;
  max-width: 200px;
}

/* Loading, Error, Empty States */
.loading-state, .error-state, .empty-state { 
  text-align: center; 
  padding: 3rem 1.5rem; 
  background: var(--bg-card, #ffffff); 
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.error-state {
  border: 1px solid #fecaca;
}

.error-icon {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.empty-state h3 {
  margin: 0.5rem 0;
  font-size: 1.25rem;
}

.empty-state p {
  color: var(--text-muted, #6b7280);
  margin-bottom: 1.5rem;
}

/* Desktop Table View */
.desktop-view {
  display: block;
}

.mobile-view {
  display: none;
}

.table-container {
  background: var(--bg-card, #ffffff);
  border-radius: 12px;
  overflow: visible;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.data-table { 
  width: 100%; 
  border-collapse: collapse; 
}

.data-table th, .data-table td { 
  padding: 0.85rem 1rem; 
  text-align: left; 
  border-bottom: 1px solid var(--border-color, #e5e7eb); 
}

.data-table th { 
  background: var(--bg-subtle, #f9fafb); 
  font-size: 0.8rem; 
  text-transform: uppercase; 
  color: var(--text-muted, #6b7280);
  font-weight: 600;
  white-space: nowrap;
}

.data-table tbody tr { 
  transition: background-color 0.2s ease;
  position: relative;
}

.data-table tbody tr:hover { 
  background-color: #f9fafb; 
}

/* Fix border radius for table */
.data-table thead tr:first-child th:first-child {
  border-top-left-radius: 12px;
}

.data-table thead tr:first-child th:last-child {
  border-top-right-radius: 12px;
}

.data-table tbody tr:last-child td:first-child {
  border-bottom-left-radius: 12px;
}

.data-table tbody tr:last-child td:last-child {
  border-bottom-right-radius: 12px;
}

/* Pills and Tags */
.featured-tag { 
  margin-left: 0.5rem; 
  font-size: 0.7rem; 
  background: #fef3c7; 
  color: #b45309; 
  padding: 0.1rem 0.5rem; 
  border-radius: 4px; 
  font-weight: 500;
  white-space: nowrap;
}

.priority-pill, .status-pill, .approval-pill { 
  font-size: 0.75rem; 
  padding: 0.2rem 0.6rem; 
  border-radius: 999px; 
  text-transform: capitalize; 
  font-weight: 500;
  white-space: nowrap;
  display: inline-block;
}

.priority-pill.urgent, .priority-pill.high { background: #fee2e2; color: #dc2626; }
.priority-pill.normal, .priority-pill.low { background: #e0e7ff; color: #4f46e5; }
.status-pill.published { background: #d1fae5; color: #059669; }
.status-pill.draft { background: #f3f4f6; color: #6b7280; }
.status-pill.archived { background: #fef3c7; color: #b45309; }
.approval-pill.pending_review { background: #fef3c7; color: #b45309; }
.approval-pill.approved { background: #d1fae5; color: #059669; }
.approval-pill.rejected { background: #fee2e2; color: #dc2626; }
.approval-pill.none { background: #f3f4f6; color: #9ca3af; }

/* Actions Cell */
.actions-cell {
  position: relative;
  width: 60px;
  text-align: center;
}

/* Dropdown */
.dropdown {
  position: relative;
  display: inline-block;
}

.dots-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1.25rem;
  color: #6b7280;
  transition: all 0.2s ease;
  padding: 0;
}

.dots-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
  color: #374151;
}

.dots-button:active {
  transform: scale(0.95);
}

.dots-icon {
  line-height: 1;
  font-weight: bold;
  letter-spacing: 1px;
}

/* Dropdown Backdrop */
.dropdown-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: transparent;
}

/* Dropdown Menu */
.dropdown-menu {
  position: fixed;
  min-width: 220px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  padding: 0.5rem;
  animation: dropdownFadeIn 0.2s ease;
}

@keyframes dropdownFadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.dropdown-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 0.25rem;
}

.dropdown-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.dropdown-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #9ca3af;
  cursor: pointer;
  padding: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.15s;
}

.dropdown-close:hover {
  background: #f3f4f6;
  color: #374151;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
  border-radius: 8px;
  transition: all 0.15s ease;
  text-align: left;
}

.dropdown-item:hover {
  background: #f3f4f6;
}

.dropdown-item:active {
  background: #e5e7eb;
}

.dropdown-item .item-icon {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.dropdown-item-danger {
  color: #dc2626;
}

.dropdown-item-danger:hover {
  background: #fef2f2;
}

.dropdown-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 0.25rem 0;
}

/* Mobile Card View */
.notice-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  overflow: hidden;
  border: 1px solid #e5e7eb;
  transition: box-shadow 0.2s;
}

.notice-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem;
  border-bottom: 1px solid #f3f4f6;
}

.card-title-section {
  flex: 1;
}

.card-title {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.card-badges {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.card-actions {
  flex-shrink: 0;
  margin-left: 1rem;
}

.card-body {
  padding: 1rem;
}

.card-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-label {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.info-value {
  font-size: 0.875rem;
  color: #374151;
  font-weight: 500;
}

/* Modal Styles */
.modal-overlay { 
  position: fixed; 
  inset: 0; 
  background: rgba(0,0,0,0.45); 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  z-index: 1000; 
  padding: 1rem;
  backdrop-filter: blur(2px);
}

.modal-dialog { 
  background: #ffffff; 
  border-radius: 12px; 
  width: 100%; 
  max-width: 520px; 
  max-height: 90vh; 
  overflow: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-dialog.modal-lg { 
  max-width: 720px; 
}

.modal-dialog.modal-sm {
  max-width: 400px;
}

.modal-header, .modal-footer { 
  padding: 1rem 1.25rem; 
  border-bottom: 1px solid #e5e7eb; 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  position: sticky;
  background: #ffffff;
}

.modal-header {
  top: 0;
  border-top-radius: 12px;
}

.modal-footer { 
  bottom: 0;
  border-bottom: none; 
  border-top: 1px solid #e5e7eb; 
  justify-content: flex-end; 
  gap: 0.5rem; 
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
}

.modal-body { 
  padding: 1.25rem; 
}

.modal-close { 
  background: none; 
  border: none; 
  font-size: 1.5rem; 
  cursor: pointer;
  color: #6b7280;
  padding: 0.25rem;
  line-height: 1;
  border-radius: 4px;
  transition: all 0.15s;
}

.modal-close:hover {
  background: #f3f4f6;
  color: #111827;
}

/* Form Styles */
.form-group { 
  margin-bottom: 1rem; 
}

.form-group label { 
  display: block; 
  margin-bottom: 0.35rem; 
  font-size: 0.875rem; 
  font-weight: 500;
  color: #374151;
}

.form-row { 
  display: grid; 
  grid-template-columns: 1fr 1fr; 
  gap: 1rem; 
}

.form-control { 
  width: 100%; 
  padding: 0.5rem 0.75rem; 
  border: 1px solid #d1d5db; 
  border-radius: 8px; 
  background: #ffffff; 
  color: #111827;
  font-size: 0.875rem;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.form-control:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

select.form-control {
  cursor: pointer;
}

textarea.form-control {
  resize: vertical;
  min-height: 100px;
}

.checkbox-group label { 
  display: flex; 
  align-items: center; 
  gap: 0.5rem; 
  margin-top: 1.75rem;
  cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  cursor: pointer;
}

.required { 
  color: #dc2626; 
}

/* Alert */
.alert-danger { 
  background: #fee2e2; 
  color: #dc2626; 
  padding: 0.75rem 1rem; 
  border-radius: 8px; 
  margin-bottom: 1rem; 
  font-size: 0.875rem;
  border: 1px solid #fecaca;
}

/* Attachment Styles */
.attachment-list { 
  margin-top: 0.5rem; 
}

.attachment-item { 
  display: flex; 
  justify-content: space-between; 
  align-items: center;
  gap: 0.5rem; 
  font-size: 0.85rem; 
  padding: 0.5rem;
  background: #f9fafb;
  border-radius: 6px;
  margin-bottom: 0.25rem;
}

.attachment-item a {
  color: #2563eb;
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attachment-item a:hover {
  text-decoration: underline;
}

.btn-link.danger { 
  color: #dc2626; 
  background: none; 
  border: none; 
  cursor: pointer;
  font-size: 0.85rem;
  white-space: nowrap;
}

.btn-link.danger:hover {
  text-decoration: underline;
}

.text-muted { 
  color: #6b7280; 
  font-size: 0.85rem; 
}

/* Button Styles */
.btn { 
  padding: 0.5rem 1rem; 
  border-radius: 8px; 
  border: none; 
  cursor: pointer; 
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  white-space: nowrap;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary { 
  background: #2563eb; 
  color: #fff; 
}

.btn-primary:hover:not(:disabled) { 
  background: #1d4ed8; 
}

.btn-outline { 
  background: transparent; 
  border: 1px solid #d1d5db; 
  color: #374151; 
}

.btn-outline:hover:not(:disabled) { 
  background: #f9fafb;
  border-color: #9ca3af;
}

.btn-danger { 
  background: #dc2626; 
  color: #fff; 
}

.btn-danger:hover:not(:disabled) { 
  background: #b91c1c; 
}

/* Spinner */
.spinner { 
  width: 32px; 
  height: 32px; 
  border: 3px solid #e5e7eb; 
  border-top-color: #2563eb; 
  border-radius: 50%; 
  animation: spin 0.8s linear infinite; 
  margin: 0 auto 1rem; 
}

@keyframes spin { 
  to { 
    transform: rotate(360deg); 
  } 
}

/* Responsive Styles */
@media (max-width: 1024px) {
  .page-container {
    padding: 0.75rem;
  }
  
  .filter-select {
    min-width: 130px;
  }
}

/* Tablet: Show card view */
@media (max-width: 768px) {
  .desktop-view {
    display: none;
  }
  
  .mobile-view {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-left {
    justify-content: space-between;
  }
  
  .header-actions {
    justify-content: flex-end;
  }
  
  .filter-bar {
    flex-direction: column;
  }
  
  .filter-select {
    max-width: 100%;
  }
  
  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  
  .modal-dialog {
    margin: 0.5rem;
    max-height: 85vh;
  }
  
  .modal-dialog.modal-lg {
    max-width: 100%;
  }
  
  .card-info-grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }
}

/* Mobile: Single column cards */
@media (max-width: 480px) {
  .page-container {
    padding: 0.5rem;
  }
  
  .page-header {
    gap: 0.75rem;
  }
  
  .header-left h1 {
    font-size: 1.25rem;
  }
  
  .badge-count {
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
  }
  
  .btn {
    font-size: 0.8rem;
    padding: 0.4rem 0.75rem;
  }
  
  .btn-text-visible {
    display: none;
  }
  
  .btn-icon-hidden {
    display: inline;
  }
  
  .filter-bar {
    gap: 0.5rem;
    margin-bottom: 1rem;
  }
  
  .card-info-grid {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  
  .card-header {
    padding: 0.75rem;
  }
  
  .card-body {
    padding: 0.75rem;
  }
  
  .card-title {
    font-size: 0.9rem;
  }
  
  .dropdown-menu {
    min-width: 200px;
    right: 10px;
  }
  
  .modal-overlay {
    padding: 0.5rem;
    align-items: flex-end;
  }
  
  .modal-dialog {
    max-height: 85vh;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }
  
  .modal-header, .modal-footer {
    padding: 0.75rem 1rem;
  }
  
  .modal-body {
    padding: 1rem;
  }
}

/* Small phones */
@media (max-width: 360px) {
  .btn {
    padding: 0.35rem 0.6rem;
    font-size: 0.75rem;
  }
  
  .dots-button {
    width: 32px;
    height: 32px;
  }
}
</style>