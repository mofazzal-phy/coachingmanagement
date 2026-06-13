<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Exam Analytics</h1></div>
    </div>

    <div class="info-banner">
      Analytics use <strong>published</strong> exam results only. Select an exam to view pass rates, grade distribution, and subject performance.
    </div>

    <div class="filters-card">
      <div class="filter-row">
        <div class="form-group">
          <label>Exam <span class="required">*</span></label>
          <select v-model="examId" class="form-control" @change="loadAnalytics">
            <option value="">Select exam</option>
            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
        <div class="form-group filter-action">
          <label>&nbsp;</label>
          <button class="btn btn-outline btn-sm" @click="loadAnalytics" :disabled="!examId || loading">Refresh</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading analytics...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p></div>
    <div v-else-if="!examId" class="empty-state">
      <div class="empty-icon">📈</div>
      <h3>Select an Exam</h3>
      <p>Choose an exam to view performance analytics.</p>
    </div>
    <template v-else-if="analytics">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-val">{{ analytics.total_students }}</div>
          <div class="stat-lbl">Students</div>
        </div>
        <div class="stat-card green">
          <div class="stat-val">{{ analytics.pass_rate }}%</div>
          <div class="stat-lbl">Pass Rate</div>
        </div>
        <div class="stat-card red">
          <div class="stat-val">{{ analytics.fail_rate }}%</div>
          <div class="stat-lbl">Fail Rate</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-val">{{ analytics.average_percentage }}%</div>
          <div class="stat-lbl">Avg Percentage</div>
        </div>
      </div>

      <div class="panels-row">
        <div class="panel">
          <h3>Grade Distribution</h3>
          <div v-if="Object.keys(analytics.grade_distribution || {}).length === 0" class="text-muted">No data</div>
          <div v-else class="grade-bars">
            <div v-for="(count, grade) in analytics.grade_distribution" :key="grade" class="grade-bar-row">
              <span class="grade-label">{{ grade }}</span>
              <div class="grade-bar-track"><div class="grade-bar-fill" :style="{ width: gradeBarWidth(count) + '%' }"></div></div>
              <span class="grade-count">{{ count }}</span>
            </div>
          </div>
        </div>

        <div class="panel">
          <h3>Top Performers</h3>
          <table class="data-table" v-if="analytics.top_performers?.length">
            <thead><tr><th>Rank</th><th>Student</th><th>Marks</th><th>%</th></tr></thead>
            <tbody>
              <tr v-for="p in analytics.top_performers" :key="p.student_id">
                <td>{{ p.rank }}</td>
                <td>{{ p.student_name }}</td>
                <td>{{ p.total_marks }}/{{ p.total_possible }}</td>
                <td>{{ p.percentage }}%</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-muted">No published results yet.</p>
        </div>
      </div>

      <div class="panel subject-panel" v-if="subjectAnalysis">
        <h3>Subject Analysis</h3>
        <table class="data-table" v-if="subjectAnalysis.subjects?.length">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Appeared</th>
              <th>Pass Rate</th>
              <th>Avg %</th>
              <th>Highest</th>
              <th>Lowest</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in subjectAnalysis.subjects" :key="s.subject_id">
              <td><strong>{{ s.subject_name }}</strong></td>
              <td>{{ s.appeared }}</td>
              <td>{{ s.pass_rate }}%</td>
              <td>{{ s.average_percentage }}%</td>
              <td>{{ s.highest_marks }}</td>
              <td>{{ s.lowest_marks }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="subjectAnalysis.weakest_subjects?.length" class="insight-row">
          <span class="insight weak">Weakest: {{ subjectAnalysis.weakest_subjects.map(s => s.subject_name).join(', ') }}</span>
          <span class="insight strong">Strongest: {{ (subjectAnalysis.strongest_subjects || []).map(s => s.subject_name).join(', ') }}</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import examService from '@/services/exam.service'
import reportService from '@/services/report.service'
import { extractData } from '@/utils/api.utils'

const exams = ref([])
const examId = ref('')
const analytics = ref(null)
const subjectAnalysis = ref(null)
const loading = ref(false)
const error = ref(null)

const gradeBarWidth = (count) => {
  const max = Math.max(...Object.values(analytics.value?.grade_distribution || { x: 1 }))
  return max > 0 ? Math.round((count / max) * 100) : 0
}

const loadAnalytics = async () => {
  analytics.value = null
  subjectAnalysis.value = null
  error.value = null
  if (!examId.value) return

  loading.value = true
  try {
    const [analyticsRes, subjectRes] = await Promise.all([
      reportService.examAnalytics(examId.value),
      reportService.examSubjectAnalysis(examId.value),
    ])
    analytics.value = analyticsRes.data?.data || analyticsRes.data
    subjectAnalysis.value = subjectRes.data?.data || subjectRes.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load analytics'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const res = await examService.exams.list({ per_page: 100 })
    exams.value = extractData(res, [])
  } catch {
    exams.value = []
  }
})
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; }
.page-header { margin-bottom: 1rem; }
.page-header h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.info-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.filters-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; }
.filter-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
.form-group { flex: 1; min-width: 200px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.filter-action { flex: 0 0 auto; }
.btn { padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.loading-state, .error-state, .empty-state { text-align: center; padding: 2rem; background: var(--bg-card); border-radius: 12px; }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.stat-card { background: var(--bg-card); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
.stat-card.green { border-color: #a7f3d0; }
.stat-card.red { border-color: #fecaca; }
.stat-card.purple { border-color: #c4b5fd; }
.stat-val { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.stat-lbl { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
.panels-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.panel { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); }
.panel h3 { margin: 0 0 0.75rem; font-size: 1rem; color: var(--text-primary); }
.subject-panel { margin-bottom: 1rem; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: var(--bg-accent); padding: 0.6rem; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.data-table td { padding: 0.55rem 0.6rem; font-size: 0.85rem; border-bottom: 1px solid var(--border-light); }
.grade-bars { display: flex; flex-direction: column; gap: 0.5rem; }
.grade-bar-row { display: grid; grid-template-columns: 36px 1fr 32px; gap: 0.5rem; align-items: center; font-size: 0.85rem; }
.grade-bar-track { height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
.grade-bar-fill { height: 100%; background: #4f46e5; border-radius: 4px; }
.insight-row { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.75rem; font-size: 0.85rem; }
.insight.weak { color: #b45309; }
.insight.strong { color: #059669; }
.text-muted { color: var(--text-muted); font-size: 0.85rem; }
</style>
