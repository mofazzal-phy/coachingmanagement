<template>
  <div class="page-container">
    <!-- ===== COMPACT PAGE HEADER ===== -->
    <div class="page-header">
      <div class="header-left">
        <h1>Class Routine</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline btn-sm" @click="showGenerateWizard = true">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          Auto
        </button>
        <button class="btn btn-outline btn-sm" @click="showManualWizard = true">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          Manual
        </button>
        <label class="multi-batch-toggle">
          <input type="checkbox" v-model="multiBatchEnabled" @change="onMultiBatchToggle" />
          <span>Multi</span>
        </label>
      </div>
    </div>

    <!-- ===== PRINT-ONLY AREA ===== -->
    <div class="print-area">
      <!-- Print Header -->
      <!-- <div class="print-header">
        <h1 class="print-title">📅 Class Routine</h1>
        <p class="print-subtitle">
          {{ isMultiBatch ? 'Multi-Batch View' : ((filters.level ? filters.level.charAt(0).toUpperCase() + filters.level.slice(1) : 'All Levels') + (selectedLevelName ? ' • ' + selectedLevelName : '')) }}
          {{ filters.day ? ' • Day: ' + filters.day : '' }}
        </p>
      </div> -->

      <!-- ===== LOADING / ERROR / EMPTY STATES ===== -->
      <div v-if="store.loading && !hasRoutines" class="loading-state">
        <div class="spinner"></div>
        <p>Loading routines...</p>
      </div>
      <div v-else-if="store.error && !hasRoutines" class="error-state">
        <p>⚠️ {{ store.error }}</p>
        <button class="btn btn-outline" @click="onFilterChange">Try Again</button>
      </div>
      <div v-else-if="!hasRoutines" class="empty-state">
        <div class="empty-icon">📅</div>
        <h3>No Routines Found</h3>
        <p>Get started by creating your first class slot or auto-generating a routine.</p>
        <div style="display:flex; gap: 0.75rem; justify-content: center;">
          <button class="btn btn-primary" @click="showGenerateWizard = true">Auto Generate</button>
          <button class="btn btn-outline" @click="openCreateModal()">Add Manually</button>
        </div>
      </div>

      <!-- ===== SWAP MODE BANNER ===== -->
      <div v-if="store.swapMode && hasRoutines" class="swap-banner">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Swap mode is active. Drag and drop a slot onto another slot to swap them, or click two slots sequentially.</span>
        <button class="btn btn-sm btn-outline" @click="store.swapMode = false">Exit Swap Mode</button>
      </div>

      <!-- ===== ENTERPRISE ROUTINE GRID (Multi-Batch) ===== -->
      <div v-if="store.multiBatchGrid">
        <EnterpriseRoutineGrid
          :gridData="store.multiBatchGrid"
          :selectedBatches="selectedBatchesList"
          :swapMode="store.swapMode"
          :loading="store.loading"
          :coachingName="coachingName"
          :metaInfo="gridMetaInfo"
          :batchStudentCounts="batchStudentCounts"
          :batches="batches"
          :selectedBatchIds="selectedBatchIds"
          :filters="filters"
          :hasSelection="hasSelection"
          :hasRoutines="hasRoutines"
          :multiBatchEnabled="multiBatchEnabled"
          :store="store"
          @edit-slot="openEditModal"
          @refresh="refreshMultiBatchGrid"
          @swap-slots="handleSwap"
          @routine-created="onRoutineCreated"
          @toggle-batch="toggleBatchSelection"
          @publish-all="publishAll"
          @archive-all="archiveAll"
          @check-conflicts="checkConflicts"
          @set-lunch-break="showLunchBreakModal = true"
          @set-off-day="showOffDayModal = true"
          @toggle-swap="store.swapMode = !store.swapMode"
          @export-pdf="exportPDF"
          @filter-change="onFilterChange"
          @reset-filters="resetFilters"
        />
      </div>

      <!-- ===== LEGACY WEEKLY GRID (Single Level) ===== -->
      <div v-else-if="store.routines.length">
        <WeeklyGrid
          :grid="store.weeklyGrid"
          :weekDates="store.weekDates"
          :swapMode="store.swapMode"
          @slot-click="openEditModal"
          @swap="handleSwap"
        />
      </div>
    </div>

    <!-- ===== CONFLICT PANEL ===== -->
    <div v-if="conflicts.length" class="conflict-panel">
      <div class="conflict-header">
        <div class="conflict-title">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <strong>{{ conflicts.length }} Conflict{{ conflicts.length > 1 ? 's' : '' }} Detected</strong>
        </div>
        <button class="conflict-close" @click="conflicts = []">✕</button>
      </div>
      <div class="conflict-list">
        <div v-for="(conflict, idx) in conflicts" :key="idx" class="conflict-item" :class="'severity-' + (conflict.severity || 'hard')">
          <span class="conflict-type-badge" :class="conflict.type || 'hard'">{{ (conflict.type || 'HARD').toUpperCase() }}</span>
          <span class="conflict-message">{{ conflict.message }}</span>
        </div>
      </div>
    </div>

    <!-- ===== LUNCH BREAK MODAL ===== -->
    <div v-if="showLunchBreakModal" class="modal-overlay" @click.self="showLunchBreakModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>🍽 Set Lunch Break</h3>
          <button class="modal-close" @click="showLunchBreakModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Day</label>
            <select v-model="lunchBreakForm.day" class="form-control">
              <option value="">Select Day</option>
              <option value="sat">Saturday</option>
              <option value="sun">Sunday</option>
              <option value="mon">Monday</option>
              <option value="tue">Tuesday</option>
              <option value="wed">Wednesday</option>
              <option value="thu">Thursday</option>
              <option value="fri">Friday</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Time</label>
              <input type="time" v-model="lunchBreakForm.start_time" class="form-control" />
            </div>
            <div class="form-group">
              <label>End Time</label>
              <input type="time" v-model="lunchBreakForm.end_time" class="form-control" />
            </div>
          </div>
          <p v-if="lunchBreakError" class="form-error">{{ lunchBreakError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showLunchBreakModal = false">Cancel</button>
          <button class="btn btn-primary" :disabled="lunchBreakSaving" @click="handleSetLunchBreak">
            {{ lunchBreakSaving ? 'Saving...' : 'Set Lunch Break' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ===== OFF DAY MODAL ===== -->
    <div v-if="showOffDayModal" class="modal-overlay" @click.self="showOffDayModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>🚫 Set Off Day</h3>
          <button class="modal-close" @click="showOffDayModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Date</label>
            <input type="date" v-model="offDayForm.date" class="form-control" />
          </div>
          <div class="form-group">
            <label>Reason (optional)</label>
            <input type="text" v-model="offDayForm.reason" class="form-control" placeholder="e.g. Public Holiday" />
          </div>
          <p v-if="offDayError" class="form-error">{{ offDayError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showOffDayModal = false">Cancel</button>
          <button class="btn btn-primary" :disabled="offDaySaving" @click="handleSetOffDay">
            {{ offDaySaving ? 'Saving...' : 'Set Off Day' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ===== EDIT SLOT MODAL ===== -->
    <EditSlotModal
      v-model="showEditModal"
      :slotData="selectedSlot"
      :subjects="subjects"
      :teachers="teachers"
      :rooms="rooms"
      :groups="groups"
      :batches="batches"
      :saving="saving"
      :error="formError"
      @save="handleSave"
      @delete="handleDelete"
    />

    <!-- ===== GENERATE WIZARD ===== -->
    <GenerateWizard
      v-model="showGenerateWizard"
      :batches="batches"
      :courses="courses"
      :classes="classes"
      :subjects="subjects"
      :multiBatchEnabled="multiBatchEnabled"
      :selectedBatchIds="selectedBatchIds"
      :generating="generating"
      :generationError="generationError"
      :generatedRoutines="generatedRoutines"
      @generate="handleGenerate"
      @apply="handleApplyGeneration"
    />

    <!-- ===== MANUAL SLOT WIZARD ===== -->
    <ManualSlotWizard
      v-model="showManualWizard"
      :rooms="rooms"
      :groups="groups"
      :saving="manualSaving"
      :error="manualError"
      @save="handleManualSave"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useClassRoutineStore } from '@/stores/class-routine.store'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import teacherService from '@/services/teacher.service'
import WeeklyGrid from '@/components/routine/WeeklyGrid.vue'
import EnterpriseRoutineGrid from '@/components/routine/EnterpriseRoutineGrid.vue'
import EditSlotModal from '@/components/routine/EditSlotModal.vue'
import GenerateWizard from '@/components/routine/GenerateWizard.vue'
import ManualSlotWizard from '@/components/routine/ManualSlotWizard.vue'

const store = useClassRoutineStore()

// ===== Data Lists =====
const batches = ref([])
const courses = ref([])
const classes = ref([])
const subjects = ref([])
const teachers = ref([])
const rooms = ref([])
const groups = ref([])

// ===== Multi-Batch State =====
const multiBatchEnabled = ref(false)
const selectedBatchIds = ref([])

// ===== Filter Visibility =====
const showFilters = ref(false)

// ===== Filters =====
const filters = reactive({
  level: '',
  level_id: '',
  day: '',
  status: 'published', // Default: show only published routines
  teacher_id: '',
  room_id: '',
  subject_id: '',
})

// ===== Modal State =====
const showEditModal = ref(false)
const showGenerateWizard = ref(false)
const showManualWizard = ref(false)
const showLunchBreakModal = ref(false)
const showOffDayModal = ref(false)
const selectedSlot = ref(null)
const saving = ref(false)
const formError = ref('')
const manualSaving = ref(false)
const manualError = ref('')
const lunchBreakSaving = ref(false)
const lunchBreakError = ref('')
const offDaySaving = ref(false)
const offDayError = ref('')

// ===== Lunch Break Form =====
const lunchBreakForm = reactive({
  day: '',
  start_time: '12:00',
  end_time: '13:00',
})

// ===== Off Day Form =====
const offDayForm = reactive({
  date: new Date().toISOString().split('T')[0],
  reason: '',
})

// ===== Generation State =====
const generating = ref(false)
const generationError = ref('')
const generatedRoutines = ref([])

// ===== Conflicts =====
const conflicts = ref([])

// ===== View Modes =====
const viewModes = [
  { key: 'grid', label: 'Grid', icon: '&#9776;' },
  { key: 'teacher', label: 'Teacher', icon: '&#128100;' },
  { key: 'room', label: 'Room', icon: '&#127968;' },
  { key: 'batch', label: 'Batch', icon: '&#128203;' },
]

// ===== Computed =====
const isMultiBatch = computed(() => multiBatchEnabled.value && selectedBatchIds.value.length > 0)

const hasSelection = computed(() => {
  if (isMultiBatch.value) return true
  return !!(filters.level && filters.level_id)
})

const hasRoutines = computed(() => {
  if (isMultiBatch.value) return !!store.multiBatchGrid
  return store.routines.length > 0
})

const selectedBatchesList = computed(() => {
  return batches.value.filter(b => selectedBatchIds.value.includes(b.id))
})

const levelItems = computed(() => {
  if (filters.level === 'batch') return batches.value
  if (filters.level === 'course') return courses.value
  if (filters.level === 'class') return classes.value
  return []
})

const selectedLevelName = computed(() => {
  if (!filters.level || !filters.level_id) return ''
  const item = levelItems.value.find(i => i.id === filters.level_id)
  return item ? (item.name || item.title || '') : ''
})

// ===== Active Filter Count =====
const hasActiveFilters = computed(() => {
  return !!(
    filters.day ||
    filters.status ||
    filters.teacher_id ||
    filters.room_id ||
    filters.subject_id ||
    filters.level ||
    filters.level_id
  )
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.day) count++
  if (filters.status) count++
  if (filters.teacher_id) count++
  if (filters.room_id) count++
  if (filters.subject_id) count++
  if (filters.level) count++
  if (filters.level_id) count++
  return count
})

// ===== Grid Meta Info for EnterpriseRoutineGrid =====
const coachingName = computed(() => {
  return window?.appConfig?.coachingName || 'Coaching Center'
})

const gridMetaInfo = computed(() => {
  if (!isMultiBatch.value || !selectedBatchesList.value.length) return {}
  const firstBatch = selectedBatchesList.value[0]
  return {
    class_name: firstBatch?.class?.name || firstBatch?.course?.class?.name || '',
    course_name: firstBatch?.course?.name || '',
  }
})

const batchStudentCounts = computed(() => {
  // Try from multiBatchStats first
  if (store.multiBatchStats?.batch_students) {
    return store.multiBatchStats.batch_students
  }
  // Fallback: try from grid data _stats
  if (store.multiBatchGrid?._stats?.batch_students) {
    return store.multiBatchGrid._stats.batch_students
  }
  return {}
})

// ===== Batch Color Helper =====
function getBatchColor(batchId) {
  if (!batchId) return '#6366F1'
  const colors = ['#6366F1', '#EC4899', '#14B8A6', '#F97316', '#84CC16', '#8B5CF6', '#06B6D4', '#F43F5E']
  const index = batchId.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % colors.length
  return colors[index]
}

// ===== Methods =====
async function loadInitialData() {
  const extractData = (res) => {
    if (!res) return []
    if (res.data?.data && Array.isArray(res.data.data)) return res.data.data
    if (Array.isArray(res.data)) return res.data
    if (res.data?.data && Array.isArray(res.data.data)) return res.data.data
    return []
  }

  const safeCall = async (fn) => {
    try {
      return await fn()
    } catch (e) {
      console.warn('loadInitialData: API call failed', e)
      return null
    }
  }

  const [batchesRes, coursesRes, classesRes, subjectsRes, teachersRes, roomsRes, groupsRes] = await Promise.all([
    safeCall(() => enrollmentService.getBatches({ per_page: 100 })),
    safeCall(() => enrollmentService.listAllCourses()),
    safeCall(() => academicService.classes.listAll()),
    safeCall(() => academicService.subjects.listAll()),
    safeCall(() => teacherService.listAll()),
    safeCall(() => academicService.rooms.listAll()),
    safeCall(() => academicService.groups.listAll()),
  ])

  batches.value = extractData(batchesRes)
  courses.value = extractData(coursesRes)
  classes.value = extractData(classesRes)
  subjects.value = extractData(subjectsRes)
  teachers.value = extractData(teachersRes)
  rooms.value = extractData(roomsRes)
  groups.value = extractData(groupsRes)
}

function toggleBatchSelection(batchId) {
  const idx = selectedBatchIds.value.indexOf(batchId)
  if (idx === -1) {
    selectedBatchIds.value.push(batchId)
  } else {
    selectedBatchIds.value.splice(idx, 1)
  }
  // Persist to localStorage
  persistMultiBatchState()
}

function persistMultiBatchState() {
  try {
    localStorage.setItem('routine_multi_batch_enabled', multiBatchEnabled.value ? 'true' : 'false')
    localStorage.setItem('routine_multi_batch_ids', JSON.stringify(selectedBatchIds.value))
  } catch (e) {
    // localStorage may be full or unavailable
  }
}

function onMultiBatchToggle() {
  if (multiBatchEnabled.value && selectedBatchIds.value.length > 0) {
    persistMultiBatchState()
    refreshMultiBatchGrid()
  } else if (!multiBatchEnabled.value) {
    persistMultiBatchState()
    store.multiBatchGrid = null
    if (filters.level && filters.level_id) {
      fetchRoutines()
    }
  }
}

async function refreshMultiBatchGrid() {
  if (!multiBatchEnabled.value || selectedBatchIds.value.length === 0) return
  const filterParams = {}
  if (filters.day) filterParams.day = filters.day
  if (filters.status) filterParams.status = filters.status
  if (filters.teacher_id) filterParams.teacher_id = filters.teacher_id
  if (filters.room_id) filterParams.room_id = filters.room_id
  if (filters.subject_id) filterParams.subject_id = filters.subject_id
  await store.fetchMultiBatchGrid([...selectedBatchIds.value], filterParams)
  await store.fetchMultiBatchStats([...selectedBatchIds.value])
}

/**
 * Auto-load the multi-batch grid with ALL batches that have routines.
 * This enables the enterprise grid to show automatically after creating routines,
 * without requiring the user to manually toggle multi-batch mode or select batches.
 */
async function autoLoadMultiBatchGrid() {
  // Use all available batches as the batch IDs
  const allBatchIds = batches.value.map(b => b.id).filter(Boolean)
  if (allBatchIds.length === 0) return

  // Enable multi-batch mode silently
  multiBatchEnabled.value = true
  selectedBatchIds.value = allBatchIds

  const filterParams = {}
  if (filters.day) filterParams.day = filters.day
  if (filters.status) filterParams.status = filters.status
  if (filters.teacher_id) filterParams.teacher_id = filters.teacher_id
  if (filters.room_id) filterParams.room_id = filters.room_id
  if (filters.subject_id) filterParams.subject_id = filters.subject_id

  await store.fetchMultiBatchGrid([...allBatchIds], filterParams)
  await store.fetchMultiBatchStats([...allBatchIds])
}

function switchViewMode(mode) {
  store.viewMode = mode
}

async function fetchRoutines() {
  const params = {}
  if (filters.level && filters.level_id) {
    params[filters.level + '_id'] = filters.level_id
  }
  if (filters.day) params.day = filters.day
  if (filters.status) params.status = filters.status
  await store.fetchRoutines(params)
}

function onLevelChange() {
  filters.level_id = ''
  if (filters.level) {
    fetchRoutines()
  }
}

function onFilterChange() {
  if (isMultiBatch.value) {
    refreshMultiBatchGrid()
  } else if (filters.level && filters.level_id) {
    fetchRoutines()
  }
}

function resetFilters() {
  filters.level = ''
  filters.level_id = ''
  filters.day = ''
  filters.status = 'published' // Reset to default (published only)
  filters.teacher_id = ''
  filters.room_id = ''
  filters.subject_id = ''

  // Preserve multi-batch state - user can toggle it off manually
  if (multiBatchEnabled.value) {
    // Keep selectedBatchIds intact, just refresh the grid without filters
    if (selectedBatchIds.value.length > 0) {
      refreshMultiBatchGrid()
    }
    // Don't clear multi-batch routines from store
    return
  }

  store.clearRoutines()
}

function openCreateModal() {
  selectedSlot.value = null
  formError.value = ''
  showEditModal.value = true
}

/**
 * Clear the status filter so newly created routines (including drafts) appear in the grid.
 */
function clearStatusFilter() {
  if (filters.status) {
    filters.status = ''
  }
}

/**
 * Handler for @routine-created event from EnterpriseRoutineGrid's built-in wizard.
 * Clears the status filter and refreshes the grid so the new routine appears immediately.
 */
async function onRoutineCreated() {
  clearStatusFilter()
  await refreshMultiBatchGrid()
}

function openEditModal(slot) {
  selectedSlot.value = slot
  formError.value = ''
  showEditModal.value = true
}

async function handleSave(data) {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...data }
    ;['room_id', 'group_id', 'section_id', 'batch_id', 'course_id', 'class_id'].forEach(key => {
      if (payload[key] === '' || payload[key] === undefined) {
        payload[key] = null
      }
    })

    // Multi-batch mode: use batch_id from form data directly
    if (isMultiBatch.value) {
      // batch_id should already be in payload from EditSlotModal
      if (!payload.batch_id && selectedBatchIds.value.length > 0) {
        payload.batch_id = selectedBatchIds.value[0]
      }
    } else {
      // Single mode: attach level context from filters
      if (filters.level === 'batch' && filters.level_id) payload.batch_id = filters.level_id
      if (filters.level === 'course' && filters.level_id) payload.course_id = filters.level_id
      if (filters.level === 'class' && filters.level_id) payload.class_id = filters.level_id
    }

    if (selectedSlot.value?.id) {
      await store.updateRoutine(selectedSlot.value.id, payload)
    } else {
      await store.createRoutine(payload)
    }
    showEditModal.value = false
    // Clear status filter so newly created routines (including drafts) appear
    clearStatusFilter()
    // Auto-load multi-batch grid to show all batches with routines
    await autoLoadMultiBatchGrid()
  } catch (e) {
    formError.value = e.response?.data?.message || e.message || 'Failed to save routine'
  } finally {
    saving.value = false
  }
}

async function handleManualSave(data) {
  manualSaving.value = true
  manualError.value = ''
  try {
    const payload = { ...data }
    ;['room_id', 'group_id', 'section_id', 'batch_id', 'course_id', 'class_id'].forEach(key => {
      if (payload[key] === '' || payload[key] === undefined) {
        payload[key] = null
      }
    })

    await store.createRoutine(payload)
    showManualWizard.value = false

    // Clear status filter so newly created routines (including drafts) appear
    clearStatusFilter()
    // Auto-load multi-batch grid to show all batches with routines
    await autoLoadMultiBatchGrid()
  } catch (e) {
    manualError.value = e.response?.data?.message || e.message || 'Failed to save routine'
  } finally {
    manualSaving.value = false
  }
}

async function handleDelete(id) {
  if (!confirm('Are you sure you want to delete this slot?')) return
  try {
    await store.deleteRoutine(id)
    showEditModal.value = false
    // Auto-load multi-batch grid to reflect deletion
    await autoLoadMultiBatchGrid()
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to delete'
  }
}

async function handleSwap(slot1Id, slot2Id) {
  try {
    await store.swapSlots(slot1Id, slot2Id)
    await autoLoadMultiBatchGrid()
  } catch (e) {
    conflicts.value = [{ message: e.response?.data?.message || 'Swap failed' }]
  }
}

async function publishAll() {
  if (isMultiBatch.value) {
    try {
      await store.publishMultiBatch([...selectedBatchIds.value])
      await autoLoadMultiBatchGrid()
    } catch (e) {
      conflicts.value = [{ message: e.response?.data?.message || 'Publish failed' }]
    }
  } else if (filters.level && filters.level_id) {
    try {
      await store.publishRoutine(filters.level, filters.level_id)
      await fetchRoutines()
    } catch (e) {
      conflicts.value = [{ message: e.response?.data?.message || 'Publish failed' }]
    }
  }
}

async function archiveAll() {
  if (isMultiBatch.value) {
    try {
      await store.archiveMultiBatch([...selectedBatchIds.value])
      await autoLoadMultiBatchGrid()
    } catch (e) {
      conflicts.value = [{ message: e.response?.data?.message || 'Archive failed' }]
    }
  } else if (filters.level && filters.level_id) {
    try {
      await store.archiveRoutine(filters.level, filters.level_id)
      await fetchRoutines()
    } catch (e) {
      conflicts.value = [{ message: e.response?.data?.message || 'Archive failed' }]
    }
  }
}

async function checkConflicts() {
  if (isMultiBatch.value) {
    const result = await store.fetchMultiBatchConflicts([...selectedBatchIds.value])
    conflicts.value = result.length ? result : [{ message: 'No conflicts found!' }]
    if (result.length === 0) {
      setTimeout(() => { conflicts.value = [] }, 3000)
    }
  } else if (filters.level && filters.level_id) {
    const result = await store.fetchConflicts({ level: filters.level, level_id: filters.level_id })
    conflicts.value = result.length ? result : [{ message: 'No conflicts found!' }]
    if (result.length === 0) {
      setTimeout(() => { conflicts.value = [] }, 3000)
    }
  }
}

async function handleSetLunchBreak() {
  if (!lunchBreakForm.day || !lunchBreakForm.start_time || !lunchBreakForm.end_time) {
    lunchBreakError.value = 'Please fill all fields'
    return
  }
  lunchBreakSaving.value = true
  lunchBreakError.value = ''
  try {
    const batchIds = isMultiBatch.value ? [...selectedBatchIds.value] : (filters.level_id ? [filters.level_id] : [])
    await store.setLunchBreak({
      batch_ids: batchIds,
      day: lunchBreakForm.day,
      start_time: lunchBreakForm.start_time,
      end_time: lunchBreakForm.end_time,
    })
    showLunchBreakModal.value = false
    await autoLoadMultiBatchGrid()
  } catch (e) {
    lunchBreakError.value = e.response?.data?.message || 'Failed to set lunch break'
  } finally {
    lunchBreakSaving.value = false
  }
}

async function handleSetOffDay() {
  if (!offDayForm.date) {
    offDayError.value = 'Please select a date'
    return
  }
  offDaySaving.value = true
  offDayError.value = ''
  try {
    const batchId = isMultiBatch.value ? selectedBatchIds.value[0] : filters.level_id
    if (!batchId) {
      offDayError.value = 'Please select a batch first'
      return
    }
    await store.setOffDay({
      batch_id: batchId,
      date: offDayForm.date,
      reason: offDayForm.reason || null,
    })
    showOffDayModal.value = false
    await autoLoadMultiBatchGrid()
  } catch (e) {
    offDayError.value = e.response?.data?.message || 'Failed to set off day'
  } finally {
    offDaySaving.value = false
  }
}

async function handleGenerate(data) {
  generating.value = true
  generationError.value = ''
  try {
    // If multi-batch is enabled, ensure batch_ids are included in the payload
    const payload = { ...data }
    if (isMultiBatch.value && selectedBatchIds.value.length > 0) {
      payload.batch_ids = [...selectedBatchIds.value]
      // Set level to batch and use first batch as level_id for backward compatibility
      payload.level = 'batch'
      if (!payload.level_id) {
        payload.level_id = selectedBatchIds.value[0]
      }
    }
    const result = await store.generate(payload)
    generatedRoutines.value = result?.generated || result || []
  } catch (e) {
    generationError.value = e.response?.data?.message || 'Generation failed'
    generatedRoutines.value = []
  } finally {
    generating.value = false
  }
}

async function handleApplyGeneration() {
  if (!generatedRoutines.value.length) return
  try {
    await store.bulkStore ? store.bulkStore({ routines: generatedRoutines.value }) : null
    showGenerateWizard.value = false
    generatedRoutines.value = []
    // Clear status filter so newly generated routines (including drafts) appear
    clearStatusFilter()
    // Auto-load multi-batch grid to show all batches with routines
    await autoLoadMultiBatchGrid()
  } catch (e) {
    generationError.value = e.response?.data?.message || 'Failed to apply'
  }
}

function exportPDF() {
  const gridEl = document.querySelector('.enterprise-routine-grid')
  if (!gridEl) return

  const clonedGrid = gridEl.cloneNode(true)

  // Remove interactive / non-grid elements from clone
  clonedGrid.querySelectorAll('.grid-header').forEach(el => el.remove())
  clonedGrid.querySelectorAll('.legend-bar').forEach(el => el.remove())
  clonedGrid.querySelectorAll('.loading-overlay').forEach(el => el.remove())
  clonedGrid.querySelectorAll('.modal-overlay, .modal-backdrop').forEach(el => el.remove())
  clonedGrid.querySelectorAll('.empty-state').forEach(el => el.remove())
  clonedGrid.querySelectorAll('.btn, button, select, input, textarea').forEach(el => el.remove())

  const printWindow = window.open('', '_blank', 'width=1400,height=900')
  if (!printWindow) return

  const styles = Array.from(document.styleSheets)
    .map(sheet => {
      try {
        return Array.from(sheet.cssRules || []).map(rule => rule.cssText).join('')
      } catch (e) { return '' }
    })
    .join('')

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Class Routine</title>
      <style>
        ${styles}
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          background: var(--bg-card);
          color: var(--text-primary);
          font-size: 9pt;
          padding: 0.3in;
        }

        /* ── Hide everything except the grid ── */
        .page-container, .page-header, .stats-row,
        .no-print, .action-bar, .form-card, .swap-banner,
        .loading-state, .error-state, .empty-state,
        .alert, .conflict-banner, .conflict-panel,
        .weekly-grid-mobile,
        .grid-header, .legend-bar, .loading-overlay,
        .modal-overlay, .modal-backdrop, .modal-dialog,
        .batch-dropdown, .more-dropdown, .batch-selector,
        .more-actions, .header-btn, .add-btn, .more-btn,
        .legend-toggle, .batch-select-btn,
        .btn, button, select, input, textarea,
        .create-wizard, .manual-wizard, .generate-wizard {
          display: none !important;
        }

        /* ── Grid container ── */
        .enterprise-routine-grid {
          display: block !important;
          box-shadow: none !important;
          border: 1px solid var(--border-color) !important;
          border-radius: 4px !important;
          overflow: visible !important;
          page-break-inside: avoid;
        }

        .grid-scroll-container {
          max-height: none !important;
          overflow: visible !important;
        }

        .grid-wrapper {
          min-width: auto !important;
        }

        /* ── Grid header row (Time / Day columns) ── */
        .grid-header-row {
          position: static !important;
          display: grid !important;
          grid-template-columns: 80px repeat(7, 1fr) !important;
          background: #f3f4f6 !important;
          border-bottom: 2px solid #d1d5db !important;
        }

        .time-col-header,
        .day-col-header {
          padding: 6px 4px !important;
          font-size: 8pt !important;
          font-weight: 600 !important;
          text-align: center !important;
          border-right: 1px solid #e5e7eb !important;
        }
        .day-col-header:last-child {
          border-right: none !important;
        }

        /* ── Grid body rows ── */
        .grid-body {
          display: block !important;
        }

        .grid-row {
          display: grid !important;
          grid-template-columns: 80px repeat(7, 1fr) !important;
          border-bottom: 1px solid var(--border-color) !important;
          page-break-inside: avoid;
        }

        /* ── Time cell ── */
        .time-cell {
          position: static !important;
          width: 80px !important;
          padding: 6px 4px !important;
          font-size: 7.5pt !important;
          border-right: 1px solid #e5e7eb !important;
          background: #fafafa !important;
          display: flex !important;
          flex-direction: column !important;
          align-items: center !important;
          justify-content: center !important;
        }
        .time-slot-name {
          font-weight: 600 !important;
          font-size: 7.5pt !important;
        }
        .time-slot-range {
          font-size: 6.5pt !important;
          color: var(--text-muted) !important;
        }

        /* ── Day cells ── */
        .day-cell {
          padding: 3px !important;
          border-right: 1px solid #e5e7eb !important;
          min-height: 50px !important;
          vertical-align: top !important;
        }
        .day-cell:last-child {
          border-right: none !important;
        }

        /* ── Routine stack / cards ── */
        .routine-stack {
          display: flex !important;
          flex-direction: column !important;
          gap: 2px !important;
        }

        .routine-card {
          padding: 3px 4px !important;
          border-radius: 2px !important;
          border: 1px solid var(--border-color) !important;
          font-size: 6.5pt !important;
          line-height: 1.3 !important;
          page-break-inside: avoid;
          break-inside: avoid;
        }

        .card-batch-row {
          margin-bottom: 1px !important;
        }
        .card-batch-name {
          font-weight: 600 !important;
          font-size: 6.5pt !important;
        }
        .batch-dot-sm {
          display: inline-block !important;
          width: 5px !important;
          height: 5px !important;
          border-radius: 50% !important;
          margin-right: 3px !important;
          vertical-align: middle !important;
        }

        .card-details-row {
          display: flex !important;
          align-items: center !important;
          gap: 2px !important;
          flex-wrap: wrap !important;
        }
        .card-detail-text {
          font-size: 6pt !important;
          color: var(--text-secondary) !important;
        }
        .card-detail-sep {
          font-size: 5pt !important;
          color: var(--text-muted) !important;
        }
        .card-detail-teacher svg {
          width: 7px !important;
          height: 7px !important;
          vertical-align: middle !important;
          margin-right: 1px !important;
        }

        /* ── Hide live badge in print ── */
        .card-live-badge {
          display: none !important;
        }

        /* ── Empty cells ── */
        .empty-cell {
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          min-height: 50px !important;
          color: #d1d5db !important;
          font-size: 8pt !important;
        }

        /* ── Off day cell ── */
        .off-day-content-wrapper {
          display: flex !important;
          flex-direction: column !important;
          align-items: center !important;
          justify-content: center !important;
          min-height: 50px !important;
        }
        .off-day-icon {
          font-size: 10pt !important;
        }
        .off-day-text-block {
          text-align: center !important;
        }
        .off-day-title {
          font-size: 6.5pt !important;
          font-weight: 600 !important;
          color: #ef4444 !important;
        }
        .off-day-sub {
          font-size: 6pt !important;
          color: var(--text-muted) !important;
        }

        /* ── Footer section ── */
        .grid-footer-section {
          display: block !important;
          page-break-inside: avoid;
          break-inside: avoid;
          border-top: 2px solid #d1d5db !important;
          padding: 8px 6px !important;
        }
        .footer-columns {
          display: grid !important;
          grid-template-columns: 1fr 1fr 1fr !important;
          gap: 12px !important;
        }
        .footer-title {
          font-size: 8pt !important;
          font-weight: 700 !important;
          margin-bottom: 4px !important;
        }
        .instructions-list {
          padding-left: 14px !important;
          font-size: 6.5pt !important;
          color: var(--text-secondary) !important;
        }
        .instructions-list li {
          margin-bottom: 2px !important;
        }
        .legend-grid {
          font-size: 6.5pt !important;
        }
        .legend-row {
          display: flex !important;
          align-items: center !important;
          gap: 4px !important;
          margin-bottom: 2px !important;
        }
        .legend-icon {
          font-size: 8pt !important;
        }
        .legend-label {
          color: var(--text-secondary) !important;
        }
        .signature-block {
          display: flex !important;
          flex-direction: column !important;
          gap: 8px !important;
        }
        .signature-item {
          display: flex !important;
          flex-direction: column !important;
        }
        .sig-line {
          border-bottom: 1px solid #9ca3af !important;
          width: 120px !important;
          margin-bottom: 2px !important;
        }
        .sig-label {
          font-size: 6.5pt !important;
          color: var(--text-muted) !important;
        }
        .footer-bottom {
          display: flex !important;
          justify-content: space-between !important;
          margin-top: 8px !important;
          padding-top: 6px !important;
          border-top: 1px solid var(--border-color) !important;
          font-size: 6pt !important;
          color: var(--text-muted) !important;
        }

        /* ── Page setup ── */
        @page {
          margin: 0.3in;
          size: landscape;
        }
        @media print {
          body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
      </style>
    </head>
    <body>
      ${clonedGrid.innerHTML}
    </body>
    </html>
  `)

  printWindow.document.close()
  setTimeout(() => {
    printWindow.focus()
    printWindow.print()
    printWindow.close()
  }, 500)
}

onMounted(async () => {
  await loadInitialData()

  // Auto-load multi-batch grid with all batches on page load
  // This shows the full routine grid immediately without requiring
  // the user to toggle multi-batch mode or select batches manually
  await autoLoadMultiBatchGrid()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');

/* ===== GLOBAL PAGE CONTAINER ===== */
.page-container {
  font-family: 'Outfit', sans-serif;
  color: var(--text-dark);
  background: var(--bg-surface-muted);
  min-height: 100vh;
  padding: 1rem;
}

/* ===== COMPACT PAGE HEADER ===== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  background: var(--bg-card);
  padding: 0.75rem 1rem;
  border-radius: 10px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

.page-header h1 {
  font-size: 18px;
  font-weight: 800;
  color: #0f2963;
  margin: 0;
  letter-spacing: -0.02em;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ===== COMPACT BUTTONS ===== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.4rem 0.85rem;
  font-size: 12px;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: 'Outfit', sans-serif;
  gap: 5px;
  white-space: nowrap;
}

.btn-sm {
  padding: 0.35rem 0.7rem;
  font-size: 11px;
}

.btn-primary {
  background: #0f2963;
  color: #ffffff;
}

.btn-primary:hover {
  background: #1d3d82;
}

.btn-outline {
  background: var(--bg-card);
  border-color: #cbd5e1;
  color: var(--text-secondary);
}

.btn-outline:hover:not(:disabled) {
  border-color: #0f2963;
  color: #0f2963;
  background: var(--bg-surface-muted);
}

.btn-ghost {
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-secondary);
  box-shadow: none;
}

.btn-ghost:hover {
  background: var(--bg-accent);
  color: #0f2963;
}

/* ===== MULTI-BATCH TOGGLE (compact) ===== */
.multi-batch-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  padding: 5px 10px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  background: var(--bg-card);
  transition: all 0.15s ease;
  user-select: none;
}

.multi-batch-toggle:hover {
  border-color: #0f2963;
  color: #0f2963;
  background: #f8faff;
}

.multi-batch-toggle input[type="checkbox"] {
  accent-color: #0f2963;
  width: 13px;
  height: 13px;
  cursor: pointer;
  margin: 0;
}

/* ===== SWAP BANNER ===== */
.swap-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  background: #f0f4ff;
  border: 1.5px solid #a5b4fc;
  border-radius: 10px;
  margin-bottom: 1rem;
}

.swap-banner svg {
  color: #0f2963;
  flex-shrink: 0;
}

.swap-banner span {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
  color: #0f2963;
  text-align: left;
}

/* Conflict Panel */
.conflict-panel {
  margin-top: 1rem;
  border-radius: 12px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(220, 38, 38, 0.04);
}

.conflict-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: #fee2e2;
  border-bottom: 1px solid #fecaca;
}

.conflict-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #991b1b;
  font-weight: 700;
}

.conflict-title svg {
  color: #dc2626;
}

.conflict-close {
  color: #991b1b;
  font-size: 18px;
}

.conflict-list {
  padding: 6px 16px;
}

.conflict-item {
  border-bottom: 1px solid #fee2e2;
  padding: 8px 0;
  font-size: 12px;
  font-weight: 600;
}

.conflict-type-badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
}

/* Modals redesign */
.modal-overlay {
  background: rgba(15, 41, 99, 0.4);
  backdrop-filter: blur(4px);
}

.modal-content {
  border-radius: 16px;
  border: 1px solid var(--border-color);
  box-shadow: 0 25px 50px -12px rgba(15, 41, 99, 0.25);
  font-family: 'Outfit', sans-serif;
  overflow: hidden;
}

.modal-header {
  background: var(--bg-surface-muted);
  padding: 16px 24px;
}

.modal-header h3 {
  font-size: 16px;
  font-weight: 800;
  color: #0f2963;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-footer {
  padding: 16px 24px;
  background: var(--bg-surface-muted);
}
</style>
