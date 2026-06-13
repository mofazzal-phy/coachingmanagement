<template>
  <div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1>My Exam Schedule</h1>
        <span class="badge-count">{{ totalDuties }} duties</span>
      </div>
      <div class="header-actions">
        <button
          class="btn btn-outline"
          @click="viewMode = viewMode === 'upcoming' ? 'today' : 'upcoming'"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px; vertical-align: middle;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          {{ viewMode === 'upcoming' ? "Today's View" : 'Upcoming View' }}
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eef2ff;">
          <svg width="18" height="18" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ totalDuties }}</div>
          <div class="stat-lbl">Total Duties</div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon" style="background: #ccfbf1;">
          <svg width="18" height="18" fill="none" stroke="#14b8a6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <div class="stat-val">{{ todayDuties.length }}</div>
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
          <div class="stat-val">{{ completedCount }}</div>
          <div class="stat-lbl">Completed</div>
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

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading exam schedule...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="fetchSchedule">Try Again</button>
    </div>

    <!-- Today's Duties -->
    <template v-else-if="viewMode === 'today'">
      <div class="form-card" style="margin-bottom: 1.5rem;">
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
          <div class="stat-icon" style="background: #eef2ff;">
            <svg width="20" height="20" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h2 style="font-size:0.875rem; font-weight:600; color:var(--text-dark);">Today's Exam Duties</h2>
            <p style="font-size:0.75rem; color:var(--text-muted);">{{ formatDate(todayDate) }} — {{ todayDuties.length }} dut{{ todayDuties.length !== 1 ? 'ies' : 'y' }}</p>
          </div>
        </div>

        <!-- Today's Timeline -->
        <div v-if="todayDuties.length" class="timeline">
          <div
            v-for="slot in todayDuties"
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
                <span class="subject-badge" :style="{ background: getSubjectColor(slot.subject_name || '') + '20', color: getSubjectColor(slot.subject_name || '') }">
                  {{ slot.subject_name || slot.subject?.name || 'N/A' }}
                </span>
                <span v-if="slot.exam_type_name" class="exam-type-tag">{{ slot.exam_type_name }}</span>
                <span v-if="isCurrentSlot(slot)" class="live-badge">
                  <span class="live-badge-dot"></span>
                  ONGOING
                </span>
                <span v-else-if="isUpcomingSlot(slot)" class="upcoming-tag">Upcoming</span>
                <span v-else class="completed-tag">Completed</span>
              </div>
              <div class="timeline-meta">
                <span class="timeline-meta-item">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                  {{ slot.room_name || slot.room?.name || slot.room?.room_number || 'N/A' }}
                </span>
                <span class="timeline-meta-item">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  {{ slot.exam_name || slot.exam?.name || 'N/A' }}
                </span>
                <span class="timeline-meta-item">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  {{ slot.total_marks || 'N/A' }} marks
                </span>
                <span v-if="slot.batch_name" class="timeline-meta-item">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  {{ slot.batch_name }}
                </span>
              </div>
            </div>

            <!-- Action Button -->
            <div class="timeline-action">
              <button
                class="btn btn-sm btn-primary"
                @click="openAttendance(slot)"
                :disabled="isPastSlot(slot)"
              >
                {{ isPastSlot(slot) ? 'Done' : 'Mark Attendance' }}
              </button>
            </div>
          </div>
        </div>

        <!-- No duties today -->
        <div v-else class="empty-state" style="padding: 1.5rem;">
          <p>No exam duties scheduled for today</p>
        </div>
      </div>
    </template>

    <!-- Upcoming Duties -->
    <template v-else>
      <h2 style="font-size:0.875rem; font-weight:600; color:var(--text-dark); margin-bottom:0.75rem;">Upcoming Exam Duties</h2>

      <div v-if="upcomingDuties.length === 0" class="empty-state">
        <p>No upcoming exam duties</p>
      </div>

      <div v-else class="upcoming-list">
        <div
          v-for="slot in upcomingDuties"
          :key="slot.id"
          class="upcoming-card"
        >
          <div class="upcoming-date-badge">
            <div class="upcoming-date-day">{{ getDayName(slot.exam_date) }}</div>
            <div class="upcoming-date-num">{{ getDayNumber(slot.exam_date) }}</div>
            <div class="upcoming-date-month">{{ getMonthName(slot.exam_date) }}</div>
          </div>
          <div class="upcoming-info">
            <div class="upcoming-info-top">
              <span class="subject-badge" :style="{ background: getSubjectColor(slot.subject_name || '') + '20', color: getSubjectColor(slot.subject_name || '') }">
                {{ slot.subject_name || slot.subject?.name || 'N/A' }}
              </span>
              <span v-if="slot.exam_type_name" class="exam-type-tag">{{ slot.exam_type_name }}</span>
              <span class="upcoming-exam-name">{{ slot.exam_name || slot.exam?.name || 'N/A' }}</span>
            </div>
            <div class="upcoming-meta">
              <span>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ formatTime(slot.start_time) }} - {{ formatTime(slot.end_time) }}
              </span>
              <span>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                {{ slot.room_name || slot.room?.name || slot.room?.room_number || 'N/A' }}
              </span>
              <span>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                {{ slot.total_marks || 'N/A' }} marks
              </span>
              <span v-if="slot.batch_name">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {{ slot.batch_name }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Attendance Modal -->
    <div v-if="showAttendanceModal" class="modal-overlay" @click.self="showAttendanceModal = false">
      <div class="modal-container">
        <div class="modal-header">
          <h3>Mark Attendance</h3>
          <button class="modal-close" @click="showAttendanceModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="attendance-info">
            <div class="attendance-info-item">
              <span class="attendance-label">Subject</span>
              <span class="attendance-value">{{ selectedSlot?.subject_name || selectedSlot?.subject?.name || 'N/A' }}</span>
            </div>
            <div class="attendance-info-item">
              <span class="attendance-label">Exam</span>
              <span class="attendance-value">{{ selectedSlot?.exam_name || selectedSlot?.exam?.name || 'N/A' }}</span>
            </div>
            <div class="attendance-info-item">
              <span class="attendance-label">Date</span>
              <span class="attendance-value">{{ formatDate(selectedSlot?.exam_date) }}</span>
            </div>
            <div class="attendance-info-item">
              <span class="attendance-label">Time</span>
              <span class="attendance-value">{{ formatTime(selectedSlot?.start_time) }} - {{ formatTime(selectedSlot?.end_time) }}</span>
            </div>
          </div>

          <div class="attendance-form">
            <div class="form-group">
              <label class="form-label">Attendance Status</label>
              <div class="radio-group">
                <label class="radio-label">
                  <input type="radio" v-model="attendanceData.status" value="present" />
                  <span>Present</span>
                </label>
                <label class="radio-label">
                  <input type="radio" v-model="attendanceData.status" value="absent" />
                  <span>Absent</span>
                </label>
                <label class="radio-label">
                  <input type="radio" v-model="attendanceData.status" value="late" />
                  <span>Late</span>
                </label>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Notes (Optional)</label>
              <textarea
                v-model="attendanceData.notes"
                class="form-input"
                rows="2"
                placeholder="Any additional notes..."
              ></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showAttendanceModal = false">Cancel</button>
          <button class="btn btn-primary" @click="submitAttendance" :disabled="attendanceSubmitting">
            <span v-if="attendanceSubmitting" class="spinner-sm"></span>
            {{ attendanceSubmitting ? 'Submitting...' : 'Submit Attendance' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import examService from '@/services/exam.service'

// ===== Duties State =====
const viewMode = ref('today')
const schedule = ref([])
const loading = ref(true)
const error = ref(null)
const showAttendanceModal = ref(false)
const selectedSlot = ref(null)
const attendanceSubmitting = ref(false)
const attendanceData = ref({
  status: 'present',
  notes: '',
})

const SUBJECT_COLORS = [
  '#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626',
  '#7c3aed', '#db2777', '#2563eb', '#16a34a', '#ca8a04',
  '#9333ea', '#e11d48', '#0d9488', '#65a30d', '#f97316',
]

const todayDate = new Date().toISOString().split('T')[0]

// ===== Duties Computed =====
const todayDuties = computed(() => {
  return schedule.value.filter(r => r.exam_date === todayDate)
})

const upcomingDuties = computed(() => {
  return schedule.value.filter(r => r.exam_date > todayDate)
    .sort((a, b) => a.exam_date.localeCompare(b.exam_date) || (a.start_time || '00:00').localeCompare(b.start_time || '00:00'))
})

const totalDuties = computed(() => schedule.value.length)

const completedCount = computed(() => {
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return todayDuties.value.filter(r => {
    if (!r.end_time) return false
    return r.end_time < currentTime
  }).length
})

const upcomingCount = computed(() => {
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return todayDuties.value.filter(r => {
    if (!r.start_time) return false
    return r.start_time > currentTime
  }).length
})

// ===== Utility Functions =====
function formatTime(time) {
  if (!time) return '--:--'
  const [h, m] = time.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  })
}

function getDayName(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short' })
}

function getDayNumber(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').getDate()
}

function getMonthName(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short' })
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

function isPastSlot(slot) {
  if (!slot.end_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  return currentTime > slot.end_time
}

function getSubjectColor(subjectName) {
  if (!subjectName) return SUBJECT_COLORS[0]
  let hash = 0
  for (let i = 0; i < subjectName.length; i++) {
    hash = subjectName.charCodeAt(i) + ((hash << 5) - hash)
  }
  return SUBJECT_COLORS[Math.abs(hash) % SUBJECT_COLORS.length]
}

function openAttendance(slot) {
  selectedSlot.value = slot
  attendanceData.value = { status: 'present', notes: '' }
  showAttendanceModal.value = true
}

async function submitAttendance() {
  if (!selectedSlot.value) return
  attendanceSubmitting.value = true
  try {
    await examService.teacher.markAttendance(selectedSlot.value.id, attendanceData.value)
    showAttendanceModal.value = false
    await fetchSchedule()
  } catch (e) {
    console.error('Failed to mark attendance:', e)
  } finally {
    attendanceSubmitting.value = false
  }
}

async function fetchSchedule() {
  loading.value = true
  error.value = null
  try {
    const res = await examService.teacher.schedule()
    const data = res.data?.data || res.data || []
    if (Array.isArray(data)) {
      schedule.value = data
    } else if (data?.flat && Array.isArray(data.flat)) {
      schedule.value = data.flat
    } else if (data?.routines && Array.isArray(data.routines)) {
      schedule.value = data.routines
    } else {
      schedule.value = []
      console.warn('[TeacherExamRoutine] Unexpected response format:', data)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load exam schedule'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSchedule()
})
</script>

<style scoped>
.page-container {
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-left h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
}

.badge-count {
  background: #eef2ff;
  color: var(--primary-color);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
}

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
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 0.125rem;
}

.form-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 1.25rem;
  border: 1px solid var(--border-color);
}

/* Timeline */
.timeline {
  position: relative;
}

.timeline-item {
  display: flex;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-color);
  align-items: flex-start;
  transition: background 0.2s;
}

.timeline-item:last-child {
  border-bottom: none;
}

.timeline-item.current {
  background: #f0fdf4;
  margin: 0 -1rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  border-bottom-color: transparent;
}

.timeline-time {
  min-width: 70px;
  text-align: right;
  flex-shrink: 0;
}

.timeline-time-start {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
}

.timeline-time-end {
  font-size: 0.6875rem;
  color: var(--text-muted);
  margin-top: 0.125rem;
}

.timeline-line {
  width: 2px;
  background: var(--border-color);
  position: relative;
  flex-shrink: 0;
  align-self: stretch;
  margin: 0.25rem 0;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--border-color);
  position: absolute;
  top: 0.25rem;
  left: 50%;
  transform: translateX(-50%);
}

.timeline-dot.live {
  background: var(--secondary-color);
  box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.2);
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
  margin-bottom: 0.375rem;
}

.subject-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.1875rem 0.5rem;
  border-radius: 0.375rem;
  white-space: nowrap;
}

.exam-type-tag {
  font-size: 0.625rem;
  font-weight: 600;
  color: #6b21a8;
  background: #f3e8ff;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--secondary-color);
  background: #f0fdf4;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
}

.live-badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--secondary-color);
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.upcoming-tag {
  font-size: 0.6875rem;
  font-weight: 600;
  color: #d97706;
  background: #fef3c7;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
}

.completed-tag {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--text-muted);
  background: #f3f4f6;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
}

.timeline-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.timeline-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--text-muted);
}

.timeline-meta-item svg {
  flex-shrink: 0;
}

.timeline-action {
  flex-shrink: 0;
  align-self: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
}

.btn-outline {
  background: var(--bg-card);
  color: var(--text-dark);
  border-color: var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-accent);
  border-color: #d1d5db;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

/* Upcoming List */
.upcoming-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.upcoming-card {
  display: flex;
  gap: 1rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  transition: box-shadow 0.2s;
}

.upcoming-card:hover {
  box-shadow: var(--shadow-md);
}

.upcoming-date-badge {
  text-align: center;
  min-width: 60px;
  padding: 0.5rem;
  background: var(--bg-accent);
  border-radius: 0.5rem;
  flex-shrink: 0;
}

.upcoming-date-day {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.upcoming-date-num {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}

.upcoming-date-month {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
}

.upcoming-info {
  flex: 1;
  min-width: 0;
}

.upcoming-info-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.375rem;
}

.upcoming-exam-name {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.upcoming-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.upcoming-meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--text-muted);
}

.upcoming-meta span svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-container {
  background: var(--bg-card);
  border-radius: 0.75rem;
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.modal-body {
  padding: 1.25rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-color);
}

.attendance-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

.attendance-info-item {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.attendance-label {
  font-size: 0.6875rem;
  color: var(--text-muted);
  font-weight: 500;
}

.attendance-value {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
}

.attendance-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
}

.form-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  color: var(--text-dark);
  background: var(--bg-card);
  transition: border-color 0.2s;
  font-family: inherit;
  resize: vertical;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.radio-group {
  display: flex;
  gap: 1rem;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: var(--text-dark);
  cursor: pointer;
}

.radio-label input[type="radio"] {
  accent-color: var(--primary-color);
}

.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
}

.loading-state p, .error-state p, .empty-state p {
  margin: 0.75rem 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  margin: 0 auto;
}
</style>