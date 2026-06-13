<template>
  <div class="exam-marks-workspace">
    <header class="ems-hero">
      <div class="ems-hero-text">
        <h1>Exam Results & Marks</h1>
        <p>Select an exam on the left, review published results from status cards, or open <strong>Marks Entry</strong> for subjects ready after the slot ends. MCQ + CQ: each part must meet its own pass mark.</p>
      </div>
      <span class="ems-hero-badge">Teacher</span>
    </header>

    <div class="ems-channel-bar">
      <span class="ems-channel-label">Marks channel</span>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'offline' }">
        <input type="radio" value="offline" v-model="resultsChannel" /> Offline
      </label>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'online' }">
        <input type="radio" value="online" v-model="resultsChannel" /> Online (CQ entry)
      </label>
    </div>

    <div class="ems-panel ems-workflow-wrap">
      <ExamResultsWorkflow mode="teacher" :delivery-channel="resultsChannel" @enter-marks="onOpenRoutineFromPanel" />
    </div>

    <section v-if="selectedRoutine" ref="markEntryPanelRef" class="ems-mark-panel">
      <div class="ems-mark-head">
        <div>
          <h3>{{ markEntryTitle }} — Marks entry</h3>
          <p>{{ markEntryHint }}</p>
        </div>
        <div class="ems-mark-actions">
          <button type="button" class="ems-btn ems-btn-outline" @click="closeMarkEntry">Cancel</button>
          <button type="button" class="ems-btn ems-btn-outline" @click="fetchStudents" :disabled="studentsLoading">Refresh</button>
          <button
            type="button"
            class="ems-btn ems-btn-primary"
            @click="submitAllMarks"
            :disabled="marksSubmitting || students.length === 0 || !canSaveMarks"
          >
            {{ marksSubmitting ? 'Saving…' : 'Save all' }}
          </button>
        </div>
      </div>

      <div v-if="markEntryBlockReason" class="ems-alert">{{ markEntryBlockReason }}</div>

      <div v-if="studentsLoading" class="ems-loading ems-mark-body">
        <div class="ems-spinner"></div>
        <p>Loading students…</p>
      </div>

      <div v-else-if="students.length === 0" class="ems-empty ems-mark-body">
        <p>No students found for this exam routine.</p>
      </div>

      <div v-else class="ems-mark-body">
        <div class="ems-table-shell">
          <div class="ems-table-toolbar">
            <span class="ems-badge-count">{{ students.length }} students</span>
            <span class="ems-muted-hint">Save per row or use Save all</span>
          </div>
          <MarkEntryGrid
            v-model="entryData"
            :students="students"
            :mark-config="effectiveMarkConfig"
            :total-marks="totalMarks"
            :show-evaluation-status="true"
            :per-row-save="true"
            :disabled="!canSaveMarks"
            :saving-student-id="savingStudentId"
            :row-save-disabled="() => !canSaveMarks || marksSubmitting"
            @save-row="submitOneStudent"
          />
        </div>
      </div>
    </section>

    <div v-if="showToast" class="ems-toast" :class="toastType">{{ toastMessage }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import ExamResultsWorkflow from '@/components/exam/ExamResultsWorkflow.vue'
import examService from '@/services/exam.service'
import studentService from '@/services/student.service'
import enrollmentService from '@/services/enrollment.service'
import apiClient from '@/services/api.service'
import MarkEntryGrid from '@/components/exam/MarkEntryGrid.vue'
import { extractData } from '@/utils/api.utils'
import { loadGradingRules, calculateResultGrade } from '@/utils/grading.utils'
import {
  configTotalMarks,
  createEmptyBreakdown,
  getActiveComponents,
  formatPassCriteria,
} from '@/utils/markConfig.utils'
import { resolveMarkConfig } from '@/utils/examType.utils'
import {
  isRoutineOpenForTeacherMarks,
  teacherMarksClosedMessage,
} from '@/utils/routine.utils'
import {
  resolveRoutineLifecycle,
  routineLifecycleMeta,
  routineMarksLockedByLifecycle,
} from '@/utils/routineLifecycle.utils'
import '@/styles/exam-marks-workspace.css'

const resultsChannel = ref('offline')
const markEntryPanelRef = ref(null)
const students = ref([])
const selectedExam = ref(null)
const selectedRoutine = ref(null)
const error = ref(null)
const studentsLoading = ref(false)
const marksSubmitting = ref(false)
const savingStudentId = ref('')
const entryData = ref({})
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
const routineLifecycleMap = ref({})

const totalMarks = computed(() => {
  const cfg = effectiveMarkConfig.value
  if (cfg) {
    const configured = configTotalMarks(cfg)
    if (configured > 0) return configured
  }
  return selectedRoutine.value?.total_marks || 100
})

const effectiveMarkConfig = computed(() =>
  resolveMarkConfig(selectedRoutine.value, selectedExam.value)
)

const markEntryTitle = computed(() => {
  const r = selectedRoutine.value
  return r?.subject?.name || r?.subject_name || 'Subject'
})

const markEntryHint = computed(() => {
  const cols = getActiveComponents(effectiveMarkConfig.value)
  const passRule = formatPassCriteria(effectiveMarkConfig.value)
  if (!cols.length) return 'Enter marks obtained for each student.'
  const limits = cols.map((c) => `${c.label} max ${c.max_marks}`).join(', ')
  let hint = `Routine limits: ${limits}. Total is calculated automatically.`
  if (passRule) hint += ` Pass rule: ${passRule}.`
  return hint
})

const markEntryBlockReason = computed(() => {
  const r = selectedRoutine.value
  if (!r) return ''
  return teacherMarksClosedMessage(r, { lifecycle: teacherRoutinePhase(r) })
})

const canSaveMarks = computed(() => {
  const r = selectedRoutine.value
  if (!r) return false
  return isRoutineOpenForTeacherMarks(r, { lifecycle: teacherRoutinePhase(r) })
})

const SUBJECT_COLORS = [
  '#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626',
  '#7c3aed', '#db2777', '#2563eb', '#16a34a', '#ca8a04',
  '#9333ea', '#e11d48', '#0d9488', '#65a30d', '#f97316',
]

function formatTime(time) {
  if (!time) return '--:--'
  const [h, m] = time.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
  })
}

function getSubjectColor(subjectName) {
  if (!subjectName) return SUBJECT_COLORS[0]
  let hash = 0
  for (let i = 0; i < subjectName.length; i++) {
    hash = subjectName.charCodeAt(i) + ((hash << 5) - hash)
  }
  return SUBJECT_COLORS[Math.abs(hash) % SUBJECT_COLORS.length]
}

function showToastMessage(message, type = 'success') {
  toastMessage.value = message
  toastType.value = type
  showToast.value = true
  setTimeout(() => { showToast.value = false }, 3000)
}

async function onOpenRoutineFromPanel({ examId, examName, routine }) {
  try {
    selectedExam.value = { id: examId, name: examName || 'Exam' }
    selectedRoutine.value = routine
    await fetchStudents()
    await nextTick()
    markEntryPanelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  } catch (e) {
    showToastMessage(e.response?.data?.message || 'Could not open marks entry', 'error')
  }
}

function closeMarkEntry() {
  selectedRoutine.value = null
  students.value = []
  entryData.value = {}
}

function teacherRoutinePhase(routine) {
  if (!routine) return 'scheduled'
  return routineLifecycleMap.value[routine.id] || resolveRoutineLifecycle(routine)
}

function routineMarksLabel(routine) {
  const cfg = resolveMarkConfig(routine, selectedExam.value)
  const cols = getActiveComponents(cfg)
  if (cols.length && cols[0].key !== 'total') {
    return cols.map((c) => `${c.label} ${c.max_marks}`).join(' · ')
  }
  return `${routine.total_marks || '—'} total`
}

async function selectRoutine(routine) {
  selectedRoutine.value = routine
  await fetchStudents()
}

async function fetchStudents() {
  if (!selectedRoutine.value) return
  studentsLoading.value = true
  try {
    const exam = selectedExam.value
    const routine = selectedRoutine.value
    let studentRows = []

    const classId = routine.class_id || exam?.class_id
    const batchId = routine.batch_id || exam?.batch_id

    if (classId) {
      const res = await studentService.list({
        current_class_id: classId,
        status: 'active',
        per_page: 500,
      })
      studentRows = extractData(res, [])
    } else if (batchId) {
      const res = await enrollmentService.getEnrollments({
        batch_id: batchId,
        status: 'active',
        per_page: 500,
      })
      const enrollments = extractData(res, [])
      studentRows = enrollments.map((e) => e.student).filter(Boolean)
    }

    students.value = studentRows.map((s) => ({
      id: s.id,
      student: s,
      name: [s.first_name, s.last_name].filter(Boolean).join(' '),
      roll_no: s.roll_no,
    }))

    entryData.value = {}
    const components = getActiveComponents(effectiveMarkConfig.value)
    students.value.forEach((s) => {
      entryData.value[s.id] = {
        breakdown: createEmptyBreakdown(components),
        remarks: '',
        total: 0,
        evaluation_status: 'pending',
      }
    })

    if (selectedRoutine.value?.id && exam?.id) {
      const routineMode = selectedRoutine.value.delivery_mode || 'offline'
      const deliveryChannel = ['online', 'hybrid'].includes(routineMode) ? 'online' : 'offline'
      const resultsRes = await examService.results.list({
        exam_id: exam.id,
        subject_id: selectedRoutine.value.subject_id,
        exam_routine_id: selectedRoutine.value.id,
        delivery_channel: deliveryChannel,
        per_page: 500,
      })
      const existing = extractData(resultsRes, [])
      const pub = existing.filter((r) => r.status === 'published').length
      const pend = existing.filter((r) => r.status === 'pending').length
      routineLifecycleMap.value[routine.id] = resolveRoutineLifecycle(routine, {
        results_published_count: pub,
        results_pending_count: pend,
      })
      existing.forEach((r) => {
        const sid = r.student_id || r.student?.id
        if (!sid || !entryData.value[sid]) return
        const breakdown = r.marks_breakdown || { total: r.marks_obtained }
        entryData.value[sid] = {
          breakdown: { ...createEmptyBreakdown(components), ...breakdown },
          remarks: r.remarks || '',
          total: Number(r.marks_obtained) || 0,
          evaluation_status: r.evaluation_status || 'pending',
        }
      })
    }
  } catch (e) {
    console.error('Failed to load students:', e)
  } finally {
    studentsLoading.value = false
  }
}

function buildResultRow(student) {
  const sid = student.id || student.student?.id
  const data = entryData.value[sid] || {}
  const marksObtained = Number(data.total) || 0
  const gradeInfo = calculateResultGrade(
    marksObtained,
    totalMarks.value,
    data.breakdown || {},
    effectiveMarkConfig.value,
  )
  return {
    student_id: sid,
    marks_obtained: marksObtained,
    marks_breakdown: data.breakdown || {},
    grade: gradeInfo.grade,
    grade_point: gradeInfo.grade_point,
    remarks: data.remarks || null,
  }
}

function hasMarksForRow(row) {
  const hasBreakdown = Object.values(row.marks_breakdown || {}).some((v) => v !== null && v !== '' && v !== undefined)
  return row.marks_obtained > 0 || hasBreakdown
}

async function saveMarksPayload(results) {
  if (!selectedRoutine.value || !selectedExam.value) return
  if (!canSaveMarks.value) {
    showToastMessage(markEntryBlockReason.value || 'Cannot save marks right now.', 'error')
    return
  }
  const payload = {
    exam_id: selectedExam.value.id,
    exam_routine_id: selectedRoutine.value.id,
    subject_id: selectedRoutine.value.subject_id,
    total_marks: totalMarks.value,
    results,
  }
  await examService.results.markBulk(payload)
}

async function submitOneStudent(student) {
  if (!student?.id) return
  const row = buildResultRow(student)
  if (!hasMarksForRow(row)) {
    showToastMessage('Enter marks before saving this student.', 'error')
    return
  }
  savingStudentId.value = student.id
  try {
    await saveMarksPayload([row])
    showToastMessage(`Saved marks for ${student.name || 'student'}.`, 'success')
  } catch (e) {
    showToastMessage(e.response?.data?.message || 'Failed to save marks', 'error')
  } finally {
    savingStudentId.value = ''
  }
}

async function submitAllMarks() {
  if (!selectedRoutine.value || !selectedExam.value) return
  marksSubmitting.value = true
  try {
    const results = students.value.map(buildResultRow).filter(hasMarksForRow)
    if (!results.length) {
      showToastMessage('Enter marks for at least one student.', 'error')
      return
    }
    await saveMarksPayload(results)
    showToastMessage('All marks saved as Pending. Admin will publish for students.')
  } catch (e) {
    showToastMessage(e.response?.data?.message || 'Failed to save marks', 'error')
  } finally {
    marksSubmitting.value = false
  }
}

onMounted(async () => {
  await loadGradingRules(apiClient)
})
</script>

<style scoped>
.exam-marks-workspace .ems-muted-hint {
  font-size: 0.78rem;
  color: var(--text-muted);
  font-weight: 600;
}

</style>
