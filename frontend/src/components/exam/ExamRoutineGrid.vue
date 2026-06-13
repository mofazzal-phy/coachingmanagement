<template>
  <div class="exam-routine-grid">
    <!-- Loading State -->
    <div v-if="loading" class="grid-loading">
      <div class="loading-spinner"></div>
      <p>Loading exam routines...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!Object.keys(groupedByDate).length" class="grid-empty">
      <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h3>No Exam Routines Found</h3>
      <p>Select an exam or generate routines to get started.</p>
    </div>

    <!-- Date Groups -->
    <div v-else class="grid-dates">
      <div
        v-for="(routines, date) in sortedDateGroups"
        :key="date"
        class="date-group"
      >
        <!-- Date Header -->
        <div class="date-header">
          <div class="date-header-left">
            <span class="date-badge">{{ formatDate(date) }}</span>
            <span class="date-day">{{ getDayName(date) }}</span>
          </div>
          <span class="date-count">{{ routines.length }} slot{{ routines.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Routine Cards for this date -->
        <div class="date-slots">
          <ExamRoutineCard
            v-for="routine in routines"
            :key="routine.id"
            :routine="routine"
            :color="getSubjectColor(routine)"
            :showActions="showActions"
            @edit="(r) => $emit('edit', r)"
            @delete="(r) => $emit('delete', r)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ExamRoutineCard from '@/components/exam/ExamRoutineCard.vue'

const props = defineProps({
  groupedByDate: { type: Object, required: true },
  subjectColorMap: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  showActions: { type: Boolean, default: false },
})

defineEmits(['edit', 'delete'])

// Sort date groups chronologically
const sortedDateGroups = computed(() => {
  const dates = Object.keys(props.groupedByDate).sort()
  const sorted = {}
  dates.forEach(date => {
    sorted[date] = props.groupedByDate[date]
  })
  return sorted
})

/**
 * Format date string to readable format.
 */
function formatDate(dateStr) {
  if (!dateStr) return 'TBD'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

/**
 * Get day name from date string.
 */
function getDayName(dateStr) {
  if (!dateStr) return ''
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
  const d = new Date(dateStr + 'T00:00:00')
  return days[d.getDay()]
}

/**
 * Get subject color from the color map.
 */
function getSubjectColor(routine) {
  const subjId = routine.subject_id || routine.subject?.id
  if (subjId && props.subjectColorMap[subjId]) {
    return props.subjectColorMap[subjId]
  }
  return '#6366F1'
}
</script>

<style scoped>
.exam-routine-grid {
  min-height: 200px;
}

/* Loading State */
.grid-loading {
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

/* Empty State */
.grid-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: var(--text-muted);
  text-align: center;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.grid-empty h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-muted);
  margin: 0 0 0.25rem;
}

.grid-empty p {
  font-size: 0.875rem;
  margin: 0;
}

/* Date Groups */
.grid-dates {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.date-group {
  background: var(--bg-card);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.date-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
}

.date-header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.date-badge {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-primary);
}

.date-day {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.date-count {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
  background: #f3f4f6;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
}

.date-slots {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
  padding: 0.75rem;
}

@media (max-width: 640px) {
  .date-slots {
    grid-template-columns: 1fr;
  }
}
</style>
