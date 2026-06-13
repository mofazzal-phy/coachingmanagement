<template>
  <div class="stat-card" :class="[tone, { loading }]">
    <div class="stat-accent" :style="{ background: accentColor }"></div>
    <div class="stat-body">
      <div class="stat-top">
        <span class="stat-icon" :style="{ background: iconBg, color: accentColor }">{{ icon }}</span>
        <span v-if="badge" class="stat-badge" :class="badgeTone">{{ badge }}</span>
      </div>
      <p class="stat-label">{{ label }}</p>
      <p class="stat-value">
        <span v-if="loading" class="skeleton-val"></span>
        <template v-else>{{ displayValue }}</template>
      </p>
      <p v-if="subtext" class="stat-sub">{{ subtext }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: 0 },
  icon: { type: String, default: '📊' },
  tone: { type: String, default: 'indigo' },
  accentColor: { type: String, default: '#4f46e5' },
  iconBg: { type: String, default: '#eef2ff' },
  format: { type: String, default: 'number' },
  subtext: { type: String, default: '' },
  badge: { type: String, default: '' },
  badgeTone: { type: String, default: 'neutral' },
  loading: { type: Boolean, default: false },
})

const displayValue = computed(() => {
  if (props.format === 'currency') {
    const n = Number(props.value) || 0
    return `৳${n.toLocaleString('en-BD')}`
  }
  if (props.format === 'percent') {
    return `${props.value}%`
  }
  if (props.format === 'text') {
    return props.value
  }
  const n = Number(props.value)
  return Number.isFinite(n) ? n.toLocaleString('en-BD') : props.value
})
</script>

<style scoped>
.stat-card {
  position: relative;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.stat-accent {
  height: 3px;
  width: 100%;
}

.stat-body {
  padding: 1rem 1.1rem 1.05rem;
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.65rem;
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

.stat-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.stat-badge.neutral { background: var(--bg-accent); color: var(--text-muted); }
.stat-badge.success { background: #064e3b; color: #6ee7b7; }
.stat-badge.warning { background: #78350f; color: #fcd34d; }
.stat-badge.danger { background: #450a0a; color: #fca5a5; }

[data-theme="light"] .stat-badge.success { background: #dcfce7; color: #15803d; }
[data-theme="light"] .stat-badge.warning { background: #fef3c7; color: #b45309; }
[data-theme="light"] .stat-badge.danger { background: #fee2e2; color: #b91c1c; }

.stat-label {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stat-value {
  margin: 0.2rem 0 0;
  font-size: 1.55rem;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1.2;
}

.stat-sub {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.skeleton-val {
  display: inline-block;
  width: 72px;
  height: 1.4rem;
  border-radius: 6px;
  background: linear-gradient(90deg, var(--bg-accent) 25%, var(--border-color) 50%, var(--bg-accent) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
