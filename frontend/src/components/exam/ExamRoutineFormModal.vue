<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-container">
      <div class="modal-header">
        <div>
          <h3>{{ isEditing ? 'Edit Exam Slot' : 'Add Exam Slot' }}</h3>
          <p v-if="slotContextLabel" class="slot-context">{{ slotContextLabel }}</p>
        </div>
        <button class="modal-close" @click="$emit('close')">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <!-- Error Alert -->
        <div v-if="formError" class="alert alert-error">
          <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ formError }}</span>
        </div>

        <!-- Exam Selection (hidden when exam already chosen on routine page) -->
        <div v-if="!lockExam" class="form-group">
          <label class="form-label">Exam <span class="required">*</span></label>
          <div v-if="!exams.length" class="empty-exams-notice">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>No exams available. Please select a batch, course, or class that has exams, or create a new exam first.</span>
          </div>
          <select v-else v-model="form.exam_id" class="form-select" required @change="onExamChange">
            <option value="" disabled>Select exam</option>
            <option v-for="exam in exams" :key="exam.id" :value="exam.id">
              {{ exam.name }}
            </option>
          </select>
        </div>
        <div v-else class="form-group">
          <label class="form-label">Exam</label>
          <p class="locked-exam">{{ selectedExam?.name || 'Selected exam' }}</p>
        </div>

        <!-- Paper format (MCQ / CQ / MCQ+CQ) -->
        <div class="form-group">
          <label class="form-label">Paper Format <span class="required">*</span></label>
          <select v-model="selectedExamTypeId" class="form-select" @change="onPaperFormatChange">
            <option value="">Select format (MCQ, CQ, or MCQ+CQ)</option>
            <option v-for="et in paperExamTypes" :key="et.id" :value="et.id">{{ et.name }}</option>
          </select>
          <p class="field-hint">Mark components below update based on paper format.</p>
        </div>

        <!-- ===== CASCADING SELECTION ===== -->

        <!-- Step 1: Class -->
        <div class="form-group">
          <label class="form-label">Class <span class="required">*</span></label>
          <select v-model="selectedClassId" class="form-select" @change="onClassChange">
            <option value="">-- Select Class --</option>
            <option v-for="cls in classes" :key="cls.id" :value="cls.id">
              {{ cls.name }}
            </option>
          </select>
        </div>

        <!-- Step 2: Course (filtered by class) -->
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

        <!-- Step 3: Batch (filtered by course) -->
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

        <!-- Step 4: Subject (filtered by batch/course) -->
        <div class="form-group" v-if="selectedBatchId">
          <label class="form-label">Subject <span class="required">*</span></label>
          <div v-if="subjectsLoading" class="loading-inline">
            <span class="spinner-sm"></span> Loading subjects...
          </div>
          <select v-else v-model="form.subject_id" class="form-select" required>
            <option value="" disabled>Select subject</option>
            <option v-for="subject in filteredSubjects" :key="subject.id" :value="subject.id">
              {{ subject.name }}
            </option>
          </select>
        </div>

        <!-- Delivery mode (per slot) -->
        <div class="form-group">
          <label class="form-label">Delivery Mode</label>
          <select v-if="!lockDeliveryMode" v-model="form.delivery_mode" class="form-select">
            <option value="offline">Offline (default — printed / center exam)</option>
            <option value="online">Online (live exam in student portal)</option>
            <option value="hybrid">Hybrid (online + offline components)</option>
          </select>
          <p v-else class="locked-exam">
            {{ form.delivery_mode === 'online' ? 'Online' : form.delivery_mode === 'hybrid' ? 'Hybrid' : 'Offline' }}
            <span class="field-hint"> — fixed for the current routine grid view</span>
          </p>
          <p v-if="!lockDeliveryMode" class="field-hint">Offline slots stay in the routine schedule only. Online slots appear under Live Exams after publish.</p>
        </div>

        <!-- ===== ROUTINE DETAILS ===== -->

        <!-- Date -->
        <div class="form-group">
          <label class="form-label">Exam Date <span class="required">*</span></label>
          <input v-model="form.exam_date" type="date" class="form-input" required />
        </div>

        <!-- Time Row -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Time <span class="required">*</span></label>
            <input v-model="form.start_time" type="time" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">End Time <span class="required">*</span></label>
            <input v-model="form.end_time" type="time" class="form-input" required />
          </div>
        </div>

        <!-- Marks Row -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Total Marks</label>
            <input v-model.number="form.total_marks" type="number" class="form-input" min="0" placeholder="100" />
          </div>
          <div class="form-group">
            <label class="form-label">Pass Marks</label>
            <input v-model.number="form.pass_marks" type="number" class="form-input" min="0" placeholder="33" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mark Components</label>
          <MarkConfigEditor v-model="form.mark_config" @total-change="onMarkConfigTotalChange" />
        </div>

        <div class="form-group">
          <label class="form-label">Paper Instructions</label>
          <textarea v-model="form.instructions" class="form-input" rows="3" placeholder="Optional instructions for this paper"></textarea>
        </div>

        <!-- Teacher -->
        <div class="form-group">
          <label class="form-label">Invigilator Teacher</label>
          <select v-model="form.teacher_id" class="form-select">
            <option value="">-- Not assigned --</option>
            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
              {{ teacher.name || teacher.first_name + ' ' + (teacher.last_name || '') }}
            </option>
          </select>
        </div>

        <!-- Room -->
        <div class="form-group">
          <label class="form-label">Room</label>
          <select v-model="form.room_id" class="form-select">
            <option value="">-- Not assigned --</option>
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.name || room.room_number }}
            </option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')" :disabled="saving">
          Cancel
        </button>
        <button class="btn btn-primary" @click="handleSubmit" :disabled="saving || !isValid">
          <span v-if="saving" class="btn-spinner"></span>
          {{ saving ? 'Saving...' : isEditing ? 'Update Slot' : 'Add Slot' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import MarkConfigEditor from '@/components/exam/MarkConfigEditor.vue'
import { DEFAULT_MARK_CONFIG, configTotalMarks } from '@/utils/markConfig.utils'
import { markConfigFromExamType } from '@/utils/examType.utils'

const props = defineProps({
  routine: { type: Object, default: null },
  exams: { type: Array, default: () => [] },
  classes: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
  examTypes: { type: Array, default: () => [] },
  selectedExam: { type: Object, default: null },
  initialSlot: { type: Object, default: null },
  defaultDeliveryMode: { type: String, default: 'offline' },
  lockDeliveryMode: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const isEditing = computed(() => !!props.routine)
const lockExam = computed(() => !!(props.selectedExam?.id) && !isEditing.value)

const slotContextLabel = computed(() => {
  const s = props.initialSlot
  if (!s?.exam_date || isEditing.value) return ''
  const parts = []
  if (s.day_name) parts.push(s.day_name)
  parts.push(s.exam_date)
  if (s.start_time && s.end_time) parts.push(`${s.start_time} – ${s.end_time}`)
  return parts.join(' · ')
})

function normalizeTimeInput(value) {
  if (!value) return ''
  const str = String(value)
  return str.length >= 5 ? str.substring(0, 5) : str
}
const paperExamTypes = computed(() =>
  (props.examTypes || []).filter((t) => t.status !== 'inactive')
)
const saving = ref(false)
const formError = ref(null)

// ===== EXAM TYPE STATE =====
const selectedExamTypeId = ref('')

// ===== CASCADING STATE =====
const selectedClassId = ref('')
const selectedCourseId = ref('')
const selectedBatchId = ref('')

const filteredCourses = ref([])
const filteredBatches = ref([])
const filteredSubjects = ref([])

const coursesLoading = ref(false)
const batchesLoading = ref(false)
const subjectsLoading = ref(false)

const form = ref({
  exam_id: '',
  subject_id: '',
  exam_date: '',
  start_time: '',
  end_time: '',
  total_marks: 100,
  pass_marks: 33,
  mark_config: JSON.parse(JSON.stringify(DEFAULT_MARK_CONFIG)),
  instructions: '',
  teacher_id: '',
  room_id: '',
  delivery_mode: 'offline',
})

// Populate form when editing
watch(() => props.routine, (val) => {
  if (val) {
    form.value = {
      exam_id: val.exam_id || '',
      subject_id: val.subject_id || '',
      exam_date: val.exam_date ? val.exam_date.substring(0, 10) : '',
      start_time: val.start_time ? val.start_time.substring(0, 5) : '',
      end_time: val.end_time ? val.end_time.substring(0, 5) : '',
      total_marks: val.total_marks || 100,
      pass_marks: val.pass_marks || 33,
      mark_config: val.mark_config ? JSON.parse(JSON.stringify(val.mark_config)) : JSON.parse(JSON.stringify(DEFAULT_MARK_CONFIG)),
      instructions: val.instructions || '',
      teacher_id: val.teacher_id || '',
      room_id: val.room_id || '',
      delivery_mode: val.delivery_mode || 'offline',
    }
    // Restore cascading selections from routine data
    if (val.class_id) selectedClassId.value = val.class_id
    if (val.course_id) selectedCourseId.value = val.course_id
    if (val.batch_id) selectedBatchId.value = val.batch_id
  }
}, { immediate: true })

// Pre-select exam from selectedExam when creating (not editing)
watch(() => props.selectedExam, (val) => {
  if (!isEditing.value && val && val.id) {
    form.value.exam_id = val.id
  }
}, { immediate: true })

// Pre-fill date/time/delivery when adding from grid cell click
watch(() => props.initialSlot, (val) => {
  if (isEditing.value || !val) return
  if (val.exam_date) form.value.exam_date = val.exam_date
  if (val.start_time) form.value.start_time = normalizeTimeInput(val.start_time)
  if (val.end_time) form.value.end_time = normalizeTimeInput(val.end_time)
  if (val.delivery_mode) form.value.delivery_mode = val.delivery_mode
}, { immediate: true })

watch(() => props.defaultDeliveryMode, (val) => {
  if (!isEditing.value && val) {
    form.value.delivery_mode = val
  }
}, { immediate: true })

watch(() => props.lockDeliveryMode, (locked) => {
  if (!isEditing.value && locked && props.defaultDeliveryMode) {
    form.value.delivery_mode = props.defaultDeliveryMode
  }
}, { immediate: true })

// ===== CASCADING HANDLERS =====

function onExamChange() {
  // Reset cascading when exam changes
  selectedClassId.value = ''
  selectedCourseId.value = ''
  selectedBatchId.value = ''
  form.value.subject_id = ''
  filteredCourses.value = []
  filteredBatches.value = []
  filteredSubjects.value = []
}

async function onClassChange() {
  selectedCourseId.value = ''
  selectedBatchId.value = ''
  form.value.subject_id = ''
  filteredCourses.value = []
  filteredBatches.value = []
  filteredSubjects.value = []

  if (!selectedClassId.value) return

  coursesLoading.value = true
  try {
    const res = await enrollmentService.getCourses({ class_id: selectedClassId.value })
    filteredCourses.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to load courses:', e)
    formError.value = 'Failed to load courses for this class.'
  } finally {
    coursesLoading.value = false
  }
}

async function onCourseChange() {
  selectedBatchId.value = ''
  form.value.subject_id = ''
  filteredBatches.value = []
  filteredSubjects.value = []

  if (!selectedCourseId.value) return

  batchesLoading.value = true
  try {
    const res = await enrollmentService.getBatchesByCourse(selectedCourseId.value)
    filteredBatches.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to load batches:', e)
    formError.value = 'Failed to load batches for this course.'
  } finally {
    batchesLoading.value = false
  }
}

async function onBatchChange() {
  form.value.subject_id = ''
  filteredSubjects.value = []

  if (!selectedBatchId.value) return

  subjectsLoading.value = true
  try {
    // Try to get subjects by course (since batch belongs to a course)
    if (selectedCourseId.value) {
      const res = await academicService.subjects.byCourse(selectedCourseId.value)
      filteredSubjects.value = res.data?.data || res.data || []
    } else {
      // Fallback: use all subjects from props
      filteredSubjects.value = [...props.subjects]
    }
  } catch (e) {
    console.error('Failed to load subjects:', e)
    formError.value = 'Failed to load subjects.'
  } finally {
    subjectsLoading.value = false
  }
}

const isValid = computed(() => {
  return form.value.exam_id
    && selectedExamTypeId.value
    && selectedClassId.value
    && selectedCourseId.value
    && selectedBatchId.value
    && form.value.subject_id
    && form.value.exam_date
    && form.value.start_time
    && form.value.end_time
    && form.value.start_time < form.value.end_time
})

function onPaperFormatChange() {
  const et = paperExamTypes.value.find((t) => t.id === selectedExamTypeId.value)
  const cfg = markConfigFromExamType(et)
  if (cfg) {
    form.value.mark_config = JSON.parse(JSON.stringify(cfg))
    const total = configTotalMarks(cfg)
    if (total > 0) {
      form.value.total_marks = total
      form.value.pass_marks = Math.round(total * 0.4)
    }
  }
}

function onMarkConfigTotalChange(total) {
  if (total > 0) {
    form.value.total_marks = total
  }
}

async function handleSubmit() {
  if (!isValid.value) return
  saving.value = true
  formError.value = null
  try {
    const payload = {
      exam_id: form.value.exam_id,
      subject_id: form.value.subject_id,
      exam_type_id: selectedExamTypeId.value || null,
      class_id: selectedClassId.value,
      course_id: selectedCourseId.value,
      batch_id: selectedBatchId.value,
      exam_date: form.value.exam_date,
      start_time: form.value.start_time,
      end_time: form.value.end_time,
      total_marks: form.value.total_marks || null,
      pass_marks: form.value.pass_marks || null,
      mark_config: form.value.mark_config || null,
      instructions: form.value.instructions || null,
      teacher_id: form.value.teacher_id || null,
      room_id: form.value.room_id || null,
      delivery_mode: form.value.delivery_mode || 'offline',
    }
    emit('save', payload)
  } catch (e) {
    formError.value = e.message || 'Failed to save'
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

.slot-context {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  color: #4f46e5;
  font-weight: 500;
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

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
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

.form-input,
.form-select {
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color 0.2s, box-shadow 0.2s;
  width: 100%;
  box-sizing: border-box;
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
  font-size: 0.8125rem;
  color: var(--text-muted);
}

.locked-exam {
  margin: 0;
  padding: 0.5rem 0.75rem;
  background: #eef2ff;
  border-radius: 8px;
  font-weight: 600;
  color: #4f46e5;
  font-size: 0.9rem;
}
.field-hint {
  margin: 0.25rem 0 0;
  font-size: 0.75rem;
  color: var(--text-muted);
}
.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}
</style>
