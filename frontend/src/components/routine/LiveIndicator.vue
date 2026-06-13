<template>
  <div
    class="live-indicator"
    :class="isLive ? 'live' : 'upcoming'"
  >
    <span
      class="live-dot"
      :class="isLive ? 'pulse' : ''"
    ></span>
    {{ isLive ? 'Live' : 'Upcoming' }}
  </div>
</template>

<script setup>
defineProps({
  isLive: { type: Boolean, default: false },
})
</script>

<style scoped>
.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.live-indicator.live {
  background-color: #d4edda;
  color: #155724;
}

.live-indicator.upcoming {
  background-color: #f3f4f6;
  color: var(--text-muted);
}

.live-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
}

.live-indicator.live .live-dot {
  background-color: #28a745;
}

.live-indicator.upcoming .live-dot {
  background-color: var(--text-muted);
}

.live-dot.pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
</style>
