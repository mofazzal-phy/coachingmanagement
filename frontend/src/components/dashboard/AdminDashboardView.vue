<template>
  <div class="admin-dashboard">
    <!-- Hero -->
    <section class="hero">
      <div class="hero-content">
        <p class="hero-eyebrow">Coaching Management Overview</p>
        <h1>Welcome back, {{ userName }} 👋</h1>
        <p class="hero-date">{{ currentDate }}</p>
        <p v-if="summaryLine" class="hero-summary">{{ summaryLine }}</p>
      </div>
      <div class="hero-side">
        <span class="role-pill">Administrator</span>
        <button class="refresh-btn" :disabled="loading" @click="loadData">
          <span :class="{ spin: loading }">🔄</span>
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
        <span v-if="lastUpdated" class="updated-at">Updated {{ lastUpdated }}</span>
      </div>
    </section>

    <!-- KPI Cards -->
    <div class="kpi-grid">
      <DashboardStatCard
        v-if="can('view users')"
        label="Total Users"
        :value="data.userStats?.total_users ?? 0"
        icon="👥"
        accent-color="#4f46e5"
        icon-bg="#eef2ff"
        :loading="loading"
        :subtext="`${data.userStats?.active_users ?? 0} active`"
      />
      <DashboardStatCard
        v-if="can('view students')"
        label="Students"
        :value="data.overview?.total_students ?? data.userStats?.total_students ?? 0"
        icon="🎓"
        accent-color="#2563eb"
        icon-bg="#dbeafe"
        :loading="loading"
        :subtext="`${data.overview?.active_enrollments ?? 0} enrolled`"
      />
      <DashboardStatCard
        v-if="can('view employees') || can('view teachers')"
        label="Staff & Teachers"
        :value="(data.userStats?.total_employees ?? 0) + (data.userStats?.total_teachers ?? 0)"
        icon="👔"
        accent-color="#7c3aed"
        icon-bg="#ede9fe"
        :loading="loading"
        :subtext="`${data.userStats?.total_teachers ?? 0} teachers`"
      />
      <DashboardStatCard
        v-if="can('view reports') || can('view fee collections')"
        label="Total Revenue"
        :value="data.overview?.total_revenue ?? data.feeDashboard?.collection?.total ?? 0"
        icon="💰"
        format="currency"
        accent-color="#d97706"
        icon-bg="#fef3c7"
        :loading="loading"
        :subtext="`This month ৳${formatNum(data.feeDashboard?.collection?.this_month)}`"
      />
      <DashboardStatCard
        v-if="can('view fee collections')"
        label="Today's Collection"
        :value="data.feeDashboard?.collection?.today ?? 0"
        icon="📥"
        format="currency"
        accent-color="#059669"
        icon-bg="#d1fae5"
        :loading="loading"
        badge="Live"
        badge-tone="success"
      />
      <DashboardStatCard
        v-if="can('view fee collections')"
        label="Pending Payments"
        :value="data.feeDashboard?.pending_confirmation?.count ?? 0"
        icon="⏳"
        accent-color="#dc2626"
        icon-bg="#fee2e2"
        :loading="loading"
        :subtext="`৳${formatNum(data.feeDashboard?.pending_confirmation?.amount)} awaiting`"
        :badge="(data.feeDashboard?.overdue?.count ?? 0) > 0 ? `${data.feeDashboard.overdue.count} overdue` : ''"
        badge-tone="danger"
      />
      <DashboardStatCard
        v-if="can('view attendance') || can('view student attendance')"
        label="Student Attendance"
        :value="data.attendanceToday?.students?.percentage ?? 0"
        icon="✅"
        format="percent"
        accent-color="#0891b2"
        icon-bg="#cffafe"
        :loading="loading"
        :subtext="`${data.attendanceToday?.students?.present ?? 0} present today`"
      />
      <DashboardStatCard
        v-if="can('view courses') || can('view batches')"
        label="Courses & Batches"
        :value="(data.courseStats?.active_courses ?? data.courseStats?.total_courses ?? 0)"
        icon="📚"
        accent-color="#db2777"
        icon-bg="#fce7f3"
        :loading="loading"
        :subtext="`${data.batchStats?.open_batches ?? data.batchStats?.total_batches ?? 0} open batches`"
      />
    </div>

    <PortalCmsSummary portal="admin" />

    <!-- Charts Row -->
    <div class="charts-grid">
      <DashboardPanel
        v-if="can('view reports')"
        title="Revenue & Enrollment Trend"
        subtitle="Last 6 months performance"
        icon="📈"
        :loading="loading"
      >
        <DashboardModernChart
          type="line"
          :data="revenueLineData"
          :options="revenueLineOptions"
          :empty="!revenueTrendItems.length"
          empty-text="No revenue trend data"
          :height="260"
        />
      </DashboardPanel>

      <DashboardPanel
        v-if="can('view attendance') || can('view student attendance')"
        title="Monthly Attendance"
        subtitle="Student attendance rate"
        icon="📊"
        :loading="loading"
      >
        <DashboardModernChart
          type="polarArea"
          :data="attendancePolarData"
          :empty="!attendanceTrendItems.length"
          empty-text="No attendance trend data"
          :height="260"
        />
      </DashboardPanel>

      <DashboardPanel
        v-if="can('view attendance') || can('view student attendance')"
        title="Today's Attendance"
        subtitle="Student breakdown"
        icon="🍩"
        :loading="loading"
      >
        <div class="donut-embed">
          <DoughnutChart :data="attendanceDonut" :date="todayShort" />
        </div>
      </DashboardPanel>

      <DashboardPanel
        v-if="can('view reports')"
        title="Batch Occupancy"
        subtitle="Top batches by fill rate"
        icon="🏫"
        :loading="loading"
      >
        <div v-if="!topOccupancy.length" class="inline-empty">No occupancy data</div>
        <div v-else class="occ-list">
          <div v-for="o in topOccupancy" :key="o.id" class="occ-row">
            <div class="occ-meta">
              <strong>{{ o.name }}</strong>
              <small>{{ o.course }}</small>
            </div>
            <div class="occ-bar-track">
              <div
                class="occ-bar-fill"
                :class="occClass(o.occupancy)"
                :style="{ width: Math.min(o.occupancy, 100) + '%' }"
              ></div>
            </div>
            <span class="occ-pct">{{ o.occupancy }}%</span>
          </div>
        </div>
      </DashboardPanel>

      <DashboardPanel
        v-if="can('view reports')"
        title="Enrollment by Mode"
        subtitle="Online vs offline distribution"
        icon="🌐"
        :loading="loading"
      >
        <DashboardModernChart
          type="pie"
          :data="modePieData"
          :empty="!modeItems.length"
          empty-text="No mode data"
          :height="260"
        />
      </DashboardPanel>
    </div>

    <!-- Modules + Activity -->
    <div class="bottom-grid">
      <DashboardPanel title="System Modules" subtitle="Quick snapshot across the platform" icon="🧩" :loading="loading">
        <div class="module-grid">
          <router-link
            v-for="mod in moduleCards"
            :key="mod.label"
            :to="mod.to"
            class="module-card"
            :style="{ '--mod-color': mod.color }"
          >
            <span class="mod-icon">{{ mod.icon }}</span>
            <div class="mod-info">
              <strong>{{ mod.label }}</strong>
              <span>{{ mod.value }}</span>
            </div>
            <span class="mod-arrow">→</span>
          </router-link>
        </div>
      </DashboardPanel>

      <DashboardPanel
        v-if="can('view attendance')"
        title="Recent Activity"
        subtitle="Live attendance scans"
        icon="🕐"
        :loading="loading"
      >
        <template #actions>
          <span class="live-pill">LIVE</span>
        </template>
        <div v-if="!activityFeed.length" class="inline-empty">No recent activity</div>
        <div v-else class="feed-list">
          <div v-for="(item, i) in activityFeed" :key="i" class="feed-item">
            <span class="feed-dot" :class="'st-' + (item.status || 'present')"></span>
            <div class="feed-body">
              <strong>{{ item.name }}</strong>
              <small>{{ item.detail || item.type }} · {{ item.time }}</small>
            </div>
            <span class="feed-status">{{ item.status }}</span>
          </div>
        </div>
      </DashboardPanel>

      <DashboardPanel title="Alerts & Notifications" icon="⚠️" :loading="loading">
        <div class="alerts-wrap">
          <div v-if="can('view attendance') && lowAlerts.length" class="alert-block">
            <h4>Low Attendance</h4>
            <div v-for="(a, i) in lowAlerts" :key="'low-' + i" class="alert-row">
              <span>{{ a.student_name || a.name }}</span>
              <strong class="text-danger">{{ a.percentage }}%</strong>
            </div>
          </div>
          <div v-if="can('view fee collections') && (data.feeDashboard?.overdue?.count > 0)" class="alert-block">
            <h4>Overdue Fees</h4>
            <p class="alert-highlight">{{ data.feeDashboard.overdue.count }} assignments · ৳{{ formatNum(data.feeDashboard.overdue.amount) }}</p>
          </div>
          <div v-if="unreadCount > 0" class="alert-block">
            <h4>Unread Messages</h4>
            <p class="alert-highlight">{{ unreadCount }} unread notifications</p>
          </div>
          <div v-if="!hasAlerts" class="inline-empty success">✅ All clear — no urgent alerts</div>
        </div>
      </DashboardPanel>
    </div>

    <!-- Quick Actions -->
    <section class="quick-section">
      <h2>Quick Actions</h2>
      <div class="actions-grid">
        <router-link v-for="a in quickActions" :key="a.to" :to="a.to" class="action-tile">
          <span class="action-icon">{{ a.icon }}</span>
          <span class="action-label">{{ a.label }}</span>
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import dashboardService from '@/services/dashboard.service'
import DashboardStatCard from './DashboardStatCard.vue'
import DashboardPanel from './DashboardPanel.vue'
import DashboardModernChart from './DashboardModernChart.vue'
import PortalCmsSummary from './PortalCmsSummary.vue'
import DoughnutChart from '@/components/attendance/DoughnutChart.vue'
import { nowTime12 } from '@/utils/datetime'

const authStore = useAuthStore()
const userName = computed(() => authStore.user?.name || 'Admin')
const can = (p) => authStore.hasPermission(p)

const loading = ref(true)
const lastUpdated = ref('')
const currentDate = ref('')
const data = ref({})

const summaryLine = computed(() => {
  const parts = []
  if (data.value.overview?.active_enrollments != null) {
    parts.push(`${data.value.overview.active_enrollments} active enrollments`)
  }
  if (data.value.attendanceToday?.students?.percentage != null) {
    parts.push(`${data.value.attendanceToday.students.percentage}% attendance today`)
  }
  if (data.value.feeDashboard?.collection?.today > 0) {
    parts.push(`৳${formatNum(data.value.feeDashboard.collection.today)} collected today`)
  }
  return parts.join(' · ')
})

const revenueTrendItems = computed(() => {
  const trend = data.value.revenueTrend || []
  return trend.map((t) => ({
    label: (t.month || '').slice(0, 3),
    value: t.revenue || 0,
    value2: t.enrollments || 0,
  }))
})

const attendanceTrendItems = computed(() => {
  const trend = data.value.attendanceTrend || []
  return trend.map((t) => ({
    label: t.month_short || (t.month || '').slice(0, 3),
    value: t.percentage || 0,
    colorClass: 'bar-attendance',
  }))
})

const revenueLineData = computed(() => {
  const items = revenueTrendItems.value
  if (!items.length) return null
  return {
    labels: items.map((i) => i.label),
    datasets: [
      {
        label: 'Revenue (৳)',
        data: items.map((i) => i.value),
        borderColor: '#818cf8',
        backgroundColor: 'rgba(99, 102, 241, 0.18)',
        fill: true,
        tension: 0.42,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointBackgroundColor: '#6366f1',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      },
      {
        label: 'Enrollments',
        data: items.map((i) => i.value2),
        borderColor: '#34d399',
        backgroundColor: 'rgba(16, 185, 129, 0.08)',
        fill: true,
        tension: 0.42,
        pointRadius: 4,
        pointBackgroundColor: '#10b981',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        yAxisID: 'y1',
      },
    ],
  }
})

const revenueLineOptions = {
  interaction: { mode: 'index', intersect: false },
  scales: {
    x: { grid: { display: false } },
    y: {
      type: 'linear',
      position: 'left',
      beginAtZero: true,
      title: { display: true, text: 'Revenue', font: { size: 10 } },
    },
    y1: {
      type: 'linear',
      position: 'right',
      beginAtZero: true,
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Enrollments', font: { size: 10 } },
    },
  },
}

const attendancePolarData = computed(() => {
  const items = attendanceTrendItems.value
  if (!items.length) return null
  const palette = ['#22d3ee', '#a78bfa', '#fbbf24', '#34d399', '#f472b6', '#60a5fa']
  return {
    labels: items.map((i) => i.label),
    datasets: [{
      data: items.map((i) => i.value),
      backgroundColor: palette.map((c) => c + '55'),
      borderColor: palette,
      borderWidth: 2,
    }],
  }
})

const modePieData = computed(() => {
  const items = modeItems.value
  if (!items.length) return null
  return {
    labels: items.map((m) => m.label),
    datasets: [{
      data: items.map((m) => m.count),
      backgroundColor: items.map((m) => m.color + 'cc'),
      borderColor: items.map((m) => m.color),
      borderWidth: 2,
      hoverOffset: 8,
    }],
  }
})

const attendanceDonut = computed(() => {
  const s = data.value.attendanceToday?.students || {}
  return {
    present: s.present || 0,
    late: s.late || 0,
    absent: s.absent || 0,
    leave: 0,
  }
})

const todayShort = computed(() => {
  return new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
})

const topOccupancy = computed(() => {
  const list = [...(data.value.occupancy || [])]
  return list.sort((a, b) => (b.occupancy || 0) - (a.occupancy || 0)).slice(0, 6)
})

const modeItems = computed(() => {
  const raw = data.value.modeWise
  if (!raw) return []
  const list = Array.isArray(raw) ? raw : Object.entries(raw).map(([k, v]) => ({ mode: k, ...v }))
  const total = list.reduce((s, i) => s + (i.count || i.total || i.enrollments || 0), 0) || 1
  const colors = ['#4f46e5', '#10b981', '#f59e0b', '#ec4899', '#06b6d4']
  return list.map((item, idx) => {
    const count = item.count || item.total || item.enrollments || 0
    return {
      label: item.mode || item.name || 'Unknown',
      count,
      percent: Math.round((count / total) * 100),
      color: colors[idx % colors.length],
    }
  })
})

const activityFeed = computed(() => {
  const feed = data.value.realtime
  return Array.isArray(feed) ? feed.slice(0, 8) : []
})

const lowAlerts = computed(() => {
  const alerts = data.value.lowAlerts
  return Array.isArray(alerts) ? alerts.slice(0, 4) : []
})

const unreadCount = computed(() => {
  const u = data.value.unread
  return u?.count ?? u?.unread ?? (typeof u === 'number' ? u : 0)
})

const hasAlerts = computed(() => {
  return lowAlerts.value.length > 0
    || (data.value.feeDashboard?.overdue?.count > 0)
    || unreadCount.value > 0
})

const examTotal = computed(() => data.value.exams?.meta?.total ?? data.value.exams?.total ?? 0)

const moduleCards = computed(() => {
  const cards = []
  if (can('view students')) {
    cards.push({ label: 'Students', value: `${data.value.overview?.total_students ?? 0} total`, icon: '🎓', to: '/dashboard/students', color: '#2563eb' })
  }
  if (can('view courses')) {
    cards.push({ label: 'Courses', value: `${data.value.courseStats?.active_courses ?? data.value.courseStats?.total_courses ?? 0} active`, icon: '📚', to: '/dashboard/enrollment/courses', color: '#7c3aed' })
  }
  if (can('view enrollments') || can('view students')) {
    cards.push({ label: 'Enrollments', value: `${data.value.overview?.active_enrollments ?? 0} active`, icon: '📝', to: '/dashboard/enrollment/enrollments', color: '#059669' })
  }
  if (can('view fee collections')) {
    cards.push({ label: 'Finance', value: `৳${formatNum(data.value.feeDashboard?.collection?.this_month)} this month`, icon: '💰', to: '/dashboard/finance/collections', color: '#d97706' })
  }
  if (can('view attendance')) {
    cards.push({ label: 'Attendance', value: `${data.value.attendanceToday?.students?.percentage ?? 0}% today`, icon: '✅', to: '/dashboard/attendance/dashboard', color: '#0891b2' })
  }
  if (can('view exams')) {
    cards.push({ label: 'Exams', value: `${examTotal.value} published`, icon: '📋', to: '/dashboard/exams', color: '#dc2626' })
  }
  return cards
})

const quickActions = computed(() => {
  const actions = []
  if (can('create students')) actions.push({ label: 'New Student', icon: '➕', to: '/dashboard/students/create' })
  if (can('create enrollments')) actions.push({ label: 'New Enrollment', icon: '📝', to: '/dashboard/enrollment/enrollments/create' })
  if (can('view fee collections')) actions.push({ label: 'Fee Collection', icon: '💰', to: '/dashboard/finance/collections' })
  if (can('view attendance')) actions.push({ label: 'Attendance', icon: '✓', to: '/dashboard/attendance/dashboard' })
  if (can('view reports')) actions.push({ label: 'Reports', icon: '📊', to: '/dashboard/reports' })
  if (can('view users')) actions.push({ label: 'Users', icon: '👥', to: '/dashboard/users' })
  if (can('view settings')) actions.push({ label: 'Settings', icon: '⚙️', to: '/dashboard/settings' })
  return actions
})

function formatNum(n) {
  return Number(n || 0).toLocaleString('en-BD')
}

function occClass(pct) {
  if (pct >= 90) return 'danger'
  if (pct >= 70) return 'warn'
  return 'ok'
}

async function loadData() {
  loading.value = true
  try {
    data.value = await dashboardService.fetchAdminData(can)
    lastUpdated.value = nowTime12()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  currentDate.value = new Date().toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  })
  loadData()
})
</script>

<style scoped>
.admin-dashboard {
  max-width: 1440px;
  margin: 0 auto;
}

.hero {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 1.5rem 1.65rem;
  border-radius: 18px;
  background: linear-gradient(135deg, #312e81 0%, #4f46e5 45%, #6366f1 100%);
  color: #fff;
  margin-bottom: 1.25rem;
  box-shadow: 0 12px 32px rgba(79, 70, 229, 0.28);
}

.hero-eyebrow {
  margin: 0 0 0.35rem;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.85;
}

.hero h1 {
  margin: 0;
  font-size: clamp(1.25rem, 2.5vw, 1.65rem);
  font-weight: 800;
}

.hero-date, .hero-summary {
  margin: 0.35rem 0 0;
  font-size: 0.85rem;
  opacity: 0.9;
}

.hero-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}

.role-pill {
  background: rgba(255,255,255,0.18);
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 600;
  backdrop-filter: blur(4px);
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: 1px solid rgba(255,255,255,0.35);
  border-radius: 8px;
  background: rgba(255,255,255,0.12);
  color: #fff;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,0.22); }
.refresh-btn:disabled { opacity: 0.65; cursor: not-allowed; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.updated-at { font-size: 0.68rem; opacity: 0.75; }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.bottom-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr 0.9fr;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.inline-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.inline-empty.success { color: #059669; }

.occ-list, .mode-list, .feed-list { display: flex; flex-direction: column; gap: 0.65rem; }

.occ-row, .mode-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.35rem 0.75rem;
  align-items: center;
}

.occ-meta strong, .mode-label { display: block; font-size: 0.82rem; color: var(--text-primary); }
.occ-meta small { font-size: 0.68rem; color: var(--text-muted); }

.occ-bar-track, .mode-bar-track {
  grid-column: 1 / -1;
  height: 7px;
  background: var(--bg-accent);
  border-radius: 999px;
  overflow: hidden;
}

.occ-bar-fill, .mode-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}

.occ-bar-fill.ok { background: linear-gradient(90deg, #34d399, #10b981); }
.occ-bar-fill.warn { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.occ-bar-fill.danger { background: linear-gradient(90deg, #f87171, #ef4444); }

.occ-pct, .mode-val {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.mode-row { grid-template-columns: 90px 1fr 36px; align-items: center; }
.mode-label { font-weight: 600; text-transform: capitalize; }

.module-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.65rem;
}

.module-card {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.85rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
  background: var(--bg-card);
}

.module-card:hover {
  border-color: var(--mod-color);
  box-shadow: 0 4px 16px rgba(15,23,42,0.08);
  transform: translateY(-1px);
}

.mod-icon { font-size: 1.35rem; }
.mod-info { flex: 1; min-width: 0; }
.mod-info strong { display: block; font-size: 0.82rem; color: var(--text-primary); }
.mod-info span { font-size: 0.72rem; color: var(--text-muted); }
.mod-arrow { color: var(--mod-color); font-weight: 700; }

.live-pill {
  font-size: 0.62rem;
  font-weight: 800;
  color: #dc2626;
  background: #fee2e2;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  letter-spacing: 0.06em;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.feed-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 0;
  border-bottom: 1px solid var(--border-light);
}

.feed-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  background: #94a3b8;
}

.feed-dot.st-present { background: #10b981; }
.feed-dot.st-late { background: #f59e0b; }
.feed-dot.st-absent { background: #ef4444; }

.feed-body { flex: 1; min-width: 0; }
.feed-body strong { display: block; font-size: 0.8rem; color: var(--text-primary); }
.feed-body small { font-size: 0.68rem; color: var(--text-muted); }

.feed-status {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: capitalize;
  color: var(--text-muted);
}

.alerts-wrap { display: flex; flex-direction: column; gap: 0.85rem; }
.alert-block h4 { margin: 0 0 0.4rem; font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.alert-row { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 0.3rem 0; }
.alert-highlight { margin: 0; font-size: 0.85rem; font-weight: 700; color: #b45309; }
.text-danger { color: #dc2626; }

.quick-section h2 {
  font-size: 1rem;
  margin: 0 0 0.75rem;
  color: var(--text-primary);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.65rem;
}

.action-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 1rem 0.75rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s;
}

.action-tile:hover {
  border-color: var(--primary-color);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
  background: var(--bg-card-hover);
}

.action-icon { font-size: 1.4rem; }
.action-label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-align: center; }

@media (max-width: 1200px) {
  .bottom-grid { grid-template-columns: 1fr 1fr; }
  .bottom-grid > :first-child { grid-column: 1 / -1; }
}

@media (max-width: 900px) {
  .charts-grid { grid-template-columns: 1fr; }
  .bottom-grid { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
  .hero { padding: 1.15rem; }
  .hero-side { align-items: flex-start; width: 100%; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .mode-row { grid-template-columns: 70px 1fr 30px; }
}

@media (max-width: 400px) {
  .kpi-grid { grid-template-columns: 1fr; }
}

.donut-embed :deep(.chart-card) {
  border: none;
  box-shadow: none;
  background: transparent;
}

.donut-embed :deep(.chart-header) {
  display: none;
}
</style>
