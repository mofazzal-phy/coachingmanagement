<template>
  <div class="exam-marks-workspace">
    <header class="ems-hero">
      <div class="ems-hero-text">
        <h1>Exam Leaderboard</h1>
        <p>View merit rankings after results are published. Offline and online channels have separate leaderboards.</p>
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
    </div>

    <div class="ems-panel">
      <div class="ems-filters">
        <div class="ems-filter-grid">
          <div class="ems-form-group">
            <label>Exam <span class="required">*</span></label>
            <select v-model="filters.exam_id" class="ems-control" @change="onExamChange">
              <option value="">Select exam...</option>
              <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Scope</label>
            <select v-model="filters.scope" class="ems-control" @change="onScopeChange">
              <option value="all">All students with results</option>
              <option value="batch">Batch</option>
              <option value="course">Course</option>
              <option value="class">Class</option>
            </select>
          </div>
          <div v-if="filters.scope === 'batch'" class="ems-form-group">
            <label>Batch</label>
            <select v-model="filters.scope_id" class="ems-control">
              <option value="">Auto / first batch</option>
              <option v-for="b in examBatches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div v-if="filters.scope === 'course'" class="ems-form-group">
            <label>Course</label>
            <select v-model="filters.scope_id" class="ems-control">
              <option value="">Exam course (auto)</option>
              <option
                v-if="courseOption"
                :value="courseOption.id"
              >{{ courseOption.name }}</option>
            </select>
          </div>
          <div v-if="filters.scope === 'class'" class="ems-form-group">
            <label>Class</label>
            <select v-model="filters.scope_id" class="ems-control">
              <option value="">Exam class (auto)</option>
              <option
                v-if="classOption"
                :value="classOption.id"
              >{{ classOption.name }}</option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>Top</label>
            <select v-model.number="filters.top" class="ems-control">
              <option :value="20">Top 20</option>
              <option :value="50">Top 50</option>
              <option :value="100">Top 100</option>
              <option :value="0">All</option>
            </select>
          </div>
        </div>
      </div>

      <p v-if="publishHint" class="ems-info-banner">{{ publishHint }}</p>

      <div v-if="filters.exam_id" class="ems-export-block" style="margin-top:0;">
        <div class="ems-export-btns">
          <button type="button" class="ems-btn ems-btn-outline" @click="downloadPdf" :disabled="exporting || !canLoad">
            Download PDF
          </button>
        </div>
      </div>

      <ExamLeaderboard
        v-if="filters.exam_id && canLoad"
        mode="admin"
        :exam-id="filters.exam_id"
        :delivery-channel="resultsChannel"
        :scope-type="filters.scope"
        :scope-id="effectiveScopeId"
        :top="filters.top || 50"
        :show-subject-toppers="true"
        :key="leaderboardKey"
      />
      <div v-else-if="!filters.exam_id" class="ems-empty" style="margin-top:1rem;">
        <p>Select an exam to view the leaderboard.</p>
      </div>
      <div v-else class="ems-empty" style="margin-top:1rem;">
        <p>Select an exam to view the leaderboard.</p>
      </div>
    </div>

    <div v-if="errorMsg" class="ems-toast success" style="background:#fef2f2;color:#b91c1c;">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import examService from '@/services/exam.service'
import reportService from '@/services/report.service'
import ExamLeaderboard from '@/components/exam/ExamLeaderboard.vue'
import { extractData, blobFromExportResponse } from '@/utils/api.utils'
import '@/styles/exam-marks-workspace.css'

const route = useRoute()

const resultsChannel = ref('offline')
const exams = ref([])
const examBatches = ref([])
const selectedExam = ref(null)
const exporting = ref(false)
const errorMsg = ref(null)

const filters = ref({
  exam_id: route.query.exam_id ? String(route.query.exam_id) : '',
  scope: 'all',
  scope_id: '',
  top: 50,
})

const courseOption = computed(() => {
  const exam = selectedExam.value
  if (!exam) return null
  const id = exam.course_id || exam.course?.id
  if (!id) return null
  return { id, name: exam.course?.name || 'Course' }
})

const classOption = computed(() => {
  const exam = selectedExam.value
  if (!exam) return null
  const id = exam.class_id || exam.class?.id
  if (!id) return null
  return { id, name: exam.class?.name || 'Class' }
})

const effectiveScopeId = computed(() => {
  if (filters.value.scope === 'batch') {
    return filters.value.scope_id || ''
  }
  if (filters.value.scope === 'course') {
    return filters.value.scope_id || courseOption.value?.id || ''
  }
  if (filters.value.scope === 'class') {
    return filters.value.scope_id || classOption.value?.id || ''
  }
  return ''
})

const channelFlagPublished = computed(() => {
  if (!selectedExam.value) return false
  if (resultsChannel.value === 'online') {
    return selectedExam.value.online_result_status === 'published'
  }
  return selectedExam.value.result_status === 'published'
})

const canLoad = computed(() => !!filters.value.exam_id)

const publishHint = computed(() => {
  if (!selectedExam.value || channelFlagPublished.value) return ''
  const label = resultsChannel.value === 'online' ? 'Online' : 'Offline'
  return `${label} exam flag is still draft. Leaderboard loads if published result rows exist for this channel.`
})

const leaderboardKey = computed(() => [
  filters.value.exam_id,
  resultsChannel.value,
  filters.value.scope,
  effectiveScopeId.value,
  filters.value.top,
].join(':'))

async function loadExams() {
  const res = await examService.exams.list({ per_page: 100, include_all: 1 })
  exams.value = extractData(res, [])
}

async function loadExamBatches(examId) {
  examBatches.value = []
  if (!examId) return
  try {
    const res = await examService.routines.byExam(examId)
    const routines = extractData(res, [])
    const seen = new Set()
    examBatches.value = routines
      .filter((r) => r.batch_id && r.batch)
      .filter((r) => {
        if (seen.has(r.batch_id)) return false
        seen.add(r.batch_id)
        return true
      })
      .map((r) => ({ id: r.batch_id, name: r.batch?.name || `Batch ${r.batch_id}` }))
  } catch {
    examBatches.value = []
  }
}

async function syncSelectedExam() {
  const id = filters.value.exam_id
  if (!id) {
    selectedExam.value = null
    return
  }
  try {
    const res = await examService.exams.get(id)
    selectedExam.value = extractData(res, null)
  } catch {
    selectedExam.value = exams.value.find((e) => String(e.id) === String(id)) || null
  }
}

async function resolveDefaultChannel(examId) {
  if (!examId || route.query.channel) return

  for (const channel of ['offline', 'online']) {
    try {
      const res = await reportService.examMerit(examId, {
        scope: 'all',
        top: 1,
        delivery_channel: channel,
      })
      const data = res.data?.data || res.data
      if ((data?.total_students || 0) > 0) {
        resultsChannel.value = channel
        return
      }
    } catch {
      // try next channel
    }
  }
}

async function onExamChange() {
  filters.value.scope_id = ''
  await loadExamBatches(filters.value.exam_id)
  await syncSelectedExam()
  await resolveDefaultChannel(filters.value.exam_id)
}

function onScopeChange() {
  filters.value.scope_id = ''
}

const triggerBlobDownload = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

async function downloadPdf() {
  if (!filters.value.exam_id) return
  exporting.value = true
  errorMsg.value = null
  try {
    const top = filters.value.top || 50
    const params = {
      format: 'pdf',
      type: 'merit_list',
      top,
      delivery_channel: resultsChannel.value,
      scope: filters.value.scope || 'all',
    }
    if (filters.value.scope !== 'all' && effectiveScopeId.value) {
      params.scope_id = effectiveScopeId.value
    }
    const res = await reportService.exportExam(filters.value.exam_id, params)
    const blob = await blobFromExportResponse(res, 'application/pdf')
    triggerBlobDownload(blob, `merit-${resultsChannel.value}-top-${top}-${filters.value.exam_id}.pdf`)
  } catch (err) {
    errorMsg.value = err.response?.data?.message || err.message || 'Failed to download merit list'
  } finally {
    exporting.value = false
  }
}

watch(resultsChannel, () => {
  errorMsg.value = null
})

onMounted(async () => {
  try {
    await loadExams()
    if (filters.value.exam_id) {
      await loadExamBatches(filters.value.exam_id)
      await syncSelectedExam()
      await resolveDefaultChannel(filters.value.exam_id)
    }
  } catch {
    // non-blocking
  }
})
</script>
