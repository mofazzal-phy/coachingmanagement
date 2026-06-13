<template>
  <div class="kpi-card" :style="{ '--card-accent': accentColor }">
    <div class="kpi-left-section">
      <div class="kpi-icon-wrapper">
        <span class="kpi-icon">{{ icon }}</span>
      </div>
      <div class="kpi-content">
        <span class="kpi-label">{{ label }}</span>
        <span class="kpi-value">{{ value }}</span>
        <span v-if="subtext" class="kpi-subtext" :style="{ color: accentColor }">{{ subtext }}</span>
        <span v-else class="kpi-percent" :class="{ negative: percentage < 0 }">
          {{ Math.abs(percentage) }}%
        </span>
      </div>
    </div>
    <div class="kpi-trend" v-if="trendData && trendData.length">
      <svg class="trend-line" width="60" height="24" viewBox="0 0 120 24">
        <path :d="trendPath" fill="none" :stroke="accentColor" stroke-width="2" opacity="0.3"/>
        <path :d="trendPath" fill="none" :stroke="accentColor" stroke-width="2"/>
      </svg>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  icon: { type: String, default: '📊' },
  label: { type: String, default: '' },
  value: { type: [String, Number], default: '0' },
  percentage: { type: Number, default: 0 },
  accentColor: { type: String, default: '#2563eb' },
  trendData: { type: Array, default: () => [] },
  subtext: { type: String, default: '' },
})

// Generate a smooth SVG path from trend data points using cardinal spline interpolation
const trendPath = computed(() => {
  const data = props.trendData
  if (!data || data.length === 0) {
    return 'M0 18 Q30 6 60 14 T120 10'
  }
  const w = 120, h = 24, pad = 2
  const drawH = h - pad * 2  // 20px drawing area
  const toY = (val) => h - pad - (Math.min(100, Math.max(0, val)) / 100) * drawH
  if (data.length === 1) {
    const y = toY(data[0])
    return `M0,${y} L${w},${y}`
  }
  const stepX = w / (data.length - 1)
  const pts = data.map((val, i) => ({
    x: i * stepX,
    y: toY(val),
  }))
  let d = `M${pts[0].x},${pts[0].y}`
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[Math.max(0, i - 1)]
    const p1 = pts[i]
    const p2 = pts[i + 1]
    const p3 = pts[Math.min(pts.length - 1, i + 2)]
    const tension = 0.3
    const cp1x = p1.x + (p2.x - p0.x) * tension
    const cp1y = p1.y + (p2.y - p0.y) * tension
    const cp2x = p2.x - (p3.x - p1.x) * tension
    const cp2y = p2.y - (p3.y - p1.y) * tension
    d += ` C${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`
  }
  return d
})
</script>


<style scoped>
.kpi-card {
  background: var(--bg-card);
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02), 0 2px 8px rgba(0, 0, 0, 0.02);
  border: 1px solid var(--border-light);
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
  border-color: var(--card-accent);
}

.kpi-left-section {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.kpi-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--card-accent) 12%, transparent);
  flex-shrink: 0;
}

.kpi-icon {
  font-size: 1.25rem;
  line-height: 1;
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.kpi-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: capitalize;
  letter-spacing: 0px;
}

.kpi-value {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text-dark);
  line-height: 1.1;
}

.kpi-subtext {
  font-size: 0.72rem;
  font-weight: 700;
}

.kpi-percent {
  font-size: 0.72rem;
  font-weight: 700;
  color: #12b76a;
}

.kpi-percent.negative {
  color: #f04438;
}

.kpi-trend {
  display: flex;
  align-items: center;
  opacity: 0.8;
  flex-shrink: 0;
}

.trend-line {
  display: block;
}
</style>
