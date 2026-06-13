<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>CMS Approval Queue</h1>
        <span class="badge-count">{{ total }} pending</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">Refresh</button>
      </div>
    </div>

    <div class="info-banner">
      All CMS content submitted for review appears here. Approve or reject items before they can be published or activated.
    </div>

    <div class="filter-bar">
      <input v-model="filters.search" class="form-control filter-search" placeholder="Search title..." @keyup.enter="loadItems" />
      <select v-model="filters.entity_type" class="form-control filter-select" @change="loadItems">
        <option value="">All Types</option>
        <option value="pages">Pages / Blog</option>
        <option value="galleries">Gallery</option>
        <option value="testimonials">Testimonials</option>
        <option value="success_stories">Success Stories</option>
        <option value="study_materials">Study Materials</option>
        <option value="download_resources">Downloads</option>
        <option value="notice_boards">Notices</option>
      </select>
      <button class="btn btn-primary btn-sm" @click="loadItems">Filter</button>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading queue...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>Queue is clear</h3>
      <p>No content is waiting for approval.</p>
    </div>

    <template v-else>
      <div class="desktop-view">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Content</th>
                <th>Type</th>
                <th>Status</th>
                <th>Submitted By</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="`${item.entity_type}-${item.id}`">
                <td><strong>{{ item.title }}</strong></td>
                <td><span class="type-pill">{{ item.entity_label }}</span></td>
                <td><span class="status-pill">{{ item.status || '—' }}</span></td>
                <td>{{ item.editor?.name || '—' }}</td>
                <td>{{ formatDate(item.submitted_at) }}</td>
                <td>
                  <div class="action-buttons">
                    <router-link :to="item.admin_path" class="btn-icon" title="Open in admin">🔗</router-link>
                    <button class="btn-icon success" title="Approve" @click="openReview(item, 'approve')">✓</button>
                    <button class="btn-icon danger" title="Reject" @click="openReview(item, 'reject')">✕</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mobile-view">
        <div v-for="item in items" :key="`${item.entity_type}-${item.id}`" class="queue-card">
          <div class="card-top">
            <span class="type-pill">{{ item.entity_label }}</span>
            <span class="card-date">{{ formatDate(item.submitted_at) }}</span>
          </div>
          <h3>{{ item.title }}</h3>
          <p class="submitter">By {{ item.editor?.name || 'Unknown' }}</p>
          <div class="card-actions">
            <router-link :to="item.admin_path" class="btn btn-outline btn-sm">View</router-link>
            <button class="btn btn-primary btn-sm" @click="openReview(item, 'approve')">Approve</button>
            <button class="btn btn-danger btn-sm" @click="openReview(item, 'reject')">Reject</button>
          </div>
        </div>
      </div>
    </template>

    <div class="modal-overlay" v-if="reviewAction" @click.self="closeReview">
      <div class="modal-dialog modal-sm">
        <div class="modal-header">
          <h3>{{ reviewAction === 'approve' ? 'Approve Content' : 'Reject Content' }}</h3>
          <button class="modal-close" @click="closeReview">×</button>
        </div>
        <div class="modal-body">
          <p v-if="reviewTarget"><strong>{{ reviewTarget.title }}</strong> ({{ reviewTarget.entity_label }})</p>
          <div v-if="reviewAction === 'reject'" class="form-group">
            <label>Reason <span class="required">*</span></label>
            <textarea v-model="reviewReason" class="form-control" rows="3" placeholder="Explain why this is rejected..."></textarea>
          </div>
          <div class="form-group">
            <label>Comment (optional)</label>
            <textarea v-model="reviewComment" class="form-control" rows="2"></textarea>
          </div>
          <div v-if="reviewError" class="alert alert-danger">{{ reviewError }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeReview">Cancel</button>
          <button class="btn btn-primary" :disabled="reviewLoading" @click="confirmReview">
            {{ reviewLoading ? 'Processing...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import cmsService from '@/services/cms.service'
import communicationService from '@/services/communication.service'

const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)
const filters = ref({ search: '', entity_type: '' })

const reviewAction = ref(null)
const reviewTarget = ref(null)
const reviewReason = ref('')
const reviewComment = ref('')
const reviewLoading = ref(false)
const reviewError = ref(null)

const apiMap = {
  pages: cmsService.pages,
  blog: cmsService.blog,
  galleries: cmsService.galleries,
  testimonials: cmsService.testimonials,
  successStories: cmsService.successStories,
  studyMaterials: cmsService.studyMaterials,
  downloads: cmsService.downloads,
  notices: communicationService.notices,
}

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const params = {}
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.entity_type) params.entity_type = filters.value.entity_type
    const res = await cmsService.foundation.approvalQueue(params)
    items.value = res.data?.data?.items || []
    total.value = res.data?.data?.total || items.value.length
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load approval queue.'
  } finally {
    loading.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleString() : '—'

const openReview = (item, action) => {
  reviewTarget.value = item
  reviewAction.value = action
  reviewReason.value = ''
  reviewComment.value = ''
  reviewError.value = null
}

const closeReview = () => {
  reviewAction.value = null
  reviewTarget.value = null
  reviewError.value = null
}

const confirmReview = async () => {
  if (!reviewTarget.value) return
  if (reviewAction.value === 'reject' && !reviewReason.value.trim()) {
    reviewError.value = 'Rejection reason is required.'
    return
  }

  const api = apiMap[reviewTarget.value.api_group]
  if (!api) {
    reviewError.value = 'Unknown content type.'
    return
  }

  reviewLoading.value = true
  reviewError.value = null
  try {
    if (reviewAction.value === 'approve') {
      await api.approve(reviewTarget.value.id, reviewComment.value)
    } else {
      await api.reject(reviewTarget.value.id, reviewReason.value, reviewComment.value)
    }
    closeReview()
    loadItems()
  } catch (err) {
    reviewError.value = err.response?.data?.message || 'Action failed.'
  } finally {
    reviewLoading.value = false
  }
}

onMounted(loadItems)
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; padding: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: clamp(1.25rem, 4vw, 1.5rem); }
.badge-count { background: #fef3c7; color: #b45309; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.info-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-size: 0.875rem; }
.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-search { flex: 2; min-width: 200px; }
.filter-select { min-width: 180px; }
.desktop-view { display: block; }
.mobile-view { display: none; }
.table-container { background: var(--bg-card); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); }
.data-table th { background: var(--bg-subtle, #f9fafb); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); }
.type-pill { font-size: 0.75rem; background: #e0e7ff; color: #3730a3; padding: 0.15rem 0.5rem; border-radius: 999px; }
.status-pill { font-size: 0.75rem; text-transform: capitalize; }
.action-buttons { display: flex; gap: 0.35rem; }
.btn-icon { width: 32px; height: 32px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9rem; }
.btn-icon.success:hover { background: #ecfdf5; border-color: #6ee7b7; }
.btn-icon.danger:hover { background: #fef2f2; border-color: #fca5a5; }
.queue-card { background: var(--bg-card); border-radius: 12px; padding: 1rem; border: 1px solid #e5e7eb; margin-bottom: 0.75rem; }
.card-top { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
.card-date { font-size: 0.75rem; color: var(--text-muted); }
.queue-card h3 { margin: 0 0 0.35rem; font-size: 1rem; }
.submitter { margin: 0 0 0.75rem; font-size: 0.8rem; color: var(--text-muted); }
.card-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.loading-state, .error-state, .empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 420px; }
.modal-header, .modal-footer { padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
.modal-footer { border-bottom: none; border-top: 1px solid #e5e7eb; justify-content: flex-end; gap: 0.5rem; }
.modal-body { padding: 1.25rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; font-weight: 500; }
.form-control { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; }
.required { color: #dc2626; }
.alert-danger { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: 8px; margin-top: 0.5rem; }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-size: 0.875rem; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 768px) {
  .desktop-view { display: none; }
  .mobile-view { display: block; }
  .filter-bar { flex-direction: column; }
}
</style>
