<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-container modal-xl">
      <div class="modal-header">
        <h3>Bulk Create Exam Routines</h3>
        <button class="modal-close" @click="$emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div v-if="formError" class="alert alert-error">{{ formError }}</div>

        <p class="intro-hint">
          Pick class and course, then choose <strong>all batches</strong> or one batch.
          Each <strong>subject</strong> gets its own time slot (all selected batches sit in that slot together).
        </p>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Exam <span class="required">*</span></label>
            <select v-model="selectedExamId" class="form-select" :disabled="lockExam">
              <option value="" disabled>Select exam</option>
              <option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Paper Format <span class="required">*</span></label>
            <select v-model="selectedExamTypeId" class="form-select" @change="onPaperFormatChange">
              <option value="">Select format</option>
              <option v-for="et in paperExamTypes" :key="et.id" :value="et.id">{{ et.name }}</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Class <span class="required">*</span></label>
            <select v-model="selectedClassId" class="form-select" @change="onClassChange">
              <option value="">Select class</option>
              <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Course <span class="required">*</span></label>
            <select v-model="selectedCourseId" class="form-select" :disabled="!selectedClassId" @change="onCourseChange">
              <option value="">Select course</option>
              <option v-for="c in filteredCourses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <div class="form-group" v-if="batches.length">
          <label class="form-label">Batches</label>
          <div class="radio-row">
            <label class="radio-label">
              <input type="radio" v-model="batchScope" value="all" />
              All batches ({{ batches.length }})
            </label>
            <label class="radio-label">
              <input type="radio" v-model="batchScope" value="single" />
              Single batch
            </label>
          </div>
          <select
            v-if="batchScope === 'single'"
            v-model="selectedBatchId"
            class="form-select batch-select"
          >
            <option value="">Select batch</option>
            <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <div class="form-group" v-if="subjects.length">
          <div class="subjects-header">
            <label class="form-label">Subjects ({{ selectedSubjectIds.length }} / {{ subjects.length }})</label>
            <button type="button" class="btn-link" @click="toggleAllSubjects">
              {{ allSubjectsSelected ? 'Deselect all' : 'Select all' }}
            </button>
          </div>
          <div class="subject-chips">
            <label v-for="s in subjects" :key="s.id" class="subject-chip" :class="{ active: selectedSubjectIds.includes(s.id) }">
              <input type="checkbox" :value="s.id" v-model="selectedSubjectIds" />
              {{ s.name }}
            </label>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Schedule mode</label>
            <select v-model="scheduleMode" class="form-select">
              <option value="same_day">Same day — stagger times per subject</option>
              <option value="next_day">One subject per day</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" v-model="skipFriday" />
              Skip Friday
            </label>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">First exam date <span class="required">*</span></label>
            <input v-model="shared.exam_date" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">First slot start <span class="required">*</span></label>
            <input v-model="shared.start_time" type="time" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Slot duration (min)</label>
            <input v-model.number="slotMinutes" type="number" min="30" max="240" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Gap between slots (min)</label>
            <input v-model.number="gapMinutes" type="number" min="0" max="120" class="form-input" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Day ends at (roll to next day)</label>
            <input v-model="dayEndTime" type="time" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Total Marks</label>
            <input v-model.number="shared.total_marks" type="number" min="1" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Pass Marks</label>
            <input v-model.number="shared.pass_marks" type="number" min="0" class="form-input" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mark Components</label>
          <MarkConfigEditor v-model="shared.mark_config" @total-change="onMarkTotalChange" />
        </div>

        <div v-if="previewRows.length" class="preview-box">
          <h4>Preview — {{ previewRows.length }} routine(s)</h4>
          <p class="preview-note">Each subject has a unique date/time; all selected batches share that slot.</p>
          <div class="preview-scroll">
            <table class="preview-table">
              <thead>
                <tr><th>Subject</th><th>Batch</th><th>Date</th><th>Time</th><th>Marks</th></tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in previewRows.slice(0, 60)" :key="i">
                  <td>{{ row.subject_name }}</td>
                  <td>{{ row.batch_name }}</td>
                  <td>{{ row.exam_date }}</td>
                  <td>{{ row.start_time }} – {{ row.end_time }}</td>
                  <td>{{ row.total_marks }}</td>
                </tr>
              </tbody>
            </table>
            <p v-if="previewRows.length > 60" class="preview-more">… and {{ previewRows.length - 60 }} more</p>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')" :disabled="saving">Cancel</button>
        <button class="btn btn-outline" @click="checkConflicts" :disabled="!canPreview || checkingConflicts">
          {{ checkingConflicts ? 'Checking…' : 'Check Conflicts' }}
        </button>
        <button class="btn btn-primary" @click="handleSubmit" :disabled="saving || !canSubmit">
          {{ saving ? 'Creating…' : `Create ${previewRows.length} Routine(s)` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import examService from '@/services/exam.service'
import MarkConfigEditor from '@/components/exam/MarkConfigEditor.vue'
import { markConfigFromExamType } from '@/utils/examType.utils'
import { configTotalMarks } from '@/utils/markConfig.utils'
import { buildStaggeredRoutineRows } from '@/utils/routineSchedule.utils'

const props = defineProps({
  exams: { type: Array, default: () => [] },
  classes: { type: Array, default: () => [] },
  examTypes: { type: Array, default: () => [] },
  selectedExam: { type: Object, default: null },
  onSave: { type: Function, default: null },
})

const emit = defineEmits(['close', 'save'])

const lockExam = computed(() => !!(props.selectedExam?.id))
const paperExamTypes = computed(() =>
  (props.examTypes || []).filter((t) => t.status !== 'inactive')
)

const selectedExamId = ref('')
const selectedExamTypeId = ref('')
const selectedClassId = ref('')
const selectedCourseId = ref('')
const batchScope = ref('all')
const selectedBatchId = ref('')
const selectedSubjectIds = ref([])
const scheduleMode = ref('same_day')
const skipFriday = ref(true)
const slotMinutes = ref(90)
const gapMinutes = ref(15)
const dayEndTime = ref('17:00')
const filteredCourses = ref([])
const batches = ref([])
const subjects = ref([])
const saving = ref(false)
const checkingConflicts = ref(false)
const formError = ref(null)

const shared = ref({
  exam_date: '',
  start_time: '10:00',
  total_marks: 80,
  pass_marks: 32,
  mark_config: markConfigFromExamType(null) || {},
})

watch(() => props.selectedExam, (val) => {
  if (val?.id) selectedExamId.value = val.id
}, { immediate: true })

const activeBatches = computed(() => {
  if (!batches.value.length) return []
  if (batchScope.value === 'all') return batches.value
  const b = batches.value.find((x) => x.id === selectedBatchId.value)
  return b ? [b] : []
})

const activeSubjects = computed(() =>
  subjects.value.filter((s) => selectedSubjectIds.value.includes(s.id))
)

const allSubjectsSelected = computed(
  () => subjects.value.length > 0 && selectedSubjectIds.value.length === subjects.value.length
)

const previewRows = computed(() => {
  if (!activeBatches.value.length || !activeSubjects.value.length) return []
  if (!shared.value.exam_date || !shared.value.start_time) return []

  return buildStaggeredRoutineRows({
    subjects: activeSubjects.value,
    batches: activeBatches.value,
    startDate: shared.value.exam_date,
    startTime: shared.value.start_time,
    slotMinutes: slotMinutes.value,
    gapMinutes: gapMinutes.value,
    dayEndTime: dayEndTime.value,
    dayMode: scheduleMode.value === 'next_day' ? 'next_day_per_subject' : 'same_day',
    skipFriday: skipFriday.value,
    sharedFields: {
      course_id: selectedCourseId.value,
      class_id: selectedClassId.value,
      total_marks: shared.value.total_marks,
      pass_marks: shared.value.pass_marks,
      mark_config: shared.value.mark_config,
    },
  })
})

const canPreview = computed(() => previewRows.value.length > 0 && selectedExamId.value)
const canSubmit = computed(() => canPreview.value && selectedExamTypeId.value && activeBatches.value.length)

function toggleAllSubjects() {
  if (allSubjectsSelected.value) {
    selectedSubjectIds.value = []
  } else {
    selectedSubjectIds.value = subjects.value.map((s) => s.id)
  }
}

function onPaperFormatChange() {
  const et = paperExamTypes.value.find((t) => t.id === selectedExamTypeId.value)
  const cfg = markConfigFromExamType(et)
  if (cfg) {
    shared.value.mark_config = JSON.parse(JSON.stringify(cfg))
    const total = configTotalMarks(cfg)
    if (total > 0) {
      shared.value.total_marks = total
      shared.value.pass_marks = Math.round(total * 0.4)
    }
  }
}

function onMarkTotalChange(total) {
  if (total > 0) {
    shared.value.total_marks = total
    if (!shared.value.pass_marks) shared.value.pass_marks = Math.round(total * 0.4)
  }
}

async function onClassChange() {
  selectedCourseId.value = ''
  batches.value = []
  subjects.value = []
  selectedSubjectIds.value = []
  filteredCourses.value = []
  if (!selectedClassId.value) return
  const res = await enrollmentService.getCourses({ class_id: selectedClassId.value })
  filteredCourses.value = res.data?.data || res.data || []
}

async function onCourseChange() {
  batches.value = []
  subjects.value = []
  selectedSubjectIds.value = []
  selectedBatchId.value = ''
  if (!selectedCourseId.value) return
  const [batchRes, subRes] = await Promise.all([
    enrollmentService.getBatchesByCourse(selectedCourseId.value),
    academicService.subjects.byCourse(selectedCourseId.value),
  ])
  batches.value = batchRes.data?.data || batchRes.data || []
  subjects.value = subRes.data?.data || subRes.data || []
  selectedSubjectIds.value = subjects.value.map((s) => s.id)
}

async function checkConflicts() {
  if (!selectedExamId.value) return
  checkingConflicts.value = true
  formError.value = null
  try {
    const res = await examService.routines.conflicts(selectedExamId.value)
    const conflicts = res.data?.data || []
    if (conflicts.length) {
      formError.value = `Found ${conflicts.length} scheduling conflict(s). Adjust schedule or remove overlapping routines.`
    } else {
      alert('No conflicts detected for existing routines.')
    }
  } catch (e) {
    formError.value = e.response?.data?.message || 'Could not check conflicts'
  } finally {
    checkingConflicts.value = false
  }
}

function validateRows() {
  if (!selectedExamId.value) return 'Select an exam'
  if (!selectedExamTypeId.value) return 'Select paper format'
  if (!selectedClassId.value || !selectedCourseId.value) return 'Select class and course'
  if (!shared.value.exam_date || !shared.value.start_time) return 'Set first exam date and start time'
  if (batchScope.value === 'single' && !selectedBatchId.value) return 'Select a batch'
  if (!activeSubjects.value.length) return 'Select at least one subject'
  if (!previewRows.value.length) return 'No routines to create'
  const keys = new Set()
  for (const row of previewRows.value) {
    const key = `${row.batch_id}-${row.subject_id}-${row.exam_date}-${row.start_time}`
    if (keys.has(key)) return 'Duplicate batch + subject + slot in preview'
    keys.add(key)
  }
  return null
}

async function handleSubmit() {
  const err = validateRows()
  if (err) {
    formError.value = err
    return
  }
  saving.value = true
  formError.value = null
  try {
    const payload = {
      exam_id: selectedExamId.value,
      exam_type_id: selectedExamTypeId.value,
      routines: previewRows.value.map((row) => ({
        subject_id: row.subject_id,
        exam_date: row.exam_date,
        start_time: row.start_time,
        end_time: row.end_time,
        batch_id: row.batch_id,
        course_id: row.course_id,
        class_id: row.class_id,
        total_marks: row.total_marks,
        pass_marks: row.pass_marks,
        mark_config: row.mark_config,
      })),
    }
    if (typeof props.onSave === 'function') {
      await props.onSave(payload)
    } else {
      emit('save', payload)
    }
  } catch (e) {
    formError.value = e.response?.data?.message || e.message || 'Bulk create failed'
    if (e.response?.data?.errors) {
      formError.value = Object.values(e.response.data.errors).flat().join('; ')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-container { background: var(--bg-card); border-radius: 12px; max-height: 92vh; display: flex; flex-direction: column; width: 100%; max-width: 720px; }
.modal-xl { max-width: 920px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
.modal-body { padding: 1rem 1.25rem; overflow-y: auto; flex: 1; }
.modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; }
.intro-hint { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; }
.form-input, .form-select { width: 100%; padding: 0.5rem 0.65rem; border: 1px solid var(--border-strong); border-radius: 8px; }
.required { color: #dc2626; }
.alert-error { background: #fef2f2; color: #b91c1c; padding: 0.65rem; border-radius: 8px; margin-bottom: 0.75rem; }
.radio-row { display: flex; gap: 1.25rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.radio-label { display: flex; align-items: center; gap: 0.35rem; font-size: 0.9rem; cursor: pointer; }
.batch-select { margin-top: 0.35rem; }
.subjects-header { display: flex; justify-content: space-between; align-items: center; }
.btn-link { background: none; border: none; color: #2563eb; cursor: pointer; font-size: 0.85rem; }
.subject-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.35rem; }
.subject-chip { display: flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem; border: 1px solid var(--border-color); border-radius: 999px; font-size: 0.82rem; cursor: pointer; }
.subject-chip.active { background: #eff6ff; border-color: #3b82f6; }
.subject-chip input { margin: 0; }
.preview-box { margin-top: 1rem; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; }
.preview-note { font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.5rem; }
.preview-scroll { max-height: 220px; overflow: auto; }
.preview-table { width: 100%; font-size: 0.8rem; border-collapse: collapse; }
.preview-table th, .preview-table td { padding: 0.35rem 0.5rem; text-align: left; border-bottom: 1px solid var(--border-light); }
.preview-more { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem; }
.btn { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-secondary { background: #e2e8f0; }
.btn-outline { background: var(--bg-card); border: 1px solid var(--border-strong); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
