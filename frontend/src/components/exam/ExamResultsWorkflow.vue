<template>
  <div class="results-workflow">
    <!-- Step 1: Exam list (radio) -->
    <aside class="exam-rail">
      <div class="rail-head">
        <h3>Exams</h3>
        <button type="button" class="btn btn-outline btn-xs" @click="loadExams" :disabled="examsLoading">↻</button>
      </div>
      <div v-if="examsLoading" class="rail-loading">Loading…</div>
      <div v-else-if="!exams.length" class="rail-empty">No exams found</div>
      <div v-else class="exam-radio-list">
        <label
          v-for="(exam, idx) in sortedExams"
          :key="exam.id"
          class="exam-radio-item"
          :class="{ active: selectedExamId === exam.id }"
        >
          <input
            type="radio"
            name="workflow-exam"
            :value="exam.id"
            :checked="selectedExamId === exam.id"
            @change="selectExam(exam.id)"
          />
          <span class="exam-radio-num">{{ idx + 1 }}</span>
          <span class="exam-radio-body">
            <span class="exam-radio-name">{{ exam.name }}</span>
          </span>
        </label>
      </div>
    </aside>

    <main class="workflow-main">
      <div v-if="!selectedExamId" class="workflow-empty">
        <p>Select an exam from the list to continue.</p>
      </div>

      <!-- Step 2: Status cards -->
      <div v-else-if="step === 'categories'" class="workflow-step" :class="{ 'is-loading': loading }">
        <div class="step-head">
          <h3>{{ selectedExamName }}</h3>
          <button type="button" class="btn btn-outline btn-sm" @click="loadSummary" :disabled="loading">Refresh</button>
        </div>
        <div v-if="loading" class="wf-loading-bar" aria-live="polite">
          <span class="spinner-sm"></span> Updating counts…
        </div>
        <p v-if="!loading && summary.total === 0" class="summary-hint">No result rows yet for this exam. Counts update after marks are entered.</p>
        <div class="status-section">
          <h4 class="status-section-title">Publish status</h4>
          <div class="status-cards">
            <button
              v-for="cat in publishCategoryCards"
              :key="cat.key"
              type="button"
              class="status-card"
              :class="[cat.css, { 'card-busy': loading }]"
              :disabled="loading"
              @click="openCategory(cat.key)"
            >
              <span class="wf-status-count">{{ loading ? '—' : formatCount(cat.count) }}</span>
              <span class="wf-status-label">{{ cat.label }}</span>
              <span class="wf-status-unit">students</span>
            </button>
          </div>
        </div>
        <div class="status-section">
          <h4 class="status-section-title">Evaluation</h4>
          <div class="status-cards">
            <button
              v-for="cat in evalCategoryCards"
              :key="cat.key"
              type="button"
              class="status-card"
              :class="[cat.css, { 'card-busy': loading }]"
              :disabled="loading"
              @click="openCategory(cat.key)"
            >
              <span class="wf-status-count">{{ loading ? '—' : formatCount(cat.count) }}</span>
              <span class="wf-status-label">{{ cat.label }}</span>
              <span class="wf-status-unit">students</span>
            </button>
          </div>
        </div>
        <div v-if="isTeacher && summary.published > 0" class="leaderboard-link-bar">
          <p>Published results are available for this exam.</p>
          <router-link
            class="btn btn-outline btn-sm"
            :to="{
              name: 'TeacherExamLeaderboard',
              query: { exam_id: selectedExamId, channel: deliveryChannel },
            }"
          >
            View Leaderboard
          </router-link>
        </div>
        <div v-if="isTeacher" class="status-section marks-entry-section">
          <h4 class="status-section-title">Marks entry</h4>
          <div class="status-cards">
            <button
              type="button"
              class="status-card st-marks-entry"
              :class="{ 'card-busy': loading }"
              :disabled="loading || marksEntryOpenCount === 0"
              @click="openMarksEntry"
            >
              <span class="wf-status-count">{{ loading ? '—' : formatCount(marksEntryOpenCount) }}</span>
              <span class="wf-status-label">Marks Entry</span>
              <span class="wf-status-unit">{{ marksEntryOpenCount ? 'subjects open' : 'none open' }}</span>
            </button>
          </div>
        </div>
        <div v-if="isAdmin && !loading && summary.pending > 0" class="publish-all-bar">
          <p>Publish every pending result for this exam in one step.</p>
          <button
            type="button"
            class="btn btn-primary publish-all-btn"
            :disabled="publishing"
            @click="confirmPublishAll"
          >
            {{ publishing ? 'Publishing…' : `Publish all (${summary.pending})` }}
          </button>
        </div>
      </div>

      <!-- Teacher: Marks entry — subject list -->
      <div v-else-if="step === 'marks_subjects'" class="workflow-step">
        <div class="step-head">
          <button type="button" class="btn btn-outline btn-sm" @click="goCategories">← Back</button>
          <h3>Marks Entry — Subjects</h3>
          <button type="button" class="btn btn-outline btn-sm" @click="loadMarksRoutines" :disabled="loading">Refresh</button>
        </div>
        <p class="summary-hint">
          Only subjects ready for marks appear here. Published results are viewed under the <strong>Published</strong> status card.
        </p>
        <div v-if="loading" class="loading-inline"><span class="spinner-sm"></span> Loading subjects…</div>
        <div v-else-if="!marksRoutinesForEntry.length" class="workflow-empty">
          No subjects open for marks entry right now. Check slot end time or view published results under Published.
        </div>
        <div v-else class="marks-routine-grid">
          <button
            v-for="r in marksRoutinesForEntry"
            :key="r.id"
            type="button"
            class="marks-routine-card"
            :class="marksRoutineStatusClass(r)"
            @click="openMarksRoutine(r)"
          >
            <span class="mrc-subject">{{ r.subject_name || r.subject?.name || 'Subject' }}</span>
            <span class="mrc-batch">{{ r.batch_name || '—' }}</span>
            <span class="mrc-slot">{{ marksSlotLabel(r) }}</span>
            <span class="mrc-badge">{{ marksRoutineStatusLabel(r) }}</span>
          </button>
        </div>
      </div>

      <!-- Step 3: Subject list -->
      <div v-else-if="step === 'subjects'" class="workflow-step">
        <div class="step-head">
          <button type="button" class="btn btn-outline btn-sm" @click="goCategories">← Status</button>
          <h3>{{ categoryTitle }} — Subjects</h3>
          <button type="button" class="btn btn-outline btn-sm" @click="loadSubjects" :disabled="loading">Refresh</button>
        </div>
        <div v-if="loading" class="loading-inline"><span class="spinner-sm"></span> Loading subjects…</div>
        <div v-else-if="!subjects.length" class="workflow-empty">No subjects in this category.</div>
        <div v-else class="subject-cards">
          <button
            v-for="sub in subjects"
            :key="sub.subject_id + '-' + sub.exam_routine_id"
            type="button"
            class="subject-card"
            @click="openSubject(sub)"
          >
            <span class="subject-name">{{ sub.subject_name }}</span>
            <span v-if="sub.batch_name" class="subject-batch">{{ sub.batch_name }}</span>
            <span class="subject-count">{{ sub.count }} student(s)</span>
          </button>
        </div>
      </div>

      <!-- Step 4: Student results -->
      <div v-else-if="step === 'students'" class="workflow-step">
        <div class="step-head">
          <button type="button" class="btn btn-outline btn-sm" @click="goSubjects">← Subjects</button>
          <h3>{{ selectedSubject?.subject_name }} — Results</h3>
          <p v-if="isTeacher" class="teacher-marks-hint">
            To enter marks, go back and open the <strong>Marks Entry</strong> card.
          </p>
          <button
            v-if="isAdmin && summary.pending > 0"
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="publishing"
            @click="confirmPublishAll"
          >
            {{ publishing ? 'Publishing…' : `Publish all (${summary.pending})` }}
          </button>
          <button type="button" class="btn btn-outline btn-sm" @click="loadStudents" :disabled="loading">Refresh</button>
        </div>
        <div v-if="loading" class="loading-inline"><span class="spinner-sm"></span> Loading…</div>
        <div v-else-if="error" class="error-inline">{{ error }}</div>
        <div v-else-if="!resultsList.length" class="workflow-empty">No results for this subject.</div>
        <div v-else class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Roll</th>
                <th>Marks</th>
                <th>Grade</th>
                <th>Publish</th>
                <th v-if="isAdmin">Action</th>
                <th v-if="isAdmin">Marksheet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in resultsList" :key="row.id">
                <td><strong>{{ studentName(row.student) }}</strong></td>
                <td>{{ row.student?.roll_no || '—' }}</td>
                <td>{{ row.marks_obtained ?? '—' }} / {{ row.total_marks }}</td>
                <td><span class="grade-badge" :class="gradeClass(row.grade)">{{ row.grade || '—' }}</span></td>
                <td><span class="pub-badge" :class="row.status">{{ row.status }}</span></td>
                <td v-if="isAdmin">
                  <button
                    v-if="row.status === 'pending'"
                    type="button"
                    class="btn btn-primary btn-xs"
                    :disabled="publishing"
                    @click="publishOne(row.id)"
                  >Publish</button>
                  <button
                    v-else-if="row.status === 'published'"
                    type="button"
                    class="btn btn-outline btn-xs"
                    :disabled="publishing"
                    @click="unpublishOne(row.id)"
                  >Unpublish</button>
                  <span v-else>—</span>
                </td>
                <td v-if="isAdmin">
                  <button
                    v-if="row.status === 'published' && row.student_id"
                    type="button"
                    class="btn btn-outline btn-xs"
                    :disabled="downloadingMarksheetId === row.student_id"
                    @click="downloadStudentMarksheet(row)"
                  >
                    {{ downloadingMarksheetId === row.student_id ? '…' : 'PDF' }}
                  </button>
                  <span v-else>—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'
import {
  isRoutineOpenForMarks,
  isRoutineOpenForTeacherMarks,
  teacherMarksEntryStatus,
} from '@/utils/routine.utils'
import { resolveRoutineLifecycle } from '@/utils/routineLifecycle.utils'

function normalizeSummary(raw) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
    return emptySummary()
  }
  const s = raw.counts && typeof raw.counts === 'object' ? raw.counts : raw
  return {
    pending: safeCount(s.pending),
    published: safeCount(s.published),
    absent: safeCount(s.absent),
    total: safeCount(s.total),
    evaluation_pending: safeCount(s.evaluation_pending),
    evaluation_partial: safeCount(s.evaluation_partial),
    evaluation_complete: safeCount(s.evaluation_complete),
  }
}

function emptySummary() {
  return {
    pending: 0,
    published: 0,
    absent: 0,
    total: 0,
    evaluation_pending: 0,
    evaluation_partial: 0,
    evaluation_complete: 0,
  }
}

function safeCount(v) {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function formatCount(v) {
  return String(safeCount(v))
}

function aggregateSummaryFromRows(rows) {
  const out = emptySummary()
  if (!Array.isArray(rows)) return out
  for (const r of rows) {
    out.total++
    const st = r.status || 'pending'
    if (st === 'pending') out.pending++
    else if (st === 'published') out.published++
    else if (st === 'absent') out.absent++
    const ev = r.evaluation_status || 'pending'
    if (ev === 'partial') out.evaluation_partial++
    else if (ev === 'complete') out.evaluation_complete++
    else out.evaluation_pending++
  }
  return out
}

function normalizeResultRows(listRes) {
  const raw = extractData(listRes, [])
  if (Array.isArray(raw)) return raw
  if (raw && Array.isArray(raw.data)) return raw.data
  if (raw && Array.isArray(raw.items)) return raw.items
  return []
}

function routineMatchesChannel(routine) {
  const mode = routine.delivery_mode || routine.delivery_channel || 'offline'
  const channel = ['online', 'hybrid'].includes(mode) ? 'online' : 'offline'
  return channel === props.deliveryChannel
}

function channelParams(extra = {}) {
  return { delivery_channel: props.deliveryChannel, ...extra }
}

async function fetchAllResultsForExam(examId) {
  const res = await examService.results.list(channelParams({ exam_id: examId, per_page: 500 }))
  return normalizeResultRows(res)
}

/** Attach result rows to a teacher routine (routine id first, then subject + batch). */
function resultRowsForRoutine(routine, rows) {
  const rid = String(routine.id)
  const sid = String(routine.subject_id || routine.subject?.id || '')
  const bid = routine.batch_id ? String(routine.batch_id) : ''

  const byRoutine = rows.filter((row) => {
    const rowRid = row.exam_routine_id || row.routine?.id
    return rowRid && String(rowRid) === rid
  })
  if (byRoutine.length) return byRoutine

  return rows.filter((row) => {
    const rowSid = String(row.subject_id || row.subject?.id || '')
    if (!sid || rowSid !== sid) return false
    if (bid) {
      const rowBatch = row.batch_id || row.routine?.batch_id
      if (rowBatch && String(rowBatch) !== bid) return false
    }
    return true
  })
}

/** Teacher: only count results for this teacher's routines/subjects. */
function filterRowsForTeacher(rows) {
  if (!isTeacher.value || !rows.length) return rows
  const routineIds = new Set(marksRoutines.value.map((r) => String(r.id)))
  const subjectIds = new Set(
    marksRoutines.value.map((r) => String(r.subject_id || r.subject?.id)).filter(Boolean),
  )
  if (!routineIds.size && !subjectIds.size) return rows
  return rows.filter((row) => {
    if (row.exam_routine_id && routineIds.has(String(row.exam_routine_id))) return true
    if (row.subject_id && subjectIds.has(String(row.subject_id))) return true
    return false
  })
}

function mergeSummary(fromApi, fromRows) {
  if (!fromRows.total) return fromApi
  if (!fromApi.total) return fromRows
  return fromRows
}

const props = defineProps({
  mode: { type: String, default: 'admin' }, // admin | teacher
  initialExamId: { type: String, default: '' },
  /** offline | online — separate result workflows */
  deliveryChannel: { type: String, default: 'offline' },
})

const emit = defineEmits(['enter-marks', 'exam-selected'])

const isAdmin = computed(() => props.mode === 'admin')
const isTeacher = computed(() => props.mode === 'teacher')

const exams = ref([])
const examsLoading = ref(false)
const selectedExamId = ref('')
const step = ref('categories')
const categoryKey = ref('')
const selectedSubject = ref(null)
const summary = ref({ pending: 0, published: 0, absent: 0, total: 0, evaluation_pending: 0, evaluation_partial: 0, evaluation_complete: 0 })
const subjects = ref([])
const marksRoutines = ref([])
const resultsList = ref([])
const loading = ref(false)
const publishing = ref(false)
const downloadingMarksheetId = ref(null)
const error = ref(null)

const CATEGORY_META = {
  publish_pending: { title: 'Pending (unpublished)', status: 'pending', evaluation_status: '', pending_manual_only: false, label: 'Pending', css: 'st-pending', countKey: 'pending' },
  publish_published: { title: 'Published', status: 'published', evaluation_status: '', pending_manual_only: false, label: 'Published', css: 'st-published', countKey: 'published' },
  publish_absent: { title: 'Absent', status: 'absent', evaluation_status: '', pending_manual_only: false, label: 'Absent', css: 'st-absent', countKey: 'absent' },
  eval_pending: { title: 'Not evaluated', status: '', evaluation_status: 'pending', pending_manual_only: false, label: 'Unevaluated', css: 'st-eval', countKey: 'evaluation_pending' },
  eval_partial: { title: 'Partial evaluation', status: '', evaluation_status: 'partial', pending_manual_only: false, label: 'Partial', css: 'st-partial', countKey: 'evaluation_partial' },
  eval_complete: { title: 'Fully evaluated', status: '', evaluation_status: 'complete', pending_manual_only: false, label: 'Evaluated', css: 'st-complete', countKey: 'evaluation_complete' },
  all: { title: 'All results', status: '', evaluation_status: '', pending_manual_only: false, label: 'All', css: 'st-all', countKey: 'total' },
}

const sortedExams = computed(() => {
  return [...exams.value].sort((a, b) => {
    const da = a.start_date || a.created_at || ''
    const db = b.start_date || b.created_at || ''
    return db.localeCompare(da)
  })
})

const selectedExamName = computed(() => exams.value.find((e) => e.id === selectedExamId.value)?.name || 'Exam')

function buildCategoryCards(keys) {
  return keys.map((key) => {
    const m = CATEGORY_META[key]
    const count = summary.value[m.countKey] ?? 0
    return { key, label: m.label, css: m.css, count }
  })
}

const publishCategoryCards = computed(() =>
  buildCategoryCards(['publish_pending', 'publish_published', 'publish_absent', 'all']),
)

const evalCategoryCards = computed(() =>
  buildCategoryCards(['eval_pending', 'eval_partial', 'eval_complete']),
)

const marksRoutinesForEntry = computed(() =>
  marksRoutines.value.filter((r) => canOpenMarksRoutine(r)),
)

/** Same list as marks entry step — never show a count that opens to an empty screen. */
const marksEntryOpenCount = computed(() => marksRoutinesForEntry.value.length)

function marksRoutinePhase(routine) {
  return resolveRoutineLifecycle(routine, {
    results_published_count: routine.results_published_count,
    results_pending_count: routine.results_pending_count,
  })
}

function canOpenMarksRoutine(routine) {
  if (!routine._resultsEnriched) return false

  const pub = Number(routine.results_published_count ?? 0)
  const pend = Number(routine.results_pending_count ?? 0)
  const absent = Number(routine.results_absent_count ?? 0)
  const recorded = pub + pend + absent

  // All outcomes saved and none pending → nothing left to enter (published / absent only).
  if (recorded > 0 && pend === 0) return false

  const phase = marksRoutinePhase(routine)
  if (phase === 'results_out' || phase === 'completed') return false
  return isRoutineOpenForTeacherMarks(routine, { lifecycle: phase })
}

function marksRoutineStatusClass(routine) {
  return `mrc-${teacherMarksEntryStatus(routine, { lifecycle: marksRoutinePhase(routine) })}`
}

function marksRoutineStatusLabel(routine) {
  const map = {
    open: 'Open',
    waiting: 'After slot ends',
    scheduled: 'Not published',
    locked: 'Locked',
    unavailable: '—',
  }
  return map[teacherMarksEntryStatus(routine, { lifecycle: marksRoutinePhase(routine) })] || '—'
}

function marksSlotLabel(routine) {
  if (!routine.exam_date) return '—'
  const d = String(routine.exam_date).substring(0, 10)
  const date = new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  const end = routine.end_time ? String(routine.end_time).substring(0, 5) : '—'
  return `${date} · ends ${end}`
}

const categoryTitle = computed(() => CATEGORY_META[categoryKey.value]?.title || '')

const filterParams = computed(() => {
  const m = CATEGORY_META[categoryKey.value] || {}
  const p = channelParams({ exam_id: selectedExamId.value })
  if (m.status) p.status = m.status
  if (m.evaluation_status) p.evaluation_status = m.evaluation_status
  if (m.pending_manual_only) p.pending_manual_only = 1
  return p
})

function studentName(student) {
  if (!student) return '—'
  return [student.first_name, student.last_name].filter(Boolean).join(' ') || '—'
}

function gradeClass(grade) {
  if (!grade) return ''
  return `g-${String(grade).toLowerCase().replace('+', 'plus')}`
}

async function loadExams() {
  examsLoading.value = true
  try {
    if (isTeacher.value) {
      const res = await examService.teacher.myRoutines()
      const payload = res.data?.data || res.data || {}
      const list = Array.isArray(payload) ? payload : (payload.flat || payload.routines || [])
      const map = new Map()
      list.forEach((r) => {
        const exam = r.exam || { id: r.exam_id, name: r.exam_name || 'Exam' }
        const id = exam.id || r.exam_id
        if (id && !map.has(id)) map.set(id, { ...exam, id })
      })
      exams.value = Array.from(map.values())
    } else {
      const res = await examService.exams.list({ per_page: 200, include_all: 1 })
      exams.value = extractData(res, [])
    }
  } catch {
    exams.value = []
  } finally {
    examsLoading.value = false
  }
}

async function selectExam(id) {
  selectedExamId.value = id
  step.value = 'categories'
  categoryKey.value = ''
  selectedSubject.value = null
  subjects.value = []
  marksRoutines.value = []
  resultsList.value = []
  emit('exam-selected', id)
  if (isTeacher.value) {
    await prefetchMarksRoutinesForExam(id)
  }
  await loadSummary()
}

/** Lightweight: routines only (no 500-result fetch) so status cards load fast. */
async function prefetchMarksRoutinesForExam(examId) {
  try {
    const res = await examService.teacher.myRoutines()
    const payload = res.data?.data || res.data || {}
    const list = Array.isArray(payload) ? payload : (payload.flat || payload.routines || [])
    marksRoutines.value = list
      .filter((r) => String(r.exam?.id || r.exam_id) === String(examId))
      .filter((r) => routineMatchesChannel(r))
      .map((r) => ({
        ...r,
        results_published_count: 0,
        results_pending_count: 0,
        results_absent_count: 0,
        _resultsEnriched: false,
      }))
  } catch {
    marksRoutines.value = []
  }
}

function enrichRoutinesWithResultRows(routines, rows) {
  return routines.map((r) => {
    const rr = resultRowsForRoutine(r, rows)
    const published = rr.filter((row) => row.status === 'published').length
    const pending = rr.filter((row) => row.status === 'pending').length
    const absent = rr.filter((row) => row.status === 'absent').length
    return {
      ...r,
      results_published_count: published,
      results_pending_count: pending,
      results_absent_count: absent,
      _resultsEnriched: true,
    }
  })
}

async function loadMarksRoutines() {
  if (!selectedExamId.value || !isTeacher.value) {
    marksRoutines.value = []
    return
  }
  const showLoader = step.value === 'marks_subjects'
  if (showLoader) loading.value = true
  try {
    const [res, listRes] = await Promise.all([
      examService.teacher.myRoutines(),
      examService.results.list(channelParams({ exam_id: selectedExamId.value, per_page: 500 })),
    ])
    const payload = res.data?.data || res.data || {}
    const list = Array.isArray(payload) ? payload : (payload.flat || payload.routines || [])
    let rows = normalizeResultRows(listRes)
    rows = filterRowsForTeacher(rows)
    marksRoutines.value = enrichRoutinesWithResultRows(
      list
        .filter((r) => String(r.exam?.id || r.exam_id) === String(selectedExamId.value))
        .filter((r) => routineMatchesChannel(r))
        .sort((a, b) => (a.exam_date || '').localeCompare(b.exam_date || '') || (a.subject_name || '').localeCompare(b.subject_name || '')),
      rows,
    )
  } catch {
    marksRoutines.value = []
  } finally {
    if (showLoader) loading.value = false
  }
}

async function openMarksEntry() {
  if (!marksRoutines.value.some((r) => r._resultsEnriched)) {
    await loadMarksRoutines()
  }
  if (marksEntryOpenCount.value === 0) return
  step.value = 'marks_subjects'
}

function openMarksRoutine(routine) {
  if (!canOpenMarksRoutine(routine)) return
  emit('enter-marks', {
    examId: selectedExamId.value,
    examName: selectedExamName.value,
    routine,
    subject: {
      subject_id: routine.subject_id,
      subject_name: routine.subject_name || routine.subject?.name,
      exam_routine_id: routine.id,
      exam_name: selectedExamName.value,
    },
  })
}

function goCategories() {
  step.value = 'categories'
  selectedSubject.value = null
  loadSummary()
}

function goSubjects() {
  step.value = 'subjects'
  resultsList.value = []
}

async function loadSummary() {
  if (!selectedExamId.value) return
  loading.value = true
  try {
    const examId = selectedExamId.value
    const [summaryRes, listRes] = await Promise.all([
      examService.results.summary(channelParams({ exam_id: examId })),
      examService.results.list(channelParams({ exam_id: examId, per_page: 500 })),
    ])
    const fromApi = normalizeSummary(extractData(summaryRes, {}))
    let rows = normalizeResultRows(listRes)
    rows = filterRowsForTeacher(rows)
    const fromRows = aggregateSummaryFromRows(rows)
    summary.value = mergeSummary(fromApi, fromRows)
    if (isTeacher.value && marksRoutines.value.length) {
      marksRoutines.value = enrichRoutinesWithResultRows(marksRoutines.value, rows)
    }
  } catch {
    try {
      const rows = filterRowsForTeacher(await fetchAllResultsForExam(selectedExamId.value))
      summary.value = aggregateSummaryFromRows(rows)
      if (isTeacher.value && marksRoutines.value.length) {
        marksRoutines.value = enrichRoutinesWithResultRows(marksRoutines.value, rows)
      }
    } catch {
      summary.value = emptySummary()
    }
  } finally {
    loading.value = false
  }
}

function openCategory(key) {
  categoryKey.value = key
  step.value = 'subjects'
  loadSubjects()
}

async function loadSubjects() {
  if (!selectedExamId.value || !categoryKey.value) return
  loading.value = true
  error.value = null
  try {
    const res = await examService.results.subjectsSummary(filterParams.value)
    subjects.value = res.data?.data || res.data || []
  } catch (e) {
    subjects.value = []
    error.value = e.response?.data?.message || 'Failed to load subjects'
  } finally {
    loading.value = false
  }
}

function openSubject(sub) {
  selectedSubject.value = sub
  step.value = 'students'
  loadStudents()
}

async function loadStudents() {
  if (!selectedSubject.value) return
  loading.value = true
  error.value = null
  try {
    const params = { per_page: 500, ...filterParams.value, subject_id: selectedSubject.value.subject_id }
    if (selectedSubject.value.exam_routine_id) params.exam_routine_id = selectedSubject.value.exam_routine_id
    const res = await examService.results.list(params)
    resultsList.value = extractData(res, [])
  } catch (e) {
    resultsList.value = []
    error.value = e.response?.data?.message || 'Failed to load results'
  } finally {
    loading.value = false
  }
}

async function publishOne(id) {
  publishing.value = true
  try {
    await examService.results.publish(id)
    await loadStudents()
    await loadSummary()
  } finally {
    publishing.value = false
  }
}

async function unpublishOne(id) {
  publishing.value = true
  try {
    await examService.results.unpublish(id)
    await loadStudents()
    await loadSummary()
  } finally {
    publishing.value = false
  }
}

async function publishAllPending() {
  if (!selectedExamId.value) return
  publishing.value = true
  try {
    await examService.results.publishBulk({ exam_id: selectedExamId.value })
    await loadSummary()
    if (step.value === 'subjects') await loadSubjects()
    if (step.value === 'students') await loadStudents()
  } finally {
    publishing.value = false
  }
}

function confirmPublishAll() {
  const n = summary.value.pending ?? 0
  if (!n || !selectedExamId.value) return
  const name = selectedExamName.value
  if (!window.confirm(`Publish all ${n} pending result(s) for "${name}"? Students will see published marks.`)) return
  publishAllPending()
}

async function downloadStudentMarksheet(row) {
  const studentId = row.student_id || row.student?.id
  if (!selectedExamId.value || !studentId) return
  downloadingMarksheetId.value = studentId
  try {
    const res = await examService.exams.downloadMarksheet(selectedExamId.value, studentId, {
      delivery_channel: props.deliveryChannel,
    })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `marksheet-${studentId}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to download marksheet'
  } finally {
    downloadingMarksheetId.value = null
  }
}

onMounted(async () => {
  await loadExams()
  if (props.initialExamId) {
    selectExam(props.initialExamId)
  }
})

watch(
  () => props.initialExamId,
  (id) => {
    if (id && id !== selectedExamId.value) {
      selectExam(id)
    }
  },
)

watch(
  () => props.deliveryChannel,
  () => {
    if (selectedExamId.value) {
      loadSummary()
      if (isTeacher.value) prefetchMarksRoutinesForExam(selectedExamId.value)
    }
  },
)

defineExpose({ loadExams, selectExam, loadSummary })
</script>

<style scoped>
.leaderboard-link-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  font-size: 0.84rem;
  color: #3730a3;
}
.status-section { margin-bottom: 1.25rem; }
.mrc-open { border-color: #34d399 !important; background: #ecfdf5 !important; }
.mrc-waiting { border-color: #fcd34d !important; background: #fffbeb !important; }
.mrc-locked { border-color: #cbd5e1 !important; background: var(--bg-accent) !important; }
.mrc-subject { display: block; font-weight: 800; font-size: 0.9rem; }
.mrc-batch { display: block; font-size: 0.72rem; color: #2563eb; font-weight: 600; }
.mrc-slot { display: block; font-size: 0.68rem; color: var(--text-muted); }
.mrc-badge { display: inline-block; margin-top: 0.35rem; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; padding: 0.1rem 0.35rem; border-radius: 4px; }
.pub-badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 0.12rem 0.4rem; border-radius: 4px; }
.pub-badge.pending { background: #fef3c7; color: #b45309; }
.pub-badge.published { background: #d1fae5; color: #059669; }
.loading-inline, .error-inline, .workflow-empty, .rail-empty, .rail-loading { padding: 1rem; font-size: 0.88rem; color: var(--text-muted); text-align: center; }
.error-inline { color: #dc2626; }
.spinner-sm { display: inline-block; width: 14px; height: 14px; border: 2px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: wf-spin 0.7s linear infinite; }
@keyframes wf-spin { to { transform: rotate(360deg); } }
</style>
