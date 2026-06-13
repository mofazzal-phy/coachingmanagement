<template>
  <div class="chart-card">
    <div class="chart-header">
      <h4 class="chart-title">Attendance Overview</h4>
      <span class="chart-date">{{ date }}</span>
    </div>
    <div class="chart-body">
      <div class="doughnut-wrapper">
        <svg viewBox="0 0 120 120" class="doughnut-svg">
          <!-- Background circle -->
          <circle cx="60" cy="60" r="48" fill="none" class="doughnut-bg" stroke-width="12"/>
          <!-- Segments -->
          <circle
            v-for="(seg, i) in segments"
            :key="i"
            cx="60" cy="60" r="48"
            fill="none"
            :stroke="seg.color"
            :stroke-width="12"
            :stroke-dasharray="`${seg.circumference} ${totalCircumference}`"
            :stroke-dashoffset="seg.offset"
            transform="rotate(-90, 60, 60)"
            class="doughnut-segment"
            :style="{ transitionDelay: `${i * 0.1}s` }"
          />
          <!-- Center text -->
          <text x="60" y="52" text-anchor="middle" class="doughnut-total">{{ total }}</text>
          <text x="60" y="68" text-anchor="middle" class="doughnut-label">Total</text>
        </svg>
      </div>
      <div class="chart-legend">
        <div v-for="item in legendItems" :key="item.label" class="legend-item">
          <span class="legend-dot" :style="{ background: item.color }"></span>
          <span class="legend-label">{{ item.label }}</span>
          <span class="legend-value-percent">{{ item.value }} ({{ item.percent }}%)</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Object, default: () => ({ present: 0, late: 0, absent: 0, leave: 0 }) },
  date: { type: String, default: '' },
})

const total = computed(() => {
  return props.data.present + props.data.late + props.data.absent + props.data.leave
})

const totalCircumference = 2 * Math.PI * 48

const segments = computed(() => {
  const t = total.value || 1
  const items = [
    { key: 'present', color: '#12b76a', value: props.data.present },
    { key: 'late', color: '#f79009', value: props.data.late },
    { key: 'absent', color: '#f04438', value: props.data.absent },
    { key: 'leave', color: '#2563eb', value: props.data.leave },
  ]

  let offset = 0
  return items.map(item => {
    const circumference = (item.value / t) * totalCircumference
    const seg = {
      ...item,
      circumference,
      offset: -offset,
    }
    offset += circumference
    return seg
  })
})

const legendItems = computed(() => {
  const t = total.value || 1
  return [
    { label: 'Present', color: '#12b76a', value: props.data.present, percent: ((props.data.present / t) * 100).toFixed(1) },
    { label: 'Late', color: '#f79009', value: props.data.late, percent: ((props.data.late / t) * 100).toFixed(1) },
    { label: 'Absent', color: '#f04438', value: props.data.absent, percent: ((props.data.absent / t) * 100).toFixed(1) },
    { label: 'Leave', color: '#2563eb', value: props.data.leave, percent: ((props.data.leave / t) * 100).toFixed(1) },
  ]
})
</script>

<style scoped>
.chart-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  overflow: hidden;
}

.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-light);
}

.chart-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.chart-date {
  font-size: 0.7rem;
  color: var(--text-muted);
}

.doughnut-bg {
  stroke: var(--border-light);
}

.chart-body {
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.doughnut-wrapper {
  width: 130px;
  height: 130px;
  flex-shrink: 0;
}

.doughnut-svg {
  width: 100%;
  height: 100%;
}

.doughnut-segment {
  transition: stroke-dasharray 0.8s ease, stroke-dashoffset 0.8s ease;
}

.doughnut-total {
  font-size: 1.3rem;
  font-weight: 800;
  fill: var(--text-primary);
}

.doughnut-label {
  font-size: 0.55rem;
  fill: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.chart-legend {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-label {
  color: var(--text-muted);
  flex: 1;
  font-weight: 500;
}

.legend-value-percent {
  font-weight: 700;
  color: var(--text-primary);
  font-size: 0.8rem;
}
</style>
