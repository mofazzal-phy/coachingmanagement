<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Attendance Dashboard</h1>
        <p class="header-subtitle">Live overview of today's attendance across all user types</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="refreshAll" :disabled="loading">🔄 {{ loading ? 'Refreshing...' : 'Refresh' }}</button>
        <span class="last-updated" v-if="lastUpdated">Updated: {{ lastUpdated }}</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading && !todayStats" class="loading-state"><div class="spinner"></div><p>Loading dashboard...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p></div>

    <template v-else>
      <!-- Today's Overview Cards -->
      <div class="stats-grid">
        <div class="stat-card stat-students">
          <div class="stat-icon">👨‍🎓</div>
          <div class="stat-content">
            <div class="stat-label">Students Today</div>
            <div class="stat-value">{{ todayStats?.students?.total || 0 }}</div>
            <div class="stat-breakdown">
              <span class="stat-present">P: {{ todayStats?.students?.present || 0 }}</span>
              <span class="stat-absent">A: {{ todayStats?.students?.absent || 0 }}</span>
              <span class="stat-late">L: {{ todayStats?.students?.late || 0 }}</span>
            </div>
          </div>
          <div class="stat-percent" :style="{ color: studentPercentColor }">
            {{ todayStats?.students?.percentage || 0 }}%
          </div>
        </div>

        <div class="stat-card stat-teachers">
          <div class="stat-icon">👨‍🏫</div>
          <div class="stat-content">
            <div class="stat-label">Teachers Today</div>
            <div class="stat-value">{{ todayStats?.teachers?.total || 0 }}</div>
            <div class="stat-breakdown">
              <span class="stat-present">P: {{ todayStats?.teachers?.present || 0 }}</span>
              <span class="stat-absent">A: {{ todayStats?.teachers?.absent || 0 }}</span>
              <span class="stat-late">L: {{ todayStats?.teachers?.late || 0 }}</span>
            </div>
          </div>
          <div class="stat-percent" :style="{ color: teacherPercentColor }">
            {{ todayStats?.teachers?.percentage || 0 }}%
          </div>
        </div>

        <div class="stat-card stat-employees">
          <div class="stat-icon">👥</div>
          <div class="stat-content">
            <div class="stat-label">Employees Today</div>
            <div class="stat-value">{{ todayStats?.employees?.total || 0 }}</div>
            <div class="stat-breakdown">
              <span class="stat-present">P: {{ todayStats?.employees?.present || 0 }}</span>
              <span class="stat-absent">A: {{ todayStats?.employees?.absent || 0 }}</span>
              <span class="stat-late">L: {{ todayStats?.employees?.late || 0 }}</span>
            </div>
          </div>
          <div class="stat-percent" :style="{ color: employeePercentColor }">
            {{ todayStats?.employees?.percentage || 0 }}%
          </div>
        </div>

        <div class="stat-card stat-devices">
          <div class="stat-icon">📟</div>
          <div class="stat-content">
            <div class="stat-label">Devices</div>
            <div class="stat-value">{{ deviceStatus?.total || 0 }}</div>
            <div class="stat-breakdown">
              <span class="stat-present">Online: {{ deviceStatus?.online || 0 }}</span>
              <span class="stat-absent">Offline: {{ deviceStatus?.offline || 0 }}</span>
            </div>
          </div>
          <div class="stat-percent" :style="{ color: devicePercentColor }">
            {{ deviceStatus?.online_percentage || 0 }}%
          </div>
        </div>
      </div>

      <!-- Monthly Trend & Alerts Row -->
      <div class="dashboard-grid">
        <!-- Monthly Trend Chart -->
        <div class="card chart-card">
          <div class="card-header-row">
            <h3>📈 Monthly Attendance Trend</h3>
            <select v-model="trendMonths" @change="fetchMonthlyTrend" class="form-control form-control-sm">
              <option :value="3">3 Months</option>
              <option :value="6">6 Months</option>
              <option :value="12">12 Months</option>
            </select>
          </div>
          <div v-if="monthlyTrend.length === 0" class="chart-empty">No trend data available</div>
          <div v-else class="chart-container">
            <div class="bar-chart">
              <div v-for="(item, idx) in monthlyTrend" :key="idx" class="bar-group">
                <div class="bar-column">
                  <div class="bar bar-present" :style="{ height: item.percentage + '%' }" :title="`${item.month}: ${item.percentage}%`"></div>
                </div>
                <div class="bar-label">{{ item.month_short || item.month }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Low Attendance Alerts -->
        <div class="card alerts-card">
          <div class="card-header-row">
            <h3>⚠️ Low Attendance Alerts</h3>
            <span class="badge badge-danger">{{ lowAttendanceAlerts.length }}</span>
          </div>
          <div v-if="lowAttendanceAlerts.length === 0" class="alerts-empty">🎉 No low attendance alerts</div>
          <div v-else class="alerts-list">
            <div v-for="(alert, idx) in lowAttendanceAlerts.slice(0, 5)" :key="idx" class="alert-item">
              <div class="alert-info">
                <strong>{{ alert.student_name || alert.name }}</strong>
                <span class="alert-detail">{{ alert.batch_name || '' }}</span>
              </div>
              <span class="alert-percent text-danger">{{ alert.percentage }}%</span>
            </div>
          </div>
          <div class="card-footer-link" v-if="lowAttendanceAlerts.length > 5">
            <span>+{{ lowAttendanceAlerts.length - 5 }} more</span>
          </div>
        </div>

        <!-- Consecutive Absent Alerts -->
        <div class="card alerts-card">
          <div class="card-header-row">
            <h3>🚨 Consecutive Absences</h3>
            <span class="badge badge-warning">{{ consecutiveAbsentAlerts.length }}</span>
          </div>
          <div v-if="consecutiveAbsentAlerts.length === 0" class="alerts-empty">✅ No consecutive absences</div>
          <div v-else class="alerts-list">
            <div v-for="(alert, idx) in consecutiveAbsentAlerts.slice(0, 5)" :key="idx" class="alert-item">
              <div class="alert-info">
                <strong>{{ alert.student_name || alert.name }}</strong>
                <span class="alert-detail">{{ alert.days }} days absent</span>
              </div>
              <span class="alert-percent text-warning">{{ alert.days }}d</span>
            </div>
          </div>
          <div class="card-footer-link" v-if="consecutiveAbsentAlerts.length > 5">
            <span>+{{ consecutiveAbsentAlerts.length - 5 }} more</span>
          </div>
        </div>

        <!-- Realtime Activity Feed -->
        <div class="card feed-card">
          <div class="card-header-row">
            <h3>🕐 Recent Activity</h3>
            <span class="live-badge">LIVE</span>
          </div>
          <div v-if="realtimeData.length === 0" class="feed-empty">No recent activity</div>
          <div v-else class="feed-list">
            <div v-for="(item, idx) in realtimeData" :key="idx" class="feed-item">
              <span class="feed-icon" :class="'feed-' + item.type">{{ item.icon || '✓' }}</span>
              <div class="feed-info">
                <strong>{{ item.name }}</strong>
                <span class="feed-detail">{{ item.detail || item.type }} · {{ item.time }}</span>
              </div>
              <span class="feed-status" :class="'status-' + item.status">{{ item.status }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import attendanceService from '@/services/attendance.service'
import { nowTime12 } from '@/utils/datetime'

const loading = ref(false)
const error = ref(null)
const successMsg = ref(null)
const lastUpdated = ref(null)
const trendMonths = ref(6)

const todayStats = ref(null)
const monthlyTrend = ref([])
const lowAttendanceAlerts = ref([])
const consecutiveAbsentAlerts = ref([])
const deviceStatus = ref(null)
const realtimeData = ref([])

const studentPercentColor = computed(() => {
  const p = todayStats.value?.students?.percentage || 0
  return p >= 80 ? '#059669' : p >= 60 ? '#d97706' : '#dc2626'
})
const teacherPercentColor = computed(() => {
  const p = todayStats.value?.teachers?.percentage || 0
  return p >= 90 ? '#059669' : p >= 70 ? '#d97706' : '#dc2626'
})
const employeePercentColor = computed(() => {
  const p = todayStats.value?.employees?.percentage || 0
  return p >= 90 ? '#059669' : p >= 70 ? '#d97706' : '#dc2626'
})
const devicePercentColor = computed(() => {
  const p = deviceStatus.value?.online_percentage || 0
  return p >= 80 ? '#059669' : p >= 50 ? '#d97706' : '#dc2626'
})

const refreshAll = async () => {
  loading.value = true; error.value = null
  try {
    await Promise.all([
      fetchTodayOverview(),
      fetchMonthlyTrend(),
      fetchLowAttendanceAlerts(),
      fetchConsecutiveAbsentAlerts(),
      fetchDeviceStatus(),
      fetchRealtimeData(),
    ])
    lastUpdated.value = nowTime12()
    successMsg.value = 'Dashboard refreshed'
    setTimeout(() => { successMsg.value = null }, 2000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to refresh dashboard'
  } finally { loading.value = false }
}

const fetchTodayOverview = async () => {
  const res = await attendanceService.getTodayOverview()
  todayStats.value = res.data?.data ?? res.data
}

const fetchMonthlyTrend = async () => {
  const res = await attendanceService.getMonthlyTrend({ months: trendMonths.value })
  monthlyTrend.value = res.data?.data || []
}

const fetchLowAttendanceAlerts = async () => {
  const res = await attendanceService.getLowAttendanceAlerts({ threshold: 60 })
  lowAttendanceAlerts.value = res.data?.data || []
}

const fetchConsecutiveAbsentAlerts = async () => {
  const res = await attendanceService.getConsecutiveAbsentAlerts({ days: 3 })
  consecutiveAbsentAlerts.value = res.data?.data || []
}

const fetchDeviceStatus = async () => {
  const res = await attendanceService.getDeviceStatus()
  deviceStatus.value = res.data?.data ?? res.data
}

const fetchRealtimeData = async () => {
  const res = await attendanceService.getRealtimeData({ limit: 10 })
  realtimeData.value = res.data?.data || []
}

onMounted(refreshAll)
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; align-items: center; gap: 0.75rem; }
.last-updated { font-size: 0.75rem; color: var(--text-muted); }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-outline:disabled { opacity: 0.5; cursor: not-allowed; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 2rem; background: #fef2f2; border-radius: 12px; color: #dc2626; }

/* Stats Grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--bg-card); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
.stat-icon { font-size: 2rem; }
.stat-content { flex: 1; }
.stat-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-value { font-size: 1.75rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.stat-breakdown { display: flex; gap: 0.75rem; margin-top: 0.25rem; font-size: 0.75rem; }
.stat-present { color: #059669; font-weight: 600; }
.stat-absent { color: #dc2626; font-weight: 600; }
.stat-late { color: #d97706; font-weight: 600; }
.stat-percent { font-size: 1.5rem; font-weight: 700; }
.stat-students { border-left: 4px solid #4f46e5; }
.stat-teachers { border-left: 4px solid #0891b2; }
.stat-employees { border-left: 4px solid #7c3aed; }
.stat-devices { border-left: 4px solid #059669; }

/* Dashboard Grid */
.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }

/* Cards */
.card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); overflow: hidden; }
.card-header-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light); }
.card-header-row h3 { margin: 0; font-size: 0.95rem; color: var(--text-primary); }
.form-control-sm { padding: 0.35rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-card); }
.card-footer-link { padding: 0.6rem 1.25rem; text-align: center; font-size: 0.8rem; color: var(--text-muted); border-top: 1px solid #f3f4f6; }

/* Chart */
.chart-card { grid-column: 1 / -1; }
.chart-empty, .alerts-empty, .feed-empty { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
.chart-container { padding: 1rem 1.25rem; }
.bar-chart { display: flex; align-items: flex-end; gap: 0.5rem; height: 160px; }
.bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; }
.bar-column { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
.bar { width: 60%; max-width: 40px; border-radius: 4px 4px 0 0; min-height: 4px; transition: height 0.5s ease; }
.bar-present { background: linear-gradient(180deg, #4f46e5, #818cf8); }
.bar-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.35rem; text-align: center; white-space: nowrap; }

/* Alerts */
.alerts-card { grid-column: span 1; }
.badge { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-warning { background: #fef3c7; color: #d97706; }
.alerts-list { padding: 0.5rem 0; }
.alert-item { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 1.25rem; border-bottom: 1px solid #f9fafb; }
.alert-item:last-child { border-bottom: none; }
.alert-info { flex: 1; }
.alert-info strong { display: block; font-size: 0.85rem; color: var(--text-primary); }
.alert-detail { font-size: 0.75rem; color: var(--text-muted); }
.alert-percent { font-weight: 700; font-size: 0.9rem; }
.text-danger { color: #dc2626; }
.text-warning { color: #d97706; }

/* Feed */
.feed-card { grid-column: span 1; }
.live-badge { background: #dc2626; color: white; font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; letter-spacing: 0.5px; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
.feed-list { padding: 0.5rem 0; }
.feed-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1.25rem; border-bottom: 1px solid #f9fafb; }
.feed-item:last-child { border-bottom: none; }
.feed-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; background: #f3f4f6; flex-shrink: 0; }
.feed-student { background: #e0e7ff; color: #4f46e5; }
.feed-teacher { background: #cffafe; color: #0891b2; }
.feed-employee { background: #ede9fe; color: #7c3aed; }
.feed-info { flex: 1; }
.feed-info strong { display: block; font-size: 0.85rem; color: var(--text-primary); }
.feed-detail { font-size: 0.75rem; color: var(--text-muted); }
.feed-status { font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: uppercase; }
.status-present { background: #d1fae5; color: #059669; }
.status-absent { background: #fee2e2; color: #dc2626; }
.status-late { background: #fef3c7; color: #d97706; }
.status-leave { background: #e0e7ff; color: #4f46e5; }

.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
