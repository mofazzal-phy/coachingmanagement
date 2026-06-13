<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h3>Auto-Generate Exam Routine</h3>
        <button class="modal-close" @click="$emit('close')">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Step Indicator -->
      <div class="steps-bar">
        <div
          v-for="(step, idx) in steps"
          :key="idx"
          class="step"
          :class="{ active: currentStep === idx, completed: currentStep > idx }"
        >
          <div class="step-number">
            <svg v-if="currentStep > idx" class="step-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span v-else>{{ idx + 1 }}</span>
          </div>
          <span class="step-label">{{ step }}</span>
        </div>
      </div>

      <div class="modal-body">
        <!-- Error Alert -->
        <div v-if="formError" class="alert alert-error">
          <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ formError }}</span>
        </div>

        <!-- Step 1: Select Exam & Subjects -->
        <div v-if="currentStep === 0" class="step-content">
          <h4 class="step-title">Select Exam & Subjects</h4>

          <div class="form-group">
            <label class="form-label">Exam <span class="required">*</span></label>
            <div v-if="!exams.length" class="empty-exams-notice">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>No exams available. Please close this wizard, select a batch/course/class on the main page that has exams, then try again.</span>
            </div>
            <select v-else v-model="form.exam_id" class="form-select" required>
              <option value="" disabled>Select exam</option>
              <option v-for="exam in exams" :key="exam.id" :value="exam.id">
                {{ exam.name }}
              </option>
            </select>
          </div>

          <!-- Exam Type Selection -->
          <div class="form-group">
            <label class="form-label">Exam Type <span class="required">*</span></label>
            <select v-model="selectedExamTypeId" class="form-select">
              <option value="">-- Select Exam Type --</option>
              <option v-for="et in examTypes" :key="et.id" :value="et.id">
                {{ et.name }}
              </option>
            </select>
          </div>

          <!-- ===== CASCADING SELECTION ===== -->

          <!-- Step: Class -->
          <div class="form-group">
            <label class="form-label">Class <span class="required">*</span></label>
            <select v-model="selectedClassId" class="form-select" @change="onClassChange">
              <option value="">-- Select Class --</option>
              <option v-for="cls in classes" :key="cls.id" :value="cls.id">
                {{ cls.name }}
              </option>
            </select>
          </div>

          <!-- Step: Course (filtered by class) -->
          <div class="form-group" v-if="selectedClassId">
            <label class="form-label">Course <span class="required">*</span></label>
            <div v-if="coursesLoading" class="loading-inline">
              <span class="spinner-sm"></span> Loading courses...
            </div>
            <select v-else v-model="selectedCourseId" class="form-select" @change="onCourseChange">
              <option value="">-- Select Course --</option>
              <option v-for="course in filteredCourses" :key="course.id" :value="course.id">
                {{ course.name }}
              </option>
            </select>
          </div>

          <!-- Step: Batch (filtered by course) -->
          <div class="form-group" v-if="selectedCourseId">
            <label class="form-label">Batch <span class="required">*</span></label>
            <div v-if="batchesLoading" class="loading-inline">
              <span class="spinner-sm"></span> Loading batches...
            </div>
            <select v-else v-model="selectedBatchId" class="form-select" @change="onBatchChange">
              <option value="">-- Select Batch --</option>
              <option v-for="batch in filteredBatches" :key="batch.id" :value="batch.id">
                {{ batch.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Subjects <span class="required">*</span></label>
            <div v-if="subjectsLoading" class="subjects-loading">
              <span class="btn-spinner"></span>
              Loading subjects for selected exam...
            </div>
            <div v-else class="subject-checklist">
              <label
                v-for="subject in wizardSubjects"
                :key="subject.id"
                class="subject-check-item"
                :class="{ selected: selectedSubjectIds.includes(subject.id) }"
              >
                <input
                  type="checkbox"
                  :value="subject.id"
                  v-model="selectedSubjectIds"
                  class="subject-checkbox"
                />
                <span class="subject-check-name">{{ subject.name }}</span>
                <span v-if="subject.code" class="subject-check-code">{{ subject.code }}</span>
              </label>
              <div v-if="!wizardSubjects.length && !subjectsLoading" class="no-subjects">
                No subjects available for this exam level.
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Date Range & Timing -->
        <div v-if="currentStep === 1" class="step-content">
          <h4 class="step-title">Date Range & Timing</h4>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Start Date <span class="required">*</span></label>
              <input v-model="form.start_date" type="date" class="form-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">End Date <span class="required">*</span></label>
              <input v-model="form.end_date" type="date" class="form-input" required />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Slot Duration (minutes) <span class="required">*</span></label>
              <select v-model.number="form.slot_duration" class="form-select">
                <option :value="30">30 minutes</option>
                <option :value="45">45 minutes</option>
                <option :value="60">1 hour</option>
                <option :value="90">1.5 hours</option>
                <option :value="120">2 hours</option>
                <option :value="180">3 hours</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Gap Between Slots (minutes)</label>
              <select v-model.number="form.gap_minutes" class="form-select">
                <option :value="0">No gap</option>
                <option :value="5">5 minutes</option>
                <option :value="10">10 minutes</option>
                <option :value="15">15 minutes</option>
                <option :value="30">30 minutes</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Daily Start Time <span class="required">*</span></label>
              <input v-model="form.start_time" type="time" class="form-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">Daily End Time Limit</label>
              <input v-model="form.end_time_limit" type="time" class="form-input" />
              <span class="form-hint">Leave empty for no limit</span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Exclude Days</label>
            <div class="day-checkboxes">
              <label
                v-for="day in daysOfWeek"
                :key="day.value"
                class="day-check-item"
                :class="{ excluded: form.exclude_days.includes(day.value) }"
              >
                <input
                  type="checkbox"
                  :value="day.value"
                  v-model="form.exclude_days"
                  class="day-checkbox"
                />
                <span>{{ day.label }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Step 3: Auto-Assign & Preview -->
        <div v-if="currentStep === 2" class="step-content">
          <h4 class="step-title">Auto-Assign Options</h4>

          <div class="auto-options">
            <label class="auto-option">
              <input type="checkbox" v-model="form.auto_assign_rooms" class="auto-checkbox" />
              <div class="auto-option-content">
                <span class="auto-option-title">Auto-assign Rooms</span>
                <span class="auto-option-desc">Automatically assign available rooms to each slot</span>
              </div>
            </label>

            <label class="auto-option">
              <input type="checkbox" v-model="form.auto_assign_teachers" class="auto-checkbox" />
              <div class="auto-option-content">
                <span class="auto-option-title">Auto-assign Teachers</span>
                <span class="auto-option-desc">Automatically assign teachers with workload balancing</span>
              </div>
            </label>
          </div>

          <!-- Summary -->
          <div class="generate-summary">
            <h4 class="step-title">Generation Summary</h4>
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Subjects</span>
                <span class="summary-value">{{ selectedSubjectIds.length }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Date Range</span>
                <span class="summary-value">{{ form.start_date }} to {{ form.end_date }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Slot Duration</span>
                <span class="summary-value">{{ form.slot_duration }} min</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Daily Schedule</span>
                <span class="summary-value">{{ form.start_time }} - {{ form.end_time_limit || 'No limit' }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Excluded Days</span>
                <span class="summary-value">{{ form.exclude_days.length ? form.exclude_days.join(', ') : 'None' }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Auto Rooms</span>
                <span class="summary-value">{{ form.auto_assign_rooms ? 'Yes' : 'No' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="currentStep > 0 ? currentStep-- : $emit('close')">
          {{ currentStep > 0 ? 'Back' : 'Cancel' }}
        </button>
        <button
          v-if="currentStep < 2"
          class="btn btn-primary"
          @click="nextStep"
          :disabled="!canProceed"
        >
          Next
        </button>
        <button
          v-else
          class="btn btn-primary"
          @click="handleGenerate"
          :disabled="saving"
        >
          <span v-if="saving" class="btn-spinner"></span>
          {{ saving ? 'Generating...' : 'Generate Routine' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'

const props = defineProps({
  exams: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  selectedExam: { type: Object, default: null },
  classes: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
  examTypes: { type: Array, default: () => [] },
  /** offline | online — matches routine grid channel */
  deliveryMode: { type: String, default: 'offline' },
})

const emit = defineEmits(['close', 'generate'])

const steps = ['Select Exam & Subjects', 'Date Range & Timing', 'Auto-Assign & Generate']
const currentStep = ref(0)
const saving = ref(false)
const formError = ref(null)
const selectedSubjectIds = ref([])
const selectedExamTypeId = ref('')
const wizardSubjects = ref([])
const subjectsLoading = ref(false)

// ===== CASCADING SELECTION STATE =====
const selectedClassId = ref('')
const selectedCourseId = ref('')
const selectedBatchId = ref('')
const filteredCourses = ref([])
const filteredBatches = ref([])
const coursesLoading = ref(false)
const batchesLoading = ref(false)

const daysOfWeek = [
  { value: 'Friday', label: 'Fri' },
  { value: 'Saturday', label: 'Sat' },
  { value: 'Sunday', label: 'Sun' },
  { value: 'Monday', label: 'Mon' },
  { value: 'Tuesday', label: 'Tue' },
  { value: 'Wednesday', label: 'Wed' },
  { value: 'Thursday', label: 'Thu' },
]

const form = ref({
  exam_id: '',
  start_date: '',
  end_date: '',
  slot_duration: 60,
  gap_minutes: 10,
  start_time: '09:00',
  end_time_limit: '17:00',
  exclude_days: ['Friday'],
  auto_assign_rooms: true,
  auto_assign_teachers: true,
})

// Pre-select exam from selectedExam when modal opens
watch(() => props.selectedExam, (val) => {
  if (val && val.id) {
    form.value.exam_id = val.id
  }
}, { immediate: true })

// When exam selection changes, fetch only subjects associated with that exam's level
watch(() => form.value.exam_id, async (newExamId, oldExamId) => {
  // Clear selected subjects when exam changes
  if (newExamId !== oldExamId) {
    selectedSubjectIds.value = []
  }

  if (!newExamId) {
    wizardSubjects.value = []
    return
  }

  // Find the selected exam from props
  const exam = props.exams.find(e => e.id === newExamId)
  if (!exam) {
    wizardSubjects.value = []
    return
  }

  subjectsLoading.value = true
  try {
    let subjectsData = []
    if (exam.class_id) {
      // Class-level exam: fetch subjects by class
      const res = await academicService.subjects.byClass(exam.class_id)
      subjectsData = res.data?.data || []
    } else if (exam.batch_id) {
      // Batch-level exam: fetch subjects from batch's course
      // Use the batch relationship if available, otherwise fallback to all subjects
      if (exam.batch?.course?.subjects) {
        subjectsData = exam.batch.course.subjects
      } else {
        // Fallback: load all subjects (backend will filter)
        subjectsData = [...props.subjects]
      }
    } else if (exam.course_id) {
      // Course-level exam: fetch subjects from course
      if (exam.course?.subjects) {
        subjectsData = exam.course.subjects
      } else {
        // Fallback: load all subjects (backend will filter)
        subjectsData = [...props.subjects]
      }
    } else {
      subjectsData = [...props.subjects]
    }
    wizardSubjects.value = subjectsData
    console.log(`[GenerateWizard] Loaded ${subjectsData.length} subjects for exam ${exam.name}`)
  } catch (e) {
    console.error('[GenerateWizard] Failed to load subjects for exam:', e)
    // Fallback to all subjects
    wizardSubjects.value = [...props.subjects]
  } finally {
    subjectsLoading.value = false
  }
})

const canProceed = computed(() => {
  if (currentStep.value === 0) {
    return form.value.exam_id && selectedSubjectIds.value.length > 0
  }
  if (currentStep.value === 1) {
    return form.value.start_date && form.value.end_date && form.value.start_time
  }
  return true
})

// ===== CASCADING HANDLERS =====
async function onClassChange() {
  selectedCourseId.value = ''
  selectedBatchId.value = ''
  filteredCourses.value = []
  filteredBatches.value = []
  if (!selectedClassId.value) return
  coursesLoading.value = true
  try {
    const res = await enrollmentService.getCourses({ class_id: selectedClassId.value })
    filteredCourses.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to load courses:', e)
    filteredCourses.value = []
  } finally {
    coursesLoading.value = false
  }
}

async function onCourseChange() {
  selectedBatchId.value = ''
  filteredBatches.value = []
  if (!selectedCourseId.value) return
  batchesLoading.value = true
  try {
    const res = await enrollmentService.getBatchesByCourse(selectedCourseId.value)
    filteredBatches.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to load batches:', e)
    filteredBatches.value = []
  } finally {
    batchesLoading.value = false
  }
}

async function onBatchChange() {
  if (!selectedBatchId.value || !selectedCourseId.value) return
  subjectsLoading.value = true
  try {
    const res = await academicService.subjects.byCourse(selectedCourseId.value)
    wizardSubjects.value = res.data?.data || []
    selectedSubjectIds.value = []
  } catch (e) {
    console.error('Failed to load subjects:', e)
    wizardSubjects.value = []
  } finally {
    subjectsLoading.value = false
  }
}

function nextStep() {
  formError.value = null
  if (currentStep.value === 0) {
    if (!form.value.exam_id) {
      formError.value = 'Please select an exam'
      return
    }
    if (!selectedSubjectIds.value.length) {
      formError.value = 'Please select at least one subject'
      return
    }
  }
  if (currentStep.value === 1) {
    if (!form.value.start_date || !form.value.end_date) {
      formError.value = 'Please select start and end dates'
      return
    }
    if (new Date(form.value.end_date) < new Date(form.value.start_date)) {
      formError.value = 'End date must be after start date'
      return
    }
    if (!form.value.start_time) {
      formError.value = 'Please set a daily start time'
      return
    }
  }
  currentStep.value++
}

async function handleGenerate() {
  saving.value = true
  formError.value = null
  try {
    const payload = {
      exam_id: form.value.exam_id,
      exam_type_id: selectedExamTypeId.value || null,
      subject_ids: selectedSubjectIds.value,
      start_date: form.value.start_date,
      end_date: form.value.end_date,
      slot_duration: form.value.slot_duration,
      gap_minutes: form.value.gap_minutes,
      start_time: form.value.start_time,
      end_time_limit: form.value.end_time_limit || null,
      exclude_days: form.value.exclude_days,
      auto_assign_rooms: form.value.auto_assign_rooms,
      auto_assign_teachers: form.value.auto_assign_teachers,
      apply: true,
      merge_mode: 'append',
      delivery_mode: props.deliveryMode || 'offline',
      // Cascading selection IDs
      class_id: selectedClassId.value || null,
      course_id: selectedCourseId.value || null,
      batch_id: selectedBatchId.value || null,
    }
    console.log('[GenerateWizard] Emitting generate with payload:', JSON.stringify(payload, null, 2))
    emit('generate', payload)
  } catch (e) {
    formError.value = e.message || 'Generation failed'
    console.error('[GenerateWizard] Error in handleGenerate:', e)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-container {
  background: var(--bg-card);
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.modal-lg {
  max-width: 680px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.modal-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: #f3f4f6;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  transition: background 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
}

/* Steps Bar */
.steps-bar {
  display: flex;
  justify-content: center;
  gap: 2rem;
  padding: 1.25rem 1.5rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  position: relative;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8125rem;
  font-weight: 600;
  background: #e5e7eb;
  color: var(--text-muted);
  transition: all 0.3s;
}

.step.active .step-number {
  background: #4f46e5;
  color: white;
}

.step.completed .step-number {
  background: #059669;
  color: white;
}

.step-check {
  width: 16px;
  height: 16px;
}

.step-label {
  font-size: 0.6875rem;
  color: var(--text-muted);
  font-weight: 500;
  text-align: center;
  white-space: nowrap;
}

.step.active .step-label {
  color: #4f46e5;
  font-weight: 600;
}

.step.completed .step-label {
  color: #059669;
}

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modal-footer {
  display: flex;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Alert */
.alert {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
}

.alert-error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.alert-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Step Content */
.step-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.step-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

/* Form */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.form-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-secondary);
}

.required {
  color: #dc2626;
}

.form-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.form-input,
.form-select {
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

/* Subject Checklist */
.subject-checklist {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  max-height: 240px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.5rem;
}

.subject-check-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
  border: 1px solid transparent;
}

.subject-check-item:hover {
  background: var(--bg-accent);
}

.subject-check-item.selected {
  background: #eef2ff;
  border-color: #c7d2fe;
}

.subject-checkbox {
  accent-color: #4f46e5;
}

.subject-check-name {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
}

.subject-check-code {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-left: auto;
}

.subjects-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1.5rem;
  color: var(--text-muted);
  font-size: 0.875rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
}

.no-subjects {
  padding: 1.5rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.875rem;
}

/* Day Checkboxes */
.day-checkboxes {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.day-check-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  font-size: 0.8125rem;
  cursor: pointer;
  transition: all 0.2s;
}

.day-check-item.excluded {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.day-checkbox {
  accent-color: #4f46e5;
}

/* Auto Options */
.auto-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.auto-option {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.auto-option:hover {
  border-color: #c7d2fe;
  background: var(--bg-accent);
}

.auto-checkbox {
  margin-top: 2px;
  accent-color: #4f46e5;
}

.auto-option-content {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.auto-option-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
}

.auto-option-desc {
  font-size: 0.75rem;
  color: var(--text-muted);
}

/* Summary */
.generate-summary {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1rem;
  background: var(--bg-accent);
}

.summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.summary-label {
  font-size: 0.6875rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 500;
}

.summary-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
}

/* Buttons */
.btn {
  padding: 0.625rem 1.25rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: background 0.2s, opacity 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #4338ca;
}

.btn-secondary {
  background: #f3f4f6;
  color: var(--text-secondary);
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Empty exams notice */
.empty-exams-notice {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  color: #92400e;
  font-size: 0.8125rem;
  line-height: 1.4;
}

.empty-exams-notice svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  margin-top: 1px;
  color: #d97706;
}

/* Loading inline */
.loading-inline {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--bg-accent);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.8125rem;
  color: var(--text-muted);
}

.spinner-sm {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

/* Form group */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.form-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.form-select,
.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-select:focus,
.form-input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.required {
  color: #dc2626;
}

.form-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
}
</style>
