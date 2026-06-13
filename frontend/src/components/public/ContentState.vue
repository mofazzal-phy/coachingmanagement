<template>
  <div v-if="loading" class="pub-state">
    <div class="pub-spinner"></div>
    <p>{{ t('state.loading') }}</p>
  </div>
  <div v-else-if="error" class="pub-state">
    <div class="cs-icon">⚠️</div>
    <p>{{ error === true ? t('state.error') : error }}</p>
    <button v-if="onRetry" class="pub-btn pub-btn--ghost" @click="onRetry">{{ t('action.search') }}</button>
  </div>
  <div v-else-if="empty" class="pub-state">
    <div class="cs-icon">📭</div>
    <p>{{ emptyText || t('state.empty') }}</p>
  </div>
</template>

<script setup>
import { useLang } from '@/composables/useLang'

defineProps({
  loading: { type: Boolean, default: false },
  error: { type: [Boolean, String], default: false },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: '' },
  onRetry: { type: Function, default: null },
})

const { t } = useLang()
</script>

<style scoped>
.cs-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.pub-state .pub-btn { margin-top: 1rem; }
</style>
