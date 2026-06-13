<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>S Attendance</h1></div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadAttendance" :disabled="loading">🔄 Refresh</button>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filter-row">
        <div class="form-group">
          <label>Class <span class="required">*</span></label>
          <select v-model="filters.class_id" class="form-control">
            <option value="">Select class...</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Section</label>
          <select v-model="filters.section_id" class="form-control">
            <option value="">All Sections</option>
            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date <span class="required">*</span></label>
          <input v-model="filters.date" type="date" class="form-control" />
        </div>
        <div class="form-group filter-action">
          <label>&nbsp;</label>
          <button class="btn btn-primary" @click="loadAttendance" :disabled="!filters.class_id || !filters.date">
            Load Attendance
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading attendance...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p></div>

    <!-- No students -->
    <div v-else-if="students.length === 0 && filters.class_id" class="empty-state">
      <div class="empty-icon">👨‍🎓</div><h3>No Students Found</h3>
      <p>No students enrolled in this class/section</p>
    </div>

    <!-- Attendance Table -->
    <div v-else-if="students.length > 0" class="table-container">
      <div class="table-toolbar">
        <span class="badge-count">{{ students.length }} students</span>
        <div class="bulk-actions">
          <button class="btn btn-sm btn-outline" @click="markAll('present')">✅ All Present</button>
          <button class="btn btn-sm btn-outline" @click="markAll('absent')">❌ All Absent</button>
          <button class="btn btn-sm btn-outline" @click="markAll('late')">⏰ All Late</button>
          <button class="btn btn-primary btn-sm" @click="saveAttendance" :disabled="saving">
            {{ saving ? 'Saving...' : '💾 Save Attendance' }}
          </button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Roll</th>
            <th>Status</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(student, index) in students" :key="student.id">
            <td>{{ index + 1 }}</td>
            <td><strong>{{ student.name }}</strong></td>
            <td>{{ student.roll_no || '—' }}</td>
            <td>
              <div class="status-radio-group">
                <label class="radio-btn" :class="{ active: attendanceData[student.id]?.status === 'present' }">
                  <input type="radio" :name="'status-' + student.id" value="present" v-model="attendanceData[student.id].status" />
                  <span>✅ Present</span>
                </label>
                <label class="radio-btn" :class="{ active: attendanceData[student.id]?.status === 'absent' }">
                  <input type="radio" :name="'status-' + student.id" value="absent" v-model="attendanceData[student.id].status" />
                  <span>❌ Absent</span>
                </label>
                <label class="radio-btn" :class="{ active: attendanceData[student.id]?.status === 'late' }">
                  <input type="radio" :name="'status-' + student.id" value="late" v-model="attendanceData[student.id].status" />
                  <span>⏰ Late</span>
                </label>
                <label class="radio-btn" :class="{ active: attendanceData[student.id]?.status === 'excused' }">
                  <input type="radio" :name="'status-' + student.id" value="excused" v-model="attendanceData[student.id].status" />
                  <span>📝 Excused</span>
                </label>
              </div>
            </td>
            <td>
              <input v-model="attendanceData[student.id].remarks" class="form-control form-control-sm" placeholder="Optional remarks" />
            </td>
          </tr>
        </tbody>
      </table>
      <div class="table-footer">
        <button class="btn btn-primary" @click="saveAttendance" :disabled="saving">
          {{ saving ? 'Saving...' : '💾 Save Attendance' }}
        </button>
      </div>
    </div>

    <!-- Success toast -->
    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import attendanceService from '@/services/attendance.service'
import academicService from '@/services/academic.service'

const classes = ref([])
const sections = ref([])
const students = ref([])
const attendanceData = ref({})
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const successMsg = ref(null)
const currentSessionId = ref(null)

const filters = ref({
  class_id: '',
  section_id: '',
  date: new Date().toISOString().split('T')[0],
})

// Load classes and current session on mount
onMounted(async () => {
  try {
    const [classRes, sessionRes] = await Promise.all([
      academicService.classes.list(),
      academicService.sessions.current().catch(() => ({ data: { data: null } })),
    ])
    classes.value = classRes.data.data || []
    currentSessionId.value = sessionRes.data.data?.id || null
  } catch {}
})

// Load sections when class changes
watch(() => filters.value.class_id, async (classId) => {
  filters.value.section_id = ''
  if (!classId) { sections.value = []; return }
  try {
    const res = await academicService.sections.list({ class_id: classId })
    sections.value = res.data.data || []
  } catch {}
})

const loadAttendance = async () => {
  if (!filters.value.class_id || !filters.value.date) {
    error.value = 'Please select class and date'
    return
  }
  loading.value = true; error.value = null
  try {
    // Use the byClass endpoint to get students with existing attendance
    const res = await attendanceService.getByClass({
      class_id: filters.value.class_id,
      section_id: filters.value.section_id || undefined,
      date: filters.value.date,
    })
    const studentsData = res.data.data || []
    
    // Set students list
    students.value = studentsData
    
    // Initialize attendance data from response
    studentsData.forEach(s => {
      attendanceData.value[s.id] = {
        status: s.status || 'present',
        remarks: s.remarks || '',
      }
    })
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load attendance'
  } finally { loading.value = false }
}

const markAll = (status) => {
  Object.keys(attendanceData.value).forEach(id => {
    attendanceData.value[id].status = status
  })
}

const saveAttendance = async () => {
  saving.value = true; error.value = null; successMsg.value = null
  try {
    const records = Object.entries(attendanceData.value).map(([student_id, data]) => ({
      student_id: parseInt(student_id),
      status: data.status,
      remarks: data.remarks,
    }))
    
    await attendanceService.markBulk({
      academic_session_id: currentSessionId.value,
      class_id: filters.value.class_id,
      section_id: filters.value.section_id || null,
      date: filters.value.date,
      records,
    })
    
    successMsg.value = 'Attendance saved successfully!'
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save attendance'
  } finally { saving.value = false }
}
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; }
.filter-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
.form-group { flex: 1; min-width: 150px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.form-control-sm { padding: 0.35rem 0.5rem; font-size: 0.8rem; }
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
.table-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-accent); border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.5rem; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.bulk-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: var(--bg-accent); padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.data-table td { padding: 0.6rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-light); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--bg-accent); }
.status-radio-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.radio-btn { display: flex; align-items: center; gap: 0.25rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; transition: all 0.2s; border: 1px solid transparent; }
.radio-btn:hover { background: #f3f4f6; }
.radio-btn.active { background: #e8eaf6; border-color: #4f46e5; }
.radio-btn input[type="radio"] { display: none; }
.table-footer { padding: 0.75rem 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; }
.toast { position: fixed; bottom: 2rem; right: 2rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
