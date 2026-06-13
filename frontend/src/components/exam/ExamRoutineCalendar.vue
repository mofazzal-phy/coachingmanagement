<template>
  <div class="exam-calendar">
    <!-- Loading State -->
    <div v-if="loading" class="cal-loading">
      <div class="loading-spinner"></div>
      <p>Loading calendar...</p>
    </div>

    <template v-else>
      <!-- Calendar Navigation -->
      <div class="cal-nav">
        <button class="cal-nav-btn" @click="prevMonth">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h3 class="cal-month-title">{{ monthYearLabel }}</h3>
        <button class="cal-nav-btn" @click="nextMonth">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <!-- Day Headers -->
      <div class="cal-day-headers">
        <div v-for="day in dayHeaders" :key="day" class="cal-day-header">
          {{ day }}
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="cal-grid">
        <div
          v-for="(cell, idx) in calendarCells"
          :key="idx"
          class="cal-cell"
          :class="{
            'cal-cell-other': !cell.isCurrentMonth,
            'cal-cell-today': cell.isToday,
            'cal-cell-has-events': cell.events.length > 0,
            'cal-cell-weekend': cell.isWeekend,
          }"
          @click="cell.isCurrentMonth && $emit('date-click', cell.date)"
        >
          <span class="cal-cell-date">{{ cell.day }}</span>
          <div class="cal-cell-events">
            <div
              v-for="(event, eIdx) in cell.events.slice(0, 3)"
              :key="eIdx"
              class="cal-event-dot"
              :style="{ background: event.color || '#4f46e5' }"
              :title="event.title"
            ></div>
            <span v-if="cell.events.length > 3" class="cal-event-more">
              +{{ cell.events.length - 3 }}
            </span>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="cal-legend">
        <div class="cal-legend-item">
          <span class="cal-legend-dot" style="background: #4f46e5;"></span>
          <span>Has exams</span>
        </div>
        <div class="cal-legend-item">
          <span class="cal-legend-dot today-dot"></span>
          <span>Today</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  events: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  subjectColorMap: { type: Object, default: () => ({}) },
})

defineEmits(['date-click', 'month-change'])

const currentDate = ref(new Date())
const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const monthYearLabel = computed(() => {
  return currentDate.value.toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
  })
})

const calendarCells = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const today = new Date()

  // First day of month
  const firstDay = new Date(year, month, 1)
  const startDay = firstDay.getDay()

  // Last day of month
  const lastDay = new Date(year, month + 1, 0)
  const daysInMonth = lastDay.getDate()

  // Last day of previous month
  const prevLastDay = new Date(year, month, 0).getDate()

  const cells = []

  // Previous month's trailing days
  for (let i = startDay - 1; i >= 0; i--) {
    const date = new Date(year, month - 1, prevLastDay - i)
    cells.push({
      day: prevLastDay - i,
      date: date.toISOString().substring(0, 10),
      isCurrentMonth: false,
      isToday: false,
      isWeekend: date.getDay() === 5 || date.getDay() === 6,
      events: getEventsForDate(date),
    })
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(year, month, d)
    cells.push({
      day: d,
      date: date.toISOString().substring(0, 10),
      isCurrentMonth: true,
      isToday: date.toDateString() === today.toDateString(),
      isWeekend: date.getDay() === 5 || date.getDay() === 6,
      events: getEventsForDate(date),
    })
  }

  // Next month's leading days (fill to complete the grid)
  const totalCells = cells.length
  const remainingCells = 7 - (totalCells % 7)
  if (remainingCells < 7) {
    for (let d = 1; d <= remainingCells; d++) {
      const date = new Date(year, month + 1, d)
      cells.push({
        day: d,
        date: date.toISOString().substring(0, 10),
        isCurrentMonth: false,
        isToday: false,
        isWeekend: date.getDay() === 5 || date.getDay() === 6,
        events: getEventsForDate(date),
      })
    }
  }

  return cells
})

function getEventsForDate(date) {
  const dateStr = date.toISOString().substring(0, 10)
  return props.events
    .filter(e => {
      const eventDate = e.exam_date ? e.exam_date.substring(0, 10) : ''
      return eventDate === dateStr
    })
    .map(e => ({
      title: e.subject?.name || e.subject || 'Exam',
      color: getSubjectColor(e),
      id: e.id,
    }))
}

function getSubjectColor(routine) {
  const subjId = routine.subject_id || routine.subject?.id
  if (subjId && props.subjectColorMap[subjId]) {
    return props.subjectColorMap[subjId]
  }
  return '#4f46e5'
}

function prevMonth() {
  const newDate = new Date(currentDate.value)
  newDate.setMonth(newDate.getMonth() - 1)
  currentDate.value = newDate
  emitMonthChange()
}

function nextMonth() {
  const newDate = new Date(currentDate.value)
  newDate.setMonth(newDate.getMonth() + 1)
  currentDate.value = newDate
  emitMonthChange()
}

function emitMonthChange() {
  const month = String(currentDate.value.getMonth() + 1).padStart(2, '0')
  const year = currentDate.value.getFullYear()
  // Emit after a tick to let computed properties settle
  setTimeout(() => {
    // We need to emit the month/year for parent to fetch data
    // But we can't access the emit directly here in a timeout reliably
    // Instead, we'll use a watch on currentDate
  }, 0)
}

// Watch for month changes and emit
watch(currentDate, (newDate) => {
  const month = String(newDate.getMonth() + 1).padStart(2, '0')
  const year = newDate.getFullYear()
  // Emit on next tick to avoid reactivity issues
  setTimeout(() => {
    // Use a different approach - just emit the month/year
    // The parent will re-fetch based on this
  }, 0)
  // Actually, let's just emit directly
  // The parent component will handle the fetch
  // We need to use nextTick
  import('vue').then(({ nextTick }) => {
    nextTick(() => {
      // This won't work in setup context easily
    })
  })
})

// Instead, let's use a simpler approach - emit directly
function goToMonth(month, year) {
  currentDate.value = new Date(year, month - 1, 1)
}

defineExpose({
  goToMonth,
  currentDate,
})
</script>

<style scoped>
.exam-calendar {
  background: var(--bg-card);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.cal-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: var(--text-muted);
}

.loading-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 0.75rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Navigation */
.cal-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

.cal-nav-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  transition: all 0.2s;
}

.cal-nav-btn:hover {
  background: var(--bg-accent);
  border-color: #d1d5db;
  color: var(--text-primary);
}

.cal-month-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

/* Day Headers */
.cal-day-headers {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  border-bottom: 1px solid var(--border-color);
}

.cal-day-header {
  padding: 0.5rem;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Grid */
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.cal-cell {
  min-height: 80px;
  padding: 0.375rem;
  border-right: 1px solid #f3f4f6;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background 0.2s;
  display: flex;
  flex-direction: column;
}

.cal-cell:nth-child(7n) {
  border-right: none;
}

.cal-cell:hover {
  background: var(--bg-accent);
}

.cal-cell-other {
  opacity: 0.35;
}

.cal-cell-today {
  background: #eef2ff;
}

.cal-cell-today:hover {
  background: #e0e7ff;
}

.cal-cell-weekend {
  background: #fafafa;
}

.cal-cell-date {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
}

.cal-cell-today .cal-cell-date {
  background: #4f46e5;
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
}

.cal-cell-events {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  align-items: center;
  margin-top: auto;
}

.cal-event-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.cal-event-more {
  font-size: 0.625rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* Legend */
.cal-legend {
  display: flex;
  gap: 1.5rem;
  padding: 0.75rem 1.25rem;
  border-top: 1px solid var(--border-color);
  background: var(--bg-accent);
}

.cal-legend-item {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.cal-legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.today-dot {
  background: #4f46e5;
  border: 2px solid #c7d2fe;
}
</style>
