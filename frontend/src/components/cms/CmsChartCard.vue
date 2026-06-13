<template>
  <div class="chart-card">
    <div class="chart-card-header">
      <div>
        <h3>{{ title }}</h3>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <span v-if="badge" class="chart-badge">{{ badge }}</span>
    </div>
    <div class="chart-card-body">
      <div v-if="empty" class="chart-empty">
        <span class="empty-icon">📊</span>
        <p>{{ emptyText }}</p>
      </div>
      <div v-else class="chart-canvas-wrap" :class="size">
        <canvas ref="canvasRef"></canvas>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import {
  Chart,
  ArcElement,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  RadialLinearScale,
  Tooltip,
  Legend,
  Filler,
  PieController,
  LineController,
  RadarController,
  PolarAreaController,
} from 'chart.js'

Chart.register(
  ArcElement,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  RadialLinearScale,
  Tooltip,
  Legend,
  Filler,
  PieController,
  LineController,
  RadarController,
  PolarAreaController,
)

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  badge: { type: String, default: '' },
  type: { type: String, default: 'line' },
  data: { type: Object, default: null },
  options: { type: Object, default: () => ({}) },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No data for this period.' },
  size: { type: String, default: 'md' },
})

const canvasRef = ref(null)
let chartInstance = null

const defaultOptions = {
  responsive: true,
  maintainAspectRatio: false,
  animation: {
    duration: 700,
    easing: 'easeOutQuart',
  },
  plugins: {
    legend: {
      labels: {
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 16,
        font: { size: 12, family: 'inherit' },
        color: '#6b7280',
      },
    },
    tooltip: {
      backgroundColor: '#111827',
      titleFont: { size: 13, family: 'inherit' },
      bodyFont: { size: 12, family: 'inherit' },
      padding: 12,
      cornerRadius: 8,
      displayColors: true,
    },
  },
}

const mergeOptions = (base, extra) => {
  const merged = { ...base, ...extra }
  merged.plugins = { ...base.plugins, ...(extra.plugins || {}) }
  return merged
}

const destroyChart = () => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
}

const renderChart = async () => {
  destroyChart()
  if (props.empty || !props.data || !canvasRef.value) return

  await nextTick()
  if (!canvasRef.value) return

  chartInstance = new Chart(canvasRef.value, {
    type: props.type,
    data: props.data,
    options: mergeOptions(defaultOptions, props.options),
  })
}

watch(
  () => [props.data, props.empty, props.type, props.options],
  () => renderChart(),
  { deep: true },
)

onMounted(renderChart)
onBeforeUnmount(destroyChart)
</script>

<style scoped>
.chart-card {
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
}

.chart-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1.1rem 1.25rem 0.75rem;
  border-bottom: 1px solid var(--border-light, #f3f4f6);
}

.chart-card-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary, #111827);
}

.chart-card-header p {
  margin: 0.25rem 0 0;
  font-size: 0.78rem;
  color: var(--text-muted, #6b7280);
}

.chart-badge {
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  color: #4338ca;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}

.chart-card-body {
  padding: 1rem 1.25rem 1.25rem;
}

.chart-canvas-wrap {
  position: relative;
  width: 100%;
}

.chart-canvas-wrap.sm { height: 220px; }
.chart-canvas-wrap.md { height: 280px; }
.chart-canvas-wrap.lg { height: 340px; }

.chart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 220px;
  color: var(--text-muted, #9ca3af);
  text-align: center;
  gap: 0.5rem;
}

.empty-icon {
  font-size: 2rem;
  opacity: 0.5;
}

.chart-empty p {
  margin: 0;
  font-size: 0.85rem;
}
</style>
