<template>
  <div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1>My Exam Results</h1>
        <span class="badge-count">{{ resultsTab === 'official' ? results.length : onlineResults.length }} results</span>
      </div>
      <div class="header-actions">
        <button
          class="btn btn-outline"
          @click="viewMode = viewMode === 'summary' ? 'detailed' : 'summary'"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px; vertical-align: middle;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
          {{ viewMode === 'summary' ? 'Detailed View' : 'Summary View' }}
        </button>
      </div>
    </div>

    <div class="results-tabs">
      <button class="results-tab" :class="{ active: resultsTab === 'official' }" @click="resultsTab = 'official'">
        Official Results
      </button>
      <button class="results-tab" :class="{ active: resultsTab === 'online' }" @click="resultsTab = 'online'">
        Provisional MCQ (pre-publish)
        <span v-if="onlineResults.length" class="tab-count">{{ onlineResults.length }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading exam results...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="fetchResults">Try Again</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="resultsTab === 'official' && results.length === 0" class="empty-state">
      <svg width="48" height="48" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3>No Results Available</h3>
      <p>Your exam results have not been published yet.</p>
    </div>

    <div v-else-if="resultsTab === 'online' && onlineResults.length === 0" class="empty-state">
      <h3>No Online Scores Yet</h3>
      <p>Complete a live online exam to see auto-scored MCQ results here.</p>
    </div>

    <!-- Online MCQ Scores -->
    <div v-else-if="resultsTab === 'online'" class="online-results-list">
      <div v-for="group in groupedOnlineExams" :key="group.examId" class="online-exam-group">
        <div class="online-group-header">
          <h3>{{ group.examName }}</h3>
          <button
            v-if="group.examId && group.examId !== 'unknown'"
            class="btn btn-outline btn-xs"
            :class="{ active: expandedProvisionalLeaderboard === group.examId }"
            @click="toggleProvisionalLeaderboard(group.examId)"
          >
            {{ expandedProvisionalLeaderboard === group.examId ? 'Hide MCQ Standings' : 'MCQ Standings (unofficial)' }}
          </button>
        </div>

        <ExamLeaderboard
          v-if="expandedProvisionalLeaderboard === group.examId && group.examId && group.examId !== 'unknown'"
          mode="provisional"
          :exam-id="group.examId"
          :top="50"
          :show-subject-toppers="false"
        />

        <div v-for="row in group.results" :key="row.id" class="online-result-card">
          <div class="online-result-head">
            <div>
              <span class="online-subject">{{ row.routine?.subject?.name || row.subject?.name || 'Subject' }}</span>
            </div>
            <span class="online-badge">Online MCQ</span>
          </div>
          <div class="online-score-row">
            <span class="online-score">{{ row.mcq_score ?? row.marks ?? 0 }}</span>
            <span class="online-total">/ {{ row.total_marks || 100 }}</span>
            <span class="online-pct">{{ row.mcq_percentage ?? 0 }}% MCQ</span>
          </div>
          <div class="online-meta">
            <span>Status: {{ row.evaluation_status === 'partial' ? 'MCQ scored — CQ pending review' : 'Auto-scored' }}</span>
            <span>{{ formatDate(row.routine?.exam_date) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary View -->
    <template v-else-if="resultsTab === 'official' && viewMode === 'summary'">
      <!-- Overall Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon" style="background: #eef2ff;">
            <svg width="18" height="18" fill="none" stroke="var(--primary-color)" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div>
            <div class="stat-val">{{ totalExams }}</div>
            <div class="stat-lbl">Exams Taken</div>
          </div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon" style="background: #d4edda;">
            <svg width="18" height="18" fill="none" stroke="var(--secondary-color)" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <div class="stat-val">{{ passedCount }}</div>
            <div class="stat-lbl">Passed</div>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon" style="background: #fee2e2;">
            <svg width="18" height="18" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <div>
            <div class="stat-val">{{ failedCount }}</div>
            <div class="stat-lbl">Failed</div>
          </div>
        </div>
        <div class="stat-card purple">
          <div class="stat-icon" style="background: #f3e8ff;">
            <svg width="18" height="18" fill="none" stroke="#7c3aed" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
          <div>
            <div class="stat-val">{{ averageMarks }}%</div>
            <div class="stat-lbl">Average</div>
          </div>
        </div>
      </div>

      <!-- Results by Exam -->
      <div v-for="examGroup in groupedByExam" :key="examGroup.groupKey || examGroup.examId" class="exam-group">
        <div class="exam-group-header">
          <div class="exam-group-title">
            <h3>{{ examGroup.examName }}</h3>
            <span v-if="examGroup.deliveryChannel" class="channel-badge" :class="'ch-' + examGroup.deliveryChannel">
              {{ examGroup.deliveryChannel === 'online' ? 'Online' : 'Offline' }}
            </span>
            <span v-if="examGroup.position" class="position-badge">
              Rank {{ examGroup.position }}<span v-if="examGroup.totalStudents"> / {{ examGroup.totalStudents }}</span>
            </span>
            <span v-if="examGroup.overallGrade" class="overall-grade-badge">{{ examGroup.overallGrade }}</span>
            <span v-if="examGroup.gpa != null" class="gpa-badge">GPA {{ examGroup.gpa }}</span>
            <span v-if="examGroup.examPercentage != null" class="pct-badge">{{ examGroup.examPercentage }}% overall</span>
          </div>
          <div class="exam-group-actions">
            <button
              v-if="examGroup.examId && examGroup.examId !== 'unknown'"
              class="btn btn-outline btn-xs"
              @click="downloadMarksheet(examGroup.examId, examGroup.deliveryChannel)"
              :disabled="downloadingExamId === examGroup.examId"
            >
              {{ downloadingExamId === examGroup.examId ? '...' : 'Download PDF' }}
            </button>
            <button
              v-if="examGroup.examId && examGroup.examId !== 'unknown'"
              class="btn btn-outline btn-xs"
              @click="printMarksheet(examGroup.examId, examGroup.deliveryChannel)"
              :disabled="downloadingExamId === examGroup.examId"
            >
              Print
            </button>
            <button
              v-if="examGroup.examId && examGroup.examId !== 'unknown'"
              class="btn btn-outline btn-xs"
              :class="{ active: expandedLeaderboard === examGroup.groupKey }"
              @click="toggleLeaderboard(examGroup.groupKey)"
            >
              {{ expandedLeaderboard === examGroup.groupKey ? 'Hide Leaderboard' : 'Leaderboard' }}
            </button>
          </div>
          <div class="exam-group-stats">
            <span class="exam-group-stat">
              <span class="exam-group-stat-val">{{ examGroup.passed }}</span>
              Passed
            </span>
            <span class="exam-group-stat">
              <span class="exam-group-stat-val">{{ examGroup.failed }}</span>
              Failed
            </span>
            <span class="exam-group-stat">
              <span class="exam-group-stat-val">{{ examGroup.average }}%</span>
              Avg
            </span>
          </div>
        </div>
        <ExamLeaderboard
          v-if="expandedLeaderboard === examGroup.groupKey && examGroup.examId && examGroup.examId !== 'unknown'"
          mode="student"
          :exam-id="examGroup.examId"
          :delivery-channel="examGroup.deliveryChannel || 'offline'"
          :top="50"
          :show-subject-toppers="true"
        />
        <div class="exam-group-body">
          <div
            v-for="result in examGroup.results"
            :key="result.id"
            class="result-row"
          >
            <div class="result-subject">
              <span class="subject-badge" :style="{ background: getSubjectColor(result.subject?.name || '') + '20', color: getSubjectColor(result.subject?.name || '') }">
                {{ result.subject?.name || 'N/A' }}
              </span>
            </div>
            <div class="result-marks-bar">
              <div class="marks-bar-track">
                <div
                  class="marks-bar-fill"
                  :style="{ width: getPercentage(result.marks, result.routine?.total_marks || 100) + '%', background: getGradeColor(result.marks, result.routine?.total_marks || 100) }"
                ></div>
              </div>
              <span class="marks-bar-label">{{ result.marks ?? '—' }}/{{ resultTotalMarks(result) }}</span>
            </div>
            <div class="result-grade">
              <span class="grade-badge" :class="getGradeClass(result.marks, resultTotalMarks(result))">
                {{ result.grade || getGrade(result.marks, resultTotalMarks(result)) }}
              </span>
            </div>
            <div v-if="resultBreakdownText(result)" class="result-breakdown">{{ resultBreakdownText(result) }}</div>
            <div v-if="subjectHighMark(result)" class="result-high">
              Highest in exam: {{ subjectHighMark(result) }}
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Detailed View -->
    <template v-else-if="resultsTab === 'official'">
      <div class="detailed-list">
        <div
          v-for="result in sortedResults"
          :key="result.id"
          class="result-card"
        >
          <div class="result-card-header">
            <span class="subject-badge" :style="{ background: getSubjectColor(result.subject?.name || '') + '20', color: getSubjectColor(result.subject?.name || '') }">
              {{ result.subject?.name || 'N/A' }}
            </span>
            <span class="result-card-exam">{{ result.exam?.name || 'N/A' }}</span>
            <span class="grade-badge" :class="getGradeClass(result.marks, result.routine?.total_marks || 100)">
              {{ getGrade(result.marks, result.routine?.total_marks || 100) }}
            </span>
          </div>
          <div class="result-card-body">
            <div class="result-card-info">
              <div class="result-card-info-item">
                <span class="result-card-label">Marks</span>
                <span class="result-card-value">{{ result.marks }}/{{ result.routine?.total_marks || 100 }}</span>
              </div>
              <div class="result-card-info-item">
                <span class="result-card-label">Percentage</span>
                <span class="result-card-value">{{ getPercentage(result.marks, result.routine?.total_marks || 100) }}%</span>
              </div>
              <div class="result-card-info-item">
                <span class="result-card-label">Pass Marks</span>
                <span class="result-card-value">{{ result.routine?.pass_marks || 'N/A' }}</span>
              </div>
              <div class="result-card-info-item">
                <span class="result-card-label">Date</span>
                <span class="result-card-value">{{ formatDate(result.routine?.exam_date) }}</span>
              </div>
            </div>
            <div class="result-card-bar">
              <div class="marks-bar-track" style="height: 8px;">
                <div
                  class="marks-bar-fill"
                  :style="{ width: getPercentage(result.marks, result.routine?.total_marks || 100) + '%', background: getGradeColor(result.marks, result.routine?.total_marks || 100) }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import examService from '@/services/exam.service'
import apiClient from '@/services/api.service'
import ExamLeaderboard from '@/components/exam/ExamLeaderboard.vue'
import { loadGradingRules, calculateGradeFromMarks } from '@/utils/grading.utils'
import { formatBreakdownSummary } from '@/utils/markConfig.utils'
import { resolveMarkConfig } from '@/utils/examType.utils'

const viewMode = ref('summary')
const resultsTab = ref('official')
const results = ref([])
const onlineResults = ref([])
const examSummaries = ref({})
const subjectHighsByExam = ref({})
const loading = ref(true)
const error = ref(null)
const downloadingExamId = ref(null)
const expandedLeaderboard = ref(null)
const expandedProvisionalLeaderboard = ref(null)

function toggleLeaderboard(groupKey) {
  expandedLeaderboard.value = expandedLeaderboard.value === groupKey ? null : groupKey
}

function toggleProvisionalLeaderboard(examId) {
  expandedProvisionalLeaderboard.value = expandedProvisionalLeaderboard.value === examId ? null : examId
}

const groupedOnlineExams = computed(() => {
  const groups = {}
  onlineResults.value.forEach((row) => {
    const examId = row.exam?.id || row.exam_id || 'unknown'
    if (!groups[examId]) {
      groups[examId] = {
        examId,
        examName: row.exam?.name || 'Online Exam',
        results: [],
      }
    }
    groups[examId].results.push(row)
  })
  return Object.values(groups)
})

const SUBJECT_COLORS = [
  '#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626',
  '#7c3aed', '#db2777', '#2563eb', '#16a34a', '#ca8a04',
  '#9333ea', '#e11d48', '#0d9488', '#65a30d', '#f97315',
]

const sortedResults = computed(() => {
  return [...results.value].sort((a, b) => {
    const dateA = a.routine?.exam_date || ''
    const dateB = b.routine?.exam_date || ''
    return dateB.localeCompare(dateA)
  })
})

const totalExams = computed(() => {
  const exams = new Set(results.value.map(r => r.exam?.id).filter(Boolean))
  return exams.size
})

const passedCount = computed(() => {
  return results.value.filter(r => {
    const marks = r.marks || 0
    const passMarks = r.routine?.pass_marks || 0
    return marks >= passMarks
  }).length
})

const failedCount = computed(() => {
  return results.value.filter(r => {
    const marks = r.marks || 0
    const passMarks = r.routine?.pass_marks || 0
    return marks < passMarks
  }).length
})

const averageMarks = computed(() => {
  if (results.value.length === 0) return 0
  const total = results.value.reduce((sum, r) => {
    const marks = r.marks || 0
    const totalMarks = r.routine?.total_marks || 100
    return sum + ((marks / totalMarks) * 100)
  }, 0)
  return Math.round(total / results.value.length)
})

function summaryKeyForResult(r) {
  const examId = r.exam?.id || 'unknown'
  const channel = r.delivery_channel || r.is_online_result ? 'online' : 'offline'
  return `${examId}:${channel}`
}

const groupedByExam = computed(() => {
  const groups = {}
  results.value.forEach(r => {
    const examId = r.exam?.id || 'unknown'
    const channel = r.delivery_channel || (r.is_online_result ? 'online' : 'offline')
    const groupKey = `${examId}:${channel}`
    const summary = examSummaries.value[groupKey] || examSummaries.value[examId] || {}
    if (!groups[groupKey]) {
      groups[groupKey] = {
        examId,
        groupKey,
        deliveryChannel: channel,
        examName: r.exam?.name || 'Unknown Exam',
        results: [],
        passed: 0,
        failed: 0,
        average: 0,
        position: summary.position ?? r.position ?? null,
        totalStudents: summary.total_students ?? null,
        gpa: summary.gpa ?? r.gpa ?? null,
        overallGrade: summary.overall_grade ?? r.overall_grade ?? null,
        examPercentage: summary.percentage ?? r.exam_percentage ?? null,
      }
    }
    groups[groupKey].results.push(r)
  })

  Object.values(groups).forEach(group => {
    group.passed = group.results.filter(r => {
      const marks = r.marks || 0
      const passMarks = r.routine?.pass_marks || 0
      return marks >= passMarks
    }).length
    group.failed = group.results.length - group.passed
    const total = group.results.reduce((sum, r) => {
      const marks = r.marks || 0
      const totalMarks = r.routine?.total_marks || 100
      return sum + ((marks / totalMarks) * 100)
    }, 0)
    group.average = Math.round(total / group.results.length)
  })

  return Object.values(groups)
})

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

function getPercentage(marks, total) {
  if (!marks || !total) return 0
  return Math.round((marks / total) * 100)
}

function resultTotalMarks(result) {
  return result.total_marks || result.routine?.total_marks || 100
}

function subjectHighMark(result) {
  const key = summaryKeyForResult(result)
  const highs = subjectHighsByExam.value[key] || subjectHighsByExam.value[result.exam?.id]
  const subjectId = result.subject_id || result.subject?.id
  return highs?.[subjectId]?.highest_marks ?? null
}

async function printMarksheet(examId, deliveryChannel = 'offline') {
  downloadingExamId.value = examId
  try {
    const res = await examService.student.downloadMarksheet(examId, { delivery_channel: deliveryChannel })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const w = window.open(url, '_blank')
    if (w) w.onload = () => { w.print() }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to open marksheet for print'
  } finally {
    downloadingExamId.value = null
  }
}

function resultBreakdownText(result) {
  const cfg = resolveMarkConfig(result.routine, result.exam)
  return formatBreakdownSummary(result.marks_breakdown, cfg)
}

function getGrade(marks, totalMarks) {
  if (marks === undefined || marks === null || !totalMarks) return '-'
  return calculateGradeFromMarks(marks, totalMarks).grade
}

function getGradeClass(marks, totalMarks) {
  if (marks === undefined || marks === null || !totalMarks) return ''
  const grade = getGrade(marks, totalMarks)
  if (grade === 'A+' || grade === 'A') return 'grade-excellent'
  if (grade === 'A-' || grade === 'B') return 'grade-good'
  if (grade === 'C' || grade === 'D') return 'grade-average'
  return 'grade-fail'
}

function getGradeColor(marks, totalMarks) {
  if (marks === undefined || marks === null || !totalMarks) return '#d1d5db'
  const percentage = (marks / totalMarks) * 100
  if (percentage >= 80) return '#059669'
  if (percentage >= 60) return '#4f46e5'
  if (percentage >= 40) return '#d97706'
  return '#dc2626'
}

async function fetchResults() {
  loading.value = true
  error.value = null
  try {
    const res = await examService.student.results()
    const payload = res.data?.data || res.data || {}
    const rows = Array.isArray(payload)
      ? payload
      : (payload.results || [])

    examSummaries.value = payload.exam_summaries || {}
    subjectHighsByExam.value = payload.subject_highs_by_exam || {}

    results.value = rows.map((row) => ({
      ...row,
      marks: row.marks_obtained ?? row.marks ?? null,
      routine: row.routine || row.exam_routine || null,
    }))
    onlineResults.value = (payload.online_results || []).map((row) => ({
      ...row,
      marks: row.marks_obtained ?? row.marks ?? null,
      routine: row.routine || row.exam_routine || null,
    }))
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load exam results'
  } finally {
    loading.value = false
  }
}

async function downloadMarksheet(examId, deliveryChannel = 'offline') {
  downloadingExamId.value = examId
  try {
    const res = await examService.student.downloadMarksheet(examId, { delivery_channel: deliveryChannel })
    const url = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `marksheet-${examId}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to download marksheet'
  } finally {
    downloadingExamId.value = null
  }
}

onMounted(async () => {
  await loadGradingRules(apiClient)
  await fetchResults()
})
</script>

<style scoped>
.page-container {
  max-width: 900px;
  margin: 0 auto;
}

.channel-badge {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}
.channel-badge.ch-offline { background: #f3f4f6; color: var(--text-secondary); }
.channel-badge.ch-online { background: #dbeafe; color: #1d4ed8; }

.results-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.results-tab {
  padding: 0.45rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-card);
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 600;
}
.results-tab.active {
  background: #4f46e5;
  color: #fff;
  border-color: #4f46e5;
}
.tab-count {
  margin-left: 0.35rem;
  background: rgba(255,255,255,0.25);
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-size: 0.75rem;
}
.online-results-list { display: flex; flex-direction: column; gap: 1rem; }
.online-exam-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.85rem;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  background: var(--bg-card);
}
.online-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.online-group-header h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--text-dark);
}
.online-result-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-left: 4px solid #2563eb;
  border-radius: 10px;
  padding: 1rem;
}
.online-result-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
.online-result-head h3 { margin: 0; font-size: 1rem; }
.online-subject { font-size: 0.82rem; color: var(--text-muted); }
.online-badge {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  background: #dbeafe;
  color: #1d4ed8;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
}
.online-score-row { display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.35rem; }
.online-score { font-size: 1.75rem; font-weight: 700; color: #2563eb; }
.online-total { color: var(--text-muted); }
.online-pct { color: #059669; font-weight: 600; font-size: 0.85rem; }
.online-meta { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted); flex-wrap: wrap; gap: 0.35rem; }

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-left h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
}

.badge-count {
  background: #eef2ff;
  color: var(--primary-color);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

@media (min-width: 640px) {
  .stats-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  border: 1px solid var(--border-color);
  transition: box-shadow 0.2s;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
}

.stat-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-val {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}

.stat-lbl {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 0.125rem;
}

/* Exam Group */
.exam-group {
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  margin-bottom: 1rem;
  overflow: hidden;
}

.exam-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 0.5rem;
}

.exam-group-header h3 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-dark);
}

.exam-group-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.position-badge {
  background: #eef2ff;
  color: var(--primary-color);
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
}

.overall-grade-badge, .gpa-badge, .pct-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: #f3f4f6;
  color: var(--text-secondary);
}

.result-breakdown {
  flex: 1 1 100%;
  font-size: 0.72rem;
  color: var(--text-muted);
  padding: 0.25rem 0 0 0;
}
.result-high {
  flex: 1 1 100%;
  font-size: 0.7rem;
  color: #059669;
  font-weight: 600;
}

.exam-group-body .result-row {
  flex-wrap: wrap;
}

.exam-group-actions .btn.active {
  background: #eef2ff;
  border-color: #4f46e5;
  color: #4f46e5;
}

.exam-group-actions .btn-xs {
  padding: 0.25rem 0.55rem;
  font-size: 0.6875rem;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
}

.exam-group-stats {
  display: flex;
  gap: 0.75rem;
}

.exam-group-stat {
  font-size: 0.6875rem;
  color: var(--text-muted);
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.exam-group-stat-val {
  font-weight: 700;
  font-size: 0.8125rem;
  color: var(--text-dark);
}

.exam-group-body {
  padding: 0.5rem 0;
}

.result-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.625rem 1rem;
  border-bottom: 1px solid var(--border-light);
}

.result-row:last-child {
  border-bottom: none;
}

.result-subject {
  min-width: 120px;
}

.subject-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.1875rem 0.5rem;
  border-radius: 0.375rem;
  white-space: nowrap;
}

.result-marks-bar {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.marks-bar-track {
  flex: 1;
  height: 6px;
  background: #f3f4f6;
  border-radius: 999px;
  overflow: hidden;
}

.marks-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}

.marks-bar-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-dark);
  min-width: 60px;
  text-align: right;
}

.result-grade {
  min-width: 40px;
  text-align: center;
}

.grade-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.125rem 0.5rem;
  border-radius: 0.375rem;
}

.grade-excellent {
  background: #d4edda;
  color: #059669;
}

.grade-good {
  background: #eef2ff;
  color: #4f46e5;
}

.grade-average {
  background: #fef3c7;
  color: #d97706;
}

.grade-fail {
  background: #fee2e2;
  color: #dc2626;
}

/* Detailed View */
.detailed-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.result-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  overflow: hidden;
  transition: box-shadow 0.2s;
}

.result-card:hover {
  box-shadow: var(--shadow-md);
}

.result-card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: var(--bg-accent);
  border-bottom: 1px solid var(--border-color);
}

.result-card-exam {
  flex: 1;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.result-card-body {
  padding: 0.75rem 1rem;
}

.result-card-info {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.result-card-info-item {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.result-card-label {
  font-size: 0.6875rem;
  color: var(--text-muted);
  font-weight: 500;
}

.result-card-value {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
}

.result-card-bar {
  padding-top: 0.5rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-outline {
  background: var(--bg-card);
  color: var(--text-dark);
  border-color: var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-accent);
  border-color: #d1d5db;
}

/* States */
.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
}

.loading-state p, .error-state p, .empty-state p {
  margin: 0.75rem 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.empty-state h3 {
  margin: 0.75rem 0 0.25rem;
  font-size: 1rem;
  color: var(--text-dark);
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
