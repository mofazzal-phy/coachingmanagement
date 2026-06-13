<template>
  <div>
    <!-- Desktop: Timetable Layout -->
    <div class="tt-wrapper">
      <div class="tt-table">
        <!-- Header Row: Days + Dates -->
        <div class="tt-row tt-header-row">
          <div class="tt-cell tt-time-label-cell">
            <span class="tt-label-icon">📅</span>
          </div>
          <div
            v-for="day in dayConfigs"
            :key="day.key"
            class="tt-cell tt-day-header"
            :class="day.isToday ? 'tt-day-today' : ''"
          >
            <div class="tt-day-name">{{ day.shortName }}</div>
            <div class="tt-day-date">{{ day.dateFormatted }}</div>
            <div class="tt-day-count">{{ day.slots.length }} class{{ day.slots.length !== 1 ? 'es' : '' }}</div>
          </div>
        </div>

        <!-- Time Slot Rows -->
        <div
          v-for="(timeSlot, tIdx) in timeSlots"
          :key="tIdx"
          class="tt-row"
          :class="timeSlot.isNow ? 'tt-row-now' : ''"
        >
          <!-- Time Label (left side) -->
          <div class="tt-cell tt-time-cell" :class="timeSlot.isNow ? 'tt-time-now' : ''">
            <div class="tt-time-range">{{ timeSlot.label }}</div>
            <div v-if="timeSlot.isNow" class="tt-now-indicator">NOW</div>
          </div>

          <!-- Day Columns -->
          <div
            v-for="day in dayConfigs"
            :key="day.key"
            class="tt-cell tt-slot-cell"
            :class="[
              day.isToday ? 'tt-col-today' : '',
              getCellTimeClass(day, timeSlot)
            ]"
            @click="handleCellClick(getSlotAtTime(day, timeSlot))"
          >
            <!-- Slot card inside cell -->
            <div
              v-if="getSlotAtTime(day, timeSlot)"
              class="tt-slot-card"
              :class="[
                getSlotCardClass(getSlotAtTime(day, timeSlot)),
                day.isToday ? getSlotTimeClass(getSlotAtTime(day, timeSlot)) : '',
                { 'tt-swap-target': swapMode }
              ]"
              :style="getSlotBorderStyle(getSlotAtTime(day, timeSlot))"
              draggable="true"
              @dragstart="onDragStart($event, getSlotAtTime(day, timeSlot))"
              @dragover.prevent="onDragOver($event, getSlotAtTime(day, timeSlot))"
              @drop="onDrop($event, getSlotAtTime(day, timeSlot))"
            >
              <!-- Subject -->
              <div class="tt-subject">{{ getSlotAtTime(day, timeSlot).subject?.name || getSlotAtTime(day, timeSlot).subject || 'N/A' }}</div>

              <!-- Teacher -->
              <div class="tt-teacher">
                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ getTeacherName(getSlotAtTime(day, timeSlot)) }}
              </div>

              <!-- Room -->
              <div v-if="getSlotAtTime(day, timeSlot).room" class="tt-room">
                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                {{ getSlotAtTime(day, timeSlot).room?.name || getSlotAtTime(day, timeSlot).room?.room_number }}
              </div>

              <!-- Group Badge -->
              <div v-if="getSlotAtTime(day, timeSlot).group" class="tt-group-badge">
                {{ getSlotAtTime(day, timeSlot).group?.name }}
              </div>

              <!-- Live Badge -->
              <div v-if="day.isToday && isCurrentSlot(getSlotAtTime(day, timeSlot))" class="tt-live-badge">
                <span class="tt-live-dot"></span> LIVE
              </div>
            </div>

            <!-- Empty cell -->
            <div v-else class="tt-empty-cell">
              <span class="tt-empty-dash">—</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile: Vertical Day Cards -->
    <div class="weekly-grid-mobile">
      <DayCard
        v-for="day in dayConfigs"
        :key="day.key"
        :dayName="day.fullName"
        :dayKey="day.key"
        :dayDate="day.dateFormatted"
        :slots="day.slots"
        :isToday="day.isToday"
        :swapMode="swapMode"
        @slot-click="(slot) => handleSlotClick(slot)"
        @swap="(s1, s2) => $emit('swap', s1, s2)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import DayCard from './DayCard.vue'

const props = defineProps({
  grid: { type: Object, required: true },
  days: { type: Array, default: () => [] },
  weekDates: { type: Object, default: () => ({}) },
  swapMode: { type: Boolean, default: false },
})

const emit = defineEmits(['slot-click', 'swap'])

const draggedSlot = ref(null)

// ── Collect all unique time ranges across the week ──
const timeSlots = computed(() => {
  const timeSet = new Set()
  const order = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
  order.forEach(key => {
    const slots = props.grid[key] || []
    slots.forEach(s => {
      if (s.start_time && s.end_time) {
        timeSet.add(`${s.start_time.substring(0,5)}-${s.end_time.substring(0,5)}`)
      }
    })
  })

  // Sort by start time
  const sorted = Array.from(timeSet).sort((a, b) => {
    const aStart = a.split('-')[0]
    const bStart = b.split('-')[0]
    return aStart.localeCompare(bStart)
  })

  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

  return sorted.map(range => {
    const [start, end] = range.split('-')
    return {
      start,
      end,
      range,
      label: `${formatTimeRaw(start)} – ${formatTimeRaw(end)}`,
      isNow: currentTime >= start && currentTime <= end,
    }
  })
})

function formatTimeRaw(time) {
  if (!time) return '--:--'
  const [h, m] = time.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function getSlotAtTime(day, timeSlot) {
  const slot = (day.sortedSlots || []).find(s => {
    const sStart = (s.start_time || '').substring(0, 5)
    const sEnd = (s.end_time || '').substring(0, 5)
    return sStart === timeSlot.start && sEnd === timeSlot.end
  })
  return slot || null
}

function getCellTimeClass(day, timeSlot) {
  if (!day.isToday) return ''
  const slot = getSlotAtTime(day, timeSlot)
  if (!slot) return ''
  if (isCurrentSlot(slot)) return 'tt-cell-live'
  if (isUpcomingSlot(slot)) return 'tt-cell-upcoming'
  return ''
}

const dayConfigs = computed(() => {
  const today = new Date().toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase()
  const dayMap = { sat: 'sat', sun: 'sun', mon: 'mon', tue: 'tue', wed: 'wed', thu: 'thu', fri: 'fri' }
  const todayKey = dayMap[today] || 'mon'

  const dayNames = {
    sat: { short: 'Sat', full: 'Saturday' },
    sun: { short: 'Sun', full: 'Sunday' },
    mon: { short: 'Mon', full: 'Monday' },
    tue: { short: 'Tue', full: 'Tuesday' },
    wed: { short: 'Wed', full: 'Wednesday' },
    thu: { short: 'Thu', full: 'Thursday' },
    fri: { short: 'Fri', full: 'Friday' },
  }

  const order = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']

  return order.map(key => {
    const slots = props.grid[key] || []
    const dateInfo = props.weekDates[key] || {}
    return {
      key,
      shortName: dayNames[key].short,
      fullName: dayNames[key].full,
      dateFormatted: dateInfo.formatted || '',
      isToday: key === todayKey,
      slots,
      sortedSlots: [...slots].sort((a, b) => (a.start_time || '00:00').localeCompare(b.start_time || '00:00')),
    }
  })
})

function getTeacherName(slot) {
  if (typeof slot.teacher === 'string' && slot.teacher.trim()) {
    if (/^[0-9a-f-]{8,}$/i.test(slot.teacher.trim()) || /^\d+$/.test(slot.teacher.trim())) {
      // fall through
    } else {
      return slot.teacher.trim()
    }
  }
  if (slot.teacher && typeof slot.teacher === 'object') {
    return (slot.teacher.name || '').trim() ||
      (slot.teacher.first_name && slot.teacher.last_name
        ? slot.teacher.first_name + ' ' + slot.teacher.last_name
        : '') ||
      slot.teacher.full_name ||
      ''
  }
  if (slot.teacher_name) return slot.teacher_name
  return ''
}

function formatTime(time) {
  return formatTimeRaw(time)
}

function isCurrentSlot(slot) {
  if (slot._is_live !== undefined) return !!slot._is_live
  if (!slot.start_time || !slot.end_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  const start = (slot.start_time || '').substring(0, 5)
  const end = (slot.end_time || '').substring(0, 5)
  return currentTime >= start && currentTime <= end
}

function isUpcomingSlot(slot) {
  if (!slot.start_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  const start = (slot.start_time || '').substring(0, 5)
  return start > currentTime
}

function getSlotTimeClass(slot) {
  if (isCurrentSlot(slot)) return 'tt-card-live'
  if (isUpcomingSlot(slot)) return 'tt-card-upcoming'
  return ''
}

function getSlotCardClass(slot) {
  if (slot.status === 'draft') return 'tt-draft'
  if (slot.status === 'archived') return 'tt-archived'
  return 'tt-default'
}

function getSlotBorderStyle(slot) {
  const color = slot.color || slot.subject_color || '#6366F1'
  return { borderLeftColor: color, borderLeftWidth: '3px' }
}

function handleCellClick(slot) {
  if (!slot) return
  if (props.swapMode && draggedSlot.value) {
    if (draggedSlot.value.id !== slot.id) {
      emit('swap', draggedSlot.value.id, slot.id)
    }
    draggedSlot.value = null
  } else {
    emit('slot-click', slot)
  }
}

function handleSlotClick(slot) {
  handleCellClick(slot)
}

function onDragStart(event, slot) {
  draggedSlot.value = slot
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', slot.id)
}

function onDragOver(event, slot) {
  event.dataTransfer.dropEffect = 'move'
}

function onDrop(event, targetSlot) {
  event.preventDefault()
  if (draggedSlot.value && draggedSlot.value.id !== targetSlot.id) {
    emit('swap', draggedSlot.value.id, targetSlot.id)
  }
  draggedSlot.value = null
}
</script>

<style scoped>
/* ═══════════════════════════════════════
   TIMETABLE STYLES
   ═══════════════════════════════════════ */
.tt-wrapper {
  display: none;
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  box-shadow: var(--shadow-sm);
}

@media (min-width: 1024px) {
  .tt-wrapper {
    display: block;
  }
}

.tt-table {
  display: table;
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}

.tt-row {
  display: table-row;
}

.tt-row-now {
  background: color-mix(in srgb, #22c55e 10%, var(--bg-card));
}

.tt-header-row {
  background: var(--bg-surface-muted);
}

.tt-cell {
  display: table-cell;
  padding: 10px 8px;
  border-bottom: 1px solid var(--border-light);
  border-right: 1px solid #f1f5f9;
  vertical-align: top;
}

.tt-header-row .tt-cell {
  border-bottom: 2px solid #e2e8f0;
  vertical-align: middle;
}

/* ── Time Label Column ── */
.tt-time-label-cell {
  width: 100px;
  min-width: 100px;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  background: var(--bg-surface-muted);
  border-right: 2px solid #e2e8f0;
}

.tt-label-icon {
  font-size: 1.1rem;
}

.tt-time-cell {
  width: 100px;
  min-width: 100px;
  text-align: center;
  padding: 12px 8px;
  background: var(--bg-surface-muted);
  border-right: 2px solid #e2e8f0;
  position: relative;
}

.tt-time-range {
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--text-secondary);
  line-height: 1.3;
}

.tt-time-now {
  background: color-mix(in srgb, #22c55e 16%, var(--bg-card));
}

.tt-now-indicator {
  display: inline-block;
  margin-top: 3px;
  font-size: 0.5rem;
  font-weight: 700;
  color: #16a34a;
  background: color-mix(in srgb, #22c55e 28%, var(--bg-card));
  padding: 1px 6px;
  border-radius: 4px;
  letter-spacing: 0.05em;
}

/* ── Day Headers ── */
.tt-day-header {
  text-align: center;
  padding: 12px 8px;
  min-width: 110px;
}

.tt-day-name {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.tt-day-date {
  font-size: 0.6rem;
  color: var(--text-muted);
  margin-top: 2px;
  font-weight: 500;
}

.tt-day-count {
  font-size: 0.55rem;
  color: var(--text-muted);
  margin-top: 2px;
}

.tt-day-today {
  background: color-mix(in srgb, var(--primary-color, #6366f1) 16%, var(--bg-card)) !important;
  box-shadow: inset 0 -2px 0 var(--primary-color, #6366f1);
}

.tt-day-today .tt-day-name {
  color: color-mix(in srgb, var(--primary-color, #6366f1) 60%, var(--text-primary));
}

.tt-day-today .tt-day-date {
  color: color-mix(in srgb, var(--primary-color, #6366f1) 50%, var(--text-primary));
  font-weight: 600;
}

/* ── Slot Cells ── */
.tt-slot-cell {
  padding: 6px;
  min-height: 60px;
  cursor: pointer;
  transition: background 0.15s;
}

.tt-slot-cell:hover {
  background: var(--bg-surface-muted);
}

.tt-col-today {
  background: color-mix(in srgb, var(--primary-color, #6366f1) 6%, var(--bg-card));
}

.tt-cell-live {
  background: color-mix(in srgb, #22c55e 12%, var(--bg-card)) !important;
}

.tt-cell-upcoming {
  background: color-mix(in srgb, #f59e0b 12%, var(--bg-card)) !important;
}

/* ── Slot Cards ── */
.tt-slot-card {
  position: relative;
  border-radius: 6px;
  padding: 6px 8px;
  border: 1px solid var(--border-color);
  border-left-width: 3px;
  cursor: pointer;
  transition: all 0.15s;
  min-height: 44px;
}

.tt-slot-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  transform: translateY(-1px);
}

.tt-swap-target {
  cursor: grab;
}

.tt-swap-target:active {
  cursor: grabbing;
}

.tt-default {
  background: var(--bg-card);
}

.tt-draft {
  background: color-mix(in srgb, #eab308 12%, var(--bg-card));
  border-color: color-mix(in srgb, #eab308 45%, var(--bg-card));
  opacity: 0.85;
}

.tt-archived {
  background: var(--bg-accent);
  border-color: #e5e7eb;
  opacity: 0.5;
}

/* Live / Upcoming card highlights */
.tt-card-live {
  background: color-mix(in srgb, #10b981 14%, var(--bg-card)) !important;
  border-color: #34d399 !important;
  box-shadow: 0 0 0 1px #34d399;
}

.tt-card-upcoming {
  background: color-mix(in srgb, #f59e0b 14%, var(--bg-card)) !important;
  border-color: #fbbf24 !important;
}

/* ── Card Content ── */
.tt-subject {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-dark);
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  line-height: 1.25;
  padding-right: 30px;
}

.tt-teacher {
  font-size: 0.6rem;
  color: var(--text-muted);
  display: flex;
  align-items: flex-start;
  gap: 3px;
  margin-top: 2px;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  line-height: 1.25;
}

.tt-teacher svg,
.tt-room svg {
  flex-shrink: 0;
  margin-top: 1px;
}

.tt-room {
  font-size: 0.55rem;
  color: var(--text-muted);
  display: flex;
  align-items: flex-start;
  gap: 3px;
  margin-top: 1px;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  line-height: 1.25;
}

.tt-group-badge {
  display: inline-block;
  font-size: 0.5rem;
  padding: 1px 5px;
  border-radius: 999px;
  background: var(--bg-accent);
  color: var(--text-muted);
  margin-top: 3px;
  font-weight: 500;
}

/* ── Live Badge ── */
.tt-live-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.5rem;
  font-weight: 700;
  color: #16a34a;
  background: #dcfce7;
  padding: 1px 6px;
  border-radius: 4px;
  letter-spacing: 0.03em;
}

.tt-live-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #16a34a;
  animation: tt-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* ── Empty Cell ── */
.tt-empty-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
}

.tt-empty-dash {
  font-size: 0.75rem;
  color: #d1d5db;
}

/* ── Mobile ── */
.weekly-grid-mobile {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

@media (min-width: 1024px) {
  .weekly-grid-mobile {
    display: none;
  }
}

@keyframes tt-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}
</style>
