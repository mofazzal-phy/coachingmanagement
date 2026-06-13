<template>
  <div class="exam-card" :style="{ borderLeftColor: color }">
    <div class="card-header">
      <span class="subject-name">{{ subjectName }}</span>
      <span class="status-badge" :class="routine.status">
        {{ routine.status }}
      </span>
    </div>

    <div class="card-body">
      <div class="info-row">
        <svg class="info-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="info-text">{{ startTime }} - {{ endTime }}</span>
      </div>

      <div class="info-row" v-if="roomName">
        <svg class="info-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        <span class="info-text">{{ roomName }}</span>
      </div>

      <div class="info-row" v-if="teacherName">
        <svg class="info-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span class="info-text">{{ teacherName }}</span>
      </div>

      <div class="info-row" v-if="batchName">
        <svg class="info-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="info-text">{{ batchName }}</span>
      </div>
    </div>

    <div class="card-actions" v-if="showActions">
      <slot name="actions">
        <button class="btn-icon edit" @click="$emit('edit', routine)" title="Edit">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button class="btn-icon delete" @click="$emit('delete', routine)" title="Delete">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  routine: { type: Object, required: true },
  color: { type: String, default: '#6366F1' },
  showActions: { type: Boolean, default: false },
})

defineEmits(['edit', 'delete'])

const subjectName = computed(() => {
  return props.routine.subject_name || props.routine.subject?.name || 'Unknown Subject'
})

const startTime = computed(() => {
  if (!props.routine.start_time) return '--:--'
  return props.routine.start_time.substring(0, 5)
})

const endTime = computed(() => {
  if (!props.routine.end_time) return '--:--'
  return props.routine.end_time.substring(0, 5)
})

const roomName = computed(() => {
  return props.routine.room_name || props.routine.room?.name || ''
})

const teacherName = computed(() => {
  const t = props.routine.teacher
  if (props.routine.teacher_name) return props.routine.teacher_name
  if (!t) return ''
  return t.name || t.first_name + ' ' + (t.last_name || '') || ''
})

const batchName = computed(() => {
  return props.routine.batch_name || props.routine.batch?.name || ''
})
</script>

<style scoped>
.exam-card {
  background: var(--bg-card);
  border-radius: 10px;
  border: 1px solid var(--border-color);
  border-left: 4px solid #6366F1;
  padding: 0.875rem;
  transition: box-shadow 0.2s, transform 0.2s;
  position: relative;
}

.exam-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-1px);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.625rem;
}

.subject-name {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--text-primary);
  line-height: 1.3;
}

.status-badge {
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}

.status-badge.draft { background: #fffbeb; color: #d97706; }
.status-badge.published { background: #ecfdf5; color: #059669; }
.status-badge.completed { background: #eef2ff; color: #4f46e5; }
.status-badge.cancelled { background: #fef2f2; color: #dc2626; }

.card-body {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.info-icon {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  color: var(--text-muted);
}

.info-text {
  line-height: 1.3;
}

.card-actions {
  display: flex;
  gap: 0.25rem;
  justify-content: flex-end;
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid #f3f4f6;
}

.btn-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.btn-icon.edit { background: #eef2ff; color: #4f46e5; }
.btn-icon.edit:hover { background: #c7d2fe; }
.btn-icon.delete { background: #fef2f2; color: #dc2626; }
.btn-icon.delete:hover { background: #fecaca; }
</style>
