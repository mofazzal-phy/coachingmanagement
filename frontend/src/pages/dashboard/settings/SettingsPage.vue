<template>
  <div class="page-container">
    <div class="page-header">
      <h1>Settings</h1>
      <p class="subtitle">Institution profile, academic year, attendance thresholds, and system preferences.</p>
    </div>

    <div v-if="loading" class="loading-state">Loading settings...</div>

    <template v-else>
      <div v-for="section in sections" :key="section.group" class="settings-card">
        <div class="card-header">
          <h2>{{ section.title }}</h2>
          <p v-if="section.description">{{ section.description }}</p>
        </div>

        <div class="fields-grid">
          <div v-for="field in section.fields" :key="field.key" class="field-group">
            <label>{{ field.label }}</label>
            <input
              v-if="field.type !== 'textarea'"
              v-model="form[field.key]"
              :type="field.type || 'text'"
              class="form-input"
              :placeholder="field.placeholder || ''"
            />
            <textarea
              v-else
              v-model="form[field.key]"
              class="form-input"
              rows="3"
              :placeholder="field.placeholder || ''"
            />
            <small v-if="field.hint" class="field-hint">{{ field.hint }}</small>
          </div>
        </div>
      </div>

      <div class="actions-row">
        <button class="btn btn-primary" @click="saveSettings" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Settings' }}
        </button>
      </div>

      <p v-if="message" class="message" :class="messageType">{{ message }}</p>
    </template>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import settingsService from '@/services/settings.service'

const EXCLUDED_KEYS = ['grading_rules']

const sections = [
  {
    group: 'general',
    title: 'Institution Profile',
    description: 'Basic information about your coaching center.',
    fields: [
      { key: 'site_name', label: 'Institution Name', type: 'text' },
      { key: 'site_tagline', label: 'Tagline', type: 'text' },
      { key: 'site_email', label: 'Contact Email', type: 'email' },
      { key: 'site_phone', label: 'Contact Phone', type: 'text' },
      { key: 'site_address', label: 'Address', type: 'textarea' },
      { key: 'timezone', label: 'Timezone', type: 'text', placeholder: 'Asia/Dhaka' },
    ],
  },
  {
    group: 'academic',
    title: 'Academic',
    fields: [
      { key: 'academic_year', label: 'Current Academic Year', type: 'text', placeholder: '2026' },
    ],
  },
  {
    group: 'finance',
    title: 'Finance',
    fields: [
      { key: 'currency', label: 'Currency Code', type: 'text', placeholder: 'BDT' },
    ],
  },
  {
    group: 'attendance',
    title: 'Attendance & Exam Eligibility',
    description: 'Used when attendance-based exam eligibility is enabled.',
    fields: [
      { key: 'attendance_eligibility_eligible_min', label: 'Eligible from (%)', type: 'number', hint: 'Default: 75% — student is eligible' },
      { key: 'attendance_eligibility_warning_min', label: 'Warning from (%)', type: 'number', hint: 'Default: 60% — warning band until eligible threshold' },
    ],
  },
  {
    group: 'exam',
    title: 'Exam Leaderboard',
    description: 'Privacy and display rules for student-facing leaderboards.',
    fields: [
      { key: 'leaderboard_student_top_limit', label: 'Student top list limit', type: 'number', hint: '10–100 rows (default 50)' },
      { key: 'leaderboard_anonymize_names', label: 'Anonymize other students (1=yes, 0=no)', type: 'number', hint: 'Shows "Student #rank" except the logged-in student' },
      { key: 'leaderboard_show_provisional_mcq', label: 'Show provisional MCQ standings (1=yes, 0=no)', type: 'number', hint: 'Unofficial MCQ rank before official publish' },
    ],
  },
]

const form = reactive({})
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const messageType = ref('success')

onMounted(loadSettings)

async function loadSettings() {
  loading.value = true
  try {
    const res = await settingsService.list()
    const data = res.data?.data || {}

    sections.forEach((section) => {
      section.fields.forEach((field) => {
        form[field.key] = data[field.key] ?? ''
      })
    })
  } catch {
    message.value = 'Failed to load settings.'
    messageType.value = 'error'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  message.value = ''

  const payload = []
  sections.forEach((section) => {
    section.fields.forEach((field) => {
      if (EXCLUDED_KEYS.includes(field.key)) return
      payload.push({
        key: field.key,
        value: form[field.key] ?? '',
        group: section.group,
        type: field.type === 'number' ? 'number' : (field.type === 'textarea' ? 'text' : field.type || 'text'),
      })
    })
  })

  try {
    await settingsService.update(payload)
    message.value = 'Settings saved successfully.'
    messageType.value = 'success'
  } catch (err) {
    message.value = err.response?.data?.message || 'Failed to save settings.'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.page-container { max-width: 900px; margin: 0 auto; }
.page-header h1 { margin: 0; font-size: 1.5rem; color: var(--text-primary); }
.subtitle { margin: 0.35rem 0 1.25rem; color: var(--text-muted); font-size: 0.9rem; }
.settings-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); margin-bottom: 1rem; }
.card-header h2 { margin: 0; font-size: 1.05rem; }
.card-header p { margin: 0.35rem 0 1rem; color: var(--text-muted); font-size: 0.85rem; }
.fields-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
.field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.field-group label { font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); }
.field-hint { color: var(--text-muted); font-size: 0.75rem; }
.form-input { width: 100%; padding: 0.55rem 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; box-sizing: border-box; }
.actions-row { margin-top: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: #4f46e5; color: white; }
.message { margin-top: 0.75rem; font-size: 0.85rem; }
.message.success { color: #059669; }
.message.error { color: #dc2626; }
.loading-state { padding: 2rem; text-align: center; color: var(--text-muted); }
</style>
