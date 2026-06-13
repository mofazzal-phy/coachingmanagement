<template>
  <div class="modal-overlay" v-if="modelValue" @click.self="$emit('update:modelValue', false)">
    <div class="modal-dialog" style="max-width: 600px;">
      <!-- Header -->
      <div class="modal-header">
        <h3>Add Manual Slot</h3>
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
        <!-- Step 1: Class → Course → Batch (Cascading) -->
        <div v-if="currentStep === 0">
          <!-- Class Select -->
          <div class="form-group">
            <label>Class <span class="required">*</span></label>
            <div v-if="loadingClasses" class="text-center py-2">
              <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
              <p class="text-muted">Loading classes...</p>
            </div>
            <select
              v-else
              v-model="form.class_id"
              class="form-control"
              required
            >
              <option value="" disabled>Select class</option>
              <option v-for="c in allClasses" :key="c.id" :value="c.id">
                {{ c.name || c.title }}
              </option>
            </select>
          </div>

          <!-- Course Select (filtered by class) -->
          <div v-if="form.class_id" class="form-group">
            <label>Course <span class="required">*</span></label>
            <div v-if="loadingCourses" class="text-center py-2">
              <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
              <p class="text-muted">Loading courses...</p>
            </div>
            <select
              v-else
              v-model="form.course_id"
              class="form-control"
              required
            >
              <option value="" disabled>Select course</option>
              <option v-for="co in filteredCourses" :key="co.id" :value="co.id">
                {{ co.name || co.title }}
              </option>
            </select>
            <p v-if="!loadingCourses && filteredCourses.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
              No courses available for this class
            </p>
          </div>

          <!-- Batch Select (filtered by course) -->
          <div v-if="form.course_id" class="form-group">
            <label>Batch <span class="required">*</span></label>
            <div v-if="loadingBatches" class="text-center py-2">
              <div class="spinner" style="margin: 0 auto 0.3rem;"></div>
              <p class="text-muted">Loading batches...</p>
            </div>
            <select
              v-else
              v-model="form.batch_id"
              class="form-control"
              required
            >
              <option value="" disabled>Select batch</option>
              <option v-for="b in filteredBatches" :key="b.id" :value="b.id">
                {{ b.name || b.title }}
              </option>
            </select>
            <p v-if="!loadingBatches && filteredBatches.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
              No batches available for this course
            </p>
          </div>
        </div>

        <!-- Step 2: Select Subject -->
        <div v-if="currentStep === 1">
          <div class="form-group">
            <label>
              Select Subject <span class="required">*</span>
            </label>
            <div v-if="loadingSubjects" class="text-center py-4">
              <div class="spinner" style="margin: 0 auto 0.5rem;"></div>
              <p class="text-muted">Loading subjects...</p>
            </div>
            <div v-else class="subject-list">
              <label
                v-for="subj in filteredSubjects"
                :key="subj.id"
                class="subject-item"
                :class="form.subject_id === subj.id ? 'selected' : ''"
              >
                <input
                  type="radio"
                  :value="subj.id"
                  v-model="form.subject_id"
                  name="subject_select"
                />
                <span class="subject-item-name">{{ subj.name }}</span>
                <span v-if="subj.code" class="subject-item-code">({{ subj.code }})</span>
              </label>
              <div v-if="!filteredSubjects.length" class="empty-mini">
                No subjects available for this batch
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Slot Details -->
        <div v-if="currentStep === 2">
          <!-- Teacher -->
          <div class="form-group">
            <label>Teacher <span class="required">*</span></label>
            <div v-if="loadingTeachers" class="text-center py-4">
              <div class="spinner" style="margin: 0 auto 0.5rem;"></div>
              <p class="text-muted">Loading teachers...</p>
            </div>
            <select
              v-else
              v-model="form.teacher_id"
              class="form-control"
              required
            >
              <option value="" disabled>Select teacher</option>
              <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">
                {{ t.name || (t.first_name && t.last_name ? t.first_name + ' ' + t.last_name : '') || t.full_name || t.user?.name || t.teacher_id || 'Unknown' }}
              </option>
            </select>
            <p v-if="!loadingTeachers && filteredTeachers.length === 0" class="text-muted" style="font-size:0.8rem; margin-top:0.25rem;">
              No teachers found for this subject. Select a subject first.
            </p>
          </div>

          <!-- Day of Week -->
          <div class="form-group">
            <label>Day <span class="required">*</span></label>
            <select
              v-model="form.day_of_week"
              class="form-control"
              required
            >
              <option value="" disabled>Select day</option>
              <option value="sat">Saturday</option>
              <option value="sun">Sunday</option>
              <option value="mon">Monday</option>
              <option value="tue">Tuesday</option>
              <option value="wed">Wednesday</option>
              <option value="thu">Thursday</option>
              <option value="fri">Friday</option>
            </select>
          </div>

          <!-- Time Range -->
          <div class="form-row">
            <div class="form-group">
              <label>Start Time <span class="required">*</span></label>
              <input
                v-model="form.start_time"
                type="time"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label>End Time <span class="required">*</span></label>
              <input
                v-model="form.end_time"
                type="time"
                class="form-control"
                required
              />
            </div>
          </div>

          <!-- Room -->
          <div class="form-group">
            <label>Room</label>
            <select
              v-model="form.room_id"
              class="form-control"
            >
              <option value="">No room</option>
              <option v-for="r in rooms" :key="r.id" :value="r.id">
                {{ r.name || r.room_number }}
              </option>
            </select>
          </div>

          <!-- Group -->
          <div class="form-group">
            <label>Group</label>
            <select
              v-model="form.group_id"
              class="form-control"
            >
              <option value="">No group</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">
                {{ g.name }}
              </option>
            </select>
          </div>

          <!-- Status -->
          <div class="form-group">
            <label>Status</label>
            <select
              v-model="form.status"
              class="form-control"
            >
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </select>
          </div>
        </div>

        <!-- Step 3: Preview & Confirm -->
        <div v-if="currentStep === 3">
          <div class="preview-card">
            <div class="preview-section">
              <div class="preview-label">Class</div>
              <div class="preview-value">{{ selectedClassName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Course</div>
              <div class="preview-value">{{ selectedCourseName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Batch</div>
              <div class="preview-value">{{ selectedBatchName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Subject</div>
              <div class="preview-value">{{ selectedSubjectName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Teacher</div>
              <div class="preview-value">{{ selectedTeacherName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Day</div>
              <div class="preview-value">{{ dayLabels[form.day_of_week] || form.day_of_week }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Time</div>
              <div class="preview-value">{{ form.start_time }} - {{ form.end_time }}</div>
            </div>
            <div class="preview-section" v-if="form.room_id">
              <div class="preview-label">Room</div>
              <div class="preview-value">{{ selectedRoomName }}</div>
            </div>
            <div class="preview-section" v-if="form.group_id">
              <div class="preview-label">Group</div>
              <div class="preview-value">{{ selectedGroupName }}</div>
            </div>
            <div class="preview-section">
              <div class="preview-label">Status</div>
              <div class="preview-value">{{ capitalize(form.status) }}</div>
            </div>
          </div>

          <div v-if="error" class="alert alert-danger" style="margin-top: 1rem;">
            {{ error }}
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
            :disabled="saving"
            @click="handleSave"
          >
            {{ saving ? 'Saving...' : 'Save Slot' }}
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
import teacherService from '@/services/teacher.service'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  batches: { type: Array, default: () => [] },
  courses: { type: Array, default: () => [] },
  classes: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
  // Multi-batch support
  multiBatchEnabled: { type: Boolean, default: false },
  selectedBatchIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'save'])

const steps = ['Class → Batch', 'Subject', 'Details', 'Confirm']
const currentStep = ref(0)

// Cascading dropdown state
const allClasses = ref([])
const loadingClasses = ref(false)
const filteredCourses = ref([])
const loadingCourses = ref(false)
const filteredBatches = ref([])
const loadingBatches = ref(false)

// Subject & Teacher state
const filteredSubjects = ref([])
const loadingSubjects = ref(false)
const filteredTeachers = ref([])
const loadingTeachers = ref(false)

const form = reactive({
  class_id: '',
  course_id: '',
  batch_id: '',
  subject_id: '',
  teacher_id: '',
  day_of_week: '',
  start_time: '',
  end_time: '',
  room_id: '',
  group_id: '',
  status: 'draft',
})

const dayLabels = {
  sat: 'Saturday',
  sun: 'Sunday',
  mon: 'Monday',
  tue: 'Tuesday',
  wed: 'Wednesday',
  thu: 'Thursday',
  fri: 'Friday',
}

// ===== Computed: display names for preview =====
const selectedClassName = computed(() => {
  const c = allClasses.value.find(c => c.id === form.class_id)
  return c ? (c.name || c.title) : ''
})

const selectedCourseName = computed(() => {
  const co = filteredCourses.value.find(co => co.id === form.course_id)
  return co ? (co.name || co.title) : ''
})

const selectedBatchName = computed(() => {
  const b = filteredBatches.value.find(b => b.id === form.batch_id)
  return b ? (b.name || b.title) : ''
})

const selectedSubjectName = computed(() => {
  const subj = filteredSubjects.value.find(s => s.id === form.subject_id)
  return subj ? subj.name : ''
})

const selectedTeacherName = computed(() => {
  const t = filteredTeachers.value.find(t => t.id === form.teacher_id)
  return t ? (t.name || (t.first_name && t.last_name ? t.first_name + ' ' + t.last_name : '') || t.full_name || t.user?.name || t.teacher_id || '') : ''
})

const selectedRoomName = computed(() => {
  const r = props.rooms.find(r => r.id === form.room_id)
  return r ? (r.name || r.room_number) : ''
})

const selectedGroupName = computed(() => {
  const g = props.groups.find(g => g.id === form.group_id)
  return g ? g.name : ''
})

// ===== canProceed =====
const canProceed = computed(() => {
  if (currentStep.value === 0) return !!form.batch_id
  if (currentStep.value === 1) return !!form.subject_id
  if (currentStep.value === 2) {
    return form.teacher_id && form.day_of_week && form.start_time && form.end_time
  }
  return false
})

// ===== Helpers =====
function capitalize(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function nextStep() {
  currentStep.value++
}

function handleSave() {
  const payload = {
    batch_id: form.batch_id,
    subject_id: form.subject_id,
    teacher_id: form.teacher_id,
    day_of_week: form.day_of_week,
    start_time: form.start_time,
    end_time: form.end_time,
    room_id: form.room_id || null,
    group_id: form.group_id || null,
    status: form.status,
  }
  emit('save', payload)
}

// ===== Cascading Watchers =====

// 1. Load classes on mount / modal open
async function loadClasses() {
  loadingClasses.value = true
  try {
    const res = await academicService.classes.list()
    allClasses.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to load classes', e)
    allClasses.value = []
  } finally {
    loadingClasses.value = false
  }
}

// 2. When class_id changes → fetch courses filtered by class
watch(() => form.class_id, async (newClassId) => {
  form.course_id = ''
  form.batch_id = ''
  form.subject_id = ''
  form.teacher_id = ''
  filteredCourses.value = []
  filteredBatches.value = []
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newClassId) return
  loadingCourses.value = true
  try {
    const res = await enrollmentService.getCourses({ class_id: newClassId })
    filteredCourses.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch courses for class:', newClassId, e)
    filteredCourses.value = []
  } finally {
    loadingCourses.value = false
  }
})

// 3. When course_id changes → fetch batches by course
watch(() => form.course_id, async (newCourseId) => {
  form.batch_id = ''
  form.subject_id = ''
  form.teacher_id = ''
  filteredBatches.value = []
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newCourseId) return
  loadingBatches.value = true
  try {
    const res = await enrollmentService.getBatchesByCourse(newCourseId)
    filteredBatches.value = res?.data?.data || res?.data || []
  } catch (e) {
    console.warn('Failed to fetch batches for course:', newCourseId, e)
    filteredBatches.value = []
  } finally {
    loadingBatches.value = false
  }
})

// 4. When batch_id changes → fetch subjects via batch's course
watch(() => form.batch_id, async (newBatchId) => {
  form.subject_id = ''
  form.teacher_id = ''
  filteredSubjects.value = []
  filteredTeachers.value = []
  if (!newBatchId) return
  loadingSubjects.value = true
  try {
    const res = await enrollmentService.getBatch(newBatchId)
    const batch = res?.data?.data || res?.data
    if (batch?.course_id) {
      const courseRes = await enrollmentService.getCourse(batch.course_id)
      const course = courseRes?.data?.data || courseRes?.data
      filteredSubjects.value = course?.subjects || []
    } else {
      filteredSubjects.value = []
    }
  } catch (e) {
    console.warn('Failed to fetch subjects for batch:', newBatchId, e)
    filteredSubjects.value = []
  } finally {
    loadingSubjects.value = false
  }
})

// 5. When subject_id changes → fetch teachers
watch(() => form.subject_id, async (newSubjectId) => {
  form.teacher_id = ''
  filteredTeachers.value = []
  if (!newSubjectId || !form.batch_id) return
  loadingTeachers.value = true
  try {
    const res = await teacherService.bySubject(newSubjectId)
    const data = res?.data?.data || res?.data || []
    filteredTeachers.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.warn('Failed to fetch teachers for subject:', newSubjectId, e)
    filteredTeachers.value = []
  } finally {
    loadingTeachers.value = false
  }
})

// ===== Reset on open =====
watch(() => props.modelValue, (val) => {
  if (val) {
    currentStep.value = 0
    form.class_id = ''
    form.course_id = ''
    form.batch_id = ''
    form.subject_id = ''
    form.teacher_id = ''
    form.day_of_week = ''
    form.start_time = ''
    form.end_time = ''
    form.room_id = ''
    form.group_id = ''
    form.status = 'draft'
    filteredCourses.value = []
    filteredBatches.value = []
    filteredSubjects.value = []
    filteredTeachers.value = []
    loadClasses()
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

.subject-item.selected {
  background: #eef2ff;
}

.subject-item input[type="radio"] {
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

.preview-card {
  background: var(--bg-accent);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 1rem;
}

.preview-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.preview-section:last-child {
  border-bottom: none;
}

.preview-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.preview-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-dark);
  text-align: right;
}
</style>
