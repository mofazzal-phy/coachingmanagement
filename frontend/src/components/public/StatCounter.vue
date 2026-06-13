<template>
  <div ref="root" class="stat-counter">
    <span class="sc-value">{{ display }}{{ suffix }}</span>
    <span class="sc-label">{{ label }}</span>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  value: { type: Number, default: 0 },
  label: { type: String, default: '' },
  suffix: { type: String, default: '' },
  duration: { type: Number, default: 1400 },
})

const root = ref(null)
const display = ref(0)
let started = false
let observer = null

const formatNum = (n) => Math.round(n).toLocaleString('en-US')
display.value = formatNum(0)

const animate = () => {
  if (started) return
  started = true
  const start = performance.now()
  const tick = (now) => {
    const p = Math.min(1, (now - start) / props.duration)
    const eased = 1 - Math.pow(1 - p, 3)
    display.value = formatNum(props.value * eased)
    if (p < 1) requestAnimationFrame(tick)
    else display.value = formatNum(props.value)
  }
  requestAnimationFrame(tick)
}

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') { animate(); return }
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) { animate(); observer.disconnect() }
  }, { threshold: 0.4 })
  if (root.value) observer.observe(root.value)
})
onUnmounted(() => observer?.disconnect())
</script>

<style scoped>
.stat-counter { display: flex; flex-direction: column; align-items: center; text-align: center; }
.sc-value { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; line-height: 1; color: var(--pub-text); }
.sc-label { font-size: 0.82rem; color: var(--pub-text-muted); margin-top: 0.35rem; font-weight: 600; }
</style>
