<template>
  <div class="page-container">
    <div class="page-header">
      <h1>Grade &amp; GPA Scale</h1>
    </div>

    <div class="info-banner">
      Bangladesh-style grading based on <strong>percentage</strong> (out of 100).
      Works automatically for any exam total — 50, 80, or 100 marks — because grades are calculated from
      <code>(obtained ÷ total) × 100</code>, not raw marks.
    </div>

    <div class="settings-card">
      <div class="card-header">
        <h2>Grade Letter &amp; GPA Rules</h2>
        <p>Set minimum percentage for each grade. Higher rules are checked first.</p>
      </div>

      <div v-if="loading" class="loading-state">Loading grade scale...</div>
      <div v-else>
        <table class="rules-table">
          <thead>
            <tr>
              <th>Min %</th>
              <th>Grade</th>
              <th>Grade Point (GPA)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(rule, index) in rules" :key="index">
              <td><input v-model.number="rule.min_percent" type="number" min="0" max="100" class="form-input" /></td>
              <td><input v-model="rule.grade" type="text" maxlength="10" class="form-input" /></td>
              <td><input v-model.number="rule.grade_point" type="number" min="0" max="5" step="0.5" class="form-input" /></td>
              <td><button class="btn-icon" type="button" @click="removeRule(index)" :disabled="rules.length <= 1">✕</button></td>
            </tr>
          </tbody>
        </table>

        <div class="example-box">
          <strong>Example:</strong> Total marks 50, obtained 40 → 80% → Grade <strong>A+</strong> (if min 80% rule applies).
        </div>

        <div class="actions-row">
          <button class="btn btn-outline" type="button" @click="addRule">+ Add Rule</button>
          <button class="btn btn-outline" type="button" @click="resetDefaults">Reset BD Defaults</button>
          <button class="btn btn-primary" type="button" @click="saveRules" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Grade Scale' }}
          </button>
        </div>

        <p v-if="message" class="message" :class="messageType">{{ message }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import settingsService from '@/services/settings.service'
import { DEFAULT_GRADING_RULES, setGradingRules } from '@/utils/grading.utils'

const rules = ref([])
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const messageType = ref('success')

onMounted(loadRules)

async function loadRules() {
  loading.value = true
  try {
    const res = await settingsService.getGradingRules()
    rules.value = JSON.parse(JSON.stringify(res.data?.data?.rules || DEFAULT_GRADING_RULES))
  } catch {
    rules.value = JSON.parse(JSON.stringify(DEFAULT_GRADING_RULES))
  } finally {
    loading.value = false
  }
}

function addRule() {
  rules.value.push({ min_percent: 0, grade: 'F', grade_point: 0 })
}

function removeRule(index) {
  rules.value.splice(index, 1)
}

function resetDefaults() {
  rules.value = JSON.parse(JSON.stringify(DEFAULT_GRADING_RULES))
}

async function saveRules() {
  saving.value = true
  message.value = ''
  try {
    const sorted = [...rules.value].sort((a, b) => b.min_percent - a.min_percent)
    const res = await settingsService.updateGradingRules(sorted)
    rules.value = res.data?.data?.rules || sorted
    setGradingRules(rules.value)
    message.value = 'Grade scale saved. New mark entries will use these rules.'
    messageType.value = 'success'
  } catch (err) {
    message.value = err.response?.data?.message || 'Failed to save grade scale.'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.page-container { max-width: 900px; margin: 0 auto; }
.page-header h1 { margin: 0 0 1rem; font-size: 1.5rem; color: var(--text-primary); }
.info-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-size: 0.85rem; line-height: 1.5; }
.info-banner code { background: #dbeafe; padding: 0.1rem 0.35rem; border-radius: 4px; }
.settings-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); }
.card-header h2 { margin: 0; font-size: 1.05rem; }
.card-header p { margin: 0.35rem 0 1rem; color: var(--text-muted); font-size: 0.85rem; }
.rules-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.rules-table th { text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); padding: 0.5rem; border-bottom: 1px solid var(--border-color); }
.rules-table td { padding: 0.45rem 0.5rem; border-bottom: 1px solid var(--border-light); }
.form-input { width: 100%; padding: 0.45rem 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; }
.example-box { background: var(--bg-accent); border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem; }
.actions-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn { padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: #4f46e5; color: white; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-icon { border: none; background: #fee2e2; color: #dc2626; border-radius: 6px; width: 28px; height: 28px; cursor: pointer; }
.message { margin-top: 0.75rem; font-size: 0.85rem; }
.message.success { color: #059669; }
.message.error { color: #dc2626; }
.loading-state { padding: 1rem; color: var(--text-muted); }
</style>
