<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Teacher Attendance</h1>
        <p class="header-subtitle">Mark teacher attendance — all batches and subjects load by default</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="refreshCurrentView" :disabled="loading || !date">🔄 Refresh</button>
      </div>
    </div>

    <!-- Mode tabs -->
    <div class="mode-tabs">
      <button type="button" class="mode-tab" :class="{ active: attendanceView === 'office' }" @click="setAttendanceView('office')">
        Office Attendance
      </button>
      <button type="button" class="mode-tab" :class="{ active: attendanceView === 'class' }" @click="setAttendanceView('class')">
        Today's Classes
      </button>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filter-row">
        <div class="form-group">
          <label>Date <span class="required">*</span></label>
          <input v-model="date" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>Batch</label>
          <select v-model="filters.batch_id" class="form-control" @change="onBatchChange">
            <option value="">All Batches</option>
            <option v-for="b in batches" :key="b.id" :value="b.id">
              {{ b.name }}<template v-if="b.course?.name"> ({{ b.course.name }})</template>
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>Subject</label>
          <select
            v-model="filters.subject_id"
            class="form-control"
            :disabled="loadingSubjects"
            @change="onSubjectChange"
          >
            <option value="">{{ loadingSubjects ? 'Loading subjects...' : 'All Subjects' }}</option>
            <option v-for="s in filterSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Search</label>
          <input v-model="searchQuery" type="text" class="form-control" placeholder="Teacher name, ID, email..." />
        </div>
      </div>
      <p v-if="filterContextLabel" class="filter-context">{{ filterContextLabel }}</p>
      <p v-if="hasUnsavedChanges || hasClassUnsavedChanges" class="filter-context unsaved-hint">Unsaved changes — auto-refresh paused</p>
    </div>

    <!-- Error -->
    <div v-if="error" class="error-banner"><p>⚠️ {{ error }}</p></div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>{{ attendanceView === 'class' ? 'Loading classes...' : 'Loading teachers...' }}</p></div>

    <!-- Attendance Card (Office) -->
    <div v-else-if="attendanceView === 'office' && filteredTeachers.length > 0" class="attendance-card">
      <div class="card-toolbar">
        <div class="toolbar-left">
          <span class="badge-count">{{ filteredTeachers.length }} teachers</span>
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

      <div class="teacher-list">
        <div
          v-for="(teacher, index) in filteredTeachers"
          :key="teacher.id"
          class="teacher-row"
          :class="statusRowClass(attendanceData[teacher.id]?.status)"
        >
          <div class="teacher-index">{{ index + 1 }}</div>

          <div class="teacher-info">
            <strong class="teacher-name">{{ teacher.name }}</strong>
            <div class="teacher-meta">
              <span class="meta-item">ID: {{ teacher.teacher_id || '—' }}</span>
              <span class="meta-item" v-if="teacher.routine?.batch_name">📦 {{ teacher.routine.batch_name }}</span>
              <span class="meta-item" v-if="teacher.routine?.subject_name">📚 {{ teacher.routine.subject_name }}</span>
              <span class="meta-item" v-if="teacher.email">✉ {{ teacher.email }}</span>
              <span class="meta-item" v-if="teacher.phone">📞 {{ teacher.phone }}</span>
              <span class="meta-item slot" v-if="teacher.routine?.slot_time">🕐 {{ teacher.routine.slot_time }}</span>
            </div>
          </div>

          <div class="teacher-status">
            <span class="field-label">Status</span>
            <div class="status-radio-group">
              <label class="radio-btn present-btn" :class="{ active: attendanceData[teacher.id]?.status === 'present' }">
                <input type="radio" :name="'status-' + teacher.id" value="present" v-model="attendanceData[teacher.id].status" @change="markDirty(teacher.id)" />
                <span>Present</span>
              </label>
              <label class="radio-btn absent-btn" :class="{ active: attendanceData[teacher.id]?.status === 'absent' }">
                <input type="radio" :name="'status-' + teacher.id" value="absent" v-model="attendanceData[teacher.id].status" @change="markDirty(teacher.id)" />
                <span>Absent</span>
              </label>
              <label class="radio-btn late-btn" :class="{ active: attendanceData[teacher.id]?.status === 'late' }">
                <input type="radio" :name="'status-' + teacher.id" value="late" v-model="attendanceData[teacher.id].status" @change="markDirty(teacher.id)" />
                <span>Late</span>
              </label>
              <label class="radio-btn leave-btn" :class="{ active: attendanceData[teacher.id]?.status === 'leave' }">
                <input type="radio" :name="'status-' + teacher.id" value="leave" v-model="attendanceData[teacher.id].status" @change="markDirty(teacher.id)" />
                <span>Leave</span>
              </label>
            </div>
          </div>

          <div class="teacher-times">
            <div class="time-field">
              <label class="field-label">Check In</label>
              <input
                v-model="attendanceData[teacher.id].check_in"
                type="time"
                class="form-control form-control-sm"
                :disabled="isTimeDisabled(teacher.id)"
                @change="markDirty(teacher.id)"
              />
            </div>
            <div class="time-field">
              <label class="field-label">Check Out</label>
              <input
                v-model="attendanceData[teacher.id].check_out"
                type="time"
                class="form-control form-control-sm"
                :disabled="isTimeDisabled(teacher.id)"
                @change="markDirty(teacher.id)"
              />
            </div>
          </div>

          <div class="teacher-remarks">
            <label class="field-label">Remarks</label>
            <input v-model="attendanceData[teacher.id].remarks" class="form-control form-control-sm" placeholder="Optional remarks" @input="markDirty(teacher.id)" />
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

    <!-- Class Ledger (Per-class teachers) -->
    <div v-else-if="attendanceView === 'class' && filteredClassEntries.length > 0" class="attendance-card">
      <div class="card-toolbar">
        <div class="toolbar-left">
          <span class="badge-count">{{ filteredClassEntries.length }} class(es)</span>
          <span class="badge-present" v-if="completedClassCount > 0">{{ completedClassCount }} completed</span>
        </div>
        <div class="bulk-actions">
          <button class="btn btn-sm btn-outline" @click="loadClassLedger(false)">🔄 Sync</button>
          <button class="btn btn-primary btn-sm" @click="saveClassLedger" :disabled="savingClass">
            {{ savingClass ? 'Saving...' : '💾 Save' }}
          </button>
        </div>
      </div>

      <div class="teacher-list">
        <div
          v-for="(entry, index) in filteredClassEntries"
          :key="entry.id"
          class="teacher-row"
          :class="classRowClass(classLedgerData[entry.id]?.status)"
        >
          <div class="teacher-index">{{ index + 1 }}</div>
          <div class="teacher-info">
            <strong class="teacher-name">{{ entry.teacher_name }}</strong>
            <div class="teacher-meta">
              <span class="meta-item">ID: {{ entry.teacher_code || '—' }}</span>
              <span class="meta-item" v-if="entry.batch_name">📦 {{ entry.batch_name }}</span>
              <span class="meta-item" v-if="entry.subject_name">📚 {{ entry.subject_name }}</span>
              <span class="meta-item slot" v-if="entry.start_time">🕐 {{ entry.start_time }} - {{ entry.end_time }}</span>
              <span class="meta-item">Type: {{ entry.teacher_type || 'guest' }}</span>
            </div>
          </div>
          <div class="teacher-status">
            <span class="field-label">Class Status</span>
            <div class="status-radio-group">
              <label class="radio-btn present-btn" :class="{ active: classLedgerData[entry.id]?.status === 'completed' }">
                <input type="radio" :name="'class-status-' + entry.id" value="completed" v-model="classLedgerData[entry.id].status" @change="markClassDirty(entry.id)" />
                <span>Completed</span>
              </label>
              <label class="radio-btn absent-btn" :class="{ active: classLedgerData[entry.id]?.status === 'no_show' }">
                <input type="radio" :name="'class-status-' + entry.id" value="no_show" v-model="classLedgerData[entry.id].status" @change="markClassDirty(entry.id)" />
                <span>No Show</span>
              </label>
              <label class="radio-btn leave-btn" :class="{ active: classLedgerData[entry.id]?.status === 'cancelled' }">
                <input type="radio" :name="'class-status-' + entry.id" value="cancelled" v-model="classLedgerData[entry.id].status" @change="markClassDirty(entry.id)" />
                <span>Cancelled</span>
              </label>
            </div>
          </div>
          <div class="teacher-remarks">
            <label class="field-label">Notes</label>
            <input v-model="classLedgerData[entry.id].notes" class="form-control form-control-sm" placeholder="Optional notes" @input="markClassDirty(entry.id)" />
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && attendanceView === 'office' && teachersLoaded && teachers.length === 0" class="empty-state">
      <div class="empty-icon">👨‍🏫</div>
      <h3>No Teachers Found</h3>
      <p>{{ emptyTeachersMessage }}</p>
    </div>

    <div v-else-if="!loading && attendanceView === 'office' && teachersLoaded && teachers.length > 0 && filteredTeachers.length === 0" class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>No Matching Teachers</h3>
      <p>Try a different search term or clear the search filter</p>
    </div>

    <div v-else-if="!loading && attendanceView === 'class' && classLoaded && classEntries.length === 0" class="empty-state">
      <div class="empty-icon">📚</div>
      <h3>No Classes Scheduled</h3>
      <p>Sync class sessions from the routine or pick another date/batch</p>
      <button class="btn btn-primary" @click="loadClassLedger(false)" style="margin-top: 0.75rem">Sync from Routine</button>
    </div>

    <div v-else-if="!loading && attendanceView === 'class' && classLoaded && classEntries.length > 0 && filteredClassEntries.length === 0" class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>No Matching Classes</h3>
      <p>Try a different search term or clear filters</p>
    </div>

    <div v-else-if="!loading && attendanceView === 'office' && !teachersLoaded" class="empty-state">
      <div class="empty-icon">📋</div>
      <h3>Loading Teacher List</h3>
      <p>Select a date to mark teacher attendance</p>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, getCurrentInstance } from 'vue'
import attendanceService from '@/services/attendance.service'
import enrollmentService from '@/services/enrollment.service'
import academicService from '@/services/academic.service'
import { useAttendanceMarking } from '@/composables/useAttendanceMarking'

const instance = getCurrentInstance()
const toast = (type, msg) => instance?.proxy?.$toast?.[type]?.(msg)

const teachers = ref([])
const batches = ref([])
const filterSubjects = ref([])
const attendanceData = ref({})
const loading = ref(false)
const loadingSubjects = ref(false)
const teachersLoaded = ref(false)
const error = ref(null)
const successMsg = ref(null)
const searchQuery = ref('')
const date = ref(new Date().toISOString().split('T')[0])
const attendanceView = ref('office')
const classEntries = ref([])
const classLedgerData = ref({})
const classDirtyIds = ref(new Set())
const classLoaded = ref(false)
const savingClass = ref(false)

const filters = ref({
  batch_id: '',
  subject_id: '',
})

const selectedBatchName = computed(() => {
  if (!filters.value.batch_id) return ''
  const batch = batches.value.find(b => b.id === filters.value.batch_id)
  return batch?.name || ''
})

const selectedSubjectName = computed(() => {
  if (!filters.value.subject_id) return ''
  const subject = filterSubjects.value.find(s => s.id === filters.value.subject_id)
  return subject?.name || ''
})

const filterContextLabel = computed(() => {
  if (attendanceView.value === 'class') {
    if (!classLoaded.value) return ''
    return `Showing ${filteredClassEntries.value.length} of ${classEntries.value.length} class(es)`
  }
  if (!teachersLoaded.value) return ''
  const parts = []
  parts.push(`Showing ${filteredTeachers.value.length} of ${teachers.value.length} teacher(s)`)
  if (selectedBatchName.value) parts.push(`Batch: ${selectedBatchName.value}`)
  if (selectedSubjectName.value) parts.push(`Subject: ${selectedSubjectName.value}`)
  return parts.join(' · ')
})

const filteredClassEntries = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return classEntries.value
  return classEntries.value.filter(entry => {
    return [
      entry.teacher_name,
      entry.teacher_code,
      entry.batch_name,
      entry.subject_name,
    ].some(val => String(val || '').toLowerCase().includes(q))
  })
})

const completedClassCount = computed(() =>
  filteredClassEntries.value.filter(e => classLedgerData.value[e.id]?.status === 'completed').length
)

const hasClassUnsavedChanges = computed(() => classDirtyIds.value.size > 0)

const emptyTeachersMessage = computed(() => {
  if (filters.value.batch_id && filters.value.subject_id) {
    return 'No teacher is assigned to this batch and subject in the class routine'
  }
  if (filters.value.batch_id) {
    return 'No teacher is assigned to this batch in the class routine'
  }
  if (filters.value.subject_id) {
    return 'No teacher is assigned to this subject in the class routine'
  }
  return 'No teacher is assigned in any published class routine'
})

const filteredTeachers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return teachers.value

  return teachers.value.filter(teacher => {
    return [
      teacher.name,
      teacher.teacher_id,
      teacher.email,
      teacher.phone,
      teacher.routine?.batch_name,
      teacher.routine?.subject_name,
    ].some(val => String(val || '').toLowerCase().includes(q))
  })
})

const presentCount = computed(() => countStatus('present'))
const absentCount = computed(() => countStatus('absent'))
const lateCount = computed(() => countStatus('late'))
const leaveCount = computed(() => countStatus('leave'))

function countStatus(status) {
  return filteredTeachers.value.filter(teacher => attendanceData.value[teacher.id]?.status === status).length
}

function statusRowClass(status) {
  return {
    'row-present': status === 'present',
    'row-absent': status === 'absent',
    'row-late': status === 'late',
    'row-leave': status === 'leave',
  }
}

function isTimeDisabled(teacherId) {
  const status = attendanceData.value[teacherId]?.status
  return status === 'absent' || status === 'leave'
}

function classRowClass(status) {
  return {
    'row-present': status === 'completed',
    'row-absent': status === 'no_show',
    'row-leave': status === 'cancelled',
  }
}

const setAttendanceView = async (view) => {
  if (view === attendanceView.value) return
  attendanceView.value = view
  searchQuery.value = ''
  error.value = null
  if (view === 'class') {
    await loadClassLedger(true)
  } else {
    await loadTeachers()
  }
}

const refreshCurrentView = () => {
  if (attendanceView.value === 'class') {
    loadClassLedger(false)
  } else {
    loadTeachers(false)
  }
}

const applyClassLedgerData = (entries) => {
  classEntries.value = entries
  classLedgerData.value = {}
  classDirtyIds.value = new Set()
  entries.forEach(entry => {
    classLedgerData.value[entry.id] = {
      status: entry.status || 'scheduled',
      notes: entry.notes || '',
    }
  })
}

const markClassDirty = (ledgerId) => {
  classDirtyIds.value.add(ledgerId)
}

const loadClassLedger = async (background = false) => {
  if (!date.value) return
  if (!background) loading.value = true
  error.value = null
  try {
    const params = { date: date.value, sync: background ? 0 : 1 }
    if (filters.value.batch_id) params.batch_id = filters.value.batch_id
    if (filters.value.subject_id) params.subject_id = filters.value.subject_id
    const res = await attendanceService.getTeacherClassLedger(params)
    const payload = res.data?.data
    applyClassLedgerData(payload?.entries || [])
    classLoaded.value = true
  } catch (err) {
    if (!background) {
      classLoaded.value = false
      error.value = err.response?.data?.message || 'Failed to load class ledger'
    }
  } finally {
    if (!background) loading.value = false
  }
}

const saveClassLedger = async () => {
  const changed = classEntries.value.filter(e => classDirtyIds.value.has(e.id))
  if (changed.length === 0) {
    toast('info', 'No changes to save')
    return
  }

  savingClass.value = true
  error.value = null
  successMsg.value = null
  try {
    const records = changed.map(entry => ({
      ledger_id: entry.id,
      status: classLedgerData.value[entry.id]?.status || 'completed',
      notes: classLedgerData.value[entry.id]?.notes || '',
    }))
    await attendanceService.bulkMarkTeacherClassLedger({
      date: date.value,
      records,
    })
    successMsg.value = `Saved ${records.length} class record(s)`
    classDirtyIds.value = new Set()
    await loadClassLedger(true)
    setTimeout(() => { successMsg.value = null }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save class ledger'
    toast('error', error.value)
  } finally {
    savingClass.value = false
  }
}

const extractRoutineSubject = (routine) => {
  const id = routine.subject?.id || routine.subject_id
  if (!id) return null
  return {
    id,
    name: routine.subject?.name || routine.subject_name || 'Unknown',
  }
}

const loadFilterSubjects = async () => {
  loadingSubjects.value = true
  try {
    let routines = []

    if (filters.value.batch_id) {
      const res = await academicService.routines.byBatch(filters.value.batch_id, { status: 'published' })
      routines = res.data?.data || []
    } else {
      const res = await academicService.routines.list({ status: 'published', per_page: 500 })
      routines = res.data?.data?.data || res.data?.data || []
    }

    const subjectMap = new Map()
    routines.forEach((routine) => {
      if (routine.is_lunch_break || routine.is_off_day) return
      const subject = extractRoutineSubject(routine)
      if (subject && !subjectMap.has(subject.id)) {
        subjectMap.set(subject.id, subject)
      }
    })

    filterSubjects.value = Array.from(subjectMap.values()).sort((a, b) => a.name.localeCompare(b.name))

    if (filters.value.subject_id && !filterSubjects.value.some(s => s.id === filters.value.subject_id)) {
      filters.value.subject_id = ''
    }
  } catch {
    filterSubjects.value = []
  } finally {
    loadingSubjects.value = false
  }
}

const applyTeacherData = (data) => {
  teachers.value = data
  attendanceData.value = {}
  data.forEach(t => {
    attendanceData.value[t.id] = {
      status: t.status || 'present',
      check_in: t.check_in ? t.check_in.substring(0, 5) : (t.routine?.slot_time?.split(' - ')[0] || ''),
      check_out: t.check_out ? t.check_out.substring(0, 5) : (t.routine?.slot_time?.split(' - ')[1] || ''),
      remarks: t.remarks || '',
    }
  })
  clearDirty()
}

const loadTeachers = async (background = false) => {
  if (!date.value) return
  if (!background) loading.value = true
  error.value = null
  try {
    const params = { date: date.value, mode: 'office' }
    if (filters.value.batch_id) params.batch_id = filters.value.batch_id
    if (filters.value.subject_id) params.subject_id = filters.value.subject_id
    const res = await attendanceService.getTeachers(params)
    applyTeacherData(res.data?.data || [])
    teachersLoaded.value = true
  } catch (err) {
    if (!background) {
      teachersLoaded.value = false
      error.value = err.response?.data?.message || 'Failed to load teachers'
    }
  } finally {
    if (!background) loading.value = false
  }
}

const { dirtyIds, saving, markDirty, clearDirty, hasUnsavedChanges, refresh } = useAttendanceMarking(loadTeachers)

const onBatchChange = async () => {
  searchQuery.value = ''
  teachersLoaded.value = false
  classLoaded.value = false
  error.value = null
  await loadFilterSubjects()
  if (attendanceView.value === 'class') {
    await loadClassLedger()
  } else {
    await loadTeachers()
  }
}

const onSubjectChange = async () => {
  searchQuery.value = ''
  teachersLoaded.value = false
  classLoaded.value = false
  error.value = null
  if (attendanceView.value === 'class') {
    await loadClassLedger()
  } else {
    await loadTeachers()
  }
}

const markAll = (status) => {
  filteredTeachers.value.forEach(teacher => {
    if (attendanceData.value[teacher.id]) {
      attendanceData.value[teacher.id].status = status
      markDirty(teacher.id)
    }
  })
}

const saveAttendance = async () => {
  if (teachers.value.length === 0) {
    error.value = 'Load teachers before saving attendance'
    return
  }

  const changed = teachers.value.filter(t => dirtyIds.value.has(t.id))
  if (changed.length === 0) {
    toast('info', 'No changes to save')
    return
  }

  saving.value = true
  error.value = null
  successMsg.value = null
  try {
    const records = changed.map(teacher => ({
      teacher_id: teacher.id,
      status: attendanceData.value[teacher.id]?.status || 'present',
      check_in: attendanceData.value[teacher.id]?.check_in || null,
      check_out: attendanceData.value[teacher.id]?.check_out || null,
      remarks: attendanceData.value[teacher.id]?.remarks || '',
      subject_id: filters.value.subject_id || teacher.subject_id || teacher.routine?.subject_id || null,
    }))
    await attendanceService.bulkMarkTeacherAttendance({
      date: date.value,
      subject_id: filters.value.subject_id || undefined,
      records,
    })
    successMsg.value = `Saved ${records.length} teacher record(s)`
    clearDirty()
    await loadTeachers(true)
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
    const res = await enrollmentService.getBatches({ per_page: 100 })
    batches.value = res.data?.data?.data || res.data?.data || []
  } catch {}

  await loadFilterSubjects()
  await loadTeachers()
})

watch(date, async () => {
  if (!date.value || hasUnsavedChanges.value || hasClassUnsavedChanges.value) return
  if (attendanceView.value === 'class') {
    await loadClassLedger(true)
  } else {
    await loadTeachers()
  }
})
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; padding: 0 0.5rem; }
.mode-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.mode-tab { padding: 0.45rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 999px; background: var(--bg-surface-muted); color: var(--text-muted); font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.mode-tab.active { background: #4f46e5; border-color: #4f46e5; color: #fff; }
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
.form-control:disabled { background: #f3f4f6; color: var(--text-muted); cursor: not-allowed; }
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

.teacher-list { display: flex; flex-direction: column; }
.teacher-row {
  display: grid;
  grid-template-columns: 36px minmax(180px, 1.4fr) minmax(200px, 1.2fr) minmax(140px, 0.9fr) minmax(120px, 1fr);
  gap: 0.85rem 1rem;
  align-items: start;
  padding: 1rem;
  border-bottom: 1px solid var(--border-light);
}
.teacher-row:last-child { border-bottom: none; }
.teacher-index { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); padding-top: 0.15rem; }
.teacher-name { display: block; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 0.35rem; word-break: break-word; }
.teacher-meta { display: flex; flex-direction: column; gap: 0.2rem; }
.meta-item { font-size: 0.78rem; color: var(--text-muted); word-break: break-word; line-height: 1.35; }
.meta-item.slot { color: #4f46e5; font-weight: 500; }
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

.teacher-times { display: flex; flex-direction: column; gap: 0.5rem; }
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
  .teacher-row {
    grid-template-columns: 1fr;
    gap: 0.75rem;
    padding: 1rem 0.85rem;
  }
  .teacher-index { display: none; }
  .status-radio-group { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .teacher-times { flex-direction: row; gap: 0.75rem; }
  .time-field { flex: 1; }
}
</style>
