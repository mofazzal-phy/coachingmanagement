<template>
  <span
    class="badge subject-badge"
    :style="badgeStyle"
  >
    <span v-if="showIcon" class="badge-dot" :style="{ backgroundColor: color }"></span>
    <span class="badge-text">{{ subject?.name || subject || 'N/A' }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  subject: { type: [Object, String], default: null },
  color: { type: String, default: '#6B7280' },
  showIcon: { type: Boolean, default: true },
  size: { type: String, default: 'sm' }, // sm, md, lg
})

const badgeStyle = computed(() => {
  const sizes = {
    sm: { fontSize: '0.75rem', padding: '0.125rem 0.5rem' },
    md: { fontSize: '0.8125rem', padding: '0.25rem 0.75rem' },
    lg: { fontSize: '0.875rem', padding: '0.375rem 1rem' },
  }
  const sizeStyle = sizes[props.size] || sizes.sm
  return {
    backgroundColor: hexToRgba(props.color, 0.12),
    color: props.color,
    fontSize: sizeStyle.fontSize,
    padding: sizeStyle.padding,
  }
})

function hexToRgba(hex, alpha) {
  const h = hex.replace('#', '')
  const r = parseInt(h.substring(0, 2), 16)
  const g = parseInt(h.substring(2, 4), 16)
  const b = parseInt(h.substring(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}
</script>

<style scoped>
.subject-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  border-radius: 9999px;
  font-weight: 500;
  transition: all 0.2s ease;
  max-width: 100%;
}

.badge-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  flex-shrink: 0;
}

.badge-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 120px;
}
</style>
