<template>
  <div class="exception-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Routine Exceptions</h1>
        <p class="page-subtitle">Manage holidays, cancellations, reschedules & extra classes</p>
      </div>
      <div class="header-right">
        <button class="btn btn-primary" @click="openCreateModal">
          <span>+</span> Add Exception
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filters-grid">
        <div class="filter-group">
          <label>Academic Session</label>
          <select v-model="filters.academic_session_id" class="form-select" @change="loadExceptions">
            <option value="">All Sessions</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Class</label>
          <select v-model="filters.class_id" class="form-select" @change="loadExceptions">
            <option value="">All Classes</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }} {{ c.type ? '(' + c.type + ')' : '' }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Group</label>
          <select v-model="filters.group_id" class="form-select" @change="loadExceptions">
            <option value="">All Groups</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Exception Type</label>
          <select v-model="filters.exception_type" class="form-select" @change="loadExceptions">
            <option value="">All Types</option>
            <option value="holiday">Holiday</option>
            <option value="extra_class">Extra Class</option>
            <option value="cancellation">Cancellation</option>
            <option value="reschedule">Reschedule</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Date</label>
          <input type="date" v-model="filters.exception_date" class="form-input" @change="loadExceptions" />
        </div>
        <div class="filter-group filter-actions">
          <button class="btn btn-secondary" @click="clearFilters">Clear</button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading exceptions...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadExceptions">Retry</button>
    </div>

    <!-- Empty -->
    <div v-else-if="exceptions.length === 0" class="empty-state">
      <div class="empty-icon">📅</div>
      <h3>No Exceptions Found</h3>
      <p>No routine exceptions match your filters. Create one to get started.</p>
      <button class="btn btn-primary" @click="openCreateModal">Add Exception</button>
    </div>

    <!-- Exceptions Table -->
    <div v-else class="table-card">
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Class</th>
              <th>Section</th>
              <th>Group</th>
              <th>Original Subject</th>
              <th>Substitute Teacher</th>
              <th>New Period</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exc in exceptions" :key="exc.id">
              <td>
                <span class="date-badge">{{ formatDate(exc.exception_date) }}</span>
              </td>
              <td>
                <span class="type-badge" :class="'type-' + exc.exception_type">
                  {{ exceptionTypeLabel(exc.exception_type) }}
                </span>
              </td>
              <td>{{ exc.class?.name }}{{ exc.class?.type ? ' (' + exc.class.type + ')' : '' }}</td>
              <td>{{ exc.section?.name || '—' }}</td>
              <td>{{ exc.group?.name || '—' }}</td>
              <td>{{ exc.original_subject?.name || '—' }}</td>
              <td>{{ exc.substitute_teacher ? exc.substitute_teacher.first_name + ' ' + exc.substitute_teacher.last_name : '—' }}</td>
              <td>{{ exc.new_period ? exc.new_period.name + ' (' + exc.new_period.start_time + ')' : '—' }}</td>
              <td class="reason-cell">{{ exc.reason || '—' }}</td>
              <td>
                <span class="status-badge" :class="exc.status === 'active' ? 'status-active' : 'status-inactive'">
                  {{ exc.status === 'active' ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div class="action-btns">
                  <button class="btn-icon btn-edit" title="Edit" @click="openEditModal(exc)">
                    ✏️
                  </button>
                  <button class="btn-icon btn-delete" title="Delete" @click="confirmDelete(exc)">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="pagination">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} exceptions
        </div>
        <div class="pagination-btns">
          <button :disabled="!pagination.prev_page_url" @click="goToPage(pagination.current_page - 1)" class="btn btn-sm">
            ← Prev
          </button>
          <span class="page-indicator">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)" class="btn btn-sm">
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingException ? 'Edit Exception' : 'Add Exception' }}</h2>
          <button class="modal-close" @click="closeModal">&times;</button>
        </div>
        <form @submit.prevent="saveException" class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Academic Session <span class="required">*</span></label>
              <select v-model="form.academic_session_id" class="form-select" required>
                <option value="">Select Session</option>
                <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Exception Date <span class="required">*</span></label>
              <input type="date" v-model="form.exception_date" class="form-input" required />
            </div>
            <div class="form-group">
              <label>Exception Type <span class="required">*</span></label>
              <select v-model="form.exception_type" class="form-select" required @change="onExceptionTypeChange">
                <option value="">Select Type</option>
                <option value="holiday">Holiday</option>
                <option value="extra_class">Extra Class</option>
                <option value="cancellation">Cancellation</option>
                <option value="reschedule">Reschedule</option>
              </select>
            </div>
            <div class="form-group">
              <label>Class</label>
              <select v-model="form.class_id" class="form-select" @change="onClassChange">
                <option value="">Select Class</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }} {{ c.type ? '(' + c.type + ')' : '' }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Section</label>
              <select v-model="form.section_id" class="form-select" @change="onSectionChange">
                <option value="">Select Section</option>
                <option v-for="sec in filteredSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Group</label>
              <select v-model="form.group_id" class="form-select" @change="onGroupChange">
                <option value="">Select Group</option>
                <option v-for="g in filteredGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>

            <!-- Available Routines (for cancellation/reschedule) -->
            <div class="form-group full-width" v-if="showRoutineSelector">
              <label>Select Routine <span class="required">*</span></label>
              <select v-model="form.class_routine_id" class="form-select" @change="onRoutineSelect">
                <option value="">Select a routine</option>
                <option v-for="r in availableRoutines" :key="r.id" :value="r.id">
                  {{ r.day_of_week?.toUpperCase() }} | {{ r.subject?.name || 'N/A' }} | {{ r.period?.name || 'N/A' }} | {{ r.teacher?.first_name || '' }} {{ r.teacher?.last_name || '' }}
                </option>
              </select>
              <div v-if="availableRoutines.length === 0 && form.class_id" class="form-hint">
                No routines found for the selected class/section/group.
              </div>
            </div>

            <!-- Original Subject (for extra_class) -->
            <div class="form-group" v-if="form.exception_type === 'extra_class'">
              <label>Subject <span class="required">*</span></label>
              <select v-model="form.original_subject_id" class="form-select">
                <option value="">Select Subject</option>
                <option v-for="subj in filteredSubjects" :key="subj.id" :value="subj.id">{{ subj.name }}</option>
              </select>
            </div>

            <!-- Substitute Teacher (for extra_class) -->
            <div class="form-group" v-if="form.exception_type === 'extra_class'">
              <label>Teacher <span class="required">*</span></label>
              <select v-model="form.substitute_teacher_id" class="form-select">
                <option value="">Select Teacher</option>
                <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.first_name }} {{ t.last_name }}</option>
              </select>
            </div>

            <!-- New Period (for reschedule / extra_class) -->
            <div class="form-group" v-if="form.exception_type === 'reschedule' || form.exception_type === 'extra_class'">
              <label>New Period <span class="required">*</span></label>
              <select v-model="form.new_period_id" class="form-select">
                <option value="">Select Period</option>
                <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.start_time }} - {{ p.end_time }})</option>
              </select>
            </div>

            <div class="form-group full-width">
              <label>Reason</label>
              <textarea v-model="form.reason" class="form-textarea" rows="2" placeholder="Optional reason for this exception"></textarea>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (editingException ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="modal-overlay" v-if="showDeleteConfirm" @click.self="showDeleteConfirm = false">
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h2>Confirm Delete</h2>
          <button class="modal-close" @click="showDeleteConfirm = false">&times;</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this exception?</p>
          <p class="delete-detail" v-if="deletingException">
            Date: {{ formatDate(deletingException.exception_date) }} — {{ exceptionTypeLabel(deletingException.exception_type) }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteException" :disabled="deleting">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import academicService from '@/services/academic.service'
import teacherService from '@/services/teacher.service'

// ========== Data ==========
const exceptions = ref([])
const sessions = ref([])
const classes = ref([])
const sections = ref([])
const subjects = ref([])
const teachers = ref([])
const periods = ref([])
const groups = ref([])
const availableRoutines = ref([])
const loading = ref(false)
const error = ref(null)
const pagination = ref(null)
const saving = ref(false)
const deleting = ref(false)

const showModal = ref(false)
const showDeleteConfirm = ref(false)
const editingException = ref(null)
const deletingException = ref(null)

const filters = ref({
  academic_session_id: '',
  class_id: '',
  group_id: '',
  exception_type: '',
  exception_date: '',
})

const form = ref({
  academic_session_id: '',
  exception_date: '',
  exception_type: '',
  class_id: '',
  section_id: '',
  group_id: '',
  class_routine_id: '',
  original_subject_id: '',
  substitute_teacher_id: '',
  new_period_id: '',
  reason: '',
  status: 'active',
})

// ========== Computed ==========
const showRoutineSelector = computed(() => {
  return form.value.exception_type === 'cancellation' || form.value.exception_type === 'reschedule'
})

const filteredSections = computed(() => {
  if (!form.value.class_id) return sections.value
  return sections.value.filter(s => s.class_id == form.value.class_id)
})

const filteredGroups = computed(() => {
  if (!form.value.class_id) return groups.value
  const classSubjects = subjects.value.filter(s => {
    return s.classes && s.classes.some(c => c.id == form.value.class_id)
  })
  const classSubjectIds = classSubjects.map(s => s.id)
  return groups.value.filter(g => {
    return g.subjects && g.subjects.some(s => classSubjectIds.includes(s.id))
  })
})

const filteredSubjects = computed(() => {
  let result = subjects.value
  if (form.value.class_id) {
    result = result.filter(s => {
      return s.classes && s.classes.some(c => c.id == form.value.class_id)
    })
  }
  if (form.value.group_id) {
    result = result.filter(s => {
      return s.groups && s.groups.some(g => g.id == form.value.group_id)
    })
  }
  return result
})

const filteredTeachers = computed(() => {
  if (!form.value.original_subject_id) return teachers.value
  return teachers.value.filter(t => {
    return t.subjects && Array.isArray(t.subjects) && t.subjects.some(s => s && s.id == form.value.original_subject_id)
  })
})

// ========== Methods ==========
const formatDate = (date) => {
  if (!date) return '—'
  const d = new Date(date)
  return d.toLocaleDateString('en-BD', { year: 'numeric', month: 'short', day: 'numeric' })
}

const exceptionTypeLabel = (type) => {
  const labels = {
    holiday: 'Holiday',
    extra_class: 'Extra Class',
    cancellation: 'Cancellation',
    reschedule: 'Reschedule',
  }
  return labels[type] || type
}

const loadExceptions = async (page = 1) => {
  loading.value = true
  error.value = null
  try {
    const params = { page, per_page: 20, ...filters.value }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    
    const res = await academicService.exceptions.list(params)
    exceptions.value = res.data.data || []
    const meta = res.data.meta || {}
    pagination.value = {
      current_page: meta.current_page || 1,
      last_page: meta.last_page || 1,
      from: meta.from || 0,
      to: meta.to || 0,
      total: meta.total || 0,
      prev_page_url: meta.prev_page_url || null,
      next_page_url: meta.next_page_url || null,
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load exceptions'
  } finally {
    loading.value = false
  }
}

const goToPage = (page) => {
  if (page < 1 || page > (pagination.value?.last_page || 1)) return
  loadExceptions(page)
}

const loadFormData = async () => {
  try {
    const [sessRes, clsRes, secRes, subjRes, teachRes, perRes, grpRes] = await Promise.all([
      academicService.sessions.list({ per_page: 100 }),
      academicService.classes.listAll(),
      academicService.sections.list({ per_page: 100 }),
      academicService.subjects.list({ per_page: 100 }),
      teacherService.listAll({ per_page: 100 }),
      academicService.periods.listAll(),
      academicService.groups.listAll(),
    ])

    sessions.value = sessRes.data?.data || sessRes.data || []
    classes.value = clsRes.data?.data || clsRes.data || []
    sections.value = secRes.data?.data || secRes.data || []
    subjects.value = subjRes.data?.data || subjRes.data || []
    teachers.value = teachRes.data?.data || teachRes.data || []
    periods.value = perRes.data?.data || perRes.data || []
    groups.value = grpRes.data?.data || grpRes.data || []

    // Auto-select current session
    const currentSession = sessions.value.find(s => s.is_current)
    if (currentSession) {
      filters.value.academic_session_id = currentSession.id
    }
  } catch (err) {
    console.error('Failed to load form data:', err)
  }
}

const loadAvailableRoutines = async () => {
  if (!form.value.academic_session_id || !form.value.class_id) {
    availableRoutines.value = []
    return
  }
  try {
    const params = {
      academic_session_id: form.value.academic_session_id,
      class_id: form.value.class_id,
    }
    if (form.value.section_id) params.section_id = form.value.section_id
    if (form.value.group_id) params.group_id = form.value.group_id

    const res = await academicService.exceptions.routinesForException(params)
    availableRoutines.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Failed to load available routines:', err)
    availableRoutines.value = []
  }
}

const onExceptionTypeChange = () => {
  // Reset routine-related fields when type changes
  form.value.class_routine_id = ''
  form.value.original_subject_id = ''
  form.value.substitute_teacher_id = ''
  form.value.new_period_id = ''
}

const onClassChange = () => {
  form.value.section_id = ''
  form.value.group_id = ''
  form.value.class_routine_id = ''
  form.value.original_subject_id = ''
  form.value.substitute_teacher_id = ''
  if (showRoutineSelector.value) {
    loadAvailableRoutines()
  }
}

const onSectionChange = () => {
  form.value.class_routine_id = ''
  if (showRoutineSelector.value) {
    loadAvailableRoutines()
  }
}

const onGroupChange = () => {
  form.value.class_routine_id = ''
  form.value.original_subject_id = ''
  if (showRoutineSelector.value) {
    loadAvailableRoutines()
  }
}

const onRoutineSelect = () => {
  const selected = availableRoutines.value.find(r => r.id == form.value.class_routine_id)
  if (selected) {
    form.value.original_subject_id = selected.subject_id || ''
    form.value.substitute_teacher_id = selected.teacher_id || ''
    form.value.new_period_id = selected.period_id || ''
  }
}

const openCreateModal = () => {
  editingException.value = null
  form.value = {
    academic_session_id: filters.value.academic_session_id || '',
    exception_date: '',
    exception_type: '',
    class_id: '',
    section_id: '',
    group_id: '',
    class_routine_id: '',
    original_subject_id: '',
    substitute_teacher_id: '',
    new_period_id: '',
    reason: '',
    status: 'active',
  }
  availableRoutines.value = []
  showModal.value = true
}

const openEditModal = (exc) => {
  editingException.value = exc
  form.value = {
    academic_session_id: exc.academic_session_id || '',
    exception_date: exc.exception_date || '',
    exception_type: exc.exception_type || '',
    class_id: exc.class_id || '',
    section_id: exc.section_id || '',
    group_id: exc.group_id || '',
    class_routine_id: exc.class_routine_id || '',
    original_subject_id: exc.original_subject_id || '',
    substitute_teacher_id: exc.substitute_teacher_id || '',
    new_period_id: exc.new_period_id || '',
    reason: exc.reason || '',
    status: exc.status || 'active',
  }
  showModal.value = true
  // Load available routines if needed
  if (showRoutineSelector.value && form.value.class_id) {
    loadAvailableRoutines()
  }
}

const closeModal = () => {
  showModal.value = false
  editingException.value = null
}

const saveException = async () => {
  saving.value = true
  try {
    const payload = { ...form.value }
    // Keep class_routine_id in payload - it IS a valid column on routine_exceptions table
    // and is needed for cancellation/reschedule to track which routine is affected

    if (editingException.value) {
      await academicService.exceptions.update(editingException.value.id, payload)
    } else {
      await academicService.exceptions.create(payload)
    }
    closeModal()
    await loadExceptions(pagination.value?.current_page || 1)
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save exception')
  } finally {
    saving.value = false
  }
}

const confirmDelete = (exc) => {
  deletingException.value = exc
  showDeleteConfirm.value = true
}

const deleteException = async () => {
  if (!deletingException.value) return
  deleting.value = true
  try {
    await academicService.exceptions.delete(deletingException.value.id)
    showDeleteConfirm.value = false
    deletingException.value = null
    await loadExceptions(pagination.value?.current_page || 1)
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete exception')
  } finally {
    deleting.value = false
  }
}

const clearFilters = () => {
  filters.value = { academic_session_id: '', class_id: '', group_id: '', exception_type: '', exception_date: '' }
  loadExceptions()
}

// ========== Lifecycle ==========
onMounted(async () => {
  await loadFormData()
  await loadExceptions()
})
</script>

<style scoped>
.exception-page {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a1f25;
  margin: 0;
}

.page-subtitle {
  color: var(--text-muted);
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
}

/* Filters */
.filters-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid var(--border-color);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-group label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}

.filter-actions {
  display: flex;
  align-items: flex-end;
}

/* Table */
.table-card {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: var(--bg-accent);
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--border-color);
  white-space: nowrap;
}

.data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.data-table tr:hover {
  background: var(--bg-accent);
}

.reason-cell {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Badges */
.date-badge {
  background: #eff6ff;
  color: #2563eb;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 500;
  white-space: nowrap;
}

.type-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 500;
  white-space: nowrap;
}

.type-holiday {
  background: #fef3c7;
  color: #d97706;
}

.type-extra_class {
  background: #d1fae5;
  color: #059669;
}

.type-cancellation {
  background: #fee2e2;
  color: #dc2626;
}

.type-reschedule {
  background: #e0e7ff;
  color: #4f46e5;
}

.status-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-active {
  background: #d1fae5;
  color: #059669;
}

.status-inactive {
  background: #f3f4f6;
  color: var(--text-muted);
}

/* Action Buttons */
.action-btns {
  display: flex;
  gap: 0.35rem;
}

.btn-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
  background: transparent;
}

.btn-edit:hover {
  background: #eff6ff;
}

.btn-delete:hover {
  background: #fee2e2;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 0.75rem;
}

.pagination-info {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.pagination-btns {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-indicator {
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}

.modal-content {
  background: var(--bg-card);
  border-radius: 16px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-sm {
  max-width: 450px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #1a1f25;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
  line-height: 1;
}

.modal-close:hover {
  color: #1a1f25;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border-color);
  margin-top: 1.25rem;
}

/* Form */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}

.required {
  color: #dc2626;
}

.form-input,
.form-select,
.form-textarea {
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.9rem;
  color: var(--text-secondary);
  background: var(--bg-card);
  transition: border-color 0.2s;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #4a90d9;
  box-shadow: 0 0 0 3px rgba(74,144,217,0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.form-hint {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-top: 0.25rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 1.25rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-primary {
  background: #4a90d9;
  color: white;
}

.btn-primary:hover {
  background: #357abd;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f3f4f6;
  color: var(--text-secondary);
}

.btn-secondary:hover {
  background: #e5e7eb;
}

.btn-danger {
  background: #dc2626;
  color: white;
}

.btn-danger:hover {
  background: #b91c1c;
}

.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
}

/* States */
.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid var(--border-color);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #4a90d9;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

.empty-state h3 {
  margin: 0.5rem 0;
  color: #1a1f25;
}

.empty-state p {
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.error-state p {
  color: #dc2626;
  margin-bottom: 1rem;
}

.delete-detail {
  color: var(--text-muted);
  font-size: 0.9rem;
  font-style: italic;
}

</style>