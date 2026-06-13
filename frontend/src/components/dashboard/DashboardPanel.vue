<template>
  <section class="dash-panel" :class="{ 'panel-full': full }">
    <header class="panel-header">
      <div class="panel-title-wrap">
        <span v-if="icon" class="panel-icon">{{ icon }}</span>
        <div>
          <h3 class="panel-title">{{ title }}</h3>
          <p v-if="subtitle" class="panel-subtitle">{{ subtitle }}</p>
        </div>
      </div>
      <div class="panel-actions">
        <slot name="actions" />
      </div>
    </header>
    <div class="panel-body" :class="{ 'panel-loading': loading }">
      <div v-if="loading" class="panel-skeleton">
        <div class="sk-line" v-for="n in 4" :key="n"></div>
      </div>
      <slot v-else />
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  full: { type: Boolean, default: false },
})
</script>

<style scoped>
.dash-panel {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.panel-full {
  grid-column: 1 / -1;
}

.panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--border-light);
  flex-wrap: wrap;
}

.panel-title-wrap {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
}

.panel-icon {
  font-size: 1.1rem;
  line-height: 1.4;
}

.panel-title {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--text-primary);
}

.panel-subtitle {
  margin: 0.15rem 0 0;
  font-size: 0.72rem;
  color: var(--text-muted);
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.panel-body {
  padding: 1rem 1.15rem 1.15rem;
}

.panel-skeleton {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.sk-line {
  height: 12px;
  border-radius: 6px;
  background: var(--bg-accent);
}

.sk-line:nth-child(1) { width: 100%; }
.sk-line:nth-child(2) { width: 85%; }
.sk-line:nth-child(3) { width: 70%; }
.sk-line:nth-child(4) { width: 55%; }
</style>
