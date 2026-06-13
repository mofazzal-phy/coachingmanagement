<template>
  <div class="exam-marks-workspace">
    <header class="ems-hero">
      <div class="ems-hero-text">
        <h1>Exam Leaderboard</h1>
        <p>Merit rankings for exams and batches you teach. Only your assigned exams appear here — not the full institute list.</p>
      </div>
      <span class="ems-hero-badge">Teacher</span>
    </header>

    <div class="ems-channel-bar">
      <span class="ems-channel-label">Result channel</span>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'offline' }">
        <input type="radio" value="offline" v-model="resultsChannel" /> Offline
      </label>
      <label class="ems-channel-opt" :class="{ active: resultsChannel === 'online' }">
        <input type="radio" value="online" v-model="resultsChannel" /> Online
      </label>
    </div>

    <div class="ems-panel">
      <div v-if="loadError" class="ems-info-banner" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
        {{ loadError }}
      </div>

      <div class="ems-filters">
        <div class="ems-filter-grid">
          <div class="ems-form-group">
            <label>My exam <span class="required">*</span></label>
            <select v-model="filters.exam_id" class="ems-control" @change="onExamChange" :disabled="loadingExams">
              <option value="">{{ loadingExams ? 'Loading exams...' : 'Select exam...' }}</option>
              <option v-for="e in exams" :key="e.id" :value="String(e.id)">
                {{ e.name }}
                <template v-if="e.batches?.length"> ({{ e.batches.length }} batch{{ e.batches.length > 1 ? 'es' : '' }})</template>
              </option>
            </select>
          </div>
          <div class="ems-form-group">
            <label>My batch</label>
            <select v-model="filters.scope_id" class="ems-control" :disabled="!filters.exam_id">
              <option value="">All my batches in this exam</option>
              <option v-for="b in examBatches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
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

      <p v-if="!loadingExams && !exams.length && !loadError" class="ems-info-banner">
        No exams found for your subjects or assigned duties. Ask admin to link your teacher profile to subjects/batches.
      </p>

      <p v-else-if="publishHint" class="ems-info-banner">{{ publishHint }}</p>

      <ExamLeaderboard
        v-if="filters.exam_id && channelPublished"
        mode="teacher"
        :exam-id="filters.exam_id"
        :delivery-channel="resultsChannel"
        :scope-type="effectiveScopeType"
        :scope-id="effectiveScopeId"
        :top="filters.top || 50"
        :show-subject-toppers="true"
        :key="leaderboardKey"
      />
      <div v-else-if="!filters.exam_id && exams.length" class="ems-empty" style="margin-top:1rem;">
        <p>Select one of your exams to view the leaderboard.</p>
      </div>
      <div v-else-if="filters.exam_id && !channelPublished" class="ems-empty" style="margin-top:1rem;">
        <p>{{ publishHint }}</p>
        <p class="ems-muted-hint">Switch to {{ otherChannelLabel }} if results were published on that channel.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import examService from '@/services/exam.service'
import ExamLeaderboard from '@/components/exam/ExamLeaderboard.vue'
import { extractData } from '@/utils/api.utils'
import '@/styles/exam-marks-workspace.css'

const route = useRoute()

const resultsChannel = ref(route.query.channel === 'online' ? 'online' : 'offline')
const exams = ref([])
const loadingExams = ref(false)
const loadError = ref(null)

const filters = ref({
  exam_id: route.query.exam_id ? String(route.query.exam_id) : '',
  scope_id: '',
  top: 50,
})

const selectedExam = computed(() =>
  exams.value.find((e) => String(e.id) === String(filters.value.exam_id)) || null,
)

const examBatches = computed(() => selectedExam.value?.batches || [])

const effectiveScopeType = computed(() => {
  if (filters.value.scope_id) return 'batch'
  if (examBatches.value.length === 1) return 'batch'
  return 'all'
})

const effectiveScopeId = computed(() => {
  if (filters.value.scope_id) return String(filters.value.scope_id)
  if (examBatches.value.length === 1) return String(examBatches.value[0].id)
  return ''
})

const channelPublished = computed(() => {
  if (!selectedExam.value) return false
  if (resultsChannel.value === 'online') {
    return !!selectedExam.value.online_published
  }
  return !!selectedExam.value.offline_published
})

const otherChannelLabel = computed(() =>
  resultsChannel.value === 'online' ? 'Offline' : 'Online',
)

const publishHint = computed(() => {
  if (!selectedExam.value) return ''
  if (channelPublished.value) return ''
  const label = resultsChannel.value === 'online' ? 'Online' : 'Offline'
  if (selectedExam.value.offline_published || selectedExam.value.online_published) {
    return `${label} results are not published for this exam. Try the other channel above.`
  }
  return `${label} results are not published yet for this exam.`
})

const leaderboardKey = computed(() => [
  filters.value.exam_id,
  resultsChannel.value,
  effectiveScopeType.value,
  effectiveScopeId.value,
  filters.value.top,
].join(':'))

function applyDefaultChannelForExam(exam) {
  if (!exam) return
  if (route.query.channel) return
  if (exam.online_published && !exam.offline_published) {
    resultsChannel.value = 'online'
  } else if (exam.offline_published) {
    resultsChannel.value = 'offline'
  }
}

function onExamChange() {
  filters.value.scope_id = ''
  applyDefaultChannelForExam(selectedExam.value)
}

async function loadTeacherExams() {
  loadingExams.value = true
  loadError.value = null
  try {
    const res = await examService.teacher.leaderboardContext()
    const data = extractData(res, {})
    exams.value = (data.exams || []).map((e) => ({
      ...e,
      id: String(e.id),
    }))

    if (filters.value.exam_id) {
      const exists = exams.value.some((e) => String(e.id) === String(filters.value.exam_id))
      if (!exists) {
        filters.value.exam_id = ''
      } else {
        applyDefaultChannelForExam(selectedExam.value)
      }
    }
  } catch (err) {
    exams.value = []
    loadError.value = err.response?.data?.message || 'Failed to load your exams'
  } finally {
    loadingExams.value = false
  }
}

watch(resultsChannel, () => {
  // re-render leaderboard for channel
})

onMounted(() => {
  loadTeacherExams()
})
</script>

<style scoped>
.ems-muted-hint {
  margin-top: 0.5rem;
  font-size: 0.82rem;
  color: var(--text-muted);
}
</style>
