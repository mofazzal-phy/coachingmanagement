<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>{{ title }}</h1>
        <span class="badge-count">{{ items.length }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">
          <span class="btn-text-visible">Refresh</span>
          <span class="btn-icon-hidden">↻</span>
        </button>
        <button class="btn btn-primary" @click="openCreateDialog">
          <span class="btn-text-visible">+ {{ createLabel }}</span>
          <span class="btn-icon-hidden">+</span>
        </button>
      </div>
    </div>

    <div class="filter-bar">
      <select v-model="filters.status" class="form-control filter-select" @change="loadItems">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
      </select>
      <select v-model="filters.approval_status" class="form-control filter-select" @change="loadItems">
        <option value="">All Approval</option>
        <option value="none">Not Submitted</option>
        <option value="pending_review">Pending Review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">{{ contentType === 'blog' ? '📝' : '📄' }}</div>
      <h3>No items found</h3>
      <button class="btn btn-primary" @click="openCreateDialog">+ {{ createLabel }}</button>
    </div>

    <template v-else>
      <!-- Desktop -->
      <div class="desktop-view">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Approval</th>
                <th>Featured</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <strong>{{ item.title }}</strong>
                  <span v-if="item.is_featured" class="featured-tag">Featured</span>
                </td>
                <td><code>{{ item.slug }}</code></td>
                <td><span class="status-pill" :class="item.status">{{ item.status }}</span></td>
                <td><span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span></td>
                <td>{{ item.is_featured ? 'Yes' : '—' }}</td>
                <td>{{ formatDate(item.updated_at) }}</td>
                <td class="actions-cell">
                  <CmsActionDropdown
                    :item="item"
                    :open="openDropdownId === item.id"
                    :menu-style="dropdownStyle"
                    :edit-label="`Edit ${singularLabel}`"
                    :delete-label="`Delete ${singularLabel}`"
                    :can-submit="canSubmit(item)"
                    :can-approve="canApprove"
                    @toggle="toggleDropdown(item.id)"
                    @close="closeDropdown"
                    @edit="openEditDialog(item)"
                    @publish="publishItem(item)"
                    @unpublish="unpublishItem(item)"
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

      <!-- Mobile -->
      <div class="mobile-view">
        <div v-for="item in items" :key="item.id" class="content-card">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="card-title">{{ item.title }}</h3>
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
                :edit-label="`Edit ${singularLabel}`"
                :delete-label="`Delete ${singularLabel}`"
                :can-submit="canSubmit(item)"
                :can-approve="canApprove"
                @toggle="toggleDropdown(item.id)"
                @close="closeDropdown"
                @edit="openEditDialog(item)"
                @publish="publishItem(item)"
                @unpublish="unpublishItem(item)"
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
                <span class="info-label">Slug</span>
                <span class="info-value"><code>{{ item.slug }}</code></span>
              </div>
              <div class="info-item">
                <span class="info-label">Approval</span>
                <span class="approval-pill" :class="item.approval_status || 'none'">{{ formatApproval(item.approval_status) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Featured</span>
                <span class="info-value">{{ item.is_featured ? 'Yes' : 'No' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Updated</span>
                <span class="info-value">{{ formatDate(item.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit' : 'Create' }} {{ singularLabel }}</h3>
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
            <label>Excerpt</label>
            <textarea v-model="form.excerpt" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>Content <span class="required">*</span></label>
            <textarea v-model="form.content" class="form-control" rows="8"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
            <div class="form-group checkbox-group">
              <label><input type="checkbox" v-model="form.is_featured" /> Featured</label>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Scheduled At</label>
              <input v-model="form.scheduled_at" type="datetime-local" class="form-control" />
            </div>
            <div class="form-group">
              <label>Expires At</label>
              <input v-model="form.expires_at" type="datetime-local" class="form-control" />
            </div>
          </div>
          <div class="form-group">
            <label>Featured Image</label>
            <input type="file" class="form-control" accept="image/*" @change="onImageSelect" />
            <div v-if="form.featured_image_url || form.featured_image" class="image-preview">
              <img :src="form.featured_image_url || form.featured_image" alt="Featured" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>SEO Title</label>
              <input v-model="form.meta_title" class="form-control" />
            </div>
            <div class="form-group">
              <label>SEO Keywords</label>
              <input v-model="form.seo_keywords" class="form-control" />
            </div>
          </div>
          <div class="form-group">
            <label>SEO Description</label>
            <textarea v-model="form.meta_description" class="form-control" rows="2"></textarea>
          </div>
          <div v-if="contentType === 'page'" class="form-group">
            <label>Template</label>
            <input v-model="form.template" class="form-control" placeholder="default" />
          </div>
          <div v-if="contentType === 'blog'" class="form-group">
            <label>Tags (comma separated)</label>
            <input v-model="tagsInput" class="form-control" placeholder="news, admission, results" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.title || !form.slug || !form.content || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">×</button></div>
        <div class="modal-body"><p>Delete <strong>{{ selectedItem?.title }}</strong>?</p></div>
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
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import cmsService from '@/services/cms.service'
import CmsActionDropdown from '@/components/cms/CmsActionDropdown.vue'
import CmsRejectModal from '@/components/cms/CmsRejectModal.vue'
import { useCmsApproval } from '@/composables/useCmsApproval'

const props = defineProps({
  contentType: { type: String, default: 'page' },
  title: { type: String, required: true },
})

const api = computed(() => props.contentType === 'blog' ? cmsService.blog : cmsService.pages)
const createLabel = computed(() => props.contentType === 'blog' ? 'New Post' : 'New Page')
const singularLabel = computed(() => props.contentType === 'blog' ? 'Post' : 'Page')

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
} = useCmsApproval(api)

const items = ref([])
const loading = ref(false)
const error = ref(null)
const filters = ref({ status: '', approval_status: '' })
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)
const tagsInput = ref('')
const slugTouched = ref(false)
const openDropdownId = ref(null)
const dropdownStyle = ref({})

const emptyForm = () => ({
  title: '',
  slug: '',
  content: '',
  excerpt: '',
  status: 'draft',
  is_featured: false,
  featured_image: '',
  featured_image_url: '',
  meta_title: '',
  meta_description: '',
  seo_keywords: '',
  template: 'default',
  scheduled_at: '',
  expires_at: '',
  content_type: props.contentType,
})

const form = ref(emptyForm())

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
  if (event.key === 'Escape') closeDropdown()
}

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const params = { content_type: props.contentType, per_page: 100 }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.approval_status) params.approval_status = filters.value.approval_status
    const res = await api.value.list(params)
    items.value = cmsService.extractList(res)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—'
const formatApproval = (s) => s ? s.replace('_', ' ') : '—'
const canSubmit = (item) => !item.approval_status || ['draft', 'rejected'].includes(item.approval_status)

const slugify = (text) => text.toLowerCase().trim()
  .replace(/[^\w\s-]/g, '')
  .replace(/[\s_]+/g, '-')
  .replace(/-+/g, '-')

const autoSlug = () => {
  if (!slugTouched.value && !editingItem.value) {
    form.value.slug = slugify(form.value.title)
  }
}

const openCreateDialog = () => {
  editingItem.value = null
  slugTouched.value = false
  form.value = emptyForm()
  tagsInput.value = ''
  dialogError.value = null
  showDialog.value = true
}

const openEditDialog = (item) => {
  editingItem.value = item
  slugTouched.value = true
  form.value = {
    title: item.title,
    slug: item.slug,
    content: item.content,
    excerpt: item.excerpt || '',
    status: item.status || 'draft',
    is_featured: !!item.is_featured,
    featured_image: item.featured_image || '',
    featured_image_url: item.featured_image_url || '',
    meta_title: item.meta_title || '',
    meta_description: item.meta_description || '',
    seo_keywords: item.seo_keywords || '',
    template: item.template || 'default',
    scheduled_at: item.scheduled_at ? item.scheduled_at.slice(0, 16) : '',
    expires_at: item.expires_at ? item.expires_at.slice(0, 16) : '',
    content_type: props.contentType,
  }
  tagsInput.value = Array.isArray(item.tags) ? item.tags.join(', ') : ''
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => {
  showDialog.value = false
  editingItem.value = null
}

const onImageSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  dialogError.value = null
  try {
    const res = await cmsService.media.upload(file, 'image', props.contentType === 'blog' ? 'blog' : 'pages')
    const payload = res.data?.data || res.data
    form.value.featured_image = payload.path
    form.value.featured_image_url = payload.url
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Image upload failed'
  } finally {
    event.target.value = ''
  }
}

const buildPayload = () => {
  const payload = { ...form.value }
  delete payload.featured_image_url
  if (props.contentType === 'blog') {
    payload.tags = tagsInput.value.split(',').map(t => t.trim()).filter(Boolean)
  }
  if (!payload.scheduled_at) delete payload.scheduled_at
  if (!payload.expires_at) delete payload.expires_at
  if (!payload.excerpt) delete payload.excerpt
  return payload
}

const saveItem = async () => {
  dialogLoading.value = true
  dialogError.value = null
  try {
    const payload = buildPayload()
    if (editingItem.value) await api.value.update(editingItem.value.id, payload)
    else await api.value.create(payload)
    filters.value = { status: '', approval_status: '' }
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

const publishItem = async (item) => {
  try { await api.value.publish(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to publish' }
}

const unpublishItem = async (item) => {
  try { await api.value.unpublish(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to unpublish' }
}

const submitItem = async (item) => {
  try { await api.value.submitForReview(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to submit' }
}

const approveItem = async (item) => {
  try { await api.value.approve(item.id); loadItems() }
  catch (err) { error.value = err.response?.data?.message || 'Failed to approve' }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }

const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await api.value.delete(selectedItem.value.id)
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
.badge-count { background: var(--bg-subtle, #f3f4f6); color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; white-space: nowrap; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-text-visible { display: inline; }
.btn-icon-hidden { display: none; }
.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-select { flex: 1; min-width: 150px; max-width: 200px; }

.desktop-view { display: block; }
.mobile-view { display: none; }

.table-container { background: var(--bg-card); border-radius: 12px; overflow: visible; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); }
.data-table th { background: var(--bg-subtle, #f9fafb); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; white-space: nowrap; }
.data-table tbody tr:hover { background: var(--bg-subtle, #f9fafb); }
.data-table code { font-size: 0.8rem; color: var(--text-secondary); word-break: break-all; }

.featured-tag { margin-left: 0.5rem; font-size: 0.7rem; background: #fef3c7; color: #b45309; padding: 0.1rem 0.4rem; border-radius: 4px; white-space: nowrap; }
.status-pill, .approval-pill { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 999px; text-transform: capitalize; white-space: nowrap; display: inline-block; }
.status-pill.published { background: #d1fae5; color: #059669; }
.status-pill.draft { background: #f3f4f6; color: #6b7280; }
.approval-pill.pending_review { background: #fef3c7; color: #b45309; }
.approval-pill.approved { background: #d1fae5; color: #059669; }
.approval-pill.rejected { background: #fee2e2; color: #dc2626; }
.approval-pill.none { background: #f3f4f6; color: #9ca3af; }

.actions-cell { position: relative; width: 60px; text-align: center; }

.content-card {
  background: var(--bg-card); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  border: 1px solid var(--border-color, #e5e7eb); overflow: hidden;
}
.card-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); gap: 0.75rem; }
.card-title { margin: 0 0 0.5rem; font-size: 1rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }
.card-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.card-actions { flex-shrink: 0; }
.card-body { padding: 1rem; }
.card-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.info-item { display: flex; flex-direction: column; gap: 0.25rem; }
.info-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.info-value { font-size: 0.875rem; color: var(--text-primary); word-break: break-word; }

.loading-state, .error-state, .empty-state { text-align: center; padding: 3rem 1.5rem; background: var(--bg-card); border-radius: 12px; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow: auto; }
.modal-dialog.modal-lg { max-width: 760px; }
.modal-dialog.modal-sm { max-width: 400px; }
.modal-header, .modal-footer { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color, #e5e7eb); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); }
.modal-footer { border-bottom: none; border-top: 1px solid var(--border-color, #e5e7eb); justify-content: flex-end; gap: 0.5rem; }
.modal-body { padding: 1.25rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-primary); }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-control { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color, #d1d5db); border-radius: 8px; background: var(--bg-input, #fff); color: var(--text-primary); }
.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; margin-top: 1.75rem; }
.required { color: #dc2626; }
.alert-danger { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
.image-preview img { max-width: 100%; max-height: 200px; margin-top: 0.5rem; border-radius: 8px; }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-size: 0.875rem; white-space: nowrap; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-outline { background: transparent; border: 1px solid var(--border-color, #d1d5db); color: var(--text-primary); }
.btn-danger { background: #dc2626; color: #fff; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1024px) {
  .page-container { padding: 0.75rem; }
  .filter-select { min-width: 130px; }
}

@media (max-width: 768px) {
  .desktop-view { display: none; }
  .mobile-view { display: flex; flex-direction: column; gap: 1rem; }
  .page-header { flex-direction: column; align-items: stretch; }
  .header-left { justify-content: space-between; }
  .header-actions { justify-content: flex-end; }
  .filter-bar { flex-direction: column; }
  .filter-select { max-width: 100%; }
  .form-row { grid-template-columns: 1fr; gap: 0; }
  .modal-dialog { margin: 0.5rem; max-height: 85vh; }
  .modal-dialog.modal-lg { max-width: 100%; }
}

@media (max-width: 480px) {
  .page-container { padding: 0.5rem; }
  .btn-text-visible { display: none; }
  .btn-icon-hidden { display: inline; }
  .card-info-grid { grid-template-columns: 1fr; }
  .card-header, .card-body { padding: 0.75rem; }
  .modal-overlay { padding: 0.5rem; align-items: flex-end; }
  .modal-dialog { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
}
</style>
