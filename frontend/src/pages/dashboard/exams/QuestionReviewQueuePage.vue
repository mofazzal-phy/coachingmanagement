<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Review Queue</h1>
        <span class="badge-count">{{ meta.total }} pending</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">Refresh</button>
        <router-link to="/dashboard/exams/questions" class="btn btn-outline">Question Bank</router-link>
      </div>
    </div>

    <div class="info-banner">
      শুধুমাত্র teacher/submit করা <strong>Pending</strong> প্রশ্নগুলো এখানে দেখা যাবে। Draft প্রশ্ন review queue-তে আসে না।
    </div>

    <div class="filters-card">
      <div class="filter-row">
        <input v-model="filters.search" class="form-control" placeholder="Search..." @keyup.enter="loadItems" />
        <select v-model="filters.subject_id" class="form-control" @change="loadItems">
          <option value="">All subjects</option>
          <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filters.question_type" class="form-control" @change="loadItems">
          <option value="">All types</option>
          <option value="mcq">MCQ</option>
          <option value="cq">CQ</option>
        </select>
        <button class="btn btn-primary btn-sm" @click="loadItems">Filter</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading pending questions...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>No pending questions</h3>
      <p>Teachers must explicitly submit questions for review. Saved drafts do not appear here.</p>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Question / Set</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Creator</th>
            <th>Marks</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td class="q-preview">
              <span v-if="item.set_title" class="set-tag">{{ item.set_title }}</span>
              {{ previewText(item) }}
            </td>
            <td>{{ item.subject_name || '—' }}</td>
            <td><span class="type-badge">{{ item.question_type?.toUpperCase() }}</span></td>
            <td>{{ item.creator_name || '—' }}</td>
            <td>{{ item.marks }}</td>
            <td>{{ formatDate(item.updated_at) }}</td>
            <td>
              <div class="action-buttons">
                <button class="btn-icon" title="View" @click="openView(item)">👁</button>
                <button class="btn-icon success" title="Approve" @click="openReview(item, 'approve')">✓</button>
                <button class="btn-icon danger" title="Reject" @click="openReview(item, 'reject')">✕</button>
                <button class="btn-icon" title="Send back to draft" @click="sendBack(item)">↩</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- View modal -->
    <div v-if="viewItem" class="modal-overlay" @click.self="viewItem = null">
      <div class="modal-dialog modal-lg">
        <div class="modal-header">
          <h3>Question Details</h3>
          <button class="modal-close" @click="viewItem = null">✕</button>
        </div>
        <div class="modal-body view-body">
          <p v-if="viewItem.set_title"><strong>Set:</strong> {{ viewItem.set_title }}</p>
          <div v-if="viewItem.stimulus" class="stimulus-block">
            <strong>উদ্দীপক:</strong>
            <p>{{ viewItem.stimulus }}</p>
          </div>
          <p v-if="viewItem.question_type !== 'cq'" class="view-content">{{ viewItem.content }}</p>
          <div v-if="viewItem.question_type === 'mcq' && viewItem.options?.length" class="view-options">
            <div v-for="(opt, i) in viewItem.options" :key="i" :class="{ correct: viewItem.correct_answer?.index === i }">
              {{ optionLabels[i] }} {{ opt }}
            </div>
          </div>
          <div v-if="viewItem.question_type === 'cq' && viewItem.parts?.length" class="cq-parts-view">
            <div v-for="part in viewItem.parts" :key="part.key" class="part-row">
              <strong>{{ part.label }}</strong> ({{ part.marks }} marks)
              <p>{{ part.content }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Review modal -->
    <div v-if="reviewAction" class="modal-overlay" @click.self="reviewAction = null">
      <div class="modal-dialog modal-sm">
        <div class="modal-header">
          <h3>{{ reviewAction === 'approve' ? 'Approve' : 'Reject' }}</h3>
          <button class="modal-close" @click="reviewAction = null">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="reviewAction === 'reject'" class="form-group">
            <label>Reason *</label>
            <textarea v-model="reviewReason" class="form-control" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label>Comment</label>
            <textarea v-model="reviewComment" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="reviewAction = null">Cancel</button>
          <button class="btn btn-primary" :disabled="reviewLoading" @click="confirmReview">Confirm</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import { extractData } from '@/utils/api.utils'

const optionLabels = ['(ক)', '(খ)', '(গ)', '(ঘ)']
const items = ref([])
const meta = ref({ total: 0 })
const loading = ref(false)
const error = ref(null)
const subjects = ref([])
const filters = ref({ search: '', subject_id: '', question_type: '' })

const viewItem = ref(null)
const reviewAction = ref(null)
const reviewTarget = ref(null)
const reviewReason = ref('')
const reviewComment = ref('')
const reviewLoading = ref(false)

onMounted(async () => {
  try {
    const res = await academicService.subjects.list({ per_page: 500 })
    subjects.value = extractData(res, [])
  } catch {
    subjects.value = []
  }
  await loadItems()
})

async function loadItems() {
  loading.value = true
  error.value = null
  try {
    const params = { pending_only: 1, per_page: 100 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.subject_id) params.subject_id = filters.value.subject_id
    if (filters.value.question_type) params.question_type = filters.value.question_type
    const res = await examService.questions.list(params)
    items.value = extractData(res, [])
    meta.value = res.data?.meta || { total: items.value.length }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

function previewText(item) {
  if (item.question_type === 'cq') {
    return truncate(item.stimulus || item.content)
  }
  return truncate(item.content)
}

async function openView(item) {
  try {
    const res = await examService.questions.get(item.id)
    viewItem.value = extractData(res, null)
  } catch {
    viewItem.value = item
  }
}

function openReview(item, action) {
  reviewTarget.value = item
  reviewAction.value = action
  reviewReason.value = ''
  reviewComment.value = ''
}

async function confirmReview() {
  if (!reviewTarget.value) return
  reviewLoading.value = true
  try {
    if (reviewAction.value === 'approve') {
      await examService.questions.approve(reviewTarget.value.id, { comment: reviewComment.value })
    } else {
      if (!reviewReason.value.trim()) {
        error.value = 'Rejection reason is required'
        return
      }
      await examService.questions.reject(reviewTarget.value.id, {
        reason: reviewReason.value,
        comment: reviewComment.value,
      })
    }
    reviewAction.value = null
    await loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Action failed'
  } finally {
    reviewLoading.value = false
  }
}

async function sendBack(item) {
  const comment = prompt('Comment (optional):')
  if (comment === null) return
  try {
    await examService.questions.sendBack(item.id, { comment })
    await loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Send back failed'
  }
}

function truncate(text, len = 70) {
  if (!text) return '—'
  return text.length > len ? text.slice(0, len) + '…' : text
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleString()
}
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: 1.5rem; }
.badge-count { margin-left: 0.5rem; font-size: 0.8rem; background: #fef3c7; color: #b45309; padding: 0.15rem 0.5rem; border-radius: 999px; }
.info-banner { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm); }
.filter-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.filter-row .form-control { min-width: 140px; flex: 1; }
.table-container { background: var(--bg-card); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.65rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border-light); }
.data-table th { background: var(--bg-surface-muted); font-weight: 600; }
.q-preview { max-width: 300px; }
.set-tag { display: block; font-size: 0.7rem; color: #6366f1; font-weight: 600; margin-bottom: 0.15rem; }
.type-badge { font-size: 0.7rem; background: var(--bg-accent); padding: 0.15rem 0.4rem; border-radius: 4px; }
.action-buttons { display: flex; gap: 0.25rem; }
.btn-icon { background: none; border: none; cursor: pointer; font-size: 1rem; }
.btn-icon.success { color: #059669; }
.btn-icon.danger { color: #dc2626; }
.loading-state, .empty-state, .error-state { text-align: center; padding: 2rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.75rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: inline-block; }
.btn-primary { background: #4f46e5; color: white; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.form-control { padding: 0.5rem 0.65rem; border: 1px solid var(--border-color); border-radius: 6px; width: 100%; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow: auto; }
.modal-dialog.modal-lg { max-width: 720px; }
.modal-header { display: flex; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
.modal-body { padding: 1.25rem; }
.modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; }
.stimulus-block { background: var(--bg-surface-muted); padding: 0.75rem; border-radius: 8px; margin: 0.5rem 0; }
.view-options .correct { color: #059669; font-weight: 600; }
.cq-parts-view .part-row { margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light); }
.form-group { margin-bottom: 0.75rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
</style>
