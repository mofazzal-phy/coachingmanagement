<template>
  <div class="page-container">
    <div class="page-header">
      <router-link :to="backLink" class="back-link">← Back</router-link>
      <h1>Admit Card</h1>
      <p v-if="examTitle" class="exam-subtitle">{{ examTitle }}</p>
    </div>

    <div v-if="eligibility && eligibility.exam_fee_unpaid" class="eligibility-banner banner-blocked">
      <strong>Exam fee pending</strong>
      <p>{{ eligibility.message || 'Please pay your exam fee from the student portal before downloading the admit card.' }}</p>
    </div>

    <div v-else-if="eligibility && eligibility.check_enabled" class="eligibility-banner" :class="'banner-' + (eligibility.status || 'eligible')">
      <template v-if="eligibility.status === 'blocked'">
        <strong>Admit card unavailable</strong>
        <p>Your attendance is {{ formatPct(eligibility.attendance_percent) }} (required {{ eligibility.thresholds?.eligible_min ?? 75 }}%). Contact the office.</p>
      </template>
      <template v-else-if="eligibility.status === 'warning'">
        <strong>Attendance warning</strong>
        <p>Your attendance is {{ formatPct(eligibility.attendance_percent) }}. You may download the admit card, but please improve attendance.</p>
      </template>
      <template v-else-if="eligibility.status === 'overridden'">
        <strong>Eligibility overridden</strong>
        <p>{{ eligibility.override_reason || 'Approved by administration.' }}</p>
      </template>
      <template v-else>
        <strong>Eligible</strong>
        <p>Attendance {{ formatPct(eligibility.attendance_percent) }} — you may download your admit card.</p>
      </template>
    </div>

    <ExamAdmitCard
      :admit-data="admitData"
      :loading="loading"
      :error="error"
      :downloading="downloading"
      :show-download="canDownload"
      @download="downloadPdf"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import ExamAdmitCard from '@/components/exam/ExamAdmitCard.vue'
import examService from '@/services/exam.service'
import { extractData } from '@/utils/api.utils'

const route = useRoute()
const examId = computed(() => route.params.examId)
const backLink = computed(() => {
  if (route.query.from === 'routines') {
    return { name: 'StudentExamRoutines', query: route.query.exam_id ? { exam_id: route.query.exam_id } : {} }
  }
  return { name: 'StudentExams' }
})

const loading = ref(true)
const downloading = ref(false)
const error = ref('')
const admitData = ref(null)
const eligibility = ref(null)
const examTitle = ref('')

const canDownload = computed(() => !!eligibility.value?.can_download_admit)

function formatPct(v) {
  if (v == null) return '—'
  return `${Number(v).toFixed(1)}%`
}

/** Reject admin eligibility list payloads — student page only shows one admit card. */
function normalizeAdmitPayload(data) {
  if (!data || Array.isArray(data)) return null
  if (data.students && !data.student) {
    return null
  }
  return data
}

async function loadAdmitCard() {
  loading.value = true
  error.value = ''
  admitData.value = null
  try {
    const res = await examService.student.admitCard(examId.value)
    const data = normalizeAdmitPayload(extractData(res, null))
    if (!data) {
      error.value = 'Could not load your admit card. Please try again from My Exams.'
      return
    }
    if (data.eligibility) {
      eligibility.value = data.eligibility
    }
    if (!eligibility.value?.can_download_admit) {
      error.value = eligibility.value.message || 'Admit card is not available.'
      return
    }
    admitData.value = data
    examTitle.value = data.exam?.name || ''
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to load admit card'
    error.value = msg
    if (e.response?.status === 403 && e.response?.data?.errors?.eligibility) {
      eligibility.value = e.response.data.errors.eligibility
    }
  } finally {
    loading.value = false
  }
}

async function downloadPdf() {
  if (!canDownload.value) return
  downloading.value = true
  try {
    const res = await examService.student.downloadAdmitCard(examId.value)
    const blob = res.data instanceof Blob
      ? res.data
      : new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `admit-card-${examId.value}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    if (e.response?.data instanceof Blob) {
      try {
        const text = await e.response.data.text()
        const json = JSON.parse(text)
        error.value = json.message || 'Download failed'
      } catch {
        error.value = 'Download failed'
      }
    } else {
      error.value = e.response?.data?.message || 'Download failed'
    }
  } finally {
    downloading.value = false
  }
}

onMounted(loadAdmitCard)
</script>

<style scoped>
.page-container {
  max-width: 720px;
  margin: 0 auto;
}
.page-header {
  margin-bottom: 1rem;
}
.page-header h1 {
  margin: 0.25rem 0 0;
  font-size: 1.35rem;
}
.exam-subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
  color: var(--text-muted);
}
.back-link {
  display: inline-block;
  font-size: 0.85rem;
  color: var(--primary-color, #4f46e5);
  text-decoration: none;
}
.eligibility-banner {
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
}
.eligibility-banner strong { display: block; margin-bottom: 0.25rem; }
.eligibility-banner p { margin: 0; }
.banner-eligible { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.banner-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.banner-blocked { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.banner-overridden { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; }
</style>
