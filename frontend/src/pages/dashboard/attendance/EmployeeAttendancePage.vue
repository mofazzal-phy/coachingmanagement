<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Employee Attendance</h1>
        <p class="header-subtitle">Mark daily employee attendance by department</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadEmployees(false)" :disabled="loading || !date">🔄 Refresh</button>
      </div>
    </div>

    <div class="filters-card">
      <div class="filter-row">
        <div class="form-group">
          <label>Date <span class="required">*</span></label>
          <input v-model="date" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>Department</label>
          <select v-model="filters.department_id" class="form-control" @change="onDepartmentChange">
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Search</label>
          <input v-model="searchQuery" type="text" class="form-control" placeholder="Name, ID, email..." />
        </div>
        <div class="form-group filter-action">
          <label>&nbsp;</label>
          <button class="btn btn-primary" @click="loadEmployees(false)" :disabled="!date || loading">
            {{ loading ? 'Loading...' : 'Load Employees' }}
          </button>
        </div>
      </div>
      <p v-if="selectedDepartmentName" class="filter-context">
        Department: <strong>{{ selectedDepartmentName }}</strong>
        <span v-if="employeesLoaded"> · {{ filteredEmployees.length }} employee(s)</span>
      </p>
      <p v-if="hasUnsavedChanges" class="filter-context unsaved-hint">Unsaved changes — auto-refresh paused</p>
    </div>

    <div v-if="error" class="error-banner"><p>⚠️ {{ error }}</p></div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading employees...</p></div>

    <div v-else-if="filteredEmployees.length > 0" class="attendance-card">
      <div class="card-toolbar">
        <div class="toolbar-left">
          <span class="badge-count">{{ filteredEmployees.length }} employees</span>
          <span class="badge-present" v-if="presentCount > 0">{{ presentCount }} present</span>
          <span class="badge-absent" v-if="absentCount > 0">{{ absentCount }} absent</span>
        </div>
        <div class="bulk-actions">
          <button class="btn btn-sm btn-outline" @click="markAll('present')">✅ All Present</button>
          <button class="btn btn-sm btn-outline" @click="markAll('absent')">❌ All Absent</button>
          <button class="btn btn-primary btn-sm" @click="saveAttendance" :disabled="saving">
            {{ saving ? 'Saving...' : '💾 Save' }}
          </button>
        </div>
      </div>

      <div class="employee-list">
        <div
          v-for="(emp, index) in filteredEmployees"
          :key="emp.id"
          class="employee-row"
          :class="statusRowClass(attendanceData[emp.id]?.status)"
        >
          <div class="employee-index">{{ index + 1 }}</div>

          <div class="employee-info">
            <strong class="employee-name">{{ emp.name }}</strong>
            <div class="employee-meta">
              <span class="meta-item">ID: {{ emp.employee_id || '—' }}</span>
              <span class="meta-item">🏢 {{ emp.department || '—' }}</span>
              <span class="meta-item" v-if="emp.designation && emp.designation !== 'N/A'">💼 {{ emp.designation }}</span>
              <span class="meta-item" v-if="emp.email">✉ {{ emp.email }}</span>
              <span class="meta-item" v-if="emp.phone">📞 {{ emp.phone }}</span>
              <span class="meta-item" v-if="emp.employment_type">Type: {{ emp.employment_type }}</span>
              <span class="metrics-inline" v-if="emp.metrics">
                <span class="metric-chip" v-if="emp.metrics.worked_hours > 0">⏱ {{ emp.metrics.worked_hours }}h</span>
                <span class="metric-chip late" v-if="emp.metrics.late_minutes > 0">Late {{ emp.metrics.late_minutes }}m</span>
                <span class="metric-chip warn" v-if="emp.metrics.early_leave_minutes > 0">Early {{ emp.metrics.early_leave_minutes }}m</span>
                <span class="metric-chip ot" v-if="emp.metrics.overtime_minutes > 0">OT {{ emp.metrics.overtime_minutes }}m</span>
              </span>
            </div>
          </div>

          <div class="employee-status">
            <span class="field-label">Status</span>
            <div class="status-radio-group">
              <label class="radio-btn present-btn" :class="{ active: attendanceData[emp.id]?.status === 'present' }">
                <input type="radio" :name="'status-' + emp.id" value="present" v-model="attendanceData[emp.id].status" @change="markDirty(emp.id)" />
                <span>Present</span>
              </label>
              <label class="radio-btn absent-btn" :class="{ active: attendanceData[emp.id]?.status === 'absent' }">
                <input type="radio" :name="'status-' + emp.id" value="absent" v-model="attendanceData[emp.id].status" @change="markDirty(emp.id)" />
                <span>Absent</span>
              </label>
              <label class="radio-btn late-btn" :class="{ active: attendanceData[emp.id]?.status === 'late' }">
                <input type="radio" :name="'status-' + emp.id" value="late" v-model="attendanceData[emp.id].status" @change="markDirty(emp.id)" />
                <span>Late</span>
              </label>
              <label class="radio-btn leave-btn" :class="{ active: attendanceData[emp.id]?.status === 'leave' }">
                <input type="radio" :name="'status-' + emp.id" value="leave" v-model="attendanceData[emp.id].status" @change="markDirty(emp.id)" />
                <span>Leave</span>
              </label>
            </div>
          </div>

          <div class="employee-times">
            <div class="time-field">
              <label class="field-label">Check In</label>
              <input
                v-model="attendanceData[emp.id].check_in"
                type="time"
                class="form-control form-control-sm"
                :disabled="isTimeDisabled(emp.id)"
                @change="markDirty(emp.id)"
              />
            </div>
            <div class="time-field">
              <label class="field-label">Check Out</label>
              <input
                v-model="attendanceData[emp.id].check_out"
                type="time"
                class="form-control form-control-sm"
                :disabled="isTimeDisabled(emp.id)"
                @change="markDirty(emp.id)"
              />
            </div>
          </div>

          <div class="employee-remarks">
            <label class="field-label">Remarks</label>
            <input v-model="attendanceData[emp.id].remarks" class="form-control form-control-sm" placeholder="Optional remarks" @input="markDirty(emp.id)" />
          </div>
        </div>
      </div>

      <div class="card-footer">
        <div class="footer-summary">
          <span>✅ Present: <strong>{{ presentCount }}</strong></span>
          <span>❌ Absent: <strong>{{ absentCount }}</strong></span>
          <span>⏰ Late: <strong>{{ lateCount }}</strong></span>
          <span>📝 Leave: <strong>{{ leaveCount }}</strong></span>
        </div>
        <button class="btn btn-primary" @click="saveAttendance" :disabled="saving">
          {{ saving ? 'Saving...' : '💾 Save Attendance' }}
        </button>
      </div>
    </div>

    <div v-else-if="!loading && employeesLoaded && employees.length === 0" class="empty-state">
      <div class="empty-icon">👥</div>
      <h3>No Employees Found</h3>
      <p>{{ filters.department_id ? 'No active employees in this department' : 'No active employees are registered in the system' }}</p>
    </div>

    <div v-else-if="!loading && employeesLoaded && employees.length > 0 && filteredEmployees.length === 0" class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>No Matching Employees</h3>
      <p>Try a different search term or clear the search filter</p>
    </div>

    <div v-else-if="!loading && !employeesLoaded" class="empty-state">
      <div class="empty-icon">📋</div>
      <h3>Loading Employee List</h3>
      <p>Select a date to mark employee attendance</p>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, getCurrentInstance } from 'vue'
import attendanceService from '@/services/attendance.service'
import hrService from '@/services/hr.service'
import { useAttendanceMarking } from '@/composables/useAttendanceMarking'

const instance = getCurrentInstance()
const toast = (type, msg) => instance?.proxy?.$toast?.[type]?.(msg)

const employees = ref([])
const departments = ref([])
const attendanceData = ref({})
const loading = ref(false)
const employeesLoaded = ref(false)
const error = ref(null)
const successMsg = ref(null)
const searchQuery = ref('')
const date = ref(new Date().toISOString().split('T')[0])

const filters = ref({
  department_id: '',
})

const selectedDepartmentName = computed(() => {
  if (!filters.value.department_id) return ''
  const dept = departments.value.find(d => d.id === filters.value.department_id)
  return dept?.name || ''
})

const filteredEmployees = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return employees.value

  return employees.value.filter(emp => {
    return [
      emp.name,
      emp.employee_id,
      emp.email,
      emp.phone,
      emp.department,
      emp.designation,
    ].some(val => String(val || '').toLowerCase().includes(q))
  })
})

const presentCount = computed(() => countStatus('present'))
const absentCount = computed(() => countStatus('absent'))
const lateCount = computed(() => countStatus('late'))
const leaveCount = computed(() => countStatus('leave'))

function countStatus(status) {
  return filteredEmployees.value.filter(emp => attendanceData.value[emp.id]?.status === status).length
}

function statusRowClass(status) {
  return {
    'row-present': status === 'present',
    'row-absent': status === 'absent',
    'row-late': status === 'late',
    'row-leave': status === 'leave',
  }
}

function isTimeDisabled(employeeId) {
  const status = attendanceData.value[employeeId]?.status
  return status === 'absent' || status === 'leave'
}

const applyEmployeeData = (data) => {
  employees.value = data
  attendanceData.value = {}
  data.forEach(e => {
    attendanceData.value[e.id] = {
      status: e.status || 'present',
      check_in: e.check_in ? e.check_in.substring(0, 5) : '',
      check_out: e.check_out ? e.check_out.substring(0, 5) : '',
      remarks: e.remarks || '',
    }
  })
  clearDirty()
}

const loadEmployees = async (background = false) => {
  if (!date.value) return
  if (!background) loading.value = true
  error.value = null
  try {
    const params = { date: date.value }
    if (filters.value.department_id) params.department_id = filters.value.department_id
    const res = await attendanceService.getEmployees(params)
    applyEmployeeData(res.data?.data || [])
    employeesLoaded.value = true
  } catch (err) {
    if (!background) {
      employeesLoaded.value = false
      error.value = err.response?.data?.message || 'Failed to load employees'
    }
  } finally {
    if (!background) loading.value = false
  }
}

const { dirtyIds, saving, markDirty, clearDirty, hasUnsavedChanges } = useAttendanceMarking(loadEmployees)

const onDepartmentChange = async () => {
  searchQuery.value = ''
  await loadEmployees()
}

const markAll = (status) => {
  filteredEmployees.value.forEach(emp => {
    if (attendanceData.value[emp.id]) {
      attendanceData.value[emp.id].status = status
      markDirty(emp.id)
    }
  })
}

const saveAttendance = async () => {
  if (employees.value.length === 0) {
    error.value = 'Load employees before saving attendance'
    return
  }

  const changed = employees.value.filter(e => dirtyIds.value.has(e.id))
  if (changed.length === 0) {
    toast('info', 'No changes to save')
    return
  }

  saving.value = true
  error.value = null
  successMsg.value = null
  try {
    const records = changed.map(emp => ({
      employee_id: emp.id,
      status: attendanceData.value[emp.id]?.status || 'present',
      check_in: attendanceData.value[emp.id]?.check_in || null,
      check_out: attendanceData.value[emp.id]?.check_out || null,
      remarks: attendanceData.value[emp.id]?.remarks || '',
      department_id: emp.department_id || filters.value.department_id || null,
    }))

    await attendanceService.bulkMarkEmployeeAttendance({
      date: date.value,
      department_id: filters.value.department_id || undefined,
      records,
    })
    successMsg.value = `Saved ${records.length} employee record(s)`
    clearDirty()
    await loadEmployees(true)
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    const validationErrors = err.response?.data?.errors
    error.value = err.response?.data?.message
      || (validationErrors ? Object.values(validationErrors).flat().join(', ') : 'Failed to save attendance')
    toast('error', error.value)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const res = await hrService.departments.list({ per_page: 100 })
    departments.value = res.data?.data?.data || res.data?.data || []
  } catch {}

  await loadEmployees()
})

watch(date, async () => {
  if (!date.value || hasUnsavedChanges.value) return
  await loadEmployees()
})
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; padding: 0 0.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; }
.filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; align-items: end; }
.filter-context { margin: 0.85rem 0 0; font-size: 0.82rem; color: var(--text-muted); }
.unsaved-hint { color: #d97706; font-weight: 600; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.form-control-sm { padding: 0.4rem 0.55rem; font-size: 0.82rem; }
.filter-action { display: flex; flex-direction: column; justify-content: flex-end; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-banner { margin-bottom: 1rem; padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; }
.error-banner p { margin: 0; }
.empty-state { text-align: center; padding: 3rem 1.5rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0; }

.attendance-card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; border: 1px solid var(--border-color); }
.card-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: var(--bg-accent); border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.65rem; }
.toolbar-left { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge-present { background: #d1fae5; color: #059669; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge-absent { background: #fee2e2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.bulk-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }

.employee-list { display: flex; flex-direction: column; }
.employee-row {
  display: grid;
  grid-template-columns: 36px minmax(180px, 1.4fr) minmax(200px, 1.2fr) minmax(140px, 0.9fr) minmax(120px, 1fr);
  gap: 0.85rem 1rem;
  align-items: start;
  padding: 1rem;
  border-bottom: 1px solid var(--border-light);
}
.employee-row:last-child { border-bottom: none; }
.employee-index { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); padding-top: 0.15rem; }
.employee-name { display: block; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 0.35rem; word-break: break-word; }
.employee-meta { display: flex; flex-direction: column; gap: 0.2rem; }
.meta-item { font-size: 0.78rem; color: var(--text-muted); word-break: break-word; line-height: 1.35; }
.metrics-inline { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.25rem; }
.metric-chip { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 999px; font-size: 0.68rem; font-weight: 700; background: #eef2ff; color: #4338ca; }
.metric-chip.late { background: #fef3c7; color: #b45309; }
.metric-chip.warn { background: #fee2e2; color: #b91c1c; }
.metric-chip.ot { background: #d1fae5; color: #047857; }
.field-label { display: block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-muted); margin-bottom: 0.35rem; }

.status-radio-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.35rem; }
.radio-btn {
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  padding: 0.35rem 0.45rem; border-radius: 8px; font-size: 0.72rem; font-weight: 700;
  transition: all 0.15s; border: 2px solid #e5e7eb; background: var(--bg-card); text-align: center;
}
.radio-btn input[type="radio"] { display: none; }
.present-btn { color: #059669; }
.present-btn.active { background: #d1fae5; border-color: #059669; }
.absent-btn { color: #dc2626; }
.absent-btn.active { background: #fee2e2; border-color: #dc2626; }
.late-btn { color: #d97706; }
.late-btn.active { background: #fef3c7; border-color: #d97706; }
.leave-btn { color: #2563eb; }
.leave-btn.active { background: #dbeafe; border-color: #2563eb; }

.employee-times { display: flex; flex-direction: column; gap: 0.5rem; }
.time-field .form-control:disabled { opacity: 0.45; background: #f3f4f6; }

.row-present { background: #f0fdf4; }
.row-absent { background: #fef2f2; }
.row-late { background: #fffbeb; }
.row-leave { background: #f0f9ff; }

.card-footer { padding: 0.85rem 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.65rem; background: #fafafa; }
.footer-summary { display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap; }
.toast { position: fixed; bottom: 2rem; right: 2rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

@media (max-width: 900px) {
  .employee-row {
    grid-template-columns: 1fr;
    gap: 0.75rem;
    padding: 1rem 0.85rem;
  }
  .employee-index { display: none; }
  .status-radio-group { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .employee-times { flex-direction: row; gap: 0.75rem; }
  .time-field { flex: 1; }
}
</style>
