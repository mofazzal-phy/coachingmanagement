<template>
  <div class="erp-exam-routine">
    <!-- ===== TOP ACTION BAR ===== -->
    <div class="top-action-bar">
      <div class="action-row">
        <div class="action-left">
          <h1 class="page-title">My Exam Routines</h1>
          <span class="badge-count">{{ totalRoutines }} routine{{ totalRoutines !== 1 ? 's' : '' }}</span>
        </div>
        <div class="action-right">
          <button class="btn btn-sm btn-outline" @click="fetchGrid" :disabled="loading" title="Refresh">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
            Refresh
          </button>
          <div class="btn-separator"></div>
          <button class="btn btn-sm" :class="displayMode === 'grid' ? 'btn-primary' : 'btn-outline'" @click="displayMode = 'grid'" title="Table grid view">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Grid
          </button>
          <button class="btn btn-sm" :class="displayMode === 'cards' ? 'btn-primary' : 'btn-outline'" @click="displayMode = 'cards'" title="Card view">
            <svg class="ico" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            Cards
          </button>
        </div>
      </div>
    </div>

    <!-- ===== FILTER TABS (All / Upcoming / Past) ===== -->
    <div class="filter-tabs">
      <button
        v-for="tab in filterTabs"
        :key="tab.key"
        class="filter-tab"
        :class="{ active: viewMode === tab.key }"
        @click="viewMode = tab.key"
      >
        {{ tab.label }}
        <span class="filter-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- ===== EXAM SELECTOR TABS ===== -->
    <div class="exam-selector" v-if="filteredExamGrids.length > 1">
      <button
        v-for="eg in filteredExamGrids"
        :key="eg.exam_id"
        class="exam-selector-tab"
        :class="{ active: selectedExamId === eg.exam_id }"
        @click="selectedExamId = eg.exam_id"
      >
        <svg class="exam-selector-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
        <span class="exam-selector-name">{{ eg.exam?.name || 'Exam' }}</span>
        <span class="exam-selector-count">{{ eg.routine_count }}</span>
      </button>
    </div>

    <!-- ===== CURRENT EXAM HEADER ===== -->
    <div class="exam-header" v-if="currentExamGrid">
      <div class="header-brand">
        <div class="header-brand-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div class="header-body">
          <h1 class="header-title">{{ currentExamGrid.exam?.name || 'Exam Routine' }}</h1>
          <div class="header-meta">
            <span class="meta-item">
              <svg class="meta-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
              {{ currentExamGrid.first_date || 'N/A' }} — {{ currentExamGrid.last_date || 'N/A' }}
            </span>
            <span class="meta-sep">•</span>
            <span class="meta-item">{{ currentExamGrid.exam?.exam_type || 'N/A' }}</span>
            <span class="meta-sep">•</span>
            <span class="meta-item">{{ currentExamGrid.routine_count }} routine{{ currentExamGrid.routine_count !== 1 ? 's' : '' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== ROUTINE GRID (table view) ===== -->
    <div class="routine-grid-wrapper exam-routine-grid" v-if="displayMode === 'grid' && currentExamGrid && Object.keys(currentExamGrid.grid || {}).length">
      <div class="grid-scroll">
        <table class="routine-table">
          <thead>
            <tr>
              <th class="th-time">Time</th>
              <th v-for="day in currentExamGrid.days" :key="day" class="th-day" :class="'th-day-' + day.toLowerCase()">
                <span class="day-name" :style="{ color: getDayColor(day) }">{{ day.substring(0, 3) }}</span>
                <span class="day-date">{{ getDayDate(day, currentExamGrid) }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="slot in currentExamGrid.time_slots" :key="slot.key">
              <tr v-if="!isSlotEmpty(slot, currentExamGrid)" :class="{ 'lunch-row': slot.is_lunch }">
                <td class="td-time">
                  <div class="time-slot-label" :class="{ 'time-slot-label--lunch': slot.is_lunch }">
                    <span class="slot-clock" aria-hidden="true">
                      <svg v-if="!slot.is_lunch" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" d="M12 7v5l3 2"/>
                      </svg>
                      <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M12 3v2M12 19v2M5 12H3M21 12h-2"/>
                      </svg>
                    </span>
                    <div class="slot-times">
                      <template v-if="slot.start">
                        <span class="slot-range slot-range-line">
                          {{ formatTimeDisplay(slot.start) }} — {{ formatTimeDisplay(slot.end) }}
                        </span>
                        <span class="slot-duration">{{ formatDuration(slot.start, slot.end) }}</span>
                      </template>
                      <span class="slot-range lunch-range" v-else>Lunch</span>
                    </div>
                  </div>
                </td>
                <td
                  v-for="day in currentExamGrid.days"
                  :key="day + '-' + slot.key"
                  :class="getDayCellClass(day, slot)"
                >
                  <!-- Friday OFF DAY -->
                  <div v-if="day === 'Friday' && !slot.is_lunch" class="off-day-cell">
                    <span class="off-text">OFF</span>
                  </div>
                  <!-- Lunch Break -->
                  <div v-else-if="slot.is_lunch" class="lunch-cell">
                    <span class="lunch-text">LUNCH</span>
                  </div>
                  <!-- Exam Cards -->
                  <div v-else class="cell-exams">
                    <template v-if="getCellRoutines(day, slot.key, currentExamGrid).length">
                      <template v-for="(batch, bIdx) in getCellBatches(day, slot.key, currentExamGrid)" :key="batch.batch_id">
                        <div v-if="bIdx > 0" class="batch-sep"></div>
                        <div
                          v-for="(routine, rIdx) in batch.routines"
                          :key="routine.id"
                          class="exam-card"
                          :style="{
                            background: getBatchBgColor(routine.batch_color),
                            borderLeftColor: routine.batch_color || routine.color,
                          }"
                        >
                          <div class="card-batch">
                            <span class="batch-dot" :style="{ background: routine.batch_color || routine.color }"></span>
                            <span class="card-batch-name">{{ batch.batch_name }}</span>
                            <span v-if="(routine.status || 'draft') === 'draft'" class="status-badge status-draft" title="Draft">&#9998;</span>
                            <span v-else-if="(routine.status || 'draft') === 'completed'" class="status-badge status-completed" title="Completed">&#10004;</span>
                            <span v-else-if="(routine.status || 'draft') === 'cancelled'" class="status-badge status-cancelled" title="Cancelled">&#10007;</span>
                          </div>
                          <div class="card-subject">{{ routine.subject_name }}</div>
                          <div class="card-exam-type">{{ routine.exam_type_name }}</div>
                        </div>
                      </template>
                    </template>
                    <div v-else class="cell-empty">
                      <span class="empty-dash">—</span>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===== CARD VIEW ===== -->
    <div v-else-if="displayMode === 'cards' && cardRoutines.length" class="card-view-wrapper">
      <div class="card-filter-tabs" v-if="cardBatches.length > 1">
        <button
          v-for="b in cardBatches"
          :key="b.id"
          class="card-filter-tab"
          :class="{ active: selectedCardBatch === b.id }"
          @click="selectedCardBatch = b.id"
        >
          <span class="card-filter-dot" :style="{ background: b.color }"></span>
          {{ b.name }}
          <span class="card-filter-count">{{ b.count }}</span>
        </button>
        <button
          class="card-filter-tab"
          :class="{ active: selectedCardBatch === 'all' }"
          @click="selectedCardBatch = 'all'"
        >
          All Batches
          <span class="card-filter-count">{{ cardRoutines.length }}</span>
        </button>
      </div>

      <div class="card-view-list">
        <div
          v-for="routine in filteredCardRoutines"
          :key="routine.id"
          class="card-view-item"
          :style="{
            borderLeftColor: routine.batch_color || routine.color || '#3B82F6',
          }"
        >
          <div class="card-item-date">
            <div class="card-item-day">{{ getDayShort(routine._day_date) }}</div>
            <div class="card-item-num">{{ getDayNum(routine._day_date) }}</div>
            <div class="card-item-month">{{ getMonthShort(routine._day_date) }}</div>
          </div>
          <div class="card-item-body">
            <div class="card-item-top">
              <span class="card-item-subject" :style="{ color: routine.color || '#3B82F6' }">
                {{ routine.subject_name }}
              </span>
              <span class="card-item-batch" :style="{ background: (routine.batch_color || '#3B82F6') + '20', color: routine.batch_color || '#3B82F6' }">
                {{ routine.batch_name }}
              </span>
              <span v-if="(routine.status || 'draft') === 'draft'" class="card-status draft">Draft</span>
              <span v-else-if="(routine.status || 'draft') === 'published'" class="card-status published">Published</span>
              <span v-else-if="(routine.status || 'draft') === 'completed'" class="card-status completed">Completed</span>
              <span v-else-if="(routine.status || 'draft') === 'cancelled'" class="card-status cancelled">Cancelled</span>
            </div>
            <div class="card-item-meta">
              <span class="card-meta-chip">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ routine.start_time || '--:--' }} — {{ routine.end_time || '--:--' }}
              </span>
              <span class="card-meta-chip">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                {{ routine.room_name || 'TBD' }}
              </span>
              <span class="card-meta-chip">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                {{ routine.exam_type_name || 'N/A' }}
              </span>
              <span class="card-meta-chip">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                {{ routine.teacher_name || 'Not Assigned' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== EMPTY STATE ===== -->
    <div v-else-if="!loading" class="empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <h3>No Exam Routines</h3>
      <p>No exam routines found for your subjects. Routines will appear here once admin publishes them.</p>
    </div>

    <!-- ===== LOADING ===== -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner"></div>
      <span>Loading...</span>
    </div>
  </div>
</template>

<script setup>
import '@/styles/exam-routine-grid.css'
import { ref, reactive, computed, onMounted } from 'vue'
import examService from '@/services/exam.service'

const loading = ref(false)
const displayMode = ref('grid') // 'grid' | 'cards'
const viewMode = ref('all') // 'all' | 'upcoming' | 'past'

// ===== MULTI-EXAM GRID DATA =====
const examGrids = ref([])
const selectedExamId = ref(null)
const batches = ref([])
const batchColors = ref({})
const allExams = ref([])

// ===== DATE HELPERS =====
const todayDate = computed(() => new Date().toISOString().split('T')[0])

// ===== FILTER TABS =====
const filteredExamGrids = computed(() => {
  let list = [...examGrids.value]
  if (viewMode.value === 'upcoming') {
    list = list.filter(eg => eg.last_date && eg.last_date >= todayDate.value)
  } else if (viewMode.value === 'past') {
    list = list.filter(eg => eg.last_date && eg.last_date < todayDate.value)
  }
  return list
})

const filterTabs = computed(() => [
  { key: 'all', label: 'All Exams', count: examGrids.value.length },
  { key: 'upcoming', label: 'Upcoming', count: examGrids.value.filter(eg => eg.last_date && eg.last_date >= todayDate.value).length },
  { key: 'past', label: 'Past', count: examGrids.value.filter(eg => eg.last_date && eg.last_date < todayDate.value).length },
])

// Computed: currently selected exam grid (from filtered list)
const currentExamGrid = computed(() => {
  const grids = filteredExamGrids.value
  if (!grids.length) return null
  if (!selectedExamId.value) return grids[0]
  return grids.find(eg => eg.exam_id === selectedExamId.value) || grids[0]
})

// Total routines across all filtered exams
const totalRoutines = computed(() => {
  let count = 0
  for (const eg of filteredExamGrids.value) {
    count += eg.routine_count || 0
  }
  return count
})

// ===== CARD VIEW =====
const cardRoutines = computed(() => {
  const items = []
  for (const eg of examGrids.value) {
    if (!eg.grid || !eg.days) continue
    for (const day of eg.days) {
      const daySlots = eg.grid[day]
      if (!daySlots) continue
      for (const slotKey of Object.keys(daySlots)) {
        const slot = daySlots[slotKey]
        if (!slot || slot.is_lunch) continue
        const routines = slot.routines || []
        for (const r of routines) {
          items.push({
            ...r,
            _day: day,
            _slot_key: slotKey,
            _slot_start: slot.start,
            _slot_end: slot.end,
            _day_date: eg.day_dates?.[day] || '',
            _exam_name: eg.exam?.name || '',
          })
        }
      }
    }
  }
  items.sort((a, b) => {
    const dateCmp = (a._day_date || '').localeCompare(b._day_date || '')
    if (dateCmp !== 0) return dateCmp
    return (a.start_time || '').localeCompare(b.start_time || '')
  })
  return items
})

const selectedCardBatch = ref('all')

const cardBatches = computed(() => {
  const map = {}
  for (const r of cardRoutines.value) {
    const bid = r.batch_id || 'unknown'
    if (!map[bid]) {
      map[bid] = { id: bid, name: r.batch_name || 'Unknown', color: r.batch_color || '#3B82F6', count: 0 }
    }
    map[bid].count++
  }
  return Object.values(map)
})

const filteredCardRoutines = computed(() => {
  if (selectedCardBatch.value === 'all') return cardRoutines.value
  return cardRoutines.value.filter(r => (r.batch_id || 'unknown') === selectedCardBatch.value)
})

function getDayShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { weekday: 'short' })
}
function getDayNum(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.getDate()
}
function getMonthShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short' })
}

// ===== GRID HELPERS =====
function formatTimeDisplay(timeStr) {
  if (!timeStr) return ''
  const parts = timeStr.split(':')
  let h = parseInt(parts[0])
  const m = parts[1]
  const ampm = h >= 12 ? 'PM' : 'AM'
  h = h % 12 || 12
  return `${h}:${m} ${ampm}`
}

function formatDuration(start, end) {
  if (!start || !end) return ''
  const startTs = new Date(`2000-01-01T${start}`).getTime()
  const endTs = new Date(`2000-01-01T${end}`).getTime()
  if (isNaN(startTs) || isNaN(endTs)) return ''
  const diffMs = endTs - startTs
  if (diffMs <= 0) return ''
  const totalMinutes = Math.round(diffMs / 60000)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  if (hours > 0 && minutes > 0) return `${hours}h ${minutes}m`
  if (hours > 0) return `${hours}h`
  return `${minutes}m`
}

function getDayDate(dayName, examGrid) {
  if (!examGrid || !examGrid.day_dates || !examGrid.day_dates[dayName]) return ''
  const d = new Date(examGrid.day_dates[dayName] + 'T00:00:00')
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function getCellRoutines(day, slotKey, examGrid) {
  return examGrid?.grid?.[day]?.[slotKey]?.routines || []
}

function getCellBatches(day, slotKey, examGrid) {
  const routines = getCellRoutines(day, slotKey, examGrid)
  const batchMap = {}
  for (const r of routines) {
    const bid = r.batch_id || 'unknown'
    if (!batchMap[bid]) {
      batchMap[bid] = { batch_id: bid, batch_name: r.batch_name || 'Unknown', routines: [] }
    }
    batchMap[bid].routines.push(r)
  }
  return Object.values(batchMap)
}

async function fetchGrid() {
  loading.value = true
  try {
    const res = await examService.teacher.myGrid()
    const data = res.data?.data || res.data
    if (data) {
      examGrids.value = data.exam_grids || []
      batches.value = data.batches || []
      batchColors.value = data.batch_colors || {}
      allExams.value = data.exams || []

      // Auto-select first exam
      if (examGrids.value.length > 0) {
        selectedExamId.value = examGrids.value[0].exam_id
      }
    }
  } catch (e) {
    console.error('Failed to load teacher exam routine grid:', e)
  } finally {
    loading.value = false
  }
}

function getBatchBgColor(batchColor) {
  if (!batchColor) return 'rgba(226, 232, 240, 0.3)'
  const hex = batchColor.replace('#', '')
  const r = parseInt(hex.substring(0, 2), 16)
  const g = parseInt(hex.substring(2, 4), 16)
  const b = parseInt(hex.substring(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, 0.12)`
}

function getDayColor(day) {
  const colors = {
    Saturday: '#3B82F6',
    Sunday: '#10B981',
    Monday: '#F97316',
    Tuesday: '#8B5CF6',
    Wednesday: '#EC4899',
    Thursday: '#06B6D4',
    Friday: '#EF4444',
  }
  return colors[day] || '#64748b'
}

function isSlotEmpty(slot, examGrid) {
  if (!examGrid) return true
  if (slot.is_lunch) return false
  for (const day of examGrid.days) {
    const routines = examGrid.grid?.[day]?.[slot.key]?.routines || []
    if (routines.length > 0) return false
  }
  return true
}

function getDayCellClass(day, slot) {
  const cls = { 'td-day-cell': true }
  cls['td-day-' + day.toLowerCase()] = true
  if (day === 'Friday' && !slot.is_lunch) cls['td-friday'] = true
  if (slot.is_lunch) cls['td-lunch'] = true
  return cls
}

onMounted(() => {
  fetchGrid()
})
</script>

<style scoped>
/* ===== BASE ===== */
.erp-exam-routine {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--text-dark);
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
  background: var(--bg-card);
  min-height: 100vh;
}

/* ===== TOP ACTION BAR ===== */
.top-action-bar {
  margin-bottom: 1rem;
}
.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.action-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.action-right {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex-wrap: wrap;
}
.page-title {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-dark);
}
.badge-count {
  background: #eef2ff;
  color: #4f46e5;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  white-space: nowrap;
}

/* ===== FILTER TABS (All / Upcoming / Past) ===== */
.filter-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  background: #f3f4f6;
  border-radius: 0.5rem;
  padding: 0.25rem;
}

.filter-tab {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  padding: 0.5rem 0.75rem;
  border: none;
  background: transparent;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}

.filter-tab.active {
  background: var(--bg-card);
  color: var(--text-dark);
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-tab:hover:not(.active) {
  color: var(--text-dark);
}

.filter-count {
  font-size: 0.6875rem;
  background: #e5e7eb;
  color: var(--text-muted);
  padding: 0.0625rem 0.4375rem;
  border-radius: 999px;
  min-width: 1.25rem;
  text-align: center;
}

.filter-tab.active .filter-count {
  background: #eef2ff;
  color: #4f46e5;
}

/* ===== BUTTONS ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  line-height: 1.2;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-sm { padding: 0.375rem 0.625rem; font-size: 0.75rem; }
.btn-primary { background: #4f46e5; color: white; border-color: #4f46e5; }
.btn-primary:hover { background: #4338ca; }
.btn-outline { background: var(--bg-card); color: var(--text-dark); border-color: #e2e8f0; }
.btn-outline:hover { background: var(--bg-surface-muted); border-color: #cbd5e1; }
.btn-separator { width: 1px; height: 1.5rem; background: #e2e8f0; margin: 0 0.125rem; }
.ico { width: 16px; height: 16px; }

/* ===== EXAM SELECTOR TABS ===== */
.exam-selector {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.exam-selector-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.875rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
}
.exam-selector-tab:hover {
  border-color: #4f46e5;
  color: #4f46e5;
}
.exam-selector-tab.active {
  background: #4f46e5;
  border-color: #4f46e5;
  color: white;
}
.exam-selector-ico {
  width: 16px;
  height: 16px;
}
.exam-selector-name {
  white-space: nowrap;
}
.exam-selector-count {
  font-size: 0.6875rem;
  font-weight: 600;
  background: rgba(0,0,0,0.08);
  padding: 0.0625rem 0.375rem;
  border-radius: 999px;
}
.exam-selector-tab.active .exam-selector-count {
  background: rgba(255,255,255,0.2);
}

/* ===== EXAM HEADER ===== */
.exam-header {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem 1.25rem;
  margin-bottom: 1rem;
}
.header-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.header-brand-icon {
  width: 2.5rem;
  height: 2.5rem;
  background: #eef2ff;
  border-radius: 0.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4f46e5;
  flex-shrink: 0;
}
.header-brand-icon svg { width: 1.25rem; height: 1.25rem; }
.header-body { min-width: 0; }
.header-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-dark); }
.header-meta { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; font-size: 0.75rem; color: var(--text-muted); }
.meta-item { display: inline-flex; align-items: center; gap: 0.25rem; }
.meta-ico { width: 14px; height: 14px; }
.meta-sep { color: #cbd5e1; }

/* ===== ROUTINE GRID ===== */
.routine-grid-wrapper {
  width: 100%;
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
}
/* Table layout: exam-routine-grid.css */
.exam-routine-grid .routine-table {
  font-size: 0.75rem;
}
.exam-routine-grid .td-time {
  background: var(--bg-surface-muted);
  font-weight: 500;
}
.exam-routine-grid .time-slot-label {
  overflow: hidden;
}

/* Friday OFF */
.off-day-cell {
  padding: 0.5rem;
}
.off-text {
  font-size: 0.75rem;
  font-weight: 700;
  color: #ef4444;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Lunch */
.lunch-cell {
  padding: 0.5rem;
}
.lunch-text {
  font-size: 0.6875rem;
  font-weight: 700;
  color: #f59e0b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.empty-dash { color: #cbd5e1; font-size: 0.875rem; }

.exam-routine-grid .exam-card {
  background: rgba(226, 232, 240, 0.3);
  border-left: 3px solid #3B82F6;
  border-radius: 0.375rem;
  padding: 0.375rem 0.5rem;
  text-align: left;
  transition: box-shadow 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.exam-routine-grid .card-batch,
.exam-routine-grid .card-subject,
.exam-routine-grid .card-exam-type {
  width: 100%;
}
.exam-card:hover { box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.card-batch {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-bottom: 0.125rem;
}
.batch-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.exam-routine-grid .card-batch-name {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.card-subject {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 0.0625rem;
}
.card-exam-type {
  font-size: 0.5625rem;
  color: var(--text-muted);
  font-weight: 500;
}
.batch-sep { height: 1px; background: #e2e8f0; margin: 0.125rem 0; }

/* Status badges */
.status-badge {
  margin-left: auto;
  font-size: 0.625rem;
  line-height: 1;
  flex-shrink: 0;
}
.status-draft { color: #f59e0b; }
.status-completed { color: #10b981; }
.status-cancelled { color: #ef4444; }

/* Day-specific cell styles */
.td-friday { background: #fef2f2; }
.td-lunch { background: #fffbeb; }

/* ===== CARD VIEW ===== */
.card-view-wrapper { margin-top: 0.5rem; }

.card-filter-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.card-filter-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
}
.card-filter-tab:hover {
  border-color: #4f46e5;
  color: #4f46e5;
}
.card-filter-tab.active {
  background: #4f46e5;
  border-color: #4f46e5;
  color: white;
}
.card-filter-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.card-filter-count {
  font-size: 0.625rem;
  font-weight: 600;
  background: rgba(0,0,0,0.08);
  padding: 0.0625rem 0.375rem;
  border-radius: 999px;
}
.card-filter-tab.active .card-filter-count {
  background: rgba(255,255,255,0.2);
}

.card-view-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.card-view-item {
  display: flex;
  gap: 1rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-left: 3px solid #3B82F6;
  transition: box-shadow 0.15s;
}
.card-view-item:hover {
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

.card-item-date {
  text-align: center;
  min-width: 60px;
  padding: 0.5rem;
  background: var(--bg-surface-muted);
  border-radius: 0.5rem;
  flex-shrink: 0;
}
.card-item-day {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}
.card-item-num {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}
.card-item-month {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.card-item-body {
  flex: 1;
  min-width: 0;
}
.card-item-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.375rem;
}
.card-item-subject {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
}
.card-item-batch {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  white-space: nowrap;
}

/* Card status badges */
.card-status {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.card-status.draft { background: #fef3c7; color: #d97706; }
.card-status.published { background: #dbeafe; color: #2563eb; }
.card-status.completed { background: #d1fae5; color: #059669; }
.card-status.cancelled { background: #fee2e2; color: #dc2626; }

.card-item-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.card-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--text-muted);
}
.card-meta-chip svg {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}

/* ===== EMPTY STATE ===== */
.empty-state {
  text-align: center;
  padding: 3rem 1.5rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
}
.empty-icon {
  width: 3rem;
  height: 3rem;
  color: var(--text-muted);
  margin-bottom: 0.75rem;
}
.empty-state h3 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-dark);
}
.empty-state p {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* ===== LOADING ===== */
.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  gap: 0.75rem;
  color: var(--text-muted);
  font-size: 0.875rem;
}
.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
