<template>
  <div class="mark-config-editor">
    <p class="editor-hint">Configure mark components for this subject paper. Total marks auto-calculates from enabled components.</p>
    <div class="component-grid">
      <div v-for="key in componentKeys" :key="key" class="component-row" :class="{ disabled: !localConfig[key].enabled }">
        <label class="component-toggle">
          <input type="checkbox" v-model="localConfig[key].enabled" @change="emitChange" />
          <span>{{ labels[key] }}</span>
        </label>
        <div class="component-fields">
          <div class="field">
            <label>Max</label>
            <input
              v-model.number="localConfig[key].max_marks"
              type="number"
              min="0"
              class="form-input-sm"
              :disabled="!localConfig[key].enabled"
              @input="emitChange"
            />
          </div>
          <div class="field">
            <label>Pass</label>
            <input
              v-model.number="localConfig[key].pass_marks"
              type="number"
              min="0"
              class="form-input-sm"
              :disabled="!localConfig[key].enabled"
              @input="emitChange"
            />
          </div>
          <div class="field">
            <label>Eval</label>
            <select
              v-model="localConfig[key].evaluation"
              class="form-input-sm"
              :disabled="!localConfig[key].enabled"
              @change="emitChange"
            >
              <option value="manual">Manual</option>
              <option value="auto">Auto</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="config-summary">
      <span>Configured total: <strong>{{ configuredTotal }}</strong></span>
      <button type="button" class="btn-link" @click="applyDefault">Use default (MCQ 30 + CQ 50)</button>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import {
  COMPONENT_KEYS,
  COMPONENT_LABELS,
  DEFAULT_MARK_CONFIG,
  configTotalMarks,
} from '@/utils/markConfig.utils'

const props = defineProps({
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'total-change'])

const componentKeys = ['mcq', 'cq', 'written', 'practical']
const labels = COMPONENT_LABELS

function cloneConfig(source) {
  const base = JSON.parse(JSON.stringify(DEFAULT_MARK_CONFIG))
  if (!source) return base
  COMPONENT_KEYS.forEach((key) => {
    if (source[key]) {
      base[key] = { ...base[key], ...source[key] }
    }
  })
  return base
}

const localConfig = reactive(cloneConfig(props.modelValue))

watch(() => props.modelValue, (val) => {
  Object.assign(localConfig, cloneConfig(val))
}, { deep: true })

const configuredTotal = computed(() => configTotalMarks(localConfig))

function emitChange() {
  const payload = JSON.parse(JSON.stringify(localConfig))
  emit('update:modelValue', payload)
  emit('total-change', configuredTotal.value)
}

function applyDefault() {
  Object.assign(localConfig, cloneConfig(DEFAULT_MARK_CONFIG))
  emitChange()
}
</script>

<style scoped>
.mark-config-editor { border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem; background: #fafafa; }
.editor-hint { margin: 0 0 0.75rem; font-size: 0.78rem; color: var(--text-muted); }
.component-grid { display: flex; flex-direction: column; gap: 0.5rem; }
.component-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.45rem 0.5rem; border-radius: 8px; background: var(--bg-card); border: 1px solid #eef2f7; }
.component-row.disabled { opacity: 0.55; }
.component-toggle { display: flex; align-items: center; gap: 0.4rem; min-width: 95px; font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); }
.component-fields { display: flex; gap: 0.5rem; flex: 1; }
.field { display: flex; flex-direction: column; gap: 0.15rem; }
.field label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; }
.form-input-sm { width: 72px; padding: 0.35rem 0.45rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.8rem; }
.config-summary { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; font-size: 0.82rem; color: var(--text-secondary); }
.btn-link { border: none; background: none; color: #4f46e5; font-size: 0.78rem; cursor: pointer; text-decoration: underline; }
</style>
