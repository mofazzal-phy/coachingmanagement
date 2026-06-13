<template>
  <div class="guardian-dashboard">
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-content">
        <div class="hero-profile">
          <div class="avatar-wrap">
            <img :src="avatarUrl" :alt="displayName" class="avatar" />
            <span class="status-dot"></span>
          </div>
          <div class="hero-text">
            <p class="hero-greeting">{{ greeting }}</p>
            <h1 class="hero-name">{{ displayName }}</h1>
            <div class="hero-meta">
              <span class="meta-chip">
                <span class="chip-label">Role</span>
                Guardian
              </span>
              <span class="meta-chip date-chip">{{ currentDate }}</span>
            </div>
          </div>
        </div>
        <button class="refresh-btn" @click="loadDashboard" :disabled="loading">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ spinning: loading }">
            <path d="M23 4v6h-6M1 20v-6h6"/>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
          </svg>
          {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </section>

    <div v-if="loading && !loaded" class="state-card">
      <ProgressSpinner strokeWidth="3" />
      <p>Loading your dashboard...</p>
    </div>

    <div v-else-if="error" class="state-card">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Try Again" icon="pi pi-refresh" @click="loadDashboard" class="p-button-outlined mt-3" />
    </div>

    <template v-else>
      <section class="metrics-grid">
        <div class="metric-card metric-cyan">
          <div class="metric-icon">👨‍🎓</div>
          <div class="metric-body">
            <span class="metric-label">My Children</span>
            <span class="metric-value">{{ children.length }}</span>
            <span class="metric-sub">Linked ward{{ children.length !== 1 ? 's' : '' }}</span>
          </div>
        </div>

        <div v-if="canView('view attendance')" class="metric-card metric-indigo">
          <div class="metric-icon">✓</div>
          <div class="metric-body">
            <span class="metric-label">Avg Attendance</span>
            <span class="metric-value">{{ avgAttendance }}%</span>
            <span class="metric-sub">Across all wards</span>
          </div>
        </div>

        <div v-if="canView('view exams')" class="metric-card metric-amber">
          <div class="metric-icon">📝</div>
          <div class="metric-body">
            <span class="metric-label">Upcoming Exams</span>
            <span class="metric-value">{{ upcomingExams.length }}</span>
            <span v-if="nextExam" class="metric-sub">Next: {{ formatDate(nextExam.start_date) }}</span>
          </div>
        </div>

        <div v-if="canView('view fee collections')" class="metric-card" :class="totalDue > 0 ? 'metric-danger' : 'metric-green'">
          <div class="metric-icon">💰</div>
          <div class="metric-body">
            <span class="metric-label">Total Due</span>
            <span class="metric-value">৳{{ formatNumber(totalDue) }}</span>
            <span class="metric-sub">{{ overdueCount }} overdue item{{ overdueCount !== 1 ? 's' : '' }}</span>
          </div>
        </div>
      </section>

      <PortalCmsSummary portal="guardian" />

      <section class="quick-section">
        <h2 class="section-title">Quick Actions</h2>
        <div class="quick-grid">
          <router-link
            v-for="action in visibleQuickActions"
            :key="action.to"
            :to="action.to"
            class="quick-card"
            :style="{ '--accent': action.color }"
          >
            <span class="quick-icon">{{ action.icon }}</span>
            <span class="quick-label">{{ action.label }}</span>
          </router-link>
        </div>
      </section>

      <div class="main-grid">
        <div class="main-col">
          <section class="panel">
            <div class="panel-header">
              <h3>My Children</h3>
              <router-link to="/guardian/children" class="panel-link">View All</router-link>
            </div>
            <div v-if="children.length === 0" class="panel-empty">
              <p>No linked children found. Please ensure your phone or email matches the guardian record.</p>
            </div>
            <div v-else class="children-grid">
              <router-link
                v-for="child in children"
                :key="child.id"
                :to="`/guardian/children/${child.id}`"
                class="child-card"
              >
                <div class="child-avatar">{{ childInitials(child) }}</div>
                <div class="child-info">
                  <strong>{{ childName(child) }}</strong>
                  <span>{{ child.student_id || 'Student' }}</span>
                  <span v-if="child.current_class?.name">{{ child.current_class.name }}</span>
                </div>
                <div class="child-stats" v-if="childSummaries[child.id]">
                  <span v-if="canView('view attendance')">{{ childSummaries[child.id].attendance || 0 }}% att.</span>
                  <span v-if="canView('view fee collections')" :class="{ 'due-text': childSummaries[child.id].due > 0 }">
                    ৳{{ formatNumber(childSummaries[child.id].due) }} due
                  </span>
                </div>
              </router-link>
            </div>
          </section>

          <section v-if="canView('view exams')" class="panel">
            <div class="panel-header">
              <h3>Upcoming Exams</h3>
            </div>
            <div v-if="upcomingExams.length === 0" class="panel-empty">
              <p>No upcoming exams at the moment.</p>
            </div>
            <div v-else class="exam-list">
              <div v-for="exam in upcomingExams.slice(0, 5)" :key="exam.id" class="exam-item">
                <div class="exam-icon-wrap">📝</div>
                <div class="exam-info">
                  <strong>{{ exam.name }}</strong>
                  <span>{{ exam.exam_type?.name || 'Exam' }}</span>
                </div>
                <strong class="exam-date">{{ formatDate(exam.start_date) }}</strong>
              </div>
            </div>
          </section>
        </div>

        <div class="side-col">
          <section v-if="canView('view notice board')" class="panel">
            <div class="panel-header">
              <h3>Recent Notices</h3>
              <router-link to="/guardian/notices" class="panel-link">View All</router-link>
            </div>
            <div v-if="notices.length === 0" class="panel-empty">
              <p>No notices available.</p>
            </div>
            <div v-else class="notice-list">
              <div v-for="notice in notices.slice(0, 5)" :key="notice.id" class="notice-item">
                <span class="priority-badge" :class="'priority-' + (notice.priority || 'normal').toLowerCase()">
                  {{ notice.priority || 'Normal' }}
                </span>
                <strong>{{ notice.title }}</strong>
                <span class="notice-date">{{ formatDate(notice.publish_date) }}</span>
              </div>
            </div>
          </section>

          <section v-if="canView('view fee collections')" class="panel">
            <div class="panel-header">
              <h3>Fee Overview</h3>
            </div>
            <div class="fee-overview">
              <div class="fee-row">
                <span>Total Due</span>
                <strong :class="{ 'due-text': totalDue > 0 }">৳{{ formatNumber(totalDue) }}</strong>
              </div>
              <div class="fee-row">
                <span>Overdue Items</span>
                <strong :class="{ 'due-text': overdueCount > 0 }">{{ overdueCount }}</strong>
              </div>
              <router-link to="/guardian/children" class="view-fees-btn">View Ward Fees →</router-link>
            </div>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import guardianPortalService from '@/services/guardian-portal.service'
import PortalCmsSummary from '@/components/dashboard/PortalCmsSummary.vue'

const GUARDIAN_DEFAULT_PERMISSIONS = [
  'view students', 'view attendance',
  'view exams', 'view exam results',
  'view fee collections', 'view notice board',
  'view events', 'view gallery',
]

export default {
  name: 'GuardianDashboardPage',
  components: { PortalCmsSummary },
  setup() {
    const authStore = useAuthStore()
    const loading = ref(false)
    const loaded = ref(false)
    const error = ref(null)
    const currentDate = ref('')
    const children = ref([])
    const childSummaries = ref({})
    const upcomingExams = ref([])
    const notices = ref([])
    const totalDue = ref(0)
    const overdueCount = ref(0)

    const canView = (permission) => {
      if (!permission) return true
      if (authStore.hasPermission(permission)) return true
      return GUARDIAN_DEFAULT_PERMISSIONS.includes(permission)
    }

    const displayName = computed(() => authStore.user?.name || 'Guardian')
    const avatarUrl = computed(() => {
      const name = encodeURIComponent(displayName.value)
      return authStore.user?.avatar || `https://ui-avatars.com/api/?name=${name}&background=0891b2&color=fff&size=128`
    })

    const greeting = computed(() => {
      const hour = new Date().getHours()
      if (hour < 12) return 'Good Morning'
      if (hour < 17) return 'Good Afternoon'
      return 'Good Evening'
    })

    const avgAttendance = computed(() => {
      const vals = Object.values(childSummaries.value).map(s => s.attendance).filter(v => v != null)
      if (!vals.length) return 0
      return Math.round(vals.reduce((a, b) => a + b, 0) / vals.length)
    })

    const nextExam = computed(() => upcomingExams.value[0] || null)

    const quickActions = [
      { to: '/guardian/children', icon: '👨‍🎓', label: 'My Children', permission: 'view students', color: '#0891b2' },
      { to: '/guardian/children', icon: '✓', label: 'Attendance', permission: 'view attendance', color: '#4f46e5' },
      { to: '/guardian/children', icon: '📊', label: 'Exam Results', permission: 'view exam results', color: '#d97706' },
      { to: '/guardian/children', icon: '💰', label: 'Fee Status', permission: 'view fee collections', color: '#059669' },
      { to: '/guardian/notices', icon: '📢', label: 'Notices', permission: 'view notice board', color: '#db2777' },
    ]

    const visibleQuickActions = computed(() => quickActions.filter(a => canView(a.permission)))

    const childName = (child) => `${child.first_name || ''} ${child.last_name || ''}`.trim() || 'Student'
    const childInitials = (child) => {
      const f = child.first_name?.[0] || ''
      const l = child.last_name?.[0] || ''
      return (f + l).toUpperCase() || 'S'
    }

    const loadChildSummaries = async (list) => {
      const summaries = {}
      let dueTotal = 0
      let overdue = 0

      await Promise.all(list.map(async (child) => {
        const summary = { attendance: 0, due: 0 }

        if (canView('view attendance')) {
          try {
            const attRes = await guardianPortalService.attendanceSummary(child.id)
            summary.attendance = attRes.data?.data?.percentage ?? attRes.data?.percentage ?? 0
          } catch { /* ignore */ }
        }

        if (canView('view fee collections')) {
          try {
            const feeRes = await guardianPortalService.feeSummary(child.id)
            const data = feeRes.data?.data || {}
            const due = Number(data.overall?.total_due || 0)
            summary.due = due
            dueTotal += due
            const items = data.enrollments || []
            items.forEach((e) => {
              if (e.payment_status === 'overdue' || e.next_pending_month?.status === 'overdue') overdue += 1
            })
          } catch { /* ignore */ }
        }

        summaries[child.id] = summary
      }))

      childSummaries.value = summaries
      totalDue.value = dueTotal
      overdueCount.value = overdue
    }

    const loadDashboard = async () => {
      loading.value = true
      error.value = null

      try {
        const wards = await guardianPortalService.children(authStore.user)
        children.value = wards

        const tasks = []
        if (canView('view exams')) {
          tasks.push(
            guardianPortalService.upcomingExams()
              .then((res) => {
                const list = res.data?.data || []
                upcomingExams.value = list.filter((e) => {
                  if (!e.start_date) return true
                  return new Date(e.start_date) >= new Date()
                })
              })
              .catch(() => { upcomingExams.value = [] })
          )
        }
        if (canView('view notice board')) {
          tasks.push(
            guardianPortalService.notices()
              .then((res) => { notices.value = res.data?.data || [] })
              .catch(() => { notices.value = [] })
          )
        }

        await Promise.all(tasks)
        await loadChildSummaries(wards)
        loaded.value = true
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load dashboard.'
      } finally {
        loading.value = false
      }
    }

    const formatNumber = (n) => Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })
    const formatDate = (d) => {
      if (!d) return '—'
      return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    }

    onMounted(() => {
      currentDate.value = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      })
      loadDashboard()
    })

    return {
      loading, loaded, error, currentDate, displayName, avatarUrl, greeting,
      children, childSummaries, upcomingExams, notices, totalDue, overdueCount,
      avgAttendance, nextExam, visibleQuickActions, canView,
      loadDashboard, formatNumber, formatDate, childName, childInitials,
    }
  },
}
</script>

<style scoped>
.guardian-dashboard { max-width: 1280px; margin: 0 auto; padding-bottom: 2rem; }

.hero { position: relative; border-radius: 20px; overflow: hidden; margin-bottom: 1.5rem; border: 1px solid #a5f3fc; }
.hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #0891b2 0%, #0e7490 45%, #155e75 100%); }
.hero-content { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 2rem; flex-wrap: wrap; }
.hero-profile { display: flex; align-items: center; gap: 1.25rem; }
.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar { width: 72px; height: 72px; border-radius: 18px; object-fit: cover; border: 3px solid rgba(255,255,255,0.35); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
.status-dot { position: absolute; bottom: 2px; right: 2px; width: 14px; height: 14px; background: #22c55e; border: 2px solid #fff; border-radius: 50%; }
.hero-greeting { margin: 0 0 0.25rem; font-size: 0.85rem; color: #ffffff; font-weight: 600; }
.hero-name { margin: 0 0 0.75rem; font-size: 1.75rem; font-weight: 800; color: #ffffff; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.meta-chip { display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
.chip-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.refresh-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.1rem; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; border-radius: 12px; font-size: 0.85rem; font-weight: 700; cursor: pointer; }
.refresh-btn svg.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.metrics-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.metric-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.1rem; display: flex; gap: 0.85rem; box-shadow: var(--shadow-sm); }
.metric-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.metric-cyan .metric-icon { background: #cffafe; }
.metric-indigo .metric-icon { background: #eef2ff; }
.metric-amber .metric-icon { background: #fef3c7; }
.metric-green .metric-icon { background: #d1fae5; }
.metric-danger .metric-icon { background: #fee2e2; }
.metric-label { font-size: 0.75rem; color: var(--text-primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
.metric-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; margin: 0.15rem 0; }
.metric-sub { font-size: 0.75rem; color: var(--text-dark); font-weight: 600; }

.quick-section { margin-bottom: 1.5rem; }
.section-title { font-size: 1rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.85rem; }
.quick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem; }
.quick-card { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 1rem 0.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; text-decoration: none; transition: all 0.2s; }
.quick-card:hover { border-color: var(--accent); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(15,23,42,0.1); }
.quick-icon { font-size: 1.5rem; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 12%, white); border-radius: 12px; }
.quick-label { font-size: 0.8rem; font-weight: 800; color: var(--text-primary); text-align: center; }

.main-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
.main-col, .side-col { display: flex; flex-direction: column; gap: 1.25rem; }
.panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-sm); }
.panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.panel-header h3 { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text-primary); }
.panel-link { font-size: 0.8rem; color: #0891b2; font-weight: 700; text-decoration: none; }
.panel-empty { text-align: center; padding: 1.5rem; color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }

.children-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.75rem; }
.child-card { display: flex; align-items: center; gap: 0.85rem; padding: 1rem; border: 1px solid var(--border-light); border-radius: 14px; background: var(--bg-surface-muted); text-decoration: none; transition: all 0.2s; }
.child-card:hover { border-color: #0891b2; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(8,145,178,0.12); }
.child-avatar { width: 48px; height: 48px; border-radius: 12px; background: #cffafe; color: #0e7490; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }
.child-info { flex: 1; display: flex; flex-direction: column; gap: 0.15rem; }
.child-info strong { font-size: 0.9rem; color: var(--text-primary); font-weight: 700; }
.child-info span { font-size: 0.75rem; color: var(--text-dark); font-weight: 600; }
.child-stats { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.72rem; font-weight: 700; color: var(--text-dark); text-align: right; }
.due-text { color: #dc2626 !important; }

.exam-list, .notice-list { display: flex; flex-direction: column; gap: 0.6rem; }
.exam-item { display: flex; align-items: center; gap: 0.85rem; padding: 0.85rem; border: 1px solid var(--border-light); border-radius: 12px; background: var(--bg-surface-muted); }
.exam-icon-wrap { width: 40px; height: 40px; background: #fef3c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.exam-info { flex: 1; display: flex; flex-direction: column; gap: 0.15rem; }
.exam-info strong { font-size: 0.9rem; color: var(--text-primary); font-weight: 700; }
.exam-info span { font-size: 0.75rem; color: var(--text-dark); font-weight: 600; }
.exam-date { font-size: 0.8rem; color: #0891b2; font-weight: 700; }

.notice-item { display: flex; flex-direction: column; gap: 0.3rem; padding: 0.85rem; border: 1px solid var(--border-light); border-radius: 10px; background: var(--bg-surface-muted); }
.notice-item strong { font-size: 0.85rem; color: var(--text-primary); font-weight: 700; }
.notice-date { font-size: 0.72rem; color: var(--text-dark); font-weight: 600; }
.priority-badge { align-self: flex-start; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; }
.priority-high, .priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-medium { background: #fef3c7; color: #d97706; }
.priority-normal, .priority-low { background: #cffafe; color: #0e7490; }

.fee-overview { display: flex; flex-direction: column; gap: 0.75rem; }
.fee-row { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-dark); font-weight: 600; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light); }
.fee-row strong { font-size: 1rem; color: var(--text-primary); font-weight: 800; }
.view-fees-btn { display: block; text-align: center; padding: 0.75rem; background: #0891b2; color: #fff; border-radius: 10px; font-size: 0.85rem; font-weight: 700; text-decoration: none; margin-top: 0.25rem; }

.state-card { text-align: center; padding: 3rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; }

@media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .hero-content { padding: 1.25rem; } .hero-name { font-size: 1.35rem; } .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
