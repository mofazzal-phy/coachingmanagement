<template>
  <div class="guardian-page">
    <div class="page-header">
      <router-link to="/guardian/children" class="back-link">← Back to Children</router-link>
      <h1 v-if="student">{{ fullName(student) }}</h1>
      <p v-if="student">{{ student.student_id }} · Guardian View</p>
    </div>

    <div v-if="loading" class="state-card"><ProgressSpinner /><p>Loading ward profile...</p></div>
    <div v-else-if="error" class="state-card"><Message severity="error" :closable="false">{{ error }}</Message></div>

    <template v-else-if="student">
      <div class="summary-grid">
        <div v-if="attendance" class="summary-card">
          <h3>Attendance</h3>
          <div class="big-value">{{ attendance.percentage || 0 }}%</div>
          <div class="mini-stats">
            <span>Present: <strong>{{ attendance.present || 0 }}</strong></span>
            <span>Absent: <strong>{{ attendance.absent || 0 }}</strong></span>
            <span>Late: <strong>{{ attendance.late || 0 }}</strong></span>
          </div>
        </div>

        <div v-if="feeData" class="summary-card">
          <h3>Fee Status</h3>
          <div class="big-value" :class="{ 'due-text': totalDue > 0 }">৳{{ formatNumber(totalDue) }}</div>
          <p class="card-sub">Total outstanding fees</p>
        </div>

        <div class="summary-card">
          <h3>Exam Results</h3>
          <div class="big-value">{{ examResults.length }}</div>
          <p class="card-sub">Published results on record</p>
        </div>
      </div>

      <section v-if="examResults.length" class="panel">
        <h3>Recent Exam Results</h3>
        <div class="results-list">
          <div v-for="result in examResults.slice(0, 8)" :key="result.id" class="result-item">
            <strong>{{ result.exam?.name || result.subject?.name || 'Exam' }}</strong>
            <span>{{ result.subject?.name || '' }}</span>
            <span class="result-mark">{{ result.marks_obtained ?? result.total_marks ?? '—' }}</span>
          </div>
        </div>
      </section>

      <section v-if="student.enrollments?.length" class="panel">
        <h3>Enrollments</h3>
        <div class="enrollment-grid">
          <div v-for="enr in student.enrollments" :key="enr.id" class="enrollment-card">
            <strong>{{ enr.batch?.course?.name || 'Course' }}</strong>
            <span>{{ enr.batch?.name }}</span>
            <span class="status-pill">{{ enr.status }}</span>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import guardianPortalService from '@/services/guardian-portal.service'

export default {
  name: 'GuardianChildDetailPage',
  setup() {
    const route = useRoute()
    const loading = ref(false)
    const error = ref(null)
    const student = ref(null)
    const attendance = ref(null)
    const feeData = ref(null)

    const fullName = (s) => `${s.first_name || ''} ${s.last_name || ''}`.trim()
    const examResults = computed(() => student.value?.exam_results || student.value?.examResults || [])

    const totalDue = computed(() => Number(feeData.value?.overall?.total_due || 0))

    const loadData = async () => {
      loading.value = true
      error.value = null
      try {
        const id = route.params.id
        const [childRes, attRes, feeRes] = await Promise.all([
          guardianPortalService.child(id),
          guardianPortalService.attendanceSummary(id).catch(() => null),
          guardianPortalService.feeSummary(id).catch(() => null),
        ])
        student.value = childRes.data?.data || null
        attendance.value = attRes?.data?.data || null
        feeData.value = feeRes?.data?.data || null
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load ward details.'
      } finally {
        loading.value = false
      }
    }

    const formatNumber = (n) => Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })

    onMounted(loadData)
    return { loading, error, student, attendance, feeData, examResults, totalDue, fullName, formatNumber }
  },
}
</script>

<style scoped>
.guardian-page { max-width: 1100px; margin: 0 auto; }
.page-header { margin-bottom: 1.5rem; }
.back-link { display: inline-block; margin-bottom: 0.75rem; color: #0891b2; font-weight: 700; text-decoration: none; font-size: 0.85rem; }
.page-header h1 { margin: 0 0 0.25rem; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.page-header p { margin: 0; color: var(--text-dark); font-weight: 600; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.summary-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; }
.summary-card h3 { margin: 0 0 0.75rem; font-size: 0.85rem; font-weight: 800; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.4px; }
.big-value { font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
.card-sub { margin: 0.5rem 0 0; font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.mini-stats { display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.due-text { color: #dc2626 !important; }
.panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.25rem; }
.panel h3 { margin: 0 0 1rem; font-size: 0.95rem; font-weight: 800; color: var(--text-primary); }
.results-list { display: flex; flex-direction: column; gap: 0.6rem; }
.result-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem; border: 1px solid var(--border-light); border-radius: 10px; background: var(--bg-surface-muted); }
.result-item strong { flex: 1; color: var(--text-primary); font-weight: 700; font-size: 0.9rem; }
.result-item span { font-size: 0.75rem; color: var(--text-dark); font-weight: 600; }
.result-mark { font-weight: 800 !important; color: #0891b2 !important; font-size: 0.9rem !important; }
.enrollment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; }
.enrollment-card { padding: 1rem; border: 1px solid var(--border-light); border-radius: 12px; background: var(--bg-surface-muted); display: flex; flex-direction: column; gap: 0.25rem; }
.enrollment-card strong { color: var(--text-primary); font-weight: 700; }
.enrollment-card span { font-size: 0.8rem; color: var(--text-dark); font-weight: 600; }
.status-pill { align-self: flex-start; background: #cffafe; color: #0e7490; padding: 2px 8px; border-radius: 999px; font-size: 0.65rem !important; font-weight: 800 !important; text-transform: uppercase; }
.state-card { text-align: center; padding: 2.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; }
</style>
