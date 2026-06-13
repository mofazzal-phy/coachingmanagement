<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Attendance Sessions</h1>
        <p class="header-subtitle">Manage attendance sessions for batches and classes</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="showCreateModal = true">➕ New Session</button>
        <button class="btn btn-outline" @click="fetchSessions" :disabled="loading">🔄 Refresh</button>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filter-row">
        <div class="form-group">
          <label>Batch</label>
          <select v-model="filters.batch_id" class="form-control">
            <option value="">All Batches</option>
            <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.course?.name || 'N/A' }})</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select v-model="filters.status" class="form-control">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="form-group filter-action">
          <label>&nbsp;</label>
          <button class="btn btn-primary" @click="fetchSessions" :disabled="loading">
            {{ loading ? 'Loading...' : '🔍 Filter' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading sessions...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline btn-sm" @click="fetchSessions" style="margin-top:0.75rem;">🔄 Retry</button></div>

    <!-- Sessions Table -->
    <div v-else-if="sessions.length > 0" class="table-container">
      <div class="table-toolbar">
        <span class="badge-count">{{ sessions.length }} sessions</span>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Session Name</th>
              <th>Batch</th>
              <th>Subject</th>
              <th>Teacher</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(session, index) in sessions" :key="session.id">
              <td>{{ index + 1 }}</td>
              <td><strong>{{ session.session_name || '—' }}</strong></td>
              <td>{{ session.batch?.name || '—' }}</td>
              <td>{{ session.subject?.name || '—' }}</td>
              <td>{{ session.teacher?.name || '—' }}</td>
              <td>{{ session.start_time ? session.start_time.substring(0, 5) : '—' }}</td>
              <td>{{ session.end_time ? session.end_time.substring(0, 5) : '—' }}</td>
              <td>
                <span class="status-badge" :class="'status-' + session.status">
                  {{ session.status }}
                </span>
              </td>
              <td>
                <div class="action-btns">
                  <button v-if="session.status === 'active'" class="btn btn-sm btn-success" @click="closeSession(session.id)" :disabled="closingId === session.id">
                    {{ closingId === session.id ? '...' : '✅ Close' }}
                  </button>
                  <button v-if="session.status === 'active'" class="btn btn-sm btn-outline" @click="cancelSession(session.id)" :disabled="cancellingId === session.id">
                    {{ cancellingId === session.id ? '...' : '🚫 Cancel' }}
                  </button>
                  <button class="btn btn-sm btn-outline" @click="viewSession(session)">👁️ View</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else class="empty-state">
      <div class="empty-icon">📋</div>
      <h3>No Sessions Found</h3>
      <p>Create an attendance session to get started</p>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>Create Attendance Session</h2>
          <button class="modal-close" @click="showCreateModal = false">✕</button>
        </div>
        <form @submit.prevent="createSession" class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Session Name <span class="required">*</span></label>
              <input v-model="form.session_name" class="form-control" required placeholder="e.g. Morning Lecture" />
            </div>
            <div class="form-group">
              <label>Batch <span class="required">*</span></label>
              <select v-model="form.batch_id" class="form-control" required>
                <option value="">Select batch...</option>
                <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Subject</label>
              <select v-model="form.subject_id" class="form-control">
                <option value="">Select subject...</option>
                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Teacher</label>
              <select v-model="form.teacher_id" class="form-control">
                <option value="">Select teacher...</option>
                <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name || t.full_name }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Time <span class="required">*</span></label>
              <input v-model="form.start_time" type="datetime-local" class="form-control" required />
            </div>
            <div class="form-group">
              <label>End Time <span class="required">*</span></label>
              <input v-model="form.end_time" type="datetime-local" class="form-control" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Room</label>
              <input v-model="form.room" class="form-control" placeholder="e.g. Room 101" />
            </div>
            <div class="form-group">
              <label>Description</label>
              <input v-model="form.description" class="form-control" placeholder="Optional description" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline" @click="showCreateModal = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Creating...' : 'Create Session' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div v-if="viewSessionData" class="modal-overlay" @click.self="viewSessionData = null">
      <div class="modal">
        <div class="modal-header">
          <h2>Session Details</h2>
          <button class="modal-close" @click="viewSessionData = null">✕</button>
        </div>
        <div class="modal-body">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Name</span>
              <span class="detail-value">{{ viewSessionData.session_name || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Batch</span>
              <span class="detail-value">{{ viewSessionData.batch?.name || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Subject</span>
              <span class="detail-value">{{ viewSessionData.subject?.name || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Teacher</span>
              <span class="detail-value">{{ viewSessionData.teacher?.name || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Start Time</span>
              <span class="detail-value">{{ viewSessionData.start_time || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">End Time</span>
              <span class="detail-value">{{ viewSessionData.end_time || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Room</span>
              <span class="detail-value">{{ viewSessionData.room || '—' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-value">
                <span class="status-badge" :class="'status-' + viewSessionData.status">{{ viewSessionData.status }}</span>
              </span>
            </div>
            <div class="detail-item" v-if="viewSessionData.description">
              <span class="detail-label">Description</span>
              <span class="detail-value">{{ viewSessionData.description }}</span>
            </div>
            <div class="detail-item" v-if="viewSessionData.created_at">
              <span class="detail-label">Created</span>
              <span class="detail-value">{{ new Date(viewSessionData.created_at).toLocaleString() }}</span>
            </div>
          </div>
          <div class="detail-section" v-if="viewSessionData.logs && viewSessionData.logs.length > 0">
            <h3>Attendance Logs ({{ viewSessionData.logs.length }})</h3>
            <div class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Time</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in viewSessionData.logs.slice(0, 20)" :key="log.id">
                    <td>{{ log.user_id?.substring(0, 8) || '—' }}</td>
                    <td>{{ log.user_type }}</td>
                    <td>
                      <span class="status-badge" :class="'status-' + log.attendance_status">{{ log.attendance_status }}</span>
                    </td>
                    <td>{{ log.created_at ? formatTime12(log.created_at) : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="viewSessionData = null">Close</button>
        </div>
      </div>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import attendanceService from '@/services/attendance.service'
import { formatTime12 } from '@/utils/datetime'
import enrollmentService from '@/services/enrollment.service'

const sessions = ref([])
const batches = ref([])
const subjects = ref([])
const teachers = ref([])
const loading = ref(false)
const saving = ref(false)
const closingId = ref(null)
const cancellingId = ref(null)
const error = ref(null)
const successMsg = ref(null)
const showCreateModal = ref(false)
const viewSessionData = ref(null)

const filters = ref({
  batch_id: '',
  status: '',
})

const form = ref({
  session_name: '',
  batch_id: '',
  subject_id: '',
  teacher_id: '',
  start_time: '',
  end_time: '',
  room: '',
  description: '',
})

onMounted(async () => {
  await Promise.all([
    fetchSessions(),
    fetchBatches(),
    fetchSubjects(),
    fetchTeachers(),
  ])
})

const fetchBatches = async () => {
  try {
    const res = await enrollmentService.batches.list({ per_page: 100 })
    batches.value = res.data.data || []
  } catch {}
}

const fetchSubjects = async () => {
  try {
    const res = await enrollmentService.subjects.list({ per_page: 100 })
    subjects.value = res.data.data || []
  } catch {}
}

const fetchTeachers = async () => {
  try {
    const res = await attendanceService.getTeachers({ per_page: 100 })
    teachers.value = res.data.data || []
  } catch {}
}

const fetchSessions = async () => {
  loading.value = true; error.value = null
  try {
    const params = { per_page: 50 }
    if (filters.value.batch_id) params.batch_id = filters.value.batch_id
    if (filters.value.status) params.status = filters.value.status
    const res = await attendanceService.getSessions(params)
    sessions.value = res.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load sessions'
  } finally { loading.value = false }
}

const createSession = async () => {
  saving.value = true; error.value = null
  try {
    await attendanceService.createSession(form.value)
    successMsg.value = 'Session created successfully!'
    showCreateModal.value = false
    form.value = { session_name: '', batch_id: '', subject_id: '', teacher_id: '', start_time: '', end_time: '', room: '', description: '' }
    await fetchSessions()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create session'
  } finally { saving.value = false }
}

const closeSession = async (id) => {
  closingId.value = id; error.value = null
  try {
    await attendanceService.closeSession(id)
    successMsg.value = 'Session closed successfully'
    await fetchSessions()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to close session'
  } finally { closingId.value = null }
}

const cancelSession = async (id) => {
  cancellingId.value = id; error.value = null
  try {
    await attendanceService.cancelSession(id)
    successMsg.value = 'Session cancelled'
    await fetchSessions()
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to cancel session'
  } finally { cancellingId.value = null }
}

const viewSession = async (session) => {
  try {
    const res = await attendanceService.getSession(session.id)
    viewSessionData.value = res.data.data || session
  } catch {
    viewSessionData.value = session
  }
}
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-success { background: #059669; color: white; }
.btn-success:hover { background: #047857; }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; }
.filter-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
.form-group { flex: 1; min-width: 150px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.filter-action { flex: 0 0 auto; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 2rem; background: #fef2f2; border-radius: 12px; color: #dc2626; }
.empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0; }
.table-container { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; }
.table-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-accent); border-bottom: 1px solid var(--border-color); }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: var(--bg-accent); padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1; }
.data-table td { padding: 0.6rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-light); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--bg-accent); }
.status-badge { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-active { background: #d1fae5; color: #059669; }
.status-closed { background: #f3f4f6; color: var(--text-muted); }
.status-cancelled { background: #fee2e2; color: #dc2626; }
.action-btns { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: var(--bg-card); border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
.modal-header h2 { margin: 0; font-size: 1.2rem; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); padding: 0.25rem; }
.modal-body { padding: 1.25rem; }
.form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
.form-row:last-child { margin-bottom: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.detail-item { display: flex; flex-direction: column; gap: 0.25rem; }
.detail-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.detail-value { font-size: 0.9rem; color: var(--text-primary); }
.detail-section { margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; }
.detail-section h3 { font-size: 1rem; color: var(--text-primary); margin: 0 0 0.75rem; }
.toast { position: fixed; bottom: 2rem; right: 2rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
