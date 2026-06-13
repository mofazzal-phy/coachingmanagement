<template>
  <div v-if="eligibility" class="eligibility-badge-wrap" :class="sizeClass">
    <span
      v-if="eligibility.check_enabled"
      class="exam-eligibility-badge"
      :class="'badge-' + (eligibility.status || 'eligible')"
      :title="tooltip"
    >
      <span class="badge-dot" aria-hidden="true"></span>
      <span class="badge-label">{{ statusLabel }}</span>
      <span
        v-if="showPercent && eligibility.attendance_percent != null && eligibility.attendance_percent !== ''"
        class="badge-pct"
      >
        {{ formatPct(eligibility.attendance_percent) }}
      </span>
    </span>
    <span v-else class="exam-eligibility-badge badge-off">
      <span class="badge-dot" aria-hidden="true"></span>
      <span class="badge-label">No attendance rule</span>
    </span>
    <p v-if="showHint && hintText" class="eligibility-hint-text">{{ hintText }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  eligibility: { type: Object, default: null },
  showPercent: { type: Boolean, default: true },
  showHint: { type: Boolean, default: false },
  size: { type: String, default: 'md' }, // sm | md | lg
})

const sizeClass = computed(() => `size-${props.size}`)

const statusLabel = computed(() => {
  const e = props.eligibility
  if (!e?.check_enabled) return 'No attendance rule'
  const map = {
    eligible: 'Eligible',
    warning: 'Warning',
    blocked: 'Not eligible',
    overridden: 'Eligible (approved)',
  }
  return map[e.status] || e.label || 'Unknown'
})

const hintText = computed(() => {
  const e = props.eligibility
  if (!e?.check_enabled) {
    return 'This exam does not require a minimum attendance for admit card.'
  }
  const min = e.thresholds?.eligible_min ?? 75
  const pct = e.attendance_percent
  if (e.status === 'blocked') {
    return `Your attendance is ${formatPct(pct)}. Minimum required: ${min}%. Contact the office.`
  }
  if (e.status === 'warning') {
    return `Your attendance is ${formatPct(pct)}. Required: ${min}%+. You can still get the admit card.`
  }
  if (e.status === 'overridden') {
    return e.override_reason || 'Administration approved your eligibility.'
  }
  if (e.status === 'eligible') {
    return `Your attendance is ${formatPct(pct)}. You meet the ${min}% requirement.`
  }
  return ''
})

const tooltip = computed(() => hintText.value || statusLabel.value)

function formatPct(v) {
  if (v == null || v === '') return '—'
  return `${Number(v).toFixed(1)}%`
}
</script>

<style scoped>
.eligibility-badge-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
}
.exam-eligibility-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-weight: 700;
  border: 1px solid transparent;
  line-height: 1.2;
}
.size-sm .exam-eligibility-badge { font-size: 0.62rem; padding: 0.12rem 0.4rem; }
.size-md .exam-eligibility-badge { font-size: 0.72rem; }
.size-lg .exam-eligibility-badge { font-size: 0.82rem; padding: 0.3rem 0.65rem; }

.badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.badge-eligible { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
.badge-eligible .badge-dot { background: #10b981; }
.badge-warning { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.badge-warning .badge-dot { background: #f59e0b; }
.badge-blocked { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.badge-blocked .badge-dot { background: #ef4444; }
.badge-overridden { background: #e0e7ff; color: #3730a3; border-color: #a5b4fc; }
.badge-overridden .badge-dot { background: #6366f1; }
.badge-off { background: var(--bg-accent); color: var(--text-muted); border-color: #e2e8f0; }
.badge-off .badge-dot { background: #94a3b8; }

.badge-pct {
  opacity: 0.9;
  font-weight: 800;
}
.eligibility-hint-text {
  margin: 0;
  font-size: 0.75rem;
  color: var(--text-muted);
  line-height: 1.35;
  max-width: 36rem;
}
</style>
