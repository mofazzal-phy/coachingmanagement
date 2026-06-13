<template>
  <div class="modern-chart">
    <div v-if="empty" class="chart-empty">{{ emptyText }}</div>
    <div v-else class="chart-wrap" :style="{ height: height + 'px' }">
      <canvas ref="canvasRef"></canvas>
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
  LineController,
  PolarAreaController,
  PieController,
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
  LineController,
  PolarAreaController,
  PieController,
)

const props = defineProps({
  type: { type: String, default: 'line' },
  data: { type: Object, default: null },
  options: { type: Object, default: () => ({}) },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No data available' },
  height: { type: Number, default: 240 },
})

const canvasRef = ref(null)
let chartInstance = null

const themeColors = () => {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark'
  return {
    text: dark ? '#94a3b8' : '#64748b',
    grid: dark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(148, 163, 184, 0.2)',
    tooltipBg: dark ? '#0f172a' : '#111827',
  }
}

const buildOptions = () => {
  const t = themeColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 800, easing: 'easeOutQuart' },
    plugins: {
      legend: {
        labels: {
          color: t.text,
          usePointStyle: true,
          padding: 14,
          font: { size: 11, family: 'inherit' },
        },
      },
      tooltip: {
        backgroundColor: t.tooltipBg,
        titleFont: { size: 12 },
        bodyFont: { size: 11 },
        padding: 10,
        cornerRadius: 8,
      },
    },
    scales: props.type === 'line' ? {
      x: {
        grid: { display: false },
        ticks: { color: t.text, font: { size: 10 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: t.grid },
        ticks: { color: t.text, precision: 0 },
      },
    } : props.type === 'polarArea' || props.type === 'radar' ? {
      r: {
        grid: { color: t.grid },
        ticks: { display: false, backdropColor: 'transparent' },
        pointLabels: { color: t.text, font: { size: 10 } },
      },
    } : undefined,
    ...props.options,
  }
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
    options: buildOptions(),
  })
}

watch(() => [props.data, props.empty, props.type, props.options], renderChart, { deep: true })

let themeObserver = null
onMounted(() => {
  renderChart()
  themeObserver = new MutationObserver(() => renderChart())
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] })
})
onBeforeUnmount(() => {
  themeObserver?.disconnect()
  destroyChart()
})
</script>

<style scoped>
.modern-chart { width: 100%; }
.chart-wrap { position: relative; width: 100%; }
.chart-empty {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}
</style>
