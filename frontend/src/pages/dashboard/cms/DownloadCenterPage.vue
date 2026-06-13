<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Download Center</h1>
        <span class="badge-count">{{ items.length }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">
          <span class="btn-text-visible">Refresh</span>
          <span class="btn-icon-hidden">↻</span>
        </button>
        <button class="btn btn-primary" @click="openCreateDialog">
          <span class="btn-text-visible">+ New Download</span>
          <span class="btn-icon-hidden">+</span>
        </button>
      </div>
    </div>

    <div class="filter-bar">
      <input v-model="filters.search" class="form-control filter-search" placeholder="Search title, description..." @keyup.enter="loadItems" />
      <select v-model="filters.category" class="form-control filter-select" @change="loadItems">
        <option value="">All Categories</option>
        <option value="brochure">Brochure</option>
        <option value="form">Form</option>
        <option value="syllabus">Syllabus</option>
        <option value="policy">Policy</option>
        <option value="other">Other</option>
      </select>
      <select v-model="filters.access_level" class="form-control filter-select" @change="loadItems">
        <option value="">All Access</option>
        <option value="public">Public</option>
        <option value="authenticated">Authenticated</option>
        <option value="staff">Staff</option>
      </select>
      <select v-model="filters.status" class="form-control filter-select" @change="loadItems">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <select v-model="filters.approval_status" class="form-control filter-select" @change="loadItems">
        <option value="">All Approval</option>
        <option value="none">Not Submitted</option>
        <option value="pending_review">Pending Review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading downloads...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">📥</div>
      <h3>No downloads found</h3>
      <button class="btn btn-primary" @click="openCreateDialog">+ New Download</button>
    </div>

    <template v-else>
      <div class="desktop-view">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Access</th>
                <th>Status</th>
                <th>Approval</th>
                <th>Featured</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <div class="title-cell">
                    <span class="file-icon">📄</span>
                    <div>
                      <strong>{{ item.title }}</strong>
                      <span v-if="item.is_featured" class="featured-tag">Featured</span>
                      <span v-if="item.description" class="sub-text">{{ truncate(item.description, 50) }}</span>
                    </div>
                  </div>
                </td>
                <td><span class="category-pill">{{ item.category }}</span></td>
                <td><span class="access-pill">{{ item.access_level }}</span></td>
                <td><span class="status-pill" :class="item.status">{{ item.status }}</span></td>
                <td><span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span></td>
                <td>{{ item.is_featured ? 'Yes' : '—' }}</td>
                <td class="actions-cell">
                  <CmsActionDropdown
                    :item="item"
                    :open="openDropdownId === item.id"
                    :menu-style="dropdownStyle"
                    edit-label="Edit Download"
                    delete-label="Delete Download"
                    status-mode="active"
                    :can-submit="canSubmit(item)"
                    :can-approve="canApprove"
                    @toggle="toggleDropdown(item.id)"
                    @close="closeDropdown"
                    @edit="openEditDialog(item)"
                    @publish="activateItem(item)"
                    @unpublish="deactivateItem(item)"
                    @submit="submitItem(item)"
                    @approve="approveItem(item)"
                    @reject="openReject(item)"
                    @delete="confirmDelete(item)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mobile-view">
        <div v-for="item in items" :key="item.id" class="content-card">
          <div class="card-header">
            <div class="card-title-section">
              <div class="title-cell">
                <span class="file-icon">📄</span>
                <div>
                  <h3 class="card-title">{{ item.title }}</h3>
                  <span class="category-pill">{{ item.category }}</span>
                </div>
              </div>
              <div class="card-badges">
                <span v-if="item.is_featured" class="featured-tag">Featured</span>
                <span class="status-pill" :class="item.status">{{ item.status }}</span>
              </div>
            </div>
            <div class="card-actions">
              <CmsActionDropdown
                :item="item"
                :open="openDropdownId === item.id"
                :menu-style="dropdownStyle"
                edit-label="Edit Download"
                delete-label="Delete Download"
                status-mode="active"
                :can-submit="canSubmit(item)"
                :can-approve="canApprove"
                @toggle="toggleDropdown(item.id)"
                @close="closeDropdown"
                @edit="openEditDialog(item)"
                @publish="activateItem(item)"
                @unpublish="deactivateItem(item)"
                @submit="submitItem(item)"
                @approve="approveItem(item)"
                @reject="openReject(item)"
                @delete="confirmDelete(item)"
              />
            </div>
          </div>
          <div class="card-body">
            <div class="card-info-grid">
              <div class="info-item">
                <span class="info-label">Access</span>
                <span class="access-pill">{{ item.access_level }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Approval</span>
                <span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Sort Order</span>
                <span class="info-value">{{ item.sort_order ?? 0 }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Slug</span>
                <span class="info-value"><code>{{ item.slug }}</code></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit' : 'Create' }} Download</h3>
          <button class="modal-close" @click="closeDialog">×</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <div class="form-row">
            <div class="form-group">
              <label>Title <span class="required">*</span></label>
              <input v-model="form.title" class="form-control" @input="autoSlug" />
            </div>
            <div class="form-group">
              <label>Slug <span class="required">*</span></label>
              <input v-model="form.slug" class="form-control" @input="slugTouched = true" />
            </div>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" class="form-control" rows="3"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Category</label>
              <select v-model="form.category" class="form-control">
                <option value="brochure">Brochure</option>
                <option value="form">Form</option>
                <option value="syllabus">Syllabus</option>
                <option value="policy">Policy</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Access Level</label>
              <select v-model="form.access_level" class="form-control">
                <option value="public">Public</option>
                <option value="authenticated">Authenticated</option>
                <option value="staff">Staff</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="inactive">Inactive</option>
                <option value="active">Active</option>
              </select>
            </div>
            <div class="form-group">
              <label>Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
            </div>
          </div>
          <div class="form-group checkbox-group">
            <label><input type="checkbox" v-model="form.is_featured" /> Featured</label>
          </div>
          <div class="form-group">
            <label>PDF File <span class="required" v-if="!editingItem">*</span></label>
            <input type="file" class="form-control" accept=".pdf,application/pdf" @change="onFileSelect" />
            <div v-if="form.file_url" class="file-preview">
              <a :href="form.file_url" target="_blank" class="file-link">{{ form.file_name || 'View current file' }}</a>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.title || !form.slug || (!form.file_path && !editingItem) || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">×</button></div>
        <div class="modal-body"><p>Delete download <strong>{{ selectedItem?.title }}</strong>?</p></div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">Delete</button>
        </div>
      </div>
    </div>

    <CmsRejectModal
      :show="showRejectDialog"
      :title="rejectTarget?.title || rejectTarget?.name"
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
import cmsService from '@/services/cms.service'
import CmsActionDropdown from '@/components/cms/CmsActionDropdown.vue'
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
} = useCmsApproval(cmsService.downloads)

const items = ref([])
const loading = ref(false)
const error = ref(null)
const filters = ref({ search: '', category: '', access_level: '', status: '', approval_status: '' })
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)
const openDropdownId = ref(null)
const dropdownStyle = ref({})
const slugTouched = ref(false)

const emptyForm = () => ({
  title: '',
  slug: '',
  description: '',
  category: 'other',
  file_path: '',
  file_size: null,
  mime_type: '',
  file_url: '',
  file_name: '',
  access_level: 'public',
  sort_order: 0,
  status: 'inactive',
  is_featured: false,
})

const form = ref(emptyForm())

const slugify = (text) => text.toLowerCase().trim()
  .replace(/[^\w\s-]/g, '')
  .replace(/[\s_]+/g, '-')
  .replace(/-+/g, '-')

const autoSlug = () => {
  if (!slugTouched.value && !editingItem.value) {
    form.value.slug = slugify(form.value.title)
  }
}

const truncate = (text, len) => text && text.length > len ? `${text.slice(0, len)}…` : text

const toggleDropdown = async (id) => {
  if (openDropdownId.value === id) {
    closeDropdown()
    return
  }
  openDropdownId.value = id
  await nextTick()
  const button = document.querySelector('.dropdown-open .dots-button')
  if (button) {
    const rect = button.getBoundingClientRect()
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 8}px`,
      right: `${window.innerWidth - rect.right}px`,
      left: 'auto',
    }
    if (rect.right - 220 < 0) {
      dropdownStyle.value = { position: 'fixed', top: `${rect.bottom + 8}px`, left: `${rect.left}px`, right: 'auto' }
    }
  }
}

const closeDropdown = () => { openDropdownId.value = null }
const handleKeydown = (e) => { if (e.key === 'Escape') closeDropdown() }

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const params = { per_page: 100 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.category) params.category = filters.value.category
    if (filters.value.access_level) params.access_level = filters.value.access_level
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.approval_status) params.approval_status = filters.value.approval_status
    const res = await cmsService.downloads.list(params)
    items.value = cmsService.extractList(res)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load downloads'
  } finally {
    loading.value = false
  }
}

const formatApproval = (s) => s ? s.replace('_', ' ') : 'not submitted'
const canSubmit = (item) => !item.approval_status || ['draft', 'rejected'].includes(item.approval_status)

const openCreateDialog = () => {
  editingItem.value = null
  slugTouched.value = false
  form.value = emptyForm()
  dialogError.value = null
  showDialog.value = true
}

const openEditDialog = (item) => {
  editingItem.value = item
  slugTouched.value = true
  form.value = {
    title: item.title,
    slug: item.slug,
    description: item.description || '',
    category: item.category || 'other',
    file_path: item.file_path || '',
    file_size: item.file_size || null,
    mime_type: item.mime_type || '',
    file_url: item.file_url || '',
    file_name: item.original_name || '',
    access_level: item.access_level || 'public',
    sort_order: item.sort_order ?? 0,
    status: item.status || 'inactive',
    is_featured: !!item.is_featured,
  }
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => { showDialog.value = false; editingItem.value = null }

const onFileSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  try {
    const res = await cmsService.media.upload(file, 'pdf', 'downloads')
    const data = res.data?.data || res.data
    form.value.file_path = data.path
    form.value.file_url = data.url
    form.value.file_size = data.size
    form.value.mime_type = data.mime_type
    form.value.file_name = data.original_name || file.name
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'File upload failed'
  }
}

const saveItem = async () => {
  dialogLoading.value = true
  dialogError.value = null
  const payload = { ...form.value }
  delete payload.file_url
  delete payload.file_name
  try {
    if (editingItem.value) {
      await cmsService.downloads.update(editingItem.value.id, payload)
    } else {
      await cmsService.downloads.create(payload)
      filters.value = { search: '', category: '', access_level: '', status: '', approval_status: '' }
    }
    closeDialog()
    loadItems()
  } catch (err) {
    dialogError.value = err.response?.data?.message || (err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(', ')
      : 'Failed to save')
  } finally {
    dialogLoading.value = false
  }
}

const activateItem = async (item) => {
  try { await cmsService.downloads.activate(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to activate' }
}

const deactivateItem = async (item) => {
  try { await cmsService.downloads.deactivate(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to deactivate' }
}

const submitItem = async (item) => {
  try { await cmsService.downloads.submitForReview(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to submit' }
}

const approveItem = async (item) => {
  try { await cmsService.downloads.approve(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to approve' }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }

const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await cmsService.downloads.delete(selectedItem.value.id)
    showDeleteDialog.value = false
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadItems()
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.header-left h1 { margin: 0; font-size: clamp(1.25rem, 4vw, 1.5rem); }
.badge-count { background: var(--bg-subtle, #f3f4f6); color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-text-visible { display: inline; }
.btn-icon-hidden { display: none; }
.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-select { flex: 1; min-width: 130px; max-width: 180px; }
.filter-search { flex: 2; min-width: 200px; }
.desktop-view { display: block; }
.mobile-view { display: none; }
.table-container { background: var(--bg-card); border-radius: 12px; overflow: visible; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); }
.data-table th { background: var(--bg-subtle, #f9fafb); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
.data-table tbody tr:hover { background: var(--bg-subtle, #f9fafb); }
.title-cell { display: flex; align-items: center; gap: 0.75rem; }
.file-icon { font-size: 1.5rem; flex-shrink: 0; }
.sub-text { display: block; font-size: 0.8rem; color: var(--text-muted); }
.featured-tag { margin-left: 0.5rem; font-size: 0.7rem; background: #fef3c7; color: #b45309; padding: 0.1rem 0.4rem; border-radius: 4px; }
.category-pill, .access-pill { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 999px; text-transform: capitalize; background: #e0e7ff; color: #4338ca; display: inline-block; }
.status-pill, .approval-pill { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 999px; text-transform: capitalize; display: inline-block; }
.status-pill.active { background: #d1fae5; color: #059669; }
.status-pill.inactive { background: #f3f4f6; color: #6b7280; }
.approval-pill.pending_review { background: #fef3c7; color: #b45309; }
.approval-pill.approved { background: #d1fae5; color: #059669; }
.approval-pill.rejected { background: #fee2e2; color: #dc2626; }
.approval-pill.none { background: #f3f4f6; color: #9ca3af; }
.actions-cell { width: 60px; text-align: center; }
.content-card { background: var(--bg-card); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid var(--border-color, #e5e7eb); }
.card-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); gap: 0.75rem; }
.card-title { margin: 0; font-size: 1rem; font-weight: 600; }
.card-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
.card-actions { flex-shrink: 0; }
.card-body { padding: 1rem; }
.card-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.info-item { display: flex; flex-direction: column; gap: 0.25rem; }
.info-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.info-value { font-size: 0.875rem; }
.loading-state, .error-state, .empty-state { text-align: center; padding: 3rem 1.5rem; background: var(--bg-card); border-radius: 12px; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow: auto; }
.modal-dialog.modal-lg { max-width: 760px; }
.modal-dialog.modal-sm { max-width: 400px; }
.modal-header, .modal-footer { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color, #e5e7eb); display: flex; justify-content: space-between; align-items: center; }
.modal-footer { border-bottom: none; border-top: 1px solid var(--border-color, #e5e7eb); justify-content: flex-end; gap: 0.5rem; }
.modal-body { padding: 1.25rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; font-weight: 500; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-control { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color, #d1d5db); border-radius: 8px; background: var(--bg-input, #fff); color: var(--text-primary); }
.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; }
.required { color: #dc2626; }
.alert-danger { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
.file-preview { margin-top: 0.5rem; }
.file-link { font-size: 0.85rem; color: #2563eb; }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-size: 0.875rem; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-outline { background: transparent; border: 1px solid var(--border-color, #d1d5db); color: var(--text-primary); }
.btn-danger { background: #dc2626; color: #fff; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
  .desktop-view { display: none; }
  .mobile-view { display: flex; flex-direction: column; gap: 1rem; }
  .page-header { flex-direction: column; align-items: stretch; }
  .filter-bar { flex-direction: column; }
  .filter-select, .filter-search { max-width: 100%; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .btn-text-visible { display: none; }
  .btn-icon-hidden { display: inline; }
  .card-info-grid { grid-template-columns: 1fr; }
}
</style>
