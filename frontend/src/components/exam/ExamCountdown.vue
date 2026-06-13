<template>
  <div class="exam-countdown" :class="{ 'is-today': isToday, 'is-past': isPast }">
    <!-- Header -->
    <div class="countdown-header">
      <div class="countdown-header-left">
        <svg class="countdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h3 class="countdown-title">{{ exam?.name || 'Next Exam' }}</h3>
          <p class="countdown-subtitle">{{ exam?.exam_type?.name || '' }}</p>
        </div>
      </div>
      <span v-if="isToday" class="countdown-badge badge-today">Today</span>
      <span v-else-if="isPast" class="countdown-badge badge-past">Past</span>
      <span v-else class="countdown-badge badge-upcoming">Upcoming</span>
    </div>

    <!-- Timer Display -->
    <div class="countdown-timer" v-if="!isPast">
      <div class="timer-block">
        <span class="timer-value">{{ display.days }}</span>
        <span class="timer-label">Days</span>
      </div>
      <span class="timer-separator">:</span>
      <div class="timer-block">
        <span class="timer-value">{{ display.hours }}</span>
        <span class="timer-label">Hours</span>
      </div>
      <span class="timer-separator">:</span>
      <div class="timer-block">
        <span class="timer-value">{{ display.minutes }}</span>
        <span class="timer-label">Minutes</span>
      </div>
    </div>

    <!-- Today's Info -->
    <div v-if="isToday && todayRoutines.length" class="countdown-today">
      <h4 class="today-title">Today's Schedule</h4>
      <div
        v-for="routine in todayRoutines"
        :key="routine.id"
        class="today-slot"
      >
        <span class="today-slot-subject">{{ routine.subject?.name || 'N/A' }}</span>
        <span class="today-slot-time">
          {{ formatTime(routine.start_time) }} - {{ formatTime(routine.end_time) }}
        </span>
        <span v-if="routine.room" class="today-slot-room">{{ routine.room?.name || routine.room?.room_number }}</span>
      </div>
    </div>

    <!-- Exam Info -->
    <div class="countdown-info">
      <div class="info-row">
        <span class="info-label">Date</span>
        <span class="info-value">{{ examDateFormatted }}</span>
      </div>
      <div class="info-row" v-if="exam?.class">
        <span class="info-label">Class</span>
        <span class="info-value">{{ exam.class.name }}</span>
      </div>
      <div class="info-row" v-if="exam?.course">
        <span class="info-label">Course</span>
        <span class="info-value">{{ exam.course.name }}</span>
      </div>
      <div class="info-row" v-if="exam?.batch">
        <span class="info-label">Batch</span>
        <span class="info-value">{{ exam.batch.name }}</span>
      </div>
      <div class="info-row" v-if="totalSubjects">
        <span class="info-label">Subjects</span>
        <span class="info-value">{{ totalSubjects }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  exam: { type: Object, default: null },
  nextExamDate: { type: String, default: '' },
  todayRoutines: { type: Array, default: () => [] },
  totalSubjects: { type: Number, default: 0 },
})

const now = ref(new Date())
let timer = null

const examDate = computed(() => {
  if (props.nextExamDate) return new Date(props.nextExamDate + 'T00:00:00')
  if (props.exam?.start_date) return new Date(props.exam.start_date + 'T00:00:00')
  return null
})

const isToday = computed(() => {
  if (!examDate.value) return false
  const today = new Date()
  return examDate.value.toDateString() === today.toDateString()
})

const isPast = computed(() => {
  if (!examDate.value) return false
  return examDate.value < new Date(new Date().toDateString())
})

const diffMs = computed(() => {
  if (!examDate.value || isPast.value) return 0
  return examDate.value.getTime() - now.value.getTime()
})

const display = computed(() => {
  const ms = Math.max(0, diffMs.value)
  const totalSeconds = Math.floor(ms / 1000)
  const days = Math.floor(totalSeconds / 86400)
  const hours = Math.floor((totalSeconds % 86400) / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  return {
    days: String(days).padStart(2, '0'),
    hours: String(hours).padStart(2, '0'),
    minutes: String(minutes).padStart(2, '0'),
  }
})

const examDateFormatted = computed(() => {
  if (!examDate.value) return 'TBD'
  return examDate.value.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
})

function formatTime(time) {
  if (!time) return '--:--'
  return time.substring(0, 5)
}

function updateTime() {
  now.value = new Date()
}

onMounted(() => {
  timer = setInterval(updateTime, 60000) // Update every minute
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.exam-countdown {
  background: var(--bg-card);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
  transition: box-shadow 0.3s;
}

.exam-countdown:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.exam-countdown.is-today {
  border-color: #4f46e5;
  box-shadow: 0 0 0 1px #4f46e5, 0 4px 16px rgba(79, 70, 229, 0.1);
}

/* Header */
.countdown-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1.25rem 1.25rem 0.75rem;
}

.countdown-header-left {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.countdown-icon {
  width: 28px;
  height: 28px;
  color: #4f46e5;
  flex-shrink: 0;
  margin-top: 2px;
}

.countdown-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.countdown-subtitle {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin: 0.125rem 0 0;
}

.countdown-badge {
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  white-space: nowrap;
}

.badge-today {
  background: #eef2ff;
  color: #4f46e5;
}

.badge-past {
  background: #f3f4f6;
  color: var(--text-muted);
}

.badge-upcoming {
  background: #ecfdf5;
  color: #059669;
}

/* Timer */
.countdown-timer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
  margin: 0 1.25rem;
  border-radius: 12px;
}

.timer-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 60px;
}

.timer-value {
  font-size: 2rem;
  font-weight: 700;
  color: #4f46e5;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.timer-label {
  font-size: 0.625rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 500;
  margin-top: 0.25rem;
}

.timer-separator {
  font-size: 1.75rem;
  font-weight: 700;
  color: #c7d2fe;
  margin-top: -0.5rem;
}

/* Today's Schedule */
.countdown-today {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid var(--border-color);
}

.today-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem;
}

.today-slot {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  background: var(--bg-accent);
  border-radius: 8px;
  margin-bottom: 0.375rem;
  font-size: 0.8125rem;
}

.today-slot:last-child {
  margin-bottom: 0;
}

.today-slot-subject {
  font-weight: 500;
  color: var(--text-primary);
  flex: 1;
}

.today-slot-time {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.today-slot-room {
  color: var(--text-muted);
  font-size: 0.75rem;
  background: #f3f4f6;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
}

/* Info */
.countdown-info {
  padding: 0.75rem 1.25rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8125rem;
}

.info-label {
  color: var(--text-muted);
}

.info-value {
  color: var(--text-primary);
  font-weight: 500;
}
</style>
