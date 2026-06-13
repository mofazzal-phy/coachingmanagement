<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Question Bank</h1>
        <span class="badge-count">{{ meta.total }} total</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">Refresh</button>
        <template v-if="canCreate">
          <router-link
            :to="bulkMcqRoute"
            class="btn btn-primary"
          >+ Bulk MCQ</router-link>
          <router-link
            :to="bulkCqRoute"
            class="btn btn-primary"
          >+ Bulk CQ</router-link>
          <button class="btn btn-outline" @click="openCreate">+ Single Question</button>
        </template>
      </div>
    </div>

    <div v-if="isTeacherOnly" class="info-banner">
      Save করলে প্রশ্ন <strong>Draft</strong> থাকবে। Admin review-তে পাঠাতে 📤 Submit বাটন ব্যবহার করুন।
    </div>

    <div class="filters-card">
      <div class="filter-row">
        <input v-model="filters.search" class="form-control" placeholder="Search question..." @keyup.enter="loadItems" />
        <select v-model="filters.subject_id" class="form-control" @change="loadItems">
          <option value="">All subjects</option>
          <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filters.question_type" class="form-control" @change="loadItems">
          <option value="">All types</option>
          <option value="mcq">MCQ</option>
          <option value="cq">CQ</option>
          <option value="written">Written</option>
          <option value="practical">Practical</option>
        </select>
        <select v-model="filters.status" class="form-control" @change="loadItems">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <button class="btn btn-primary btn-sm" @click="loadItems">Filter</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading questions...</p></div>
    <div v-else-if="error" class="error-state"><p>{{ error }}</p></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>No questions found</h3>
      <p>Create questions using Bulk MCQ or Bulk CQ for exam papers.</p>
      <button v-if="canCreate" class="btn btn-primary" @click="openCreate">+ Add Question</button>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Question</th>
            <th>Set</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Marks</th>
            <th>Difficulty</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td class="q-preview">{{ previewText(item) }}</td>
            <td>{{ item.set_title || (item.question_set_id ? 'Set' : '—') }}</td>
            <td>{{ item.subject_name || '—' }}</td>
            <td><span class="type-badge">{{ item.question_type?.toUpperCase() }}</span></td>
            <td>{{ item.marks }}</td>
            <td>{{ item.difficulty }}</td>
            <td><span class="status-badge" :class="item.status">{{ item.status }}</span></td>
            <td>
              <div class="action-buttons">
                <button class="btn-icon" title="View" @click="openView(item)">👁</button>
                <button v-if="canEdit(item)" class="btn-icon" title="Edit" @click="openEdit(item)">✏️</button>
                <button
                  v-if="canSubmit(item)"
                  class="btn-icon"
                  title="Submit for review"
                  @click="submitQuestion(item)"
                >📤</button>
                <button
                  v-if="canSubmitSet(item)"
                  class="btn-icon"
                  title="Submit entire set for review"
                  @click="submitSet(item.question_set_id)"
                >📤📦</button>
                <button v-if="canDelete(item)" class="btn-icon danger" title="Delete" @click="confirmDelete(item)">🗑</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit modal -->
    <div v-if="showForm" class="modal-overlay" @click.self="closeForm">
      <div class="modal-dialog modal-lg">
        <div class="modal-header">
          <h3>{{ editing ? 'Edit Question' : 'Create Question' }}</h3>
          <button class="modal-close" @click="closeForm">✕</button>
        </div>
        <div class="modal-body">
          <QuestionForm
            ref="formRef"
            v-model="formData"
            :existing-attachment-url="editing?.attachment_url"
            :error="formError"
            @attachment-change="attachmentFile = $event"
          />
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeForm">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveQuestion">
            {{ saving ? 'Saving...' : (editing ? 'Update' : 'Save as Draft') }}
          </button>
        </div>
      </div>
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
          <div v-if="viewItem.stimulus" class="stimulus-block"><strong>উদ্দীপক:</strong><p>{{ viewItem.stimulus }}</p></div>
          <p v-if="viewItem.question_type !== 'cq'" class="view-content">{{ viewItem.content }}</p>
          <div v-if="viewItem.question_type === 'cq' && viewItem.parts?.length" class="view-options">
            <div v-for="part in viewItem.parts" :key="part.key">
              <strong>{{ part.label }}</strong> ({{ part.marks }}): {{ part.content }}
            </div>
          </div>
          <div class="view-meta">
            <span>{{ viewItem.subject_name }}</span>
            <span>{{ viewItem.question_type?.toUpperCase() }}</span>
            <span>{{ viewItem.marks }} marks</span>
            <span class="status-badge" :class="viewItem.status">{{ viewItem.status }}</span>
          </div>
          <div v-if="viewItem.question_type === 'mcq' && viewItem.options?.length" class="view-options">
            <div v-for="(opt, i) in viewItem.options" :key="i" :class="{ correct: viewItem.correct_answer?.index === i }">
              {{ String.fromCharCode(65 + i) }}. {{ opt }}
            </div>
          </div>
          <div v-if="reviewLogs.length" class="review-logs">
            <h4>Review history</h4>
            <div v-for="log in reviewLogs" :key="log.id" class="log-row">
              <strong>{{ log.action }}</strong> — {{ log.reviewer_name || 'System' }}
              <span v-if="log.comment">: {{ log.comment }}</span>
              <small>{{ formatDate(log.created_at) }}</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import { useAuthStore } from '@/stores/auth.store'
import { extractData } from '@/utils/api.utils'
import QuestionForm from '@/components/exam/QuestionForm.vue'

const props = defineProps({
  teacherMode: { type: Boolean, default: false },
})

const route = useRoute()
const authStore = useAuthStore()

const items = ref([])
const meta = ref({ total: 0 })
const loading = ref(false)
const error = ref(null)
const subjects = ref([])

const filters = ref({
  search: '',
  subject_id: '',
  question_type: '',
  status: '',
})

const showForm = ref(false)
const editing = ref(null)
const formRef = ref(null)
const formData = ref({})
const formError = ref('')
const saving = ref(false)
const attachmentFile = ref(null)

const viewItem = ref(null)
const reviewLogs = ref([])

const canCreate = computed(() => authStore.hasPermission('create questions'))

const isTeacherOnly = computed(() => props.teacherMode || route.meta?.teacherMode)

const bulkMcqRoute = computed(() => ({
  name: isTeacherOnly.value ? 'TeacherQuestionBulkMcq' : 'QuestionBulkMcq',
}))
const bulkCqRoute = computed(() => ({
  name: isTeacherOnly.value ? 'TeacherQuestionBulkCq' : 'QuestionBulkCq',
}))

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
    const params = { per_page: 50, search: filters.value.search || undefined }
    if (filters.value.subject_id) params.subject_id = filters.value.subject_id
    if (filters.value.question_type) params.question_type = filters.value.question_type
    if (filters.value.status) params.status = filters.value.status
    if (isTeacherOnly.value) params.my_only = 1

    const res = await examService.questions.list(params)
    items.value = extractData(res, [])
    meta.value = res.data?.meta || { total: items.value.length }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load questions'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  formData.value = {}
  formError.value = ''
  attachmentFile.value = null
  showForm.value = true
}

async function openEdit(item) {
  try {
    const res = await examService.questions.get(item.id)
    const data = extractData(res, null)
    editing.value = data
    formData.value = {
      class_id: data.class_id,
      course_id: data.course_id || '',
      batch_id: data.batch_id || '',
      subject_id: data.subject_id,
      chapter: data.chapter || '',
      topic: data.topic || '',
      question_type: data.question_type,
      difficulty: data.difficulty,
      marks: Number(data.marks),
      content: data.content,
      options: data.options || [],
      correct_answer: data.correct_answer,
      correct_index: data.correct_answer?.index ?? 0,
    }
    formError.value = ''
    showForm.value = true
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load question'
  }
}

async function openView(item) {
  try {
    const res = await examService.questions.get(item.id)
    viewItem.value = extractData(res, null)
    const logsRes = await examService.questions.reviewLogs(item.id)
    reviewLogs.value = extractData(logsRes, [])
  } catch {
    viewItem.value = item
    reviewLogs.value = []
  }
}

function closeForm() {
  showForm.value = false
  editing.value = null
}

async function saveQuestion() {
  saving.value = true
  formError.value = ''
  try {
    const fd = formRef.value?.buildFormData()
    if (!fd) throw new Error('Form not ready')
    if (editing.value?.id) {
      await examService.questions.update(editing.value.id, fd)
    } else {
      await examService.questions.create(fd)
    }
    closeForm()
    await loadItems()
  } catch (err) {
    formError.value = err.response?.data?.message || err.message || 'Save failed'
  } finally {
    saving.value = false
  }
}

async function submitQuestion(item) {
  if (!confirm('Submit this question for admin review?')) return
  try {
    await examService.questions.submit(item.id)
    await loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Submit failed'
  }
}

async function submitSet(setId) {
  if (!setId || !confirm('Submit all draft questions in this set for admin review?')) return
  try {
    await examService.questions.bulkSubmit({ question_set_id: setId })
    await loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Submit set failed'
  }
}

function canSubmitSet(item) {
  return canCreate.value && item.question_set_id && ['draft', 'rejected'].includes(item.status)
}

function previewText(item) {
  if (item.question_type === 'cq') return truncate(item.stimulus || item.content)
  return truncate(item.content)
}

function canEdit(item) {
  return authStore.hasPermission('edit questions') && ['draft', 'rejected'].includes(item.status)
}

function canSubmit(item) {
  return canCreate.value && ['draft', 'rejected'].includes(item.status)
}

function canDelete(item) {
  return authStore.hasPermission('delete questions') && ['draft', 'rejected'].includes(item.status)
}

async function confirmDelete(item) {
  if (!confirm('Delete this question?')) return
  try {
    await examService.questions.delete(item.id)
    await loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Delete failed'
  }
}

function truncate(text, len = 80) {
  if (!text) return ''
  return text.length > len ? text.slice(0, len) + '…' : text
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { margin: 0; font-size: 1.5rem; }
.badge-count { margin-left: 0.5rem; font-size: 0.8rem; background: #e0e7ff; color: #4338ca; padding: 0.15rem 0.5rem; border-radius: 999px; }
.tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.tab { padding: 0.5rem 1rem; border: 1px solid var(--border-color); background: var(--bg-card); border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
.tab.active { background: #4f46e5; color: white; border-color: #4f46e5; }
.tab-badge { background: #fbbf24; color: #78350f; padding: 0 0.4rem; border-radius: 999px; margin-left: 0.35rem; font-size: 0.7rem; }
.info-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.stimulus-block { background: var(--bg-surface-muted); padding: 0.5rem; border-radius: 6px; margin-bottom: 0.5rem; }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm); }
.filter-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.filter-row .form-control { min-width: 140px; flex: 1; }
.table-container { background: var(--bg-card); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.65rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border-light); }
.data-table th { background: var(--bg-surface-muted); font-weight: 600; }
.q-preview { max-width: 280px; }
.type-badge { font-size: 0.7rem; background: var(--bg-accent); padding: 0.15rem 0.4rem; border-radius: 4px; }
.status-badge { font-size: 0.7rem; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: capitalize; }
.status-badge.draft { background: #f3f4f6; color: var(--text-secondary); }
.status-badge.pending { background: #fef3c7; color: #b45309; }
.status-badge.approved { background: #d1fae5; color: #059669; }
.status-badge.rejected { background: #fee2e2; color: #dc2626; }
.action-buttons { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.btn-icon { background: none; border: none; cursor: pointer; font-size: 1rem; }
.btn-icon.success { color: #059669; }
.btn-icon.danger { color: #dc2626; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow: auto; }
.modal-dialog.modal-lg { max-width: 720px; }
.modal-header { display: flex; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
.modal-body { padding: 1.25rem; }
.modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; }
.view-content { white-space: pre-wrap; line-height: 1.5; }
.view-meta { display: flex; gap: 0.75rem; flex-wrap: wrap; margin: 0.75rem 0; font-size: 0.85rem; color: var(--text-muted); }
.view-options div { padding: 0.35rem 0; }
.view-options .correct { color: #059669; font-weight: 600; }
.review-logs { margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem; }
.log-row { font-size: 0.8rem; margin-bottom: 0.35rem; }
.log-row small { color: var(--text-muted); margin-left: 0.5rem; }
.loading-state, .empty-state, .error-state { text-align: center; padding: 2rem; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.75rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }
.form-control { padding: 0.5rem 0.65rem; border: 1px solid var(--border-color); border-radius: 6px; }
.required { color: #dc2626; }
</style>
