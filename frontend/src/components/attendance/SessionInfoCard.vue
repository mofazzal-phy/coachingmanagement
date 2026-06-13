<template>
  <div class="session-card">
    <div class="session-card-header">
      <span class="session-card-icon">📋</span>
      <h4 class="session-card-title">Session Information</h4>
    </div>
    <div class="session-card-body">
      <div class="session-row" v-for="row in rows" :key="row.label">
        <span class="session-row-icon">{{ row.icon }}</span>
        <span class="session-row-label">{{ row.label }}</span>
        <span class="session-row-value">{{ row.value }}</span>
      </div>
    </div>
    <div class="session-card-footer" v-if="!hasSession">
      <button class="detect-btn" @click="$emit('detect')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
          <path d="M7 0a7 7 0 100 14A7 7 0 007 0zm1 7H5V4h3v3z"/>
        </svg>
        Auto Detect Session
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  session: { type: Object, default: null },
})

defineEmits(['detect'])

const hasSession = computed(() => props.session && props.session.id)

const rows = computed(() => {
  if (!hasSession.value) {
    return [
      { icon: '👨‍🏫', label: 'Teacher', value: '—' },
      { icon: '🚪', label: 'Room', value: '—' },
      { icon: '👥', label: 'Total Students', value: '—' },
      { icon: '⏰', label: 'Started At', value: '—' },
    ]
  }
  return [
    { icon: '👨‍🏫', label: 'Teacher', value: props.session.teacher_name || '—' },
    { icon: '🚪', label: 'Room', value: props.session.room || '—' },
    { icon: '👥', label: 'Total Students', value: props.session.total_students || '—' },
    { icon: '⏰', label: 'Started At', value: props.session.start_time || '—' },
  ]
})
</script>

<style scoped>
.session-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  overflow: hidden;
}

.session-card-header {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f0f1f3;
}

.session-card-icon {
  font-size: 1.2rem;
  line-height: 1;
}

.session-card-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.session-card-body {
  padding: 0.75rem 1.25rem;
}

.session-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f9fafb;
}

.session-row:last-child {
  border-bottom: none;
}

.session-row-icon {
  font-size: 0.9rem;
  width: 20px;
  text-align: center;
}

.session-row-label {
  font-size: 0.78rem;
  color: var(--text-label);
  flex: 1;
}

.session-row-value {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
}

.session-card-footer {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid #f0f1f3;
}

.detect-btn {
  width: 100%;
  padding: 0.6rem;
  border: 1px dashed #2563eb;
  border-radius: 10px;
  background: #eff8ff;
  color: #2563eb;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.detect-btn:hover {
  background: #2563eb;
  color: #fff;
  border-style: solid;
}
</style>
