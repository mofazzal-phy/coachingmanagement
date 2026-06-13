<template>
  <div class="routine-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">📅 Class Routine Management</h1>
        <p class="page-subtitle">Create and manage weekly & daily class routines</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="refreshRoutine" :disabled="loading">
          🔄 Refresh
        </button>
        <button class="btn btn-success" @click="showGenerateWizard = true" :disabled="!filters.academic_session_id">
          ⚡ Generate
        </button>
        <button class="btn btn-primary" @click="openCreateModal" :disabled="!filters.academic_session_id">
          + Add Routine Entry
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row" v-if="filters.academic_session_id && filters.class_id">
      <div class="stat-card"><span class="stat-num">{{ routines.length }}</span><span class="stat-label">Total Slots</span></div>
      <div class="stat-card"><span class="stat-num">{{ uniqueSubjects }}</span><span class="stat-label">Subjects</span></div>
      <div class="stat-card"><span class="stat-num">{{ uniqueTeachers }}</span><span class="stat-label">Teachers</span></div>
      <div class="stat-card"><span class="stat-num">{{ todaySlotCount }}</span><span class="stat-label">Today's Classes</span></div>
    </div>

    <!-- Filters Section -->
    <div class="filters card">
      <div class="filter-row">
        <div class="form-group">
          <label>📚 Academic Session</label>
          <select v-model="filters.academic_session_id" class="form-control" @change="onSessionChange">
            <option value="">— Select Session —</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>🏫 Class</label>
          <select v-model="filters.class_id" class="form-control" @change="onClassChange">
            <option value="">— Select Class —</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ getClassDisplayName(c) }}</option>
          </select>
        </div>
        <div class="form-group" v-if="showGroupFilter">
          <label>👥 Group</label>
          <select v-model="filters.group_id" class="form-control" @change="loadRoutineGrid">
            <option value="">All Groups</option>
            <option v-for="g in allGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>📋 Section</label>
          <select v-model="filters.section_id" class="form-control" @change="loadRoutineGrid">
            <option value="">All Sections</option>
            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>🔄 Routine Type</label>
          <select v-model="filters.routine_type" class="form-control" @change="loadRoutineGrid">
            <option value="weekly">📆 Weekly Routine (Full Week)</option>
            <option value="daily">📅 Daily Routine (Every Day)</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state card">
      <div class="spinner"></div>
      <p>Loading routine data...</p>
    </div>

    <!-- No Selection State -->
    <div v-else-if="!filters.academic_session_id || !filters.class_id" class="empty-state card">
      <div class="empty-icon">📅</div>
      <h3>Select Session & Class</h3>
      <p>Please select an academic session and class to view the routine grid.</p>
    </div>

    <!-- Routine Grid -->
    <div v-else class="routine-grid-wrapper card">
      <div class="routine-grid-header">
        <div class="grid-title-section">
          <h3>
            <span class="grid-class-name">{{ selectedClassName }}</span>
            <span v-if="selectedClassTypeLabel" class="grid-type-tag" :class="'type-' + selectedClassType">{{ selectedClassTypeLabel }}</span>
            <span v-if="selectedSectionName" class="grid-section-badge">{{ selectedSectionName }}</span>
            <span v-if="selectedGroupName" class="grid-group-badge">{{ selectedGroupName }}</span>
            <span class="grid-routine-badge" :class="filters.routine_type === 'weekly' ? 'badge-weekly' : 'badge-daily'">
              {{ filters.routine_type === 'weekly' ? '📆 Weekly' : '📅 Daily' }}
            </span>
          </h3>
          <p class="grid-session-name">{{ selectedSessionName }}</p>
        </div>
        <div class="grid-stats">
          <span class="stat-item">
            <strong>{{ totalEntries }}</strong> entries
          </span>
          <span class="stat-divider">|</span>
          <span class="stat-item">
            <strong>{{ periods.length }}</strong> slots
          </span>
        </div>
      </div>

      <!-- No Periods Warning -->
      <div v-if="periods.length === 0" class="no-periods-warning">
        <div class="warning-icon">⚠️</div>
        <p>No time slots (periods) defined for this session.</p>
        <router-link to="/dashboard/academic/periods" class="btn btn-sm btn-primary">
          Create Periods First
        </router-link>
      </div>

      <!-- The Actual Grid -->
      <div v-else class="table-wrapper">
        <table class="routine-table">
          <thead>
            <tr>
              <th class="period-col">
                <div class="th-period-label">⏰ Time Slot</div>
              </th>
              <th v-for="day in days" :key="day" class="day-col">
                <div class="day-header">{{ dayLabels[day] }}</div>
                <div class="day-sub">{{ day.toUpperCase() }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="period in periods" :key="period.id" :class="{ 'break-row': period.is_break }">
              <td class="period-cell">
                <div class="period-name">{{ period.name }}</div>
                <div class="period-time">{{ formatTime(period.start_time) }} — {{ formatTime(period.end_time) }}</div>
                <span v-if="period.is_break" class="break-tag">☕ Break</span>
              </td>
              <td v-for="day in days" :key="day" class="routine-cell" @click="openCreateFromGrid(day, period)">
                <!-- Existing Entry -->
                <div v-if="getRoutine(day, period.id)" class="routine-entry" :class="{ 'routine-cancelled': getRoutineException(day, period.id), 'routine-inactive': getRoutine(day, period.id).status === 'inactive' }" :style="{ borderLeftColor: getSubjectColor(getRoutine(day, period.id).subject_id) }">
                  <button class="btn-delete-entry" @click.stop="confirmDeleteRoutine(getRoutine(day, period.id))" title="Delete entry">
                    <i class="fas fa-times"></i>
                  </button>
                  <div class="entry-subject">{{ getRoutine(day, period.id).subject?.name || '—' }}</div>
                  <div class="entry-teacher">
                    <i class="fas fa-chalkboard-teacher"></i>
                    {{ getRoutine(day, period.id).teacher?.first_name || '' }} {{ getRoutine(day, period.id).teacher?.last_name || '' }}
                  </div>
                  <div class="entry-meta">
                    <span v-if="getRoutine(day, period.id).room" class="meta-tag room-tag">
                      <i class="fas fa-door-open"></i> {{ getRoutine(day, period.id).room.name }}
                    </span>
                    <span v-if="getRoutine(day, period.id).section" class="meta-tag section-tag">
                      {{ getRoutine(day, period.id).section.name }}
                    </span>
                  </div>
                  <!-- Exception Badge -->
                  <div v-if="getRoutineException(day, period.id)" class="exception-badge" :class="'exception-' + getRoutineException(day, period.id).exception_type">
                    <span v-if="getRoutineException(day, period.id).exception_type === 'cancellation'">❌ Cancelled</span>
                    <span v-else-if="getRoutineException(day, period.id).exception_type === 'reschedule'">🔄 Rescheduled</span>
                  </div>
                </div>
                <!-- Empty Cell -->
                <div v-else-if="!period.is_break" class="empty-cell">
                  <div class="add-icon-wrap">
                    <i class="fas fa-plus-circle"></i>
                  </div>
                  <span class="add-text">Add Class</span>
                </div>
                <div v-else class="empty-cell break-cell">
                  <span class="break-text">—</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ========== CREATE/EDIT MODAL ========== -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal modal-routine">
        <div class="modal-header">
          <div class="modal-header-left">
            <h3>
              <i class="fas" :class="editingRoutine ? 'fa-edit' : 'fa-plus-circle'"></i>
              {{ editingRoutine ? 'Edit Routine Entry' : 'Add Routine Entry' }}
            </h3>
            <p class="modal-subtitle" v-if="!editingRoutine">
              Fill in the details to {{ form.routine_type === 'weekly' ? 'schedule a class for a specific day' : 'set a daily recurring class' }}
            </p>
          </div>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <!-- Row 1: Session & Type -->
          <div class="form-row">
            <div class="form-group">
              <label>📚 Academic Session <span class="required">*</span></label>
              <select v-model="form.academic_session_id" class="form-control" disabled>
                <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>🔄 Routine Type <span class="required">*</span></label>
              <select v-model="form.routine_type" class="form-control" :disabled="!!editingRoutine" @change="onFormRoutineTypeChange">
                <option value="weekly">📆 Weekly (Specific Day)</option>
                <option value="daily">📅 Daily (Every Day)</option>
              </select>
            </div>
          </div>

          <!-- Row 2: Class & Section -->
          <div class="form-row">
            <div class="form-group">
              <label>🏫 Class <span class="required">*</span></label>
              <select v-model="form.class_id" class="form-control" @change="onFormClassChange">
                <option value="">— Select Class —</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ getClassDisplayName(c) }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>📋 Section</label>
              <select v-model="form.section_id" class="form-control">
                <option value="">— Select Section —</option>
                <option v-for="s in formSections" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
          </div>

          <!-- Row 3: Group (conditional) -->
          <div class="form-row" v-if="showFormGroup">
            <div class="form-group">
              <label>👥 Group</label>
              <select v-model="form.group_id" class="form-control" @change="onFormGroupChange">
                <option value="">— Select Group —</option>
                <option v-for="g in formGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>📖 Subject <span class="required">*</span></label>
              <select v-model="form.subject_id" class="form-control" @change="onFormSubjectChange">
                <option value="">— Select Subject —</option>
                <option v-for="sub in formSubjects" :key="sub.id" :value="sub.id">{{ sub.name }} <span v-if="sub.code">({{ sub.code }})</span></option>
              </select>
            </div>
          </div>
          <div class="form-row" v-else>
            <div class="form-group">
              <label>📖 Subject <span class="required">*</span></label>
              <select v-model="form.subject_id" class="form-control" @change="onFormSubjectChange">
                <option value="">— Select Subject —</option>
                <option v-for="sub in formSubjects" :key="sub.id" :value="sub.id">{{ sub.name }} <span v-if="sub.code">({{ sub.code }})</span></option>
              </select>
            </div>
          </div>

          <!-- Row 3b: Teacher (always visible) -->
          <div class="form-row">
            <div class="form-group">
              <label>👨‍🏫 Teacher <span class="required">*</span></label>
              <select v-model="form.teacher_id" class="form-control">
                <option value="">— Select Teacher —</option>
                <option v-for="t in formTeachers" :key="t.id" :value="t.id">{{ t.first_name }} {{ t.last_name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>🚪 Room</label>
              <select v-model="form.room_id" class="form-control">
                <option value="">— Select Room —</option>
                <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }} <span v-if="r.code">({{ r.code }})</span></option>
              </select>
            </div>
          </div>

          <!-- Row 4: Day & Period -->
          <div class="form-row">
            <div class="form-group">
              <label>📅 Day of Week <span class="required" v-if="form.routine_type === 'weekly'">*</span></label>
              <select v-model="form.day_of_week" class="form-control" :disabled="form.routine_type === 'daily'">
                <option value="">— Select Day —</option>
                <option v-for="(label, key) in dayLabels" :key="key" :value="key">{{ label }}</option>
              </select>
              <small v-if="form.routine_type === 'daily'" class="form-help">
                <i class="fas fa-info-circle"></i> Day not needed for daily routine — applies to all days
              </small>
            </div>
            <div class="form-group">
              <label>⏰ Time Slot / Period <span class="required">*</span></label>
              <select v-model="form.period_id" class="form-control">
                <option value="">— Select Period —</option>
                <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.start_time }} - {{ p.end_time }})</option>
              </select>
            </div>
          </div>

          <!-- Row 5: Status -->
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" class="form-control">
                <option value="active">✅ Active</option>
                <option value="inactive">⛔ Inactive</option>
              </select>
            </div>
          </div>

          <!-- Validation Errors -->
          <div v-if="validationErrors.length > 0" class="validation-errors">
            <div v-for="(err, idx) in validationErrors" :key="idx" class="validation-error-item">
              <i class="fas fa-exclamation-circle"></i> {{ err }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">
            <i class="fas fa-times"></i> Cancel
          </button>
          <button class="btn btn-primary" @click="saveRoutine" :disabled="saving">
            <i class="fas fa-spinner fa-spin" v-if="saving"></i>
            <i class="fas" :class="saving ? 'fa-spinner fa-spin' : (editingRoutine ? 'fa-save' : 'fa-plus')"></i>
            {{ saving ? 'Saving...' : (editingRoutine ? 'Update Routine' : 'Create Routine') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========== DELETE CONFIRMATION ========== -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h3><i class="fas fa-trash-alt text-danger"></i> Delete Routine Entry</h3>
          <button class="btn-close" @click="showDeleteConfirm = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="delete-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Are you sure you want to delete this routine entry?</p>
          </div>
          <div v-if="deletingRoutine" class="delete-details">
            <div class="delete-detail-row">
              <span class="detail-label">Subject:</span>
              <span class="detail-value">{{ deletingRoutine.subject?.name || '—' }}</span>
            </div>
            <div class="delete-detail-row">
              <span class="detail-label">Day:</span>
              <span class="detail-value">{{ dayLabels[deletingRoutine.day_of_week] || 'Daily' }}</span>
            </div>
            <div class="delete-detail-row">
              <span class="detail-label">Period:</span>
              <span class="detail-value">{{ deletingRoutine.period?.name || '—' }}</span>
            </div>
            <div class="delete-detail-row">
              <span class="detail-label">Teacher:</span>
              <span class="detail-value">{{ deletingRoutine.teacher?.first_name || '' }} {{ deletingRoutine.teacher?.last_name || '' }}</span>
            </div>
          </div>
          <p class="text-danger"><i class="fas fa-ban"></i> This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">
            <i class="fas fa-times"></i> Cancel
          </button>
          <button class="btn btn-danger" @click="deleteRoutine" :disabled="saving">
            <i class="fas fa-spinner fa-spin" v-if="saving"></i>
            <i class="fas fa-trash-alt" v-else></i>
            {{ saving ? 'Deleting...' : 'Delete Entry' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <transition name="toast">
      <div v-if="toast.show" class="toast-notification" :class="'toast-' + toast.type">
        <i class="fas" :class="toastIcon"></i>
        <span>{{ toast.message }}</span>
        <button class="toast-close" @click="toast.show = false">&times;</button>
      </div>
    </transition>
  </div>

  <!-- Generate Wizard Modal -->
  <GenerateWizard :visible="showGenerateWizard" :sessions="sessions" @close="showGenerateWizard = false" @applied="onGenerateApplied" />
</template>

<script>
import academicService from '@/services/academic.service'
import teacherService from '@/services/teacher.service'
import { extractData } from '@/utils/api.utils'
import GenerateWizard from './GenerateWizard.vue'

export default {
  name: 'RoutineListPage',
  components: { GenerateWizard },
  data() {
    return {
      loading: false,
      saving: false,
      sessions: [],
      classes: [],
      sections: [],
      subjects: [],
      teachers: [],
      rooms: [],
      periods: [],
      allGroups: [],
      days: ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'],
      dayLabels: {
        sat: 'Saturday',
        sun: 'Sunday',
        mon: 'Monday',
        tue: 'Tuesday',
        wed: 'Wednesday',
        thu: 'Thursday',
        fri: 'Friday',
      },
      routineData: {},
      filters: {
        academic_session_id: '',
        class_id: '',
        section_id: '',
        group_id: '',
        routine_type: 'weekly',
      },
      showModal: false,
      editingRoutine: null,
      showDeleteConfirm: false,
      deletingRoutine: null,
      showGenerateWizard: false,
      form: {
        academic_session_id: '',
        class_id: '',
        section_id: '',
        group_id: '',
        subject_id: '',
        teacher_id: '',
        period_id: '',
        room_id: '',
        day_of_week: '',
        routine_type: 'weekly',
        status: 'active',
      },
      formSections: [],
      formPeriods: [],
      formGroups: [],
      formSubjects: [],
      formTeachers: [],
      validationErrors: [],
      toast: {
        show: false,
        message: '',
        type: 'success',
      },
      subjectColors: {},
    }
  },
  computed: {
    selectedClassName() {
      const c = this.classes.find(c => c.id === this.filters.class_id)
      return c ? this.getClassDisplayName(c) : '—'
    },
    selectedClassType() {
      const c = this.classes.find(c => c.id === this.filters.class_id)
      return c ? c.type : ''
    },
    selectedClassTypeLabel() {
      const labels = { boys: 'Boys', girls: 'Girls', common: 'Common' }
      return labels[this.selectedClassType] || ''
    },

    selectedSectionName() {
      if (!this.filters.section_id) return ''
      const s = this.sections.find(s => s.id === this.filters.section_id)
      return s ? s.name : ''
    },
    selectedGroupName() {
      if (!this.filters.group_id) return ''
      const g = this.allGroups.find(g => g.id === this.filters.group_id)
      return g ? g.name : ''
    },
    selectedSessionName() {
      const s = this.sessions.find(s => s.id === this.filters.academic_session_id)
      return s ? s.name : ''
    },
    showGroupFilter() {
      if (!this.filters.class_id) return false
      const cls = this.classes.find(c => c.id === this.filters.class_id)
      return cls && cls.numeric_value >= 9
    },
    showFormGroup() {
      if (!this.form.class_id) return false
      const cls = this.classes.find(c => c.id === this.form.class_id)
      return cls && cls.numeric_value >= 9
    },
    totalEntries() {
      let count = 0
      for (const day of this.days) {
        if (this.routineData[day]) {
          count += this.routineData[day].length
        }
      }
      return count
    },
    routines() {
      const all = []
      for (const day of this.days) {
        if (this.routineData[day]) {
          all.push(...this.routineData[day])
        }
      }
      return all
    },
    uniqueSubjects() {
      return new Set(this.routines.map(r => r.subject_id)).size
    },
    uniqueTeachers() {
      return new Set(this.routines.map(r => r.teacher_id)).size
    },
    todaySlotCount() {
      const today = new Date().toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase()
      const map = { sat: 'sat', sun: 'sun', mon: 'mon', tue: 'tue', wed: 'wed', thu: 'thu', fri: 'fri' }
      return (this.routineData[map[today]] || []).length
    },
    toastIcon() {
      const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle',
      }
      return icons[this.toast.type] || 'fa-info-circle'
    },
  },
  async mounted() {
    await this.loadInitialData()
  },
  methods: {
    // ========== UTILITY ==========
    uniqueById(arr) {
      if (!Array.isArray(arr)) return []
      const seen = new Set()
      return arr.filter(item => {
        const key = item.id
        if (seen.has(key)) return false
        seen.add(key)
        return true
      })
    },
    getClassDisplayName(c) {
      if (!c) return '—'
      const typeLabels = { boys: 'Boys', girls: 'Girls', common: 'Common' }
      const typeLabel = typeLabels[c.type]
      return typeLabel ? `${c.name} [${typeLabel}]` : c.name
    },
    formatTime(time) {
      if (!time) return '—'
      try {
        const [h, m] = time.split(':')
        const hour = parseInt(h)
        const ampm = hour >= 12 ? 'PM' : 'AM'
        const hour12 = hour % 12 || 12
        return `${hour12}:${m} ${ampm}`
      } catch {
        return time
      }
    },
    getSubjectColor(subjectId) {
      if (!subjectId) return '#4299e1'
      if (!this.subjectColors[subjectId]) {
        const colors = [
          '#4299e1', '#48bb78', '#ed8936', '#9f7aea',
          '#f56565', '#38b2ac', '#d69e2e', '#667eea',
          '#fc8181', '#68d391', '#f6ad55', '#b794f4',
          '#4fd1c5', '#f6e05e', '#a0aec0', '#e53e3e',
        ]
        this.subjectColors[subjectId] = colors[Object.keys(this.subjectColors).length % colors.length]
      }
      return this.subjectColors[subjectId]
    },
    showToast(message, type = 'success') {
      this.toast.message = message
      this.toast.type = type
      this.toast.show = true
      setTimeout(() => {
        this.toast.show = false
      }, 4000)
    },

    // ========== DATA LOADING ==========
    async loadInitialData() {
      try {
        const [sessionsRes, classesRes, roomsRes, teachersRes, groupsRes] = await Promise.all([
          academicService.sessions.list({ per_page: 50 }),
          academicService.classes.list({ per_page: 50 }),
          academicService.rooms.listAll(),
          teacherService.listAll({ per_page: 100 }),
          academicService.groups.listAll(),
        ])
        this.sessions = extractData(sessionsRes, [])
        this.classes = extractData(classesRes, [])
        this.rooms = extractData(roomsRes, [])
        this.teachers = extractData(teachersRes, [])
        this.allGroups = this.uniqueById(extractData(groupsRes, []))

        // Auto-select current session
        const current = this.sessions.find(s => s.is_current)
        if (current) {
          this.filters.academic_session_id = current.id
          await this.loadPeriods()
        }
      } catch (e) {
        console.error('Failed to load initial data:', e)
        this.showToast('Failed to load initial data', 'error')
      }
    },
    async onSessionChange() {
      this.filters.class_id = ''
      this.filters.section_id = ''
      this.filters.group_id = ''
      this.sections = []
      this.subjects = []
      this.periods = []
      this.routineData = {}
      if (this.filters.academic_session_id) {
        await this.loadPeriods()
      }
    },
    async onClassChange() {
      this.filters.section_id = ''
      this.filters.group_id = ''
      this.sections = []
      this.routineData = {}
      if (this.filters.class_id) {
        await Promise.all([
          this.loadSections(),
          this.loadSubjectsByClass(),
        ])
      }
      await this.loadRoutineGrid()
    },
    async loadSections() {
      if (!this.filters.class_id) return
      try {
        const res = await academicService.sections.byClass(this.filters.class_id)
        this.sections = extractData(res, [])
      } catch (e) {
        console.error('Failed to load sections:', e)
      }
    },
    async loadSubjectsByClass() {
      if (!this.filters.class_id) return
      try {
        const params = {}
        if (this.filters.group_id) {
          params.group_id = this.filters.group_id
        }
        const res = await academicService.subjects.byClass(this.filters.class_id, params)
        this.subjects = extractData(res, [])
      } catch (e) {
        console.error('Failed to load subjects:', e)
      }
    },
    async loadPeriods() {
      if (!this.filters.academic_session_id) return
      try {
        const res = await academicService.periods.list({
          academic_session_id: this.filters.academic_session_id,
          status: 'active',
          per_page: 50,
        })
        this.periods = extractData(res, [])
      } catch (e) {
        console.error('Failed to load periods:', e)
      }
    },
    async loadRoutineGrid() {
      if (!this.filters.academic_session_id || !this.filters.class_id) return
      this.loading = true
      try {
        const params = {
          academic_session_id: this.filters.academic_session_id,
          class_id: this.filters.class_id,
          routine_type: this.filters.routine_type,
        }
        if (this.filters.section_id) {
          params.section_id = this.filters.section_id
        }
        if (this.filters.group_id) {
          params.group_id = this.filters.group_id
        }
        const res = await academicService.routines.byClass(params)
        const data = extractData(res, {})
        this.routineData = data.weekly_routine || {}
        if (data.periods) {
          this.periods = data.periods
        }
      } catch (e) {
        console.error('Failed to load routine:', e)
        this.showToast('Failed to load routine', 'error')
      } finally {
        this.loading = false
      }
    },
    refreshRoutine() {
      this.loadRoutineGrid()
    },
    onGenerateApplied() {
      this.loadRoutineGrid()
      this.showToast('Routine generated and saved!', 'success')
    },
    getRoutine(day, periodId) {
      if (!this.routineData[day]) return null
      return this.routineData[day].find(r => r.period_id === periodId)
    },
    getRoutineException(day, periodId) {
      const routine = this.getRoutine(day, periodId)
      if (!routine || !routine.exceptions || routine.exceptions.length === 0) return null
      // Return the first active exception (cancellation or reschedule)
      return routine.exceptions.find(e => e.status === 'active')
    },

    // ========== MODAL OPEN/CLOSE ==========
    async openCreateModal() {
      this.editingRoutine = null
      this.validationErrors = []
      this.form = {
        academic_session_id: this.filters.academic_session_id || '',
        class_id: this.filters.class_id || '',
        section_id: this.filters.section_id || '',
        group_id: this.filters.group_id || '',
        subject_id: '',
        teacher_id: '',
        period_id: '',
        room_id: '',
        day_of_week: '',
        routine_type: this.filters.routine_type || 'weekly',
        status: 'active',
      }
      await this.prepareFormData()
      this.showModal = true
    },
    async openCreateFromGrid(day, period) {
      // Don't open modal for break periods
      if (period.is_break) return

      // If there's already a routine, edit it instead
      const existing = this.getRoutine(day, period.id)
      if (existing) {
        this.openEditModal(existing)
        return
      }

      this.editingRoutine = null
      this.validationErrors = []
      this.form = {
        academic_session_id: this.filters.academic_session_id || '',
        class_id: this.filters.class_id || '',
        section_id: this.filters.section_id || '',
        group_id: this.filters.group_id || '',
        subject_id: '',
        teacher_id: '',
        period_id: period.id,
        room_id: '',
        day_of_week: day,
        routine_type: this.filters.routine_type || 'weekly',
        status: 'active',
      }
      await this.prepareFormData()
      this.showModal = true
    },
    async openEditModal(routine) {
      this.editingRoutine = routine
      this.validationErrors = []
      this.form = {
        academic_session_id: routine.academic_session_id,
        class_id: routine.class_id,
        section_id: routine.section_id || '',
        group_id: routine.group_id || '',
        subject_id: routine.subject_id,
        teacher_id: routine.teacher_id,
        period_id: routine.period_id,
        room_id: routine.room_id || '',
        day_of_week: routine.day_of_week,
        routine_type: routine.routine_type || 'weekly',
        status: routine.status || 'active',
      }
      await this.prepareFormData()
      this.showModal = true
    },
    async prepareFormData() {
      // Copy filter data as fallback
      this.formSections = [...this.sections]
      // Filter out break periods from the form dropdown
      this.formPeriods = this.periods.filter(p => !p.is_break)
      this.formGroups = [...this.allGroups]
      this.formSubjects = [...this.subjects]

      // Load fresh data for the form's class
      if (this.form.class_id) {
        await this.loadFormClassData(this.form.class_id)
      }
    },
    closeModal() {
      this.showModal = false
      this.editingRoutine = null
      this.validationErrors = []
    },

    // ========== FORM HANDLERS ==========
    async onFormClassChange() {
      this.form.section_id = ''
      this.form.group_id = ''
      this.form.subject_id = ''
      if (this.form.class_id) {
        await this.loadFormClassData(this.form.class_id)
      } else {
        this.formSections = []
        this.formSubjects = []
      }
    },
    async loadFormClassData(classId) {
      try {
        const [sectionsRes, subjectsRes] = await Promise.all([
          academicService.sections.byClass(classId),
          academicService.subjects.byClass(classId),
        ])
        this.formSections = extractData(sectionsRes, [])
        this.formSubjects = extractData(subjectsRes, [])
      } catch (e) {
        console.error('Failed to load form class data:', e)
        this.formSections = []
        this.formSubjects = []
      }
    },
    onFormRoutineTypeChange() {
      if (this.form.routine_type === 'daily') {
        this.form.day_of_week = ''
      }
    },
    async onFormGroupChange() {
      this.form.subject_id = ''
      if (this.form.class_id && this.form.group_id) {
        try {
          const res = await academicService.subjects.byClass(this.form.class_id, { group_id: this.form.group_id })
          this.formSubjects = extractData(res, [])
        } catch (e) {
          console.error('Failed to load subjects by group:', e)
          this.formSubjects = []
        }
      } else if (this.form.class_id) {
        try {
          const res = await academicService.subjects.byClass(this.form.class_id)
          this.formSubjects = extractData(res, [])
        } catch (e) {
          console.error('Failed to load subjects:', e)
          this.formSubjects = []
        }
      }
    },
    async onFormSubjectChange() {
      this.form.teacher_id = ''
      if (this.form.subject_id) {
        try {
          const res = await teacherService.bySubject(this.form.subject_id)
          this.formTeachers = extractData(res, [])
        } catch (e) {
          console.error('Failed to load teachers by subject:', e)
          this.formTeachers = []
        }
      } else {
        this.formTeachers = []
      }
    },

    // ========== SAVE / DELETE ==========
    validateForm() {
      this.validationErrors = []
      if (!this.form.class_id) this.validationErrors.push('Class is required')
      if (!this.form.subject_id) this.validationErrors.push('Subject is required')
      if (!this.form.teacher_id) this.validationErrors.push('Teacher is required')
      if (!this.form.period_id) this.validationErrors.push('Time slot / Period is required')
      if (this.form.routine_type === 'weekly' && !this.form.day_of_week) {
        this.validationErrors.push('Day of week is required for weekly routine')
      }
      return this.validationErrors.length === 0
    },
    async saveRoutine() {
      if (!this.validateForm()) return

      this.saving = true
      try {
        const payload = { ...this.form }
        payload.section_id = payload.section_id || null
        payload.room_id = payload.room_id || null
        payload.group_id = payload.group_id || null
        if (payload.routine_type === 'daily') {
          payload.day_of_week = null
        }

        if (this.editingRoutine) {
          await academicService.routines.update(this.editingRoutine.id, payload)
          this.showToast('✅ Routine updated successfully')
        } else {
          await academicService.routines.create(payload)
          this.showToast('✅ Routine created successfully')
        }
        this.closeModal()
        await this.loadRoutineGrid()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save routine'
        this.showToast(msg, 'error')
      } finally {
        this.saving = false
      }
    },
    confirmDeleteRoutine(routine) {
      this.deletingRoutine = routine
      this.showDeleteConfirm = true
    },
    async deleteRoutine() {
      if (!this.deletingRoutine) return
      this.saving = true
      try {
        await academicService.routines.delete(this.deletingRoutine.id)
        this.showToast('🗑️ Routine deleted successfully')
        this.showDeleteConfirm = false
        this.deletingRoutine = null
        await this.loadRoutineGrid()
      } catch (e) {
        const msg = e.response?.data?.message || 'Failed to delete routine'
        this.showToast(msg, 'error')
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
/* ========== PAGE LAYOUT ========== */
.routine-page {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}
.header-left {
  flex: 1;
}
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 4px;
}
.page-subtitle {
  font-size: 13px;
  color: #718096;
  margin: 0;
}
.header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

/* ========== FILTERS ========== */
.filters {
  margin-bottom: 24px;
  padding: 20px;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.filter-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.filter-row .form-group {
  flex: 1;
  min-width: 180px;
}
.filter-row label {
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  margin-bottom: 6px;
  display: block;
}

/* ========== LOADING & EMPTY STATES ========== */
.loading-state {
  text-align: center;
  padding: 60px 20px;
}
.loading-state p {
  color: #718096;
  margin-top: 12px;
}
.empty-state {
  text-align: center;
  padding: 60px 20px;
}
.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}
.empty-state h3 {
  font-size: 18px;
  color: #2d3748;
  margin: 0 0 8px;
}
.empty-state p {
  color: #718096;
  margin: 0;
}

/* ========== ROUTINE GRID ========== */
.routine-grid-wrapper {
  overflow: hidden;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.routine-grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 12px;
}
.grid-title-section h3 {
  margin: 0;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.grid-class-name {
  font-weight: 700;
  color: #2d3748;
}
.grid-type-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}
.type-boys {
  background: #bee3f8;
  color: #2b6cb0;
}
.type-girls {
  background: #fbb6ce;
  color: #9b2c2c;
}
.type-common {
  background: #e2e8f0;
  color: #4a5568;
}
.grid-section-badge {

  display: inline-block;
  background: #fefcbf;
  color: #975a16;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}
.grid-group-badge {
  display: inline-block;
  background: #e9d8fd;
  color: #6b46c1;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}
.grid-routine-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}
.badge-weekly {
  background: #bee3f8;
  color: #2b6cb0;
}
.badge-daily {
  background: #c6f6d5;
  color: #276749;
}
.grid-session-name {
  font-size: 12px;
  color: #718096;
  margin: 4px 0 0;
}
.grid-stats {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #718096;
}
.stat-item strong {
  color: #2d3748;
}
.stat-divider {
  color: #e2e8f0;
}

/* No Periods Warning */
.no-periods-warning {
  text-align: center;
  padding: 40px 20px;
}
.warning-icon {
  font-size: 36px;
  margin-bottom: 12px;
}
.no-periods-warning p {
  color: #718096;
  margin: 0 0 16px;
}

/* ========== TABLE ========== */
.table-wrapper {
  overflow-x: auto;
}
.routine-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}
.routine-table th,
.routine-table td {
  border: 1px solid var(--border-color);
  text-align: center;
  padding: 6px;
}
.routine-table th {
  background: #f7fafc;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #4a5568;
  padding: 10px 6px;
  position: sticky;
  top: 0;
  z-index: 2;
}
.routine-table .period-col {
  min-width: 150px;
  width: 150px;
}
.routine-table .day-col {
  min-width: 130px;
}
.th-period-label {
  font-size: 11px;
}
.day-header {
  font-size: 13px;
  font-weight: 700;
}
.day-sub {
  font-size: 10px;
  color: #a0aec0;
  text-transform: lowercase;
  font-weight: 400;
}

/* Period Cell */
.period-cell {
  background: #f7fafc;
  text-align: left;
  padding: 8px 12px !important;
  vertical-align: middle;
}
.period-name {
  font-weight: 600;
  font-size: 13px;
  color: #2d3748;
}
.period-time {
  font-size: 11px;
  color: #718096;
  font-family: monospace;
  margin-top: 2px;
}
.break-tag {
  display: inline-block;
  background: #fefcbf;
  color: #975a16;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  margin-top: 4px;
}
.break-row .period-cell {
  background: #fffbeb;
}
.break-row .period-name {
  color: #d69e2e;
}

/* Routine Cell */
.routine-cell {
  position: relative;
  vertical-align: top;
  cursor: pointer;
  min-height: 65px;
  padding: 4px !important;
  transition: background 0.15s;
}
.routine-cell:hover {
  background: #ebf8ff;
}

/* Routine Entry Card */
.routine-entry {
  position: relative;
  background: #ebf8ff;
  border: 1px solid #bee3f8;
  border-left: 4px solid #4299e1;
  border-radius: 6px;
  padding: 8px 10px;
  text-align: left;
  min-height: 55px;
  transition: all 0.15s;
}
.routine-entry:hover {
  background: #d6edff;
  box-shadow: 0 2px 6px rgba(66,153,225,0.15);
}
.entry-subject {
  font-weight: 700;
  font-size: 13px;
  color: #2b6cb0;
  margin-bottom: 3px;
  padding-right: 20px;
}
.entry-teacher {
  font-size: 11px;
  color: #4a5568;
  margin-bottom: 4px;
}
.entry-teacher i {
  font-size: 10px;
  margin-right: 3px;
  color: #718096;
}
.entry-meta {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 500;
}
.room-tag {
  background: #e2e8f0;
  color: #4a5568;
}
.room-tag i {
  font-size: 9px;
}
.section-tag {
  background: #fefcbf;
  color: #975a16;
}

/* Exception Badge */
.exception-badge {
  position: absolute;
  bottom: 4px;
  right: 4px;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.4;
}
.exception-cancellation {
  background: #fed7d7;
  color: #c53030;
}
.exception-reschedule {
  background: #fefcbf;
  color: #975a16;
}

/* Cancelled/Inactive Routine Entry */
.routine-cancelled {
  opacity: 0.65;
  background: #fff5f5 !important;
  border-color: #fed7d7 !important;
}
.routine-cancelled .entry-subject {
  color: #9b2c2c;
}
.routine-cancelled:hover {
  background: #ffe0e0 !important;
}
.routine-inactive {
  opacity: 0.6;
}

/* Delete Button */
.btn-delete-entry {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  background: rgba(229,62,62,0.1);
  border: none;
  border-radius: 50%;
  color: #e53e3e;
  font-size: 11px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  opacity: 0;
  transition: all 0.15s;
}
.routine-entry:hover .btn-delete-entry {
  opacity: 1;
}
.btn-delete-entry:hover {
  background: #e53e3e;
  color: white;
}

/* Empty Cell */
.empty-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 55px;
  color: #a0aec0;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.15s;
  padding: 8px;
}
.empty-cell:hover {
  background: #edf2f7;
  color: #4a5568;
}
.add-icon-wrap {
  font-size: 22px;
  line-height: 1;
  margin-bottom: 2px;
  color: #a0aec0;
  transition: color 0.15s;
}
.empty-cell:hover .add-icon-wrap {
  color: #4299e1;
}
.add-text {
  font-size: 10px;
  font-weight: 500;
}
.break-cell {
  cursor: default;
}
.break-cell:hover {
  background: transparent;
  color: #a0aec0;
}
.break-text {
  font-size: 14px;
  color: #d2d6dc;
}

/* ========== MODAL ========== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}
.modal {
  background: var(--bg-card);
  border-radius: 16px;
  width: 680px;
  max-width: 92vw;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-sm {
  width: 440px;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border-color);
}
.modal-header-left h3 {
  margin: 0;
  font-size: 18px;
  color: #1a202c;
}
.modal-header-left h3 i {
  margin-right: 8px;
  color: #4299e1;
}
.modal-subtitle {
  font-size: 12px;
  color: #718096;
  margin: 4px 0 0;
}
.btn-close {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #718096;
  line-height: 1;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  transition: all 0.15s;
}
.btn-close:hover {
  background: #f7fafc;
  color: #2d3748;
}
.modal-body {
  padding: 24px;
}
.modal-body .form-group {
  margin-bottom: 16px;
}
.form-row {
  display: flex;
  gap: 16px;
}
.form-row .form-group {
  flex: 1;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  background: #fafafa;
  border-radius: 0 0 16px 16px;
}
.form-help {
  display: block;
  font-size: 11px;
  color: #718096;
  margin-top: 4px;
}
.form-help i {
  margin-right: 3px;
}

/* Validation Errors */
.validation-errors {
  margin-top: 16px;
  padding: 12px 16px;
  background: #fff5f5;
  border: 1px solid #fed7d7;
  border-radius: 8px;
}
.validation-error-item {
  font-size: 13px;
  color: #c53030;
  padding: 2px 0;
}
.validation-error-item i {
  margin-right: 6px;
}

/* ========== DELETE CONFIRMATION ========== */
.delete-warning {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  background: #fff5f5;
  border: 1px solid #fed7d7;
  border-radius: 8px;
  margin-bottom: 16px;
}
.delete-warning i {
  font-size: 20px;
  color: #e53e3e;
  flex-shrink: 0;
  margin-top: 2px;
}
.delete-warning p {
  margin: 0;
  font-size: 14px;
  color: #c53030;
  font-weight: 500;
}
.delete-details {
  background: #f7fafc;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 12px;
}
.delete-detail-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 13px;
}
.detail-label {
  color: #718096;
  font-weight: 500;
}
.detail-value {
  color: #2d3748;
  font-weight: 600;
}
.text-danger {
  color: #e53e3e;
  font-size: 12px;
  margin: 0;
}
.text-danger i {
  margin-right: 4px;
}

/* ========== TOAST NOTIFICATION ========== */
.toast-notification {
  position: fixed;
  top: 24px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
  z-index: 9999;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
  min-width: 300px;
  max-width: 450px;
}
.toast-success {
  background: #c6f6d5;
  color: #276749;
  border: 1px solid #9ae6b4;
}
.toast-error {
  background: #fed7d7;
  color: #c53030;
  border: 1px solid #feb2b2;
}
.toast-warning {
  background: #fefcbf;
  color: #975a16;
  border: 1px solid #f6e05e;
}
.toast-info {
  background: #bee3f8;
  color: #2b6cb0;
  border: 1px solid #90cdf4;
}
.toast-notification i {
  font-size: 18px;
}
.toast-notification span {
  flex: 1;
}
.toast-close {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: inherit;
  opacity: 0.6;
  padding: 0;
  line-height: 1;
}
.toast-close:hover {
  opacity: 1;
}

/* Toast Transition */
.toast-enter-active {
  animation: toastIn 0.3s ease-out;
}
.toast-leave-active {
  animation: toastOut 0.3s ease-in;
}
@keyframes toastIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
@keyframes toastOut {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(100%); opacity: 0; }
}

/* ========== COMMON ========== */
.text-center { text-align: center; }
.py-5 { padding: 40px 0; }
.py-4 { padding: 30px 0; }
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #4299e1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Buttons */
.btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-primary {
  background: #4299e1;
  color: white;
  border-color: #4299e1;
}
.btn-primary:hover:not(:disabled) {
  background: #3182ce;
}
.btn-secondary {
  background: #edf2f7;
  color: #4a5568;
  border-color: #e2e8f0;
}
.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}
.btn-danger {
  background: #e53e3e;
  color: white;
  border-color: #e53e3e;
}
.btn-danger:hover:not(:disabled) {
  background: #c53030;
}
.btn-sm {
  padding: 4px 10px;
  font-size: 12px;
}
.btn-outline {
  background: var(--bg-card);
  color: #4a5568;
  border-color: #d2d6dc;
}
.btn-outline:hover:not(:disabled) {
  background: #f7fafc;
}

/* Form Controls */
.card {
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  margin-bottom: 6px;
}
.required {
  color: #e53e3e;
}
.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d2d6dc;
  border-radius: 8px;
  font-size: 14px;
  background: var(--bg-card);
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.form-control:focus {
  outline: none;
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66,153,225,0.15);
}
.form-control:disabled {
  background: #f7fafc;
  color: #a0aec0;
  cursor: not-allowed;
}
select.form-control {
  appearance: auto;
}

/* Scrollbar */
.table-wrapper::-webkit-scrollbar {
  height: 8px;
}
.table-wrapper::-webkit-scrollbar-track {
  background: #f7fafc;
}
.table-wrapper::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 4px;
}
.table-wrapper::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}

/* Stats Row */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
.stat-card { background: var(--bg-card); border-radius: 10px; padding: 1rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.stat-num { display: block; font-size: 1.4rem; font-weight: 700; color: var(--text-primary); }
.stat-label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }
@media (max-width: 768px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
</style>
