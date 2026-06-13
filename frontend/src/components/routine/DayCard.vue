<template>
  <div class="day-card">
    <!-- Day Header -->
    <div
      class="day-card-header"
      :class="isToday ? 'today' : ''"
    >
      <div class="day-card-header-left">
        <h3 class="day-card-title">{{ dayName }}</h3>
        <span v-if="dayDate" class="day-card-date">{{ dayDate }}</span>
        <span
          v-if="isToday"
          class="badge badge-today"
        >
          Today
        </span>
      </div>
      <span class="day-card-count">{{ slots.length }} class{{ slots.length !== 1 ? 'es' : '' }}</span>
    </div>

    <!-- Slots List -->
    <div class="day-card-body">
      <div
        v-for="slot in sortedSlots"
        :key="slot.id"
        class="day-card-slot"
        :class="[
          { 'slot-swap-active': swapMode },
          isToday ? getSlotTimeClass(slot) : ''
        ]"
        @click="handleSlotClick(slot)"
      >
        <div class="slot-row">
          <!-- Time Column -->
          <div class="slot-time">
            <div class="slot-time-start">{{ formatTime(slot.start_time) }}</div>
            <div class="slot-time-end">{{ formatTime(slot.end_time) }}</div>
          </div>

          <!-- Divider -->
          <div class="slot-divider"></div>

          <!-- Content -->
          <div class="slot-content">
            <div class="slot-content-top">
              <SubjectBadge
                :subject="slot.subject"
                :color="slot.color || slot.subject_color"
                size="sm"
              />
              <LiveIndicator v-if="isToday && isCurrentSlot(slot)" :isLive="true" />
              <span v-else-if="isToday && isUpcomingSlot(slot)" class="upcoming-tag">Upcoming</span>
            </div>
            <div class="slot-meta">
              <span v-if="slot.teacher" class="slot-meta-item">
                <svg class="slot-meta-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ getTeacherName(slot) }}
              </span>
              <span v-if="slot.room" class="slot-meta-item">
                <svg class="slot-meta-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                {{ slot.room?.name || slot.room?.room_number || 'N/A' }}
              </span>
              <span v-if="slot.group" class="slot-meta-item">
                <svg class="slot-meta-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ slot.group?.name || 'N/A' }}
              </span>
            </div>
          </div>

          <!-- Status Badge -->
          <div v-if="slot.status" class="slot-status">
            <span
              class="status-badge"
              :class="slot.status"
            >
              {{ slot.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!slots.length" class="day-card-empty">
        <svg class="day-card-empty-icon" width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p>No classes scheduled</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import SubjectBadge from './SubjectBadge.vue'
import LiveIndicator from './LiveIndicator.vue'

const props = defineProps({
  dayName: { type: String, required: true },
  dayKey: { type: String, required: true },
  dayDate: { type: String, default: '' },
  slots: { type: Array, default: () => [] },
  isToday: { type: Boolean, default: false },
  swapMode: { type: Boolean, default: false },
})

const emit = defineEmits(['slot-click', 'swap'])

const selectedSlot = ref(null)

const sortedSlots = computed(() => {
  return [...props.slots].sort((a, b) => (a.start_time || '00:00').localeCompare(b.start_time || '00:00'))
})

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
  // If teacher is an object with various name fields
  if (slot.teacher && typeof slot.teacher === 'object') {
    return (slot.teacher.name || '').trim() ||
      (slot.teacher.first_name && slot.teacher.last_name
        ? slot.teacher.first_name + ' ' + slot.teacher.last_name
        : '') ||
      slot.teacher.full_name ||
      ''
  }
  // Fallback: check if slot has a direct teacher_name field
  if (slot.teacher_name) return slot.teacher_name
  return ''
}

function formatTime(time) {
  if (!time) return '--:--'
  const [h, m] = time.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function isCurrentSlot(slot) {
  // Prefer backend-computed _is_live field if available
  if (slot._is_live !== undefined) return !!slot._is_live
  if (!slot.start_time || !slot.end_time) return false
  const now = new Date()
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  // Normalize times: compare only HH:MM portion
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
  if (isCurrentSlot(slot)) return 'slot-current'
  if (isUpcomingSlot(slot)) return 'slot-upcoming'
  return ''
}

function handleSlotClick(slot) {
  if (props.swapMode) {
    if (!selectedSlot.value) {
      selectedSlot.value = slot
    } else if (selectedSlot.value.id !== slot.id) {
      emit('swap', selectedSlot.value.id, slot.id)
      selectedSlot.value = null
    } else {
      selectedSlot.value = null
    }
  } else {
    emit('slot-click', slot)
  }
}
</script>

<style scoped>
.day-card {
  background: var(--bg-card);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.day-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-light);
}

.day-card-header.today {
  background: #eef2ff;
}

.day-card-header-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.day-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-dark);
}

.day-card-date {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.badge-today {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
  background: #dbeafe;
  color: #1d4ed8;
}

.day-card-count {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.day-card-body {
  /* container for slots */
}

.day-card-slot {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background 0.2s;
}

.day-card-slot:last-child {
  border-bottom: none;
}

.day-card-slot:hover {
  background: #f8f9fa;
}

.day-card-slot.slot-swap-active:hover {
  background: #eef2ff;
}

/* ── Live / Current slot highlight ── */
.day-card-slot.slot-current {
  background: #ecfdf5 !important;
  border-left: 3px solid #34d399;
}

.day-card-slot.slot-upcoming {
  background: #fffbeb !important;
  border-left: 3px solid #fbbf24;
}

.slot-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.slot-time {
  flex-shrink: 0;
  width: 4rem;
  text-align: center;
}

.slot-time-start {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-dark);
}

.slot-time-end {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.slot-divider {
  flex-shrink: 0;
  width: 1px;
  min-height: 2.5rem;
  background: var(--border-color);
}

.slot-content {
  flex: 1;
  min-width: 0;
}

.slot-content-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
  flex-wrap: wrap;
}

.slot-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.slot-meta-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.slot-meta-icon {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.slot-status {
  flex-shrink: 0;
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

.day-card-empty {
  padding: 2rem 1rem;
  text-align: center;
}

.day-card-empty-icon {
  margin: 0 auto 0.5rem;
  color: #d1d5db;
}

.day-card-empty p {
  font-size: 0.875rem;
  color: var(--text-muted);
}
</style>
