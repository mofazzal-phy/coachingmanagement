<template>
  <div class="bar-chart-wrap">
    <div v-if="!items.length" class="chart-empty">{{ emptyText }}</div>
    <div v-else class="bar-chart" :class="{ grouped: dual }">
      <div v-for="(item, idx) in items" :key="item.label + idx" class="bar-col">
        <div class="bars-stack">
          <div
            v-if="dual"
            class="bar bar-secondary"
            :style="{ height: barHeight(item.value2) + 'px' }"
            :title="`${item.label}: ${item.value2}`"
          ></div>
          <div
            class="bar"
            :class="item.colorClass"
            :style="{ height: barHeight(item.value) + 'px', background: item.color || undefined }"
            :title="`${item.label}: ${item.value}`"
          ></div>
        </div>
        <span class="bar-label">{{ item.label }}</span>
        <span v-if="showValues" class="bar-value">{{ formatVal(item.value) }}</span>
      </div>
    </div>
    <div v-if="dual && items.length" class="chart-legend">
      <span class="legend-item"><i class="dot primary"></i>{{ primaryLabel }}</span>
      <span class="legend-item"><i class="dot secondary"></i>{{ secondaryLabel }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  dual: { type: Boolean, default: false },
  maxHeight: { type: Number, default: 120 },
  showValues: { type: Boolean, default: true },
  emptyText: { type: String, default: 'No data available' },
  primaryLabel: { type: String, default: 'Primary' },
  secondaryLabel: { type: String, default: 'Secondary' },
  valueFormat: { type: String, default: 'number' },
})

const maxVal = computed(() => {
  const vals = props.items.flatMap((i) => {
    const arr = [Number(i.value) || 0]
    if (props.dual) arr.push(Number(i.value2) || 0)
    return arr
  })
  return Math.max(...vals, 1)
})

function barHeight(val) {
  const n = Number(val) || 0
  return Math.max(4, (n / maxVal.value) * props.maxHeight)
}

function formatVal(val) {
  const n = Number(val) || 0
  if (props.valueFormat === 'currency') {
    return n >= 1000 ? `${(n / 1000).toFixed(0)}k` : n
  }
  if (props.valueFormat === 'percent') return `${n}%`
  return n
}
</script>

<style scoped>
.bar-chart-wrap {
  width: 100%;
}

.bar-chart {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 0.45rem;
  min-height: 150px;
  padding-top: 0.5rem;
  overflow-x: auto;
}

.bar-col {
  flex: 1;
  min-width: 42px;
  max-width: 72px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}

.bars-stack {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 130px;
}

.bar {
  width: 18px;
  background: linear-gradient(180deg, #6366f1, #4f46e5);
  border-radius: 5px 5px 0 0;
  transition: height 0.45s ease;
  min-height: 4px;
}

.bar-secondary {
  background: linear-gradient(180deg, #34d399, #10b981);
}

.bar-revenue { background: linear-gradient(180deg, #fbbf24, #f59e0b); }
.bar-attendance { background: linear-gradient(180deg, #60a5fa, #3b82f6); }

.bar-label {
  font-size: 0.62rem;
  color: var(--text-muted);
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.bar-value {
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.chart-empty {
  text-align: center;
  padding: 2.5rem 1rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.chart-legend {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 0.75rem;
  font-size: 0.72rem;
  color: var(--text-muted);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.dot.primary { background: #4f46e5; }
.dot.secondary { background: #10b981; }
</style>
