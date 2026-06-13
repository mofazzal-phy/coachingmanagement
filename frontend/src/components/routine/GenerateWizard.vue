<template>
  <div class="modal-overlay" v-if="modelValue" @click.self="$emit('update:modelValue', false)">
    <div class="modal-dialog" style="max-width: 600px;">
      <!-- Header -->
      <div class="modal-header">
        <h3>Auto-Generate Class Routine</h3>
        <button class="modal-close" @click="$emit('update:modelValue', false)">✕</button>
      </div>

      <!-- Step Indicator -->
      <div class="wizard-steps">
        <div
          v-for="(step, idx) in steps"
          :key="idx"
          class="wizard-step"
        >
          <div
            class="wizard-step-circle"
            :class="currentStep > idx ? 'completed' : currentStep === idx ? 'active' : 'pending'"
          >
            <svg v-if="currentStep > idx" class="wizard-step-check" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span v-else>{{ idx + 1 }}</span>
          </div>
          <span class="wizard-step-label" :class="currentStep >= idx ? 'active' : ''">
            {{ step }}
          </span>
          <svg v-if="idx < steps.length - 1" class="wizard-step-arrow" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </div>

      <!-- Step Content -->
      <div class="modal-body">
        <!-- Step 1: Select Level -->
        <div v-if="currentStep === 0">
          <div class="form-group">
            <label>Select Level <span class="required">*</span></label>
            <div class="level-options">
              <button
                v-for="opt in levelOptions"
                :key="opt.value"
                class="level-option-btn"
                :class="form.level === opt.value ? 'selected' : ''"
                @click="form.level = opt.value; form.level_id = ''"
              >
                <div class="level-option-icon" :class="form.level === opt.value ? 'selected' : ''">
                  <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <div class="level-option-label" :class="form.level === opt.value ? 'selected' : ''">{{ opt.label }}</div>
              </button>
            </div>
          </div>

          <div v-if="form.level" class="form-group" style="margin-top: 1rem;">
            <label>Select {{ form.level }} <span class="required">*</span></label>
            <select
              v-model="form.level_id"
              class="form-control"
              required
            >
              <option value="" disabled>Choose {{ form.level }}</option>
              <option v-for="item in levelItems" :key="item.id" :value="item.id">
                {{ item.name || item.title }}
              </option>
            </select>
          </div>
        </div>

        <!-- Multi-batch info banner -->
        <div v-if="multiBatchEnabled && selectedBatchIds.length > 0" class="multi-batch-info">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Generating for <strong>{{ selectedBatchIds.length }} batches</strong></span>
        </div>

        <!-- Step 2: Select Subjects -->
        <div v-if="currentStep === 1">
          <div class="form-group">
            <label>
              Select Subjects <span class="required">*</span>
              <span class="text-muted" style="font-weight: normal;"> ({{ selectedSubjects.length }} selected)</span>
            </label>
            <div v-if="loadingSubjects" class="text-center py-4">
              <div class="spinner" style="margin: 0 auto 0.5rem;"></div>
              <p class="text-muted">Loading subjects...</p>
            </div>
            <div v-else class="subject-list">
              <label
                v-for="subj in availableSubjects"
                :key="subj.id"
                class="subject-item"
              >
                <input
                  type="checkbox"
                  :value="subj.id"
                  v-model="form.subject_ids"
                />
                <span class="subject-item-name">{{ subj.name }}</span>
                <span v-if="subj.code" class="subject-item-code">({{ subj.code }})</span>
              </label>
              <div v-if="!availableSubjects.length" class="empty-mini">
                No subjects available for this level
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Constraints -->
        <div v-if="currentStep === 2">
          <div class="form-row">
            <div class="form-group">
              <label>Max Classes Per Day</label>
              <input
                v-model.number="form.constraints.max_per_day"
                type="number"
                min="1"
                max="12"
                class="form-control"
                placeholder="e.g. 8"
              />
            </div>
            <div class="form-group">
              <label>Days to Skip</label>
              <div class="skip-days">
                <button
                  v-for="d in dayOptions"
                  :key="d.value"
                  class="skip-day-btn"
                  :class="form.constraints.skip_days.includes(d.value) ? 'skipped' : ''"
                  @click="toggleSkipDay(d.value)"
                >
                  {{ d.label }}
                </button>
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Start Date</label>
              <input
                v-model="form.start_date"
                type="date"
                class="form-control"
              />
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input
                v-model="form.end_date"
                type="date"
                class="form-control"
              />
            </div>
          </div>

          <label class="checkbox-label">
            <input
              v-model="form.apply"
              type="checkbox"
            />
            <span>Apply immediately (save to database)</span>
          </label>
        </div>

        <!-- Step 4: Preview -->
        <div v-if="currentStep === 3">
          <div v-if="generating" class="text-center py-4">
            <div class="spinner" style="margin: 0 auto 1rem;"></div>
            <p class="text-muted">Generating optimal routine...</p>
          </div>

          <div v-else-if="generatedRoutines.length">
            <div class="preview-header">
              <p class="text-muted">
                Generated <strong>{{ generatedRoutines.length }}</strong> slots successfully
              </p>
              <span class="badge badge-success">No conflicts</span>
            </div>

            <div class="preview-list">
              <div
                v-for="(slot, idx) in generatedRoutines"
                :key="idx"
                class="preview-item"
              >
                <span class="preview-idx">{{ idx + 1 }}.</span>
                <span class="preview-day">{{ slot.day_of_week }}</span>
                <span class="preview-time">{{ slot.start_time }} - {{ slot.end_time }}</span>
                <span class="preview-subject">{{ slot.subject?.name || slot.subject }}</span>
                <span class="preview-teacher">{{ slot.teacher?.name || slot.teacher }}</span>
              </div>
            </div>
          </div>

          <div v-else-if="!generating" class="text-center py-4">
            <svg class="empty-icon-lg" width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-muted">Click "Generate" to create the routine</p>
          </div>

          <div v-if="generationError" class="alert alert-danger" style="margin-top: 1rem;">
            {{ generationError }}
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button
          v-if="currentStep > 0"
          type="button"
          class="btn btn-outline"
          @click="currentStep--"
        >
          Back
        </button>
        <div v-else></div>
        <div style="display:flex; gap: 0.5rem;">
          <button
            type="button"
            class="btn btn-outline"
            @click="$emit('update:modelValue', false)"
          >
            Cancel
          </button>
          <button
            v-if="currentStep < 3"
            type="button"
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            Next
          </button>
          <button
            v-if="currentStep === 3"
            type="button"
            class="btn btn-primary"
            :disabled="generating || !generatedRoutines.length"
            @click="applyRoutines"
          >
            {{ generating ? 'Generating...' : 'Apply & Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  batches: { type: Array, default: () => [] },
  courses: { type: Array, default: () => [] },
  classes: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  generating: { type: Boolean, default: false },
  generationError: { type: String, default: '' },
  generatedRoutines: { type: Array, default: () => [] },
  // Multi-batch support
  multiBatchEnabled: { type: Boolean, default: false },
  selectedBatchIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'generate', 'apply'])

const steps = ['Level & Class', 'Subjects', 'Constraints', 'Preview']
const currentStep = ref(0)
const availableSubjects = ref([])
const loadingSubjects = ref(false)

const form = reactive({
  level: '',
  level_id: '',
  subject_ids: [],
  constraints: {
    max_per_day: 8,
    skip_days: ['fri'],
  },
  start_date: '',
  end_date: '',
  apply: false,
  // Multi-batch: store selected batch IDs for generation
  batch_ids: [],
})

const levelOptions = [
  { value: 'batch', label: 'Batch' },
  { value: 'course', label: 'Course' },
  { value: 'class', label: 'Class' },
]

// When multi-batch is enabled, auto-select batch level and populate batch_ids
watch(() => props.multiBatchEnabled, (enabled) => {
  if (enabled && props.selectedBatchIds.length > 0) {
    form.level = 'batch'
    form.batch_ids = [...props.selectedBatchIds]
    form.level_id = props.selectedBatchIds[0]
  } else if (!enabled) {
    form.batch_ids = []
  }
}, { immediate: true })

// Sync batch_ids when selectedBatchIds prop changes
watch(() => props.selectedBatchIds, (ids) => {
  if (props.multiBatchEnabled && ids.length > 0) {
    form.batch_ids = [...ids]
    form.level_id = ids[0]
  }
}, { deep: true })

const dayOptions = [
  { value: 'sat', label: 'Sat' },
  { value: 'sun', label: 'Sun' },
  { value: 'mon', label: 'Mon' },
  { value: 'tue', label: 'Tue' },
  { value: 'wed', label: 'Wed' },
  { value: 'thu', label: 'Thu' },
  { value: 'fri', label: 'Fri' },
]

const levelItems = computed(() => {
  if (form.level === 'batch') return props.batches
  if (form.level === 'course') return props.courses
  if (form.level === 'class') return props.classes
  return []
})

const selectedSubjects = computed(() => form.subject_ids)

const canProceed = computed(() => {
  if (currentStep.value === 0) {
    // In multi-batch mode, level is auto-set and batch_ids are used instead of level_id
    if (props.multiBatchEnabled) return form.level && form.batch_ids.length > 0
    return form.level && form.level_id
  }
  if (currentStep.value === 1) return form.subject_ids.length > 0
  if (currentStep.value === 2) return true
  return false
})

// Fetch subjects when level selection changes
watch(() => form.level_id, async (newVal) => {
  if (!form.level || !newVal) {
    availableSubjects.value = []
    form.subject_ids = []
    return
  }
  loadingSubjects.value = true
  try {
    if (form.level === 'class') {
      const res = await academicService.subjects.byClass(newVal)
      availableSubjects.value = res?.data?.data || res?.data || []
    } else if (form.level === 'course') {
      const res = await enrollmentService.getCourse(newVal)
      const course = res?.data?.data || res?.data
      availableSubjects.value = course?.subjects || []
    } else if (form.level === 'batch') {
      const res = await enrollmentService.getBatch(newVal)
      const batch = res?.data?.data || res?.data
      if (batch?.course_id) {
        const courseRes = await enrollmentService.getCourse(batch.course_id)
        const course = courseRes?.data?.data || courseRes?.data
        availableSubjects.value = course?.subjects || []
      } else {
        availableSubjects.value = []
      }
    }
  } catch (e) {
    console.warn('Failed to fetch subjects for level:', form.level, newVal, e)
    availableSubjects.value = []
  } finally {
    loadingSubjects.value = false
  }
})

function toggleSkipDay(day) {
  const idx = form.constraints.skip_days.indexOf(day)
  if (idx > -1) {
    form.constraints.skip_days.splice(idx, 1)
  } else {
    form.constraints.skip_days.push(day)
  }
}

const allDays = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']

function nextStep() {
  if (currentStep.value === 2) {
    // Trigger generation
    // Convert skip_days (days to skip) to days (days to include) for the backend
    const payload = {
      ...form,
      constraints: {
        ...form.constraints,
        days: allDays.filter(d => !form.constraints.skip_days.includes(d)),
      },
    }

    // If multi-batch is enabled, send batch_ids instead of single level_id
    if (props.multiBatchEnabled && form.batch_ids.length > 0) {
      payload.batch_ids = [...form.batch_ids]
      // Keep level_id as first batch for backward compatibility
      payload.level_id = form.batch_ids[0]
    }

    emit('generate', payload)
  }
  currentStep.value++
}

function applyRoutines() {
  emit('apply')
}

watch(() => props.modelValue, (val) => {
  if (val) {
    currentStep.value = 0
    form.level = ''
    form.level_id = ''
    form.subject_ids = []
    form.batch_ids = []
    form.constraints.max_per_day = 8
    form.constraints.skip_days = ['fri']
    form.start_date = ''
    form.end_date = ''
    form.apply = false
    availableSubjects.value = []

    // If multi-batch is enabled, pre-populate
    if (props.multiBatchEnabled && props.selectedBatchIds.length > 0) {
      form.level = 'batch'
      form.batch_ids = [...props.selectedBatchIds]
      form.level_id = props.selectedBatchIds[0]
    }
  }
})
</script>

<style scoped>
.wizard-steps {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.5rem 0;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.wizard-step-circle {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.2s;
}

.wizard-step-circle.completed {
  background: var(--primary-color);
  color: #fff;
}

.wizard-step-circle.active {
  background: #eef2ff;
  color: var(--primary-color);
  box-shadow: 0 0 0 2px #a5b4fc;
}

.wizard-step-circle.pending {
  background: #f3f4f6;
  color: var(--text-muted);
}

.wizard-step-check {
  width: 16px;
  height: 16px;
}

.wizard-step-label {
  font-size: 0.75rem;
  font-weight: 500;
}

.wizard-step-label.active {
  color: var(--text-dark);
}

.wizard-step-label:not(.active) {
  color: var(--text-muted);
}

.wizard-step-arrow {
  width: 16px;
  height: 16px;
  color: #d1d5db;
  margin: 0 0.25rem;
}

.level-options {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.level-option-btn {
  padding: 1rem;
  border-radius: var(--radius-md);
  border: 2px solid var(--border-color);
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--bg-card);
}

.level-option-btn:hover {
  border-color: #d1d5db;
}

.level-option-btn.selected {
  border-color: var(--primary-color);
  background: #eef2ff;
  box-shadow: 0 0 0 1px #a5b4fc;
}

.level-option-icon {
  width: 24px;
  height: 24px;
  margin: 0 auto 0.25rem;
  color: var(--text-muted);
}

.level-option-icon.selected {
  color: var(--primary-color);
}

.level-option-label {
  font-size: 0.875rem;
  font-weight: 500;
}

.level-option-label.selected {
  color: var(--primary-color);
}

.subject-list {
  max-height: 16rem;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.75rem;
}

.subject-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background 0.2s;
}

.subject-item:hover {
  background: var(--bg-accent);
}

.subject-item input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--primary-color);
}

.subject-item-name {
  font-size: 0.875rem;
  color: var(--text-dark);
}

.subject-item-code {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.empty-mini {
  text-align: center;
  padding: 1.5rem 0;
  font-size: 0.875rem;
  color: var(--text-muted);
}

.skip-days {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.skip-day-btn {
  padding: 0.375rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 500;
  border: 1px solid var(--border-color);
  cursor: pointer;
  transition: all 0.2s;
  background: var(--bg-accent);
  color: var(--text-muted);
}

.skip-day-btn:hover {
  background: #f3f4f6;
}

.skip-day-btn.skipped {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  margin-top: 1rem;
}

.checkbox-label input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--primary-color);
}

.checkbox-label span {
  font-size: 0.875rem;
  color: var(--text-dark);
}

.text-center {
  text-align: center;
}

.py-4 {
  padding-top: 1rem;
  padding-bottom: 1rem;
}

.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.badge-success {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
  background: #d4edda;
  color: #155724;
}

.preview-list {
  max-height: 20rem;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.75rem;
}

.preview-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: var(--radius-sm);
  background: var(--bg-accent);
  margin-bottom: 0.25rem;
  font-size: 0.875rem;
}

.preview-item:last-child {
  margin-bottom: 0;
}

.preview-idx {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-muted);
  width: 1.5rem;
  flex-shrink: 0;
}

.preview-day {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-muted);
  width: 3rem;
  flex-shrink: 0;
}

.preview-time {
  font-size: 0.75rem;
  color: var(--text-muted);
  width: 6rem;
  flex-shrink: 0;
}

.preview-subject {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-dark);
  flex: 1;
}

.preview-teacher {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.empty-icon-lg {
  margin: 0 auto 0.75rem;
  color: #d1d5db;
}

.multi-batch-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: #eef2ff;
  border: 1px solid #a5b4fc;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  color: var(--text-dark);
  margin-top: 0.75rem;
}

.multi-batch-info svg {
  flex-shrink: 0;
  color: var(--primary-color);
  width: 16px;
  height: 16px;
}
</style>
