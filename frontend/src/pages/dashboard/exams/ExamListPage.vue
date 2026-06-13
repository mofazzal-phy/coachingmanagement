<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Exams</h1><span class="badge-count">{{ items.length }} total</span></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadItems" :disabled="loading">🔄 Refresh</button>
        <router-link to="/dashboard/exams/wizard" class="btn btn-secondary">🧙 Setup Wizard</router-link>
        <button class="btn btn-primary" @click="openCreateDialog">+ Add Exam</button>
      </div>
    </div>

    <div class="filter-bar">
      <div class="filter-group">
        <label>Session</label>
        <select v-model="filterSession" class="form-control filter-select" @change="loadItems">
          <option value="">All Sessions</option>
          <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading exams...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadItems">Try Again</button></div>
    <div v-else-if="items.length === 0" class="empty-state">
      <div class="empty-icon">📄</div><h3>No Exams Found</h3><p>Create an exam, then add routines from Exam Routines page.</p>
      <button class="btn btn-primary" @click="openCreateDialog">+ Add Exam</button>
    </div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr><th>Exam Name</th><th>Session</th><th>Start</th><th>End</th><th>Result Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td><strong>{{ item.name }}</strong></td>
            <td>{{ item.session?.name || '—' }}</td>
            <td>{{ formatDate(item.start_date) }}</td>
            <td>{{ formatDate(item.end_date) }}</td>
            <td>
              <span class="result-badge" :class="item.result_status || 'draft'">{{ resultStatusLabel(item.result_status) }}</span>
            </td>
            <td>
              <div class="action-buttons">
                <router-link
                  :to="{ name: 'ExamSetupWizard', query: { exam_id: item.id } }"
                  class="btn-icon"
                  title="Setup Wizard"
                >🧙</router-link>
                <router-link
                  :to="{ name: 'ExamResultList', query: { exam_id: item.id } }"
                  class="btn-icon"
                  title="Results"
                >📊</router-link>
                <button class="btn-icon" title="Edit" @click="openEditDialog(item)">✏️</button>
                <button class="btn-icon danger" title="Delete" @click="confirmDelete(item)">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal-overlay" v-if="showDialog" @click.self="closeDialog">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3>{{ editingItem ? 'Edit Exam' : 'Create Exam' }}</h3>
          <button class="modal-close" @click="closeDialog">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="dialogError" class="alert alert-danger">{{ dialogError }}</div>
          <p v-if="!editingItem" class="field-hint">Add a name only. Configure class, batches, subjects, and schedules from <strong>Exam Routines</strong>.</p>
          <div class="form-group">
            <label>Exam Name <span class="required">*</span></label>
            <input v-model="form.name" class="form-control" list="exam-name-presets" placeholder="e.g. Weekly Exam, Model Test" />
            <datalist id="exam-name-presets">
              <option v-for="n in examNamePresets" :key="n" :value="n" />
            </datalist>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="closeDialog">Cancel</button>
          <button class="btn btn-primary" @click="saveItem" :disabled="!form.name?.trim() || dialogLoading">
            {{ dialogLoading ? 'Saving...' : (editingItem ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" v-if="showDeleteDialog" @click.self="showDeleteDialog = false">
      <div class="modal-dialog modal-sm">
        <div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" @click="showDeleteDialog = false">✕</button></div>
        <div class="modal-body"><p>Delete exam <strong>{{ selectedItem?.name }}</strong>?</p></div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showDeleteDialog = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteItem" :disabled="deleteLoading">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import examService from '@/services/exam.service'
import academicService from '@/services/academic.service'
import { EXAM_NAME_PRESETS } from '@/utils/examType.utils'

const examNamePresets = EXAM_NAME_PRESETS
const items = ref([])
const sessions = ref([])
const loading = ref(false)
const error = ref(null)
const showDialog = ref(false)
const editingItem = ref(null)
const dialogLoading = ref(false)
const dialogError = ref(null)
const form = ref({ name: '' })
const showDeleteDialog = ref(false)
const selectedItem = ref(null)
const deleteLoading = ref(false)
const filterSession = ref('')

const loadItems = async () => {
  loading.value = true
  error.value = null
  try {
    const params = { per_page: 100 }
    if (filterSession.value) params.academic_session_id = filterSession.value
    const res = await examService.exams.list(params)
    items.value = res.data?.data || res.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingItem.value = null
  form.value = { name: '' }
  dialogError.value = null
  showDialog.value = true
}

const openEditDialog = (item) => {
  editingItem.value = item
  form.value = { name: item.name }
  dialogError.value = null
  showDialog.value = true
}

const closeDialog = () => { showDialog.value = false }

const saveItem = async () => {
  dialogLoading.value = true
  dialogError.value = null
  try {
    if (editingItem.value) {
      await examService.exams.update(editingItem.value.id, { name: form.value.name.trim() })
    } else {
      await examService.exams.create({ name: form.value.name.trim(), status: 'draft' })
    }
    closeDialog()
    loadItems()
  } catch (err) {
    dialogError.value = err.response?.data?.message || 'Failed to save'
  } finally {
    dialogLoading.value = false
  }
}

const confirmDelete = (item) => { selectedItem.value = item; showDeleteDialog.value = true }
const deleteItem = async () => {
  deleteLoading.value = true
  try {
    await examService.exams.delete(selectedItem.value.id)
    showDeleteDialog.value = false
    loadItems()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to delete'
    showDeleteDialog.value = false
  } finally {
    deleteLoading.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const resultStatusLabel = (status) => {
  if (status === 'published') return 'Results published'
  if (status === 'processing') return 'Processing'
  return status || 'Draft'
}

onMounted(async () => {
  try {
    const res = await academicService.sessions.list()
    sessions.value = res.data?.data || res.data || []
  } catch {}
  loadItems()
})
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
.filter-bar { margin-bottom: 1.25rem; }
.filter-group { display: flex; align-items: center; gap: 0.5rem; }
.filter-select { min-width: 200px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; }
.header-left h1 { font-size: 1.5rem; margin: 0; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-left: 0.5rem; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn { padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-primary { background: #4f46e5; color: white; }
.btn-secondary { background: #e5e7eb; color: var(--text-secondary); }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-danger { background: #dc2626; color: white; }
.btn-icon { text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
.data-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); }
.data-table th { background: var(--bg-accent); padding: 0.75rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); }
.data-table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid var(--border-light); font-size: 0.85rem; }
.result-badge { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
.result-badge.published { background: #d1fae5; color: #059669; }
.result-badge.draft { background: #f3f4f6; color: var(--text-muted); }
.result-badge.processing { background: #fef3c7; color: #b45309; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-dialog { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 440px; }
.modal-header, .modal-footer { padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.25rem; }
.field-hint { font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
.required { color: #ef4444; }
.loading-state, .empty-state, .error-state { text-align: center; padding: 2rem; background: var(--bg-card); border-radius: 12px; }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
