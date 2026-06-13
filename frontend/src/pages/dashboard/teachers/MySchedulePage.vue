<template>
  <div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1>My Schedule</h1>
        <span class="badge-count">{{ todayClasses.length }} today</span>
      </div>
      <div class="header-actions">
        <button
          class="btn btn-outline"
          @click="viewMode = viewMode === 'weekly' ? 'daily' : 'weekly'"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px; vertical-align: middle;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
          {{ viewMode === 'weekly' ? 'Daily View' : 'Weekly View' }}
        </button>
      </div>
    </div>

    <!-- Weekly Stats Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eef2ff;">
          <svg width="18" height="18" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ totalSlots }}</div>
          <div class="stat-lbl">Total Classes</div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon" style="background: #ccfbf1;">
          <svg width="18" height="18" fill="none" stroke="#14b8a6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ todayClasses.length }}</div>
          <div class="stat-lbl">Today</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon" style="background: #d4edda;">
          <svg width="18" height="18" fill="none" stroke="var(--secondary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ liveClass ? 1 : 0 }}</div>
          <div class="stat-lbl">Live Now</div>
        </div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon" style="background: #f3e8ff;">
          <svg width="18" height="18" fill="none" stroke="#7c3aed" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ upcomingCount }}</div>
          <div class="stat-lbl">Upcoming</div>
        </div>
      </div>
    </div>

    <!-- Today's Overview -->
    <div class="form-card" style="margin-bottom: 1.5rem;">
      <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
        <div class="stat-icon" style="background: #eef2ff;">
          <svg width="20" height="20" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h2 style="font-size:0.875rem; font-weight:600; color:var(--text-dark);">Today's Classes</h2>
          <p style="font-size:0.75rem; color:var(--text-muted);">{{ todayName }} ({{ todayDate }}) - {{ todayClasses.length }} class{{ todayClasses.length !== 1 ? 'es' : '' }}</p>
        </div>
      </div>

      <!-- Today's Timeline -->
      <div v-if="todayClasses.length" class="timeline">
        <div
          v-for="slot in todayClasses"
          :key="slot.id"
          class="timeline-item"
          :class="isCurrentSlot(slot) ? 'current' : ''"
        >
          <!-- Time -->
          <div class="timeline-time">
            <div class="timeline-time-start">{{ formatTime(slot.start_time) }}</div>
            <div class="timeline-time-end">{{ formatTime(slot.end_time) }}</div>
          </div>

          <!-- Timeline Line -->
          <div class="timeline-line">
            <div class="timeline-dot" :class="isCurrentSlot(slot) ? 'live' : ''"></div>
          </div>

          <!-- Content -->
          <div class="timeline-content">
            <div class="timeline-content-top">
              <SubjectBadge
                :subject="slot.subject"
                :color="slot.color || slot.subject_color"
                size="sm"
              />
              <LiveIndicator v-if="isCurrentSlot(slot)" :isLive="true" />
              <span v-else-if="isUpcomingSlot(slot)" class="upcoming-tag">Upcoming</span>
            </div>
            <div class="timeline-meta">
              <span v-if="getTeacherName(slot)" class="timeline-meta-item">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ getTeacherName(slot) }}
              </span>
              <span class="timeline-meta-item">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ slot.class?.name || 'N/A' }}
              </span>
              <span v-if="slot.room" class="timeline-meta-item">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                {{ slot.room?.name || slot.room?.room_number }}
              </span>
              <span v-if="slot.group" class="timeline-meta-item">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                {{ slot.group?.name }}
              </span>
            </div>
          </div>

          <!-- Live Badge -->
          <div v-if="isCurrentSlot(slot)" class="timeline-badge">
            <span class="live-badge">
              <span class="live-badge-dot"></span>
              LIVE
            </span>
          </div>
        </div>
      </div>

      <!-- No classes today -->
      <div v-else-if="!loading" class="empty-state" style="padding: 1.5rem;">
        <p>No classes scheduled for today</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading your schedule...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="fetchSchedule">Try Again</button>
    </div>

    <!-- Weekly Schedule -->
    <div v-else-if="viewMode === 'weekly'">
      <h2 style="font-size:0.875rem; font-weight:600; color:var(--text-dark); margin-bottom:0.75rem;">Weekly Schedule</h2>
      <WeeklyGrid
        :grid="weeklyGrid"
        :weekDates="weekDates"
        @slot-click="() => {}"
      />
    </div>

    <!-- Daily View: All Days as Cards -->
    <div v-else class="daily-view">
      <DayCard
        v-for="day in dayCards"
        :key="day.key"
        :dayName="day.fullName"
        :dayKey="day.key"
        :dayDate="day.dateFormatted"
        :slots="day.slots"
        :isToday="day.isToday"
        @slot-click="() => {}"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import classRoutineService from '@/services/class-routine.service'
import WeeklyGrid from '@/components/routine/WeeklyGrid.vue'
import DayCard from '@/components/routine/DayCard.vue'
import SubjectBadge from '@/components/routine/SubjectBadge.vue'
import LiveIndicator from '@/components/routine/LiveIndicator.vue'

const viewMode = ref('weekly')
const schedule = ref([])
const loading = ref(true)
const error = ref(null)

const days = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
const dayNames = {
  sat: 'Saturday', sun: 'Sunday', mon: 'Monday', tue: 'Tuesday',
  wed: 'Wednesday', thu: 'Thursday', fri: 'Friday',
}

const today = new Date().toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase()
const dayMap = { sat: 'sat', sun: 'sun', mon: 'mon', tue: 'tue', wed: 'wed', thu: 'thu', fri: 'fri' }
const todayKey = dayMap[today] || 'mon'
const todayName = dayNames[todayKey]

// Week dates calculation
const weekDates = computed(() => {
  const now = new Date()
  const currentDay = now.getDay()
  const satOffset = (currentDay + 1) % 7
  const saturday = new Date(now)
  saturday.setDate(now.getDate() - satOffset)

  const dates = {}
  const dayOrder = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']
  dayOrder.forEach((key, index) => {
    const d = new Date(saturday)
    d.setDate(saturday.getDate() + index)
    dates[key] = {
      date: d,
      formatted: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      fullDate: d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
    }
  })
  return dates
})

const todayDate = computed(() => weekDates.value[todayKey]?.formatted || '')

const weeklyGrid = computed(() => {
  const grid = {}
  days.forEach(d => { grid[d] = [] })
  schedule.value.forEach(r => {
    if (grid[r.day_of_week]) grid[r.day_of_week].push(r)
  })
  days.forEach(d => {
    grid[d].sort((a, b) => (a.start_time || '00:00').localeCompare(b.start_time || '00:00'))
  })
  return grid
})

const todayClasses = computed(() => weeklyGrid.value[todayKey] || [])

const totalSlots = computed(() => schedule.value.length)

const liveClass = computed(() => {
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return todayClasses.value.find(r => {
    if (!r.start_time || !r.end_time) return false
    return currentTime >= r.start_time && currentTime <= r.end_time
  }) || null
})

const upcomingCount = computed(() => {
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return todayClasses.value.filter(r => {
    if (!r.start_time) return false
    return r.start_time > currentTime
  }).length
})

const dayCards = computed(() => {
  return days.map(key => ({
    key,
    fullName: dayNames[key],
    dateFormatted: weekDates.value[key]?.formatted || '',
    isToday: key === todayKey,
    slots: weeklyGrid.value[key] || [],
  }))
})

function formatTime(time) {
  if (!time) return '--:--'
  const [h, m] = time.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function isCurrentSlot(slot) {
  if (!slot.start_time || !slot.end_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return currentTime >= slot.start_time && currentTime <= slot.end_time
}

function isUpcomingSlot(slot) {
  if (!slot.start_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return slot.start_time > currentTime
}

function getTeacherName(slot) {
  // If teacher is a plain string (could be a UUID or name)
  if (typeof slot.teacher === 'string' && slot.teacher.trim()) {
    // If it looks like a UUID/ID (contains hyphens or is purely numeric), don't show it
    if (/^[0-9a-f-]{8,}$/i.test(slot.teacher.trim()) || /^\d+$/.test(slot.teacher.trim())) {
      // Fall through to teacher_name fallback
    } else {
      return slot.teacher.trim()
    }
  }
  // If teacher is an object with name fields
  if (slot.teacher && typeof slot.teacher === 'object') {
    return (slot.teacher.name || '').trim() ||
      (slot.teacher.first_name && slot.teacher.last_name
        ? slot.teacher.first_name + ' ' + slot.teacher.last_name
        : '') ||
      slot.teacher.full_name ||
      ''
  }
  // Fallback: direct teacher_name field
  if (slot.teacher_name) return slot.teacher_name
  return ''
}

async function fetchSchedule() {
  loading.value = true
  error.value = null
  try {
    const res = await classRoutineService.getTeacherSchedule()
    // API returns { data: { weekly: {...}, flat: [...] } }
    const data = res.data?.data || res.data || {}
    schedule.value = data.flat || []
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load schedule'
  } finally {
    loading.value = false
  }
}

onMounted(fetchSchedule)
</script>

<style scoped>
.stats-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

@media (min-width: 640px) {
  .stats-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  border: 1px solid var(--border-color);
  transition: box-shadow 0.2s;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
}

.stat-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-val {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}

.stat-lbl {
  font-size: 0.6875rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: var(--radius-sm);
  transition: background 0.2s;
  background: var(--bg-accent);
  border: 1px solid var(--border-light);
}

.timeline-item.current {
  background: #d4edda;
  border-color: #c3e6cb;
}

.timeline-time {
  flex-shrink: 0;
  width: 4rem;
  text-align: center;
}

.timeline-time-start {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-dark);
}

.timeline-time-end {
  font-size: 0.625rem;
  color: var(--text-muted);
}

.timeline-line {
  flex-shrink: 0;
  position: relative;
  width: 0.125rem;
  height: 2rem;
  background: var(--border-color);
  border-radius: 9999px;
}

.timeline-dot {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 50%;
  background: var(--border-color);
}

.timeline-dot.live {
  background: #28a745;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.timeline-content {
  flex: 1;
  min-width: 0;
}

.timeline-content-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.timeline-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.timeline-meta-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.timeline-meta-item svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.timeline-badge {
  flex-shrink: 0;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #d4edda;
  color: #155724;
}

.live-badge-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  background: #28a745;
  margin-right: 0.25rem;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.upcoming-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.0625rem 0.375rem;
  border-radius: 9999px;
  font-size: 0.6875rem;
  font-weight: 500;
  background: #dbeafe;
  color: #1d4ed8;
}

.daily-view {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
