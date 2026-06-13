<template>
  <div class="exam-marks-workspace">
    <header class="ems-hero">
      <div class="ems-hero-text">
        <h1>Exam Results</h1>
        <p>Browse results by exam, enter marks by class and subject, and publish when ready. MCQ + CQ papers use separate pass marks per part.</p>
      </div>
      <span class="ems-hero-badge">Admin</span>
    </header>

    <div class="ems-channel-bar">
      <span class="ems-channel-label">Result channel</span>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'offline' }">
        <input type="radio" value="offline" v-model="resultsChannel" /> Offline / Center exam
      </label>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'online' }">
        <input type="radio" value="online" v-model="resultsChannel" /> Online exam
      </label>
      <span class="ems-channel-hint">
        {{ resultsChannel === 'online'
          ? 'MCQ auto-scored after exam → teacher adds CQ → admin publishes online results only.'
          : 'Physical exam marks entry and publish — separate from online results.' }}
      </span>
    </div>

    <nav class="ems-tabs">
      <button type="button" class="ems-tab" :class="{ active: activeTab === 'list' }" @click="activeTab = 'list'">Results workflow</button>
      <button type="button" class="ems-tab" :class="{ active: activeTab === 'entry' }" @click="activeTab = 'entry'">Mark entry</button>
      <button type="button" class="ems-tab" :class="{ active: activeTab === 'publish' }" @click="activeTab = 'publish'">Publish</button>
    </nav>

    <!-- ========== RESULTS LIST ========== -->
    <div v-show="activeTab === 'list'" class="ems-panel ems-workflow-wrap">
      <ExamResultsWorkflow mode="admin" :initial-exam-id="initialExamIdFromRoute" :delivery-channel="resultsChannel" />
    </div>

    <!-- ========== MARK ENTRY TAB ========== -->
    <div v-show="activeTab === 'entry'" class="ems-panel">
      <div class="ems-info-banner">
        Marks saved here stay <strong>Pending</strong> until you publish the exam from the <strong>Publish</strong> tab.
      </div>

      <div class="ems-filters">
        <div class="ems-filter-grid">
          <div class="ems-form-group">
            <label>Exam <span class="required">*</span></label>
            <select v-model="filters.exam_id" class="ems-control">
              <option value="">Select exam...</option>
              <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}{{ e.result_status === 'published' ? ' — results out' : '' }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Class <span class="required">*</span></label>
            <select v-model="filters.class_id" class="ems-control" @change="onClassChange">
              <option value="">Select class...</option>
              <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Course <span class="required">*</span></label>
            <select v-model="filters.course_id" class="ems-control" @change="onCourseChange" :disabled="!filters.class_id">
              <option value="">Select course...</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Batch <span class="required">*</span></label>
            <select v-model="filters.batch_id" class="ems-control" @change="onBatchChange" :disabled="!filters.course_id">
              <option value="">Select batch...</option>
              <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Subject <span class="required">*</span></label>
            <select v-model="filters.subject_id" class="ems-control" :disabled="!filters.batch_id">
              <option value="">Select subject...</option>
              <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>&nbsp;</label>
            <button
              type="button"
              class="ems-btn ems-btn-primary"
              @click="loadResults"
              :disabled="!filters.exam_id || !filters.class_id || !filters.course_id || !filters.batch_id || !filters.subject_id"
            >
              Load students
            </button>
          </div>
        </div>
        <p v-if="selectedRoutine" class="ems-routine-meta" :class="isRoutineOpenForMarks(selectedRoutine) ? 'ems-routine-meta--ok' : 'ems-routine-meta--err'">
          <template v-if="isRoutineOpenForMarks(selectedRoutine)">
            Ready — total <strong>{{ totalMarks }}</strong>
            <span v-for="col in getActiveComponents(effectiveMarkConfig)" :key="col.key" class="ems-chip">
              {{ col.label }} max {{ col.max_marks }}<template v-if="col.pass_marks > 0"> · pass {{ col.pass_marks }}</template>
            </span>
            <span v-if="markPassRule" class="ems-chip">Pass: {{ markPassRule }}</span>
          </template>
          <span v-else>{{ routineMarksClosedMessage(selectedRoutine) }}</span>
        </p>
        <p v-else-if="routineError" class="ems-routine-meta ems-routine-meta--err">{{ routineError }}</p>
      </div>

      <div v-if="loading" class="ems-loading"><div class="ems-spinner"></div><p>Loading students…</p></div>
      <div v-else-if="error" class="ems-empty"><p>{{ error }}</p></div>
      <div v-else-if="students.length === 0 && filters.subject_id && selectedRoutine" class="ems-empty">
        <h3>No students</h3>
        <p>No students found for this selection.</p>
      </div>
      <div v-else-if="students.length > 0 && selectedRoutine && isRoutineOpenForMarks(selectedRoutine)" class="ems-mark-body">
        <div class="ems-table-shell">
        <div class="ems-table-toolbar">
          <span class="ems-badge-count">{{ students.length }} students</span>
          <button type="button" class="ems-btn ems-btn-primary" @click="saveResults" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save as pending' }}
          </button>
        </div>
        <MarkEntryGrid
          v-model="entryData"
          :students="students"
          :mark-config="effectiveMarkConfig"
          :total-marks="totalMarks"
          :show-evaluation-status="true"
        />
        </div>
      </div>
    </div>

    <!-- ========== PUBLISH TAB ========== -->
    <div v-show="activeTab === 'publish'" class="ems-panel">
      <div class="ems-info-banner">
        <strong>Publish {{ resultsChannel === 'online' ? 'online' : 'offline' }} results for this exam.</strong>
        {{ resultsChannel === 'online'
          ? 'Students see full marks (MCQ + CQ), rank, and merit list for online routines only.'
          : 'Offline center-exam marks become visible to students — online results stay separate.' }}
      </div>

      <div class="ems-filters">
        <div class="ems-filter-grid">
          <div class="ems-form-group">
            <label>Exam <span class="required">*</span></label>
            <select v-model="publishFilters.exam_id" class="ems-control" @change="loadPublishPreview">
              <option value="">Select exam</option>
              <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}{{ e.result_status === 'published' ? ' — results out' : '' }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>&nbsp;</label>
            <button type="button" class="ems-btn ems-btn-outline" @click="loadPublishPreview" :disabled="!publishFilters.exam_id || publishLoading">
              Refresh preview
            </button>
          </div>
        </div>
      </div>

      <div v-if="publishLoading" class="ems-loading"><div class="ems-spinner"></div><p>Loading publish preview…</p></div>
      <div v-else-if="publishError" class="ems-empty"><p>{{ publishError }}</p></div>
      <div v-else-if="!publishFilters.exam_id" class="ems-empty">
        <h3>Select an exam</h3>
        <p>Choose an exam above to preview and publish results.</p>
      </div>
      <div v-else-if="publishPreview" class="ems-publish-body">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
          <span class="ems-status-pill" :class="publishPreview.exam?.result_status || 'draft'">
            {{ resultStatusLabel(publishPreview.exam?.result_status) }}
          </span>
          <span v-if="publishPreview.exam?.result_publish_at" style="font-size:0.84rem;color: var(--text-muted);">
            Published: {{ formatPublishDate(publishPreview.exam.result_publish_at) }}
          </span>
        </div>

        <div class="ems-stats">
          <div class="ems-stat pending"><div class="ems-stat-num">{{ publishPreview.counts?.pending ?? 0 }}</div><div class="ems-stat-lbl">Pending</div></div>
          <div class="ems-stat published"><div class="ems-stat-num">{{ publishPreview.counts?.published ?? 0 }}</div><div class="ems-stat-lbl">Published</div></div>
          <div class="ems-stat absent"><div class="ems-stat-num">{{ publishPreview.counts?.absent ?? 0 }}</div><div class="ems-stat-lbl">Absent</div></div>
          <div class="ems-stat eval"><div class="ems-stat-num">{{ publishPreview.counts?.evaluation_pending ?? 0 }}</div><div class="ems-stat-lbl">Eval pending</div></div>
        </div>

        <div v-if="publishPreview.pass_fail_estimate?.total > 0" style="font-size:0.84rem;color: var(--text-secondary);display:flex;gap:1.25rem;flex-wrap:wrap;">
          <span>Pass: <strong>{{ publishPreview.pass_fail_estimate.passed }}</strong></span>
          <span>Fail: <strong>{{ publishPreview.pass_fail_estimate.failed }}</strong></span>
          <span>With marks: <strong>{{ publishPreview.pass_fail_estimate.total }}</strong></span>
        </div>

        <ul v-if="publishPreview.warnings?.length" class="ems-warnings">
          <li v-for="(w, i) in publishPreview.warnings" :key="i">{{ w }}</li>
        </ul>

        <div v-if="!publishPreview.is_published" class="ems-publish-actions">
          <label class="ems-check">
            <input type="checkbox" v-model="allowPartialPublish" />
            Allow publish with incomplete evaluations
          </label>
          <button
            type="button"
            class="ems-btn ems-btn-primary"
            @click="publishExamResults"
            :disabled="!publishPreview.can_publish || publishingExam"
          >
            {{ publishingExam ? 'Publishing…' : 'Publish exam results' }}
          </button>
        </div>

        <div v-if="publishPreview.is_published || publishPreview.exam?.result_status === 'published'" class="ems-export-block">
          <h3>Downloads & merit list</h3>
          <p style="margin:0;font-size:0.84rem;color: var(--text-muted);">Results are live for students.</p>
          <div class="ems-export-btns">
            <button type="button" class="ems-btn ems-btn-outline" @click="downloadMerit(10)" :disabled="exporting">Top 10 PDF</button>
            <button type="button" class="ems-btn ems-btn-outline" @click="downloadMerit(20)" :disabled="exporting">Top 20 PDF</button>
            <button type="button" class="ems-btn ems-btn-outline" @click="downloadMerit(50)" :disabled="exporting">Top 50 PDF</button>
            <button type="button" class="ems-btn ems-btn-outline" @click="downloadResultSheet('pdf')" :disabled="exporting">Sheet PDF</button>
            <button type="button" class="ems-btn ems-btn-outline" @click="downloadResultSheet('csv')" :disabled="exporting">Sheet CSV</button>
            <button type="button" class="ems-btn ems-btn-outline" @click="viewMeritPreview" :disabled="meritLoading">Preview merit</button>
          </div>
        </div>

        <div v-if="meritPreview?.merit_list?.length" class="ems-table-shell" style="margin-top:1.25rem;">
          <div class="ems-table-toolbar"><span style="font-weight:800;">Merit preview (top {{ meritPreview.top }})</span></div>
          <table class="data-table" style="width:100%;border-collapse:collapse;font-size:0.84rem;">
            <thead>
              <tr>
                <th>Rank</th><th>Roll</th><th>Student</th><th>Marks</th><th>%</th><th>GPA</th><th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in meritPreview.merit_list" :key="row.student_id">
                <td>{{ row.rank }}</td>
                <td>{{ row.roll_no || '—' }}</td>
                <td>{{ row.student_name }}</td>
                <td>{{ row.total_marks }}/{{ row.total_possible }}</td>
                <td>{{ row.percentage }}%</td>
                <td>{{ row.gpa }}</td>
                <td>{{ row.overall_grade }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="successMsg" class="ems-toast success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import examService from '@/services/exam.service'
import ExamResultsWorkflow from '@/components/exam/ExamResultsWorkflow.vue'
import reportService from '@/services/report.service'
import academicService from '@/services/academic.service'
import enrollmentService from '@/services/enrollment.service'
import apiClient from '@/services/api.service'
import { extractData, blobFromExportResponse } from '@/utils/api.utils'
import MarkEntryGrid from '@/components/exam/MarkEntryGrid.vue'
import { loadGradingRules, calculateResultGrade } from '@/utils/grading.utils'
import {
  configTotalMarks,
  formatBreakdownSummary,
  createEmptyBreakdown,
  getActiveComponents,
  formatPassCriteria,
} from '@/utils/markConfig.utils'
import { resolveMarkConfig } from '@/utils/examType.utils'
import { isRoutineOpenForMarks, routineMarksClosedMessage } from '@/utils/routine.utils'
import '@/styles/exam-marks-workspace.css'

const route = useRoute()
const initialExamIdFromRoute = computed(() => (route.query.exam_id ? String(route.query.exam_id) : ''))

const activeTab = ref('list')
const resultsChannel = ref('offline')
const exams = ref([])
const classes = ref([])
const courses = ref([])
const batches = ref([])
const subjects = ref([])
const students = ref([])
const entryData = ref({})
const selectedRoutine = ref(null)
const routineError = ref(null)
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const successMsg = ref(null)

const filters = ref({ exam_id: '', class_id: '', course_id: '', batch_id: '', subject_id: '' })
const publishFilters = ref({ exam_id: '' })
const publishPreview = ref(null)
const publishLoading = ref(false)
const publishError = ref(null)
const publishingExam = ref(false)
const allowPartialPublish = ref(false)
const exporting = ref(false)
const meritPreview = ref(null)
const meritLoading = ref(false)

const effectiveMarkConfig = computed(() => {
  const exam = exams.value.find((e) => e.id === filters.value.exam_id)
  return resolveMarkConfig(selectedRoutine.value, exam)
})

const totalMarks = computed(() => {
  const configured = configTotalMarks(effectiveMarkConfig.value)
  if (configured > 0) return configured
  return selectedRoutine.value?.total_marks || 100
})

const markPassRule = computed(() => formatPassCriteria(effectiveMarkConfig.value))

const formatBreakdown = (breakdown, markConfig) => formatBreakdownSummary(breakdown, markConfig)

onMounted(async () => {
  try {
    await loadGradingRules(apiClient)
    const [examsRes, classesRes] = await Promise.all([
      examService.exams.list({ per_page: 100, include_all: 1 }),
      academicService.classes.list({ per_page: 100 }),
    ])
    exams.value = extractData(examsRes, [])
    classes.value = extractData(classesRes, [])
  } catch {
    // non-blocking
  }
})

watch(activeTab, (tab) => {
  if (tab === 'publish' && publishFilters.value.exam_id) {
    loadPublishPreview()
  }
})

watch(resultsChannel, () => {
  publishPreview.value = null
  meritPreview.value = null
  if (activeTab.value === 'publish' && publishFilters.value.exam_id) {
    loadPublishPreview()
  }
})

const resultStatusLabel = (status) => {
  if (status === 'published') return 'Published'
  if (status === 'processing') return 'Processing'
  return 'Draft'
}

const formatPublishDate = (iso) => {
  if (!iso) return ''
  return new Date(iso).toLocaleString()
}

const triggerBlobDownload = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

const loadPublishPreview = async () => {
  publishPreview.value = null
  meritPreview.value = null
  publishError.value = null
  if (!publishFilters.value.exam_id) return

  publishLoading.value = true
  try {
    const res = await examService.exams.publishPreview(publishFilters.value.exam_id, {
      delivery_channel: resultsChannel.value,
    })
    publishPreview.value = res.data?.data || res.data
  } catch (err) {
    publishError.value = err.response?.data?.message || 'Failed to load publish preview'
  } finally {
    publishLoading.value = false
  }
}

const publishExamResults = async () => {
  if (!publishFilters.value.exam_id) return
  publishingExam.value = true
  publishError.value = null
  try {
    const res = await examService.exams.publishResults(publishFilters.value.exam_id, {
      allow_partial: allowPartialPublish.value,
      delivery_channel: resultsChannel.value,
    })
    const count = res.data?.data?.published_count ?? 0
    const label = resultsChannel.value === 'online' ? 'Online' : 'Offline'
    successMsg.value = `${label} exam results published (${count} row(s)). Students can now view marks and rank.`
    setTimeout(() => { successMsg.value = null }, 5000)
    await loadPublishPreview()
  } catch (err) {
    publishError.value = err.response?.data?.message || 'Failed to publish exam results'
  } finally {
    publishingExam.value = false
  }
}

const viewMeritPreview = async () => {
  if (!publishFilters.value.exam_id) return
  meritLoading.value = true
  try {
    const res = await reportService.examMerit(publishFilters.value.exam_id, {
      top: 20,
      scope: 'all',
      delivery_channel: resultsChannel.value,
    })
    meritPreview.value = res.data?.data || res.data
  } catch (err) {
    publishError.value = err.response?.data?.message || 'Failed to load merit list'
  } finally {
    meritLoading.value = false
  }
}

const downloadMerit = async (top) => {
  if (!publishFilters.value.exam_id) return
  exporting.value = true
  try {
    const res = await reportService.exportExam(publishFilters.value.exam_id, {
      format: 'pdf',
      type: 'merit_list',
      top,
      scope: 'all',
      delivery_channel: resultsChannel.value,
    })
    const blob = await blobFromExportResponse(res, 'application/pdf')
    triggerBlobDownload(blob, `merit-${resultsChannel.value}-top-${top}-${publishFilters.value.exam_id}.pdf`)
  } catch (err) {
    publishError.value = err.response?.data?.message || 'Failed to download merit list'
  } finally {
    exporting.value = false
  }
}

const downloadResultSheet = async (format) => {
  if (!publishFilters.value.exam_id) return
  exporting.value = true
  try {
    const res = await reportService.exportExam(publishFilters.value.exam_id, {
      format,
      type: 'result_sheet',
      delivery_channel: resultsChannel.value,
    })
    const ext = format === 'csv' ? 'csv' : 'pdf'
    triggerBlobDownload(new Blob([res.data]), `result-sheet-${publishFilters.value.exam_id}.${ext}`)
  } catch (err) {
    publishError.value = err.response?.data?.message || 'Failed to download result sheet'
  } finally {
    exporting.value = false
  }
}

const resetCourseBatchSubject = () => {
  filters.value.course_id = ''
  filters.value.batch_id = ''
  filters.value.subject_id = ''
  courses.value = []
  batches.value = []
  subjects.value = []
}

const onClassChange = async () => {
  resetCourseBatchSubject()
  if (!filters.value.class_id) return
  try {
    const res = await enrollmentService.getCourses({ class_id: filters.value.class_id })
    courses.value = extractData(res, [])
  } catch {
    courses.value = []
  }
}

const onCourseChange = async () => {
  filters.value.batch_id = ''
  filters.value.subject_id = ''
  batches.value = []
  subjects.value = []
  if (!filters.value.course_id) return
  try {
    const res = await enrollmentService.getBatchesByCourse(filters.value.course_id)
    batches.value = extractData(res, [])
  } catch {
    batches.value = []
  }
}

const onBatchChange = async () => {
  filters.value.subject_id = ''
  subjects.value = []
  if (!filters.value.course_id) return
  try {
    const res = await academicService.subjects.byCourse(filters.value.course_id)
    subjects.value = extractData(res, [])
  } catch {
    subjects.value = []
  }
}

const resolveRoutine = async () => {
  routineError.value = null
  selectedRoutine.value = null
  const res = await examService.routines.byExam(filters.value.exam_id, {
    delivery_channel: resultsChannel.value,
  })
  const routines = extractData(res, [])
  selectedRoutine.value = routines.find((r) => {
    if (r.subject_id !== filters.value.subject_id) return false
    if (filters.value.batch_id && r.batch_id && r.batch_id !== filters.value.batch_id) return false
    if (filters.value.course_id && r.course_id && r.course_id !== filters.value.course_id) return false
    if (filters.value.class_id && r.class_id && r.class_id !== filters.value.class_id) return false
    return true
  }) || null
  if (!selectedRoutine.value) {
    routineError.value = 'No exam routine found for this exam, class, course, batch, and subject.'
  } else if (!isRoutineOpenForMarks(selectedRoutine.value)) {
    routineError.value = routineMarksClosedMessage(selectedRoutine.value)
  }
}

const loadResults = async () => {
  if (!filters.value.exam_id || !filters.value.class_id || !filters.value.course_id || !filters.value.batch_id || !filters.value.subject_id) {
    error.value = 'Please select all filters'
    return
  }
  loading.value = true
  error.value = null
  try {
    await resolveRoutine()
    if (!selectedRoutine.value) { students.value = []; return }

    let studentRows = []
    const enrollRes = await enrollmentService.getEnrollments({
      batch_id: filters.value.batch_id,
      status: 'active',
      per_page: 500,
    })
    studentRows = extractData(enrollRes, []).map((e) => e.student).filter(Boolean)

    students.value = studentRows.map((s) => ({
      id: s.id,
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

    const existingRes = await examService.results.list({
      exam_id: filters.value.exam_id,
      subject_id: filters.value.subject_id,
      exam_routine_id: selectedRoutine.value.id,
      delivery_channel: resultsChannel.value,
      per_page: 500,
    })
    extractData(existingRes, []).forEach((r) => {
      if (!entryData.value[r.student_id]) return
      const breakdown = r.marks_breakdown || { total: r.marks_obtained }
      entryData.value[r.student_id] = {
        breakdown: { ...createEmptyBreakdown(components), ...breakdown },
        remarks: r.remarks || '',
        total: Number(r.marks_obtained) || 0,
        evaluation_status: r.evaluation_status || 'pending',
      }
    })
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

const saveResults = async () => {
  if (!selectedRoutine.value) {
    error.value = routineError.value || 'Exam routine not found'
    return
  }
  if (!isRoutineOpenForMarks(selectedRoutine.value)) {
    error.value = routineMarksClosedMessage(selectedRoutine.value)
    return
  }
  saving.value = true
  error.value = null
  successMsg.value = null
  try {
    await examService.results.markBulk({
      exam_id: filters.value.exam_id,
      exam_routine_id: selectedRoutine.value.id,
      subject_id: filters.value.subject_id,
      total_marks: totalMarks.value,
      results: students.value
        .map((student) => {
          const data = entryData.value[student.id] || {}
          const marksObtained = Number(data.total) || 0
          const gradeInfo = calculateResultGrade(
            marksObtained,
            totalMarks.value,
            data.breakdown || {},
            effectiveMarkConfig.value,
          )
          return {
            student_id: student.id,
            marks_obtained: marksObtained,
            marks_breakdown: data.breakdown || {},
            grade: gradeInfo.grade,
            grade_point: gradeInfo.grade_point,
            remarks: data.remarks || null,
            status: 'pending',
          }
        })
        .filter((row) => {
          const hasBreakdown = Object.values(row.marks_breakdown || {}).some((v) => v !== null && v !== '')
          return row.marks_obtained > 0 || hasBreakdown
        }),
    })
    successMsg.value = 'Marks saved as Pending. Publish the exam from the Publish tab when ready.'
    setTimeout(() => { successMsg.value = null }, 4000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* Legacy class hooks — layout in exam-marks-workspace.css */
.exam-marks-workspace .data-table th {
  background: var(--bg-surface-muted);
  padding: 0.65rem 0.75rem;
  text-align: left;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border-color);
}
.exam-marks-workspace .data-table td {
  padding: 0.55rem 0.75rem;
  border-bottom: 1px solid var(--border-light);
}

</style>
