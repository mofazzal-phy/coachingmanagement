<template>
  <div class="stat-chart-card" :style="{ '--accent': accentColor }">
    <div class="stat-chart-top">
      <div class="stat-meta">
        <span class="stat-label">{{ label }}</span>
        <span class="stat-value">{{ value }}</span>
        <span v-if="subtext" class="stat-sub">{{ subtext }}</span>
        <span v-else-if="percentage != null" class="stat-sub accent">{{ percentage }}% of total</span>
      </div>
      <div class="stat-visual">
        <div v-if="chartType === 'ring'" class="ring-chart">
          <svg viewBox="0 0 72 72" class="ring-svg">
            <circle cx="36" cy="36" r="28" class="ring-track" />
            <circle
              cx="36"
              cy="36"
              r="28"
              class="ring-fill"
              :stroke-dasharray="ringCircumference"
              :stroke-dashoffset="ringOffset"
            />
          </svg>
          <span class="ring-pct">{{ displayPct }}%</span>
        </div>
        <span v-else class="stat-icon">{{ icon }}</span>
      </div>
    </div>
    <div v-if="trendData && trendData.length" class="spark-area-wrap">
      <svg class="spark-svg" viewBox="0 0 120 36" preserveAspectRatio="none">
        <defs>
          <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="accentColor" stop-opacity="0.4" />
            <stop offset="100%" :stop-color="accentColor" stop-opacity="0.02" />
          </linearGradient>
        </defs>
        <path :d="areaPath" :fill="`url(#${gradId})`" />
        <path :d="linePath" fill="none" :stroke="accentColor" stroke-width="2.5" stroke-linecap="round" />
      </svg>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  icon: { type: String, default: '📊' },
  label: { type: String, default: '' },
  value: { type: [String, Number], default: 0 },
  percentage: { type: Number, default: null },
  accentColor: { type: String, default: '#2563eb' },
  trendData: { type: Array, default: () => [] },
  subtext: { type: String, default: '' },
  chartType: { type: String, default: 'ring' },
})

const gradId = computed(() => `spark-${props.label.replace(/[^a-z0-9]/gi, '-').toLowerCase()}`)

const ringCircumference = 2 * Math.PI * 28

const displayPct = computed(() => {
  if (props.percentage != null) return Math.round(Math.abs(props.percentage))
  return 0
})

const ringOffset = computed(() => {
  const pct = Math.min(100, Math.max(0, displayPct.value))
  return ringCircumference * (1 - pct / 100)
})

const buildPaths = (data) => {
  const w = 120
  const h = 36
  const pad = 3
  const drawH = h - pad * 2
  const toY = (val) => h - pad - (Math.min(100, Math.max(0, val)) / 100) * drawH

  if (!data.length) {
    const flat = `M0,${h - pad} L${w},${h - pad}`
    return { linePath: flat, areaPath: `${flat} L${w},${h} L0,${h} Z` }
  }

  const stepX = data.length === 1 ? w : w / (data.length - 1)
  const pts = data.map((val, i) => ({ x: i * stepX, y: toY(val) }))

  let line = `M${pts[0].x},${pts[0].y}`
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[Math.max(0, i - 1)]
    const p1 = pts[i]
    const p2 = pts[i + 1]
    const p3 = pts[Math.min(pts.length - 1, i + 2)]
    const t = 0.3
    const cp1x = p1.x + (p2.x - p0.x) * t
    const cp1y = p1.y + (p2.y - p0.y) * t
    const cp2x = p2.x - (p3.x - p1.x) * t
    const cp2y = p2.y - (p3.y - p1.y) * t
    line += ` C${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`
  }

  const last = pts[pts.length - 1]
  const area = `${line} L${last.x},${h} L0,${h} Z`
  return { linePath: line, areaPath: area }
}

const linePath = computed(() => buildPaths(props.trendData).linePath)
const areaPath = computed(() => buildPaths(props.trendData).areaPath)
</script>

<style scoped>
.stat-chart-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1rem 1.15rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  position: relative;
  overflow: hidden;
  transition: border-color 0.25s, box-shadow 0.25s, transform 0.25s;
  box-shadow: var(--shadow-sm);
}

.stat-chart-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 8%, transparent), transparent 55%);
  pointer-events: none;
}

.stat-chart-card:hover {
  border-color: color-mix(in srgb, var(--accent) 45%, var(--border-color));
  box-shadow: 0 8px 28px color-mix(in srgb, var(--accent) 12%, transparent);
  transform: translateY(-2px);
}

.stat-chart-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  position: relative;
  z-index: 1;
}

.stat-meta {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.stat-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1.1;
}

.stat-sub {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.stat-sub.accent {
  color: var(--accent);
}

.stat-visual {
  flex-shrink: 0;
}

.stat-icon {
  font-size: 1.5rem;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: color-mix(in srgb, var(--accent) 14%, var(--bg-card));
}

.ring-chart {
  position: relative;
  width: 56px;
  height: 56px;
}

.ring-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.ring-track {
  fill: none;
  stroke: var(--border-color);
  stroke-width: 6;
}

.ring-fill {
  fill: none;
  stroke: var(--accent);
  stroke-width: 6;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease;
}

.ring-pct {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--accent);
}

.spark-area-wrap {
  height: 36px;
  margin: 0 -0.15rem;
  position: relative;
  z-index: 1;
}

.spark-svg {
  width: 100%;
  height: 100%;
  display: block;
}
</style>
