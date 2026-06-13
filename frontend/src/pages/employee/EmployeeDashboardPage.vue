<template>
  <div class="employee-dashboard">
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
              <span v-if="employeeId" class="meta-chip">
                <span class="chip-label">ID</span>
                {{ employeeId }}
              </span>
              <span v-if="departmentName" class="meta-chip">
                <span class="chip-label">Dept</span>
                {{ departmentName }}
              </span>
              <span v-if="designationName" class="meta-chip">
                <span class="chip-label">Role</span>
                {{ designationName }}
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
        <div v-if="canView('view attendance')" class="metric-card metric-amber">
          <div class="metric-icon">✓</div>
          <div class="metric-body">
            <span class="metric-label">My Attendance</span>
            <span class="metric-value">{{ attendanceData?.percentage || 0 }}%</span>
            <span v-if="attendanceData?.today_status" class="metric-sub" :class="'status-' + attendanceData.today_status">
              Today: {{ capitalize(attendanceData.today_status) }}
            </span>
          </div>
        </div>

        <div v-if="canView('view leave requests')" class="metric-card metric-orange">
          <div class="metric-icon">📝</div>
          <div class="metric-body">
            <span class="metric-label">Leave Requests</span>
            <span class="metric-value">{{ leaveRequests.length }}</span>
            <span class="metric-sub">{{ pendingLeaves }} pending</span>
          </div>
        </div>

        <div v-if="canView('view payroll')" class="metric-card metric-green">
          <div class="metric-icon">💵</div>
          <div class="metric-body">
            <span class="metric-label">Monthly Salary</span>
            <span class="metric-value">৳{{ formatNumber(salaryAmount) }}</span>
            <span v-if="latestPayroll" class="metric-sub">{{ latestPayroll.month || 'Latest payroll' }}</span>
          </div>
        </div>

        <div v-if="departmentName" class="metric-card metric-slate">
          <div class="metric-icon">🏢</div>
          <div class="metric-body">
            <span class="metric-label">Department</span>
            <span class="metric-value metric-value-sm">{{ departmentName }}</span>
            <span v-if="joinDate" class="metric-sub">Joined {{ formatDate(joinDate) }}</span>
          </div>
        </div>

        <div v-if="canView('view notifications') && unreadNotifs > 0" class="metric-card metric-indigo">
          <div class="metric-icon">🔔</div>
          <div class="metric-body">
            <span class="metric-label">Notifications</span>
            <span class="metric-value">{{ unreadNotifs }}</span>
            <span class="metric-sub">Unread messages</span>
          </div>
        </div>
      </section>

      <PortalCmsSummary portal="employee" />

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
          <section v-if="canView('view leave requests')" class="panel">
            <div class="panel-header">
              <h3>Recent Leave Requests</h3>
              <router-link to="/dashboard/hr/leave-requests" class="panel-link">View All</router-link>
            </div>
            <div v-if="leaveRequests.length === 0" class="panel-empty">
              <p>No leave requests found.</p>
            </div>
            <div v-else class="leave-list">
              <div v-for="leave in leaveRequests.slice(0, 5)" :key="leave.id" class="leave-item">
                <div class="leave-icon-wrap">📝</div>
                <div class="leave-info">
                  <strong>{{ leave.leave_type?.name || leave.reason || 'Leave' }}</strong>
                  <span>{{ formatDate(leave.start_date) }} – {{ formatDate(leave.end_date) }}</span>
                </div>
                <span class="status-badge" :class="'status-' + (leave.status || 'pending').toLowerCase()">
                  {{ capitalize(leave.status || 'pending') }}
                </span>
              </div>
            </div>
          </section>

          <section v-if="canView('view payroll')" class="panel">
            <div class="panel-header">
              <h3>Recent Payroll</h3>
              <router-link to="/dashboard/hr/payroll" class="panel-link">View All</router-link>
            </div>
            <div v-if="payrolls.length === 0" class="panel-empty">
              <p>No payroll records found.</p>
            </div>
            <div v-else class="payroll-list">
              <div v-for="pay in payrolls.slice(0, 5)" :key="pay.id" class="payroll-item">
                <div class="payroll-icon-wrap">💵</div>
                <div class="payroll-info">
                  <strong>{{ pay.month || pay.pay_period || 'Payroll' }}</strong>
                  <span>{{ capitalize(pay.status || 'processed') }}</span>
                </div>
                <strong class="payroll-amount">৳{{ formatNumber(pay.net_salary || pay.amount || pay.total) }}</strong>
              </div>
            </div>
          </section>
        </div>

        <div class="side-col">
          <section v-if="profile" class="panel">
            <div class="panel-header">
              <h3>My Profile</h3>
            </div>
            <div class="profile-grid">
              <div class="profile-row">
                <span>Employee ID</span>
                <strong>{{ profile.employee_id || '—' }}</strong>
              </div>
              <div class="profile-row">
                <span>Department</span>
                <strong>{{ departmentName || '—' }}</strong>
              </div>
              <div class="profile-row">
                <span>Designation</span>
                <strong>{{ designationName || '—' }}</strong>
              </div>
              <div class="profile-row">
                <span>Employment</span>
                <strong>{{ capitalize(profile.employment_type || '—') }}</strong>
              </div>
              <div v-if="profile.email" class="profile-row">
                <span>Email</span>
                <strong>{{ profile.email }}</strong>
              </div>
              <div v-if="profile.phone" class="profile-row">
                <span>Phone</span>
                <strong>{{ profile.phone }}</strong>
              </div>
            </div>
          </section>

          <section v-if="canView('view attendance') && attendanceData" class="panel">
            <div class="panel-header">
              <h3>Attendance Summary</h3>
              <router-link to="/dashboard/my-attendance" class="panel-link">Details</router-link>
            </div>
            <div class="attendance-stats">
              <div class="att-row">
                <span>Present</span>
                <strong class="text-green">{{ attendanceData.present || 0 }}</strong>
              </div>
              <div class="att-row">
                <span>Absent</span>
                <strong class="text-red">{{ attendanceData.absent || 0 }}</strong>
              </div>
              <div class="att-row">
                <span>Leave</span>
                <strong class="text-amber">{{ attendanceData.leave || 0 }}</strong>
              </div>
              <div class="att-row">
                <span>Half Day</span>
                <strong>{{ attendanceData.half_day || 0 }}</strong>
              </div>
            </div>
          </section>

          <section v-if="canView('view notice board')" class="panel">
            <div class="panel-header">
              <h3>Recent Notices</h3>
              <router-link to="/employee/notices" class="panel-link">View All</router-link>
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
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import employeePortalService from '@/services/employee-portal.service'
import communicationService from '@/services/communication.service'
import PortalCmsSummary from '@/components/dashboard/PortalCmsSummary.vue'

const EMPLOYEE_DEFAULT_PERMISSIONS = [
  'view attendance',
  'view employees', 'view departments', 'view designations',
  'view staff attendance', 'create staff attendance',
  'view leave requests', 'create leave requests', 'view leave types',
  'view payroll',
  'view notice board', 'view notifications',
  'view events', 'view gallery',
]

export default {
  name: 'EmployeeDashboardPage',
  components: { PortalCmsSummary },
  setup() {
    const authStore = useAuthStore()
    const loading = ref(false)
    const loaded = ref(false)
    const error = ref(null)
    const currentDate = ref('')
    const profile = ref(null)
    const attendanceData = ref(null)
    const leaveRequests = ref([])
    const payrolls = ref([])
    const notices = ref([])
    const unreadNotifs = ref(0)

    const canView = (permission) => {
      if (!permission) return true
      if (authStore.hasPermission(permission)) return true
      return EMPLOYEE_DEFAULT_PERMISSIONS.includes(permission)
    }

    const displayName = computed(() => {
      if (profile.value) {
        const name = `${profile.value.first_name || ''} ${profile.value.last_name || ''}`.trim()
        if (name) return name
      }
      return authStore.user?.name || 'Employee'
    })

    const employeeId = computed(() => profile.value?.employee_id || '')
    const departmentName = computed(() => profile.value?.department?.name || '')
    const designationName = computed(() => profile.value?.designation?.name || '')
    const joinDate = computed(() => profile.value?.date_of_joining || null)
    const salaryAmount = computed(() => {
      if (latestPayroll.value?.net_salary) return latestPayroll.value.net_salary
      if (latestPayroll.value?.amount) return latestPayroll.value.amount
      return profile.value?.salary || 0
    })
    const latestPayroll = computed(() => payrolls.value[0] || null)
    const pendingLeaves = computed(() =>
      leaveRequests.value.filter((l) => (l.status || '').toLowerCase() === 'pending').length
    )

    const avatarUrl = computed(() => {
      const name = encodeURIComponent(displayName.value)
      return profile.value?.photo || authStore.user?.avatar ||
        `https://ui-avatars.com/api/?name=${name}&background=d97706&color=fff&size=128`
    })

    const greeting = computed(() => {
      const hour = new Date().getHours()
      if (hour < 12) return 'Good Morning'
      if (hour < 17) return 'Good Afternoon'
      return 'Good Evening'
    })

    const quickActions = [
      { to: '/dashboard/my-attendance', icon: '✓', label: 'My Attendance', permission: 'view attendance', color: '#d97706' },
      { to: '/dashboard/hr/leave-requests', icon: '📝', label: 'Leave Requests', permission: 'view leave requests', color: '#ea580c' },
      { to: '/dashboard/hr/payroll', icon: '💵', label: 'Payroll', permission: 'view payroll', color: '#059669' },
      { to: '/dashboard/hr/employees', icon: '👥', label: 'Colleagues', permission: 'view employees', color: '#4f46e5' },
      { to: '/dashboard/hr/departments', icon: '🏢', label: 'Departments', permission: 'view departments', color: '#0891b2' },
      { to: '/employee/notices', icon: '📢', label: 'Notices', permission: 'view notice board', color: '#db2777' },
    ]

    const visibleQuickActions = computed(() => quickActions.filter((a) => canView(a.permission)))

    const loadOptional = async (enabled, loader) => {
      if (!enabled) return null
      try {
        return await loader()
      } catch {
        return null
      }
    }

    const loadDashboard = async () => {
      loading.value = true
      error.value = null

      try {
        const empProfile = await employeePortalService.profile(authStore.user)
        profile.value = empProfile

        const employeeDbId = empProfile?.id

        const [attRes, leaveRes, payrollRes, noticeRes, notifRes] = await Promise.all([
          loadOptional(canView('view attendance'), () => employeePortalService.myAttendance()),
          loadOptional(canView('view leave requests') && employeeDbId, () =>
            employeePortalService.leaveRequests(employeeDbId)),
          loadOptional(canView('view payroll') && employeeDbId, () =>
            employeePortalService.payroll(employeeDbId)),
          loadOptional(canView('view notice board'), () => employeePortalService.notices()),
          loadOptional(canView('view notifications'), () => communicationService.unreadCount()),
        ])

        attendanceData.value = attRes?.data?.data || null
        leaveRequests.value = leaveRes ? (leaveRes.data?.data || []) : []
        payrolls.value = payrollRes ? (payrollRes.data?.data || []) : []
        notices.value = noticeRes?.data?.data || []
        unreadNotifs.value = notifRes?.data?.data?.count ?? notifRes?.data?.count ?? 0

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
    const capitalize = (str) => (str ? str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ') : '')

    onMounted(() => {
      currentDate.value = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      })
      loadDashboard()
    })

    return {
      loading, loaded, error, currentDate, displayName, employeeId,
      departmentName, designationName, joinDate, salaryAmount, latestPayroll,
      avatarUrl, greeting, profile, attendanceData, leaveRequests, payrolls,
      notices, unreadNotifs, pendingLeaves, visibleQuickActions,
      canView, loadDashboard, formatNumber, formatDate, capitalize,
    }
  },
}
</script>

<style scoped>
.employee-dashboard { max-width: 1280px; margin: 0 auto; padding-bottom: 2rem; }

.hero { position: relative; border-radius: 20px; overflow: hidden; margin-bottom: 1.5rem; border: 1px solid #fed7aa; }
.hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #d97706 0%, #b45309 45%, #92400e 100%); }
.hero-content { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 2rem; flex-wrap: wrap; }
.hero-profile { display: flex; align-items: center; gap: 1.25rem; }
.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar { width: 72px; height: 72px; border-radius: 18px; object-fit: cover; border: 3px solid rgba(255,255,255,0.35); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
.status-dot { position: absolute; bottom: 2px; right: 2px; width: 14px; height: 14px; background: #22c55e; border: 2px solid #fff; border-radius: 50%; }
.hero-greeting { margin: 0 0 0.25rem; font-size: 0.85rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.hero-name { margin: 0 0 0.75rem; font-size: 1.75rem; font-weight: 800; color: #fff; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.meta-chip { display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(255,255,255,0.15); color: #fff; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; backdrop-filter: blur(4px); }
.chip-label { opacity: 0.75; font-weight: 600; text-transform: uppercase; font-size: 0.6rem; letter-spacing: 0.04em; }
.refresh-btn { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.25); padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.85rem; font-weight: 700; cursor: pointer; backdrop-filter: blur(4px); transition: background 0.2s; }
.refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,0.25); }
.refresh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.state-card { text-align: center; padding: 3rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; color: var(--text-dark); font-weight: 600; }

.metrics-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.metric-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start; box-shadow: var(--shadow-sm); }
.metric-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
.metric-amber .metric-icon { background: #ffedd5; }
.metric-orange .metric-icon { background: #fed7aa; }
.metric-green .metric-icon { background: #d1fae5; }
.metric-slate .metric-icon { background: var(--bg-accent); }
.metric-indigo .metric-icon { background: #e0e7ff; }
.metric-body { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.metric-label { font-size: 0.75rem; font-weight: 700; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.03em; }
.metric-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
.metric-value-sm { font-size: 1.1rem; }
.metric-sub { font-size: 0.8rem; font-weight: 600; color: var(--text-dark); }
.status-present { color: #059669; }
.status-absent { color: #dc2626; }
.status-leave { color: #d97706; }

.section-title { font-size: 1rem; font-weight: 800; color: var(--text-primary); margin: 0 0 1rem; }
.quick-section { margin-bottom: 1.5rem; }
.quick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem; }
.quick-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: var(--shadow-sm); }
.quick-card:hover { transform: translateY(-2px); border-color: var(--accent); box-shadow: 0 4px 16px rgba(15,23,42,0.08); }
.quick-icon { font-size: 1.5rem; }
.quick-label { font-size: 0.78rem; font-weight: 700; color: var(--text-dark); text-align: center; }

.main-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
@media (max-width: 960px) { .main-grid { grid-template-columns: 1fr; } }

.panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm); }
.panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.panel-header h3 { margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text-primary); }
.panel-link { font-size: 0.8rem; font-weight: 700; color: #d97706; text-decoration: none; }
.panel-link:hover { text-decoration: underline; }
.panel-empty { padding: 1.5rem; text-align: center; color: var(--text-dark); font-weight: 600; font-size: 0.9rem; }

.leave-list, .payroll-list { display: flex; flex-direction: column; gap: 0.75rem; }
.leave-item, .payroll-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-surface-muted); border-radius: 12px; }
.leave-icon-wrap, .payroll-icon-wrap { width: 36px; height: 36px; border-radius: 10px; background: #ffedd5; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.leave-info, .payroll-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.15rem; }
.leave-info strong, .payroll-info strong { font-size: 0.88rem; font-weight: 800; color: var(--text-primary); }
.leave-info span, .payroll-info span { font-size: 0.78rem; font-weight: 600; color: var(--text-dark); }
.status-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; flex-shrink: 0; }
.status-pending { background: #fef3c7; color: #d97706; }
.status-approved { background: #d1fae5; color: #059669; }
.status-rejected { background: #fee2e2; color: #dc2626; }
.payroll-amount { font-size: 0.9rem; font-weight: 800; color: #059669; flex-shrink: 0; }

.profile-grid { display: flex; flex-direction: column; gap: 0.65rem; }
.profile-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
.profile-row span { color: var(--text-dark); font-weight: 600; }
.profile-row strong { color: var(--text-primary); font-weight: 800; text-align: right; }

.attendance-stats { display: flex; flex-direction: column; gap: 0.6rem; }
.att-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); }
.text-green { color: #059669; }
.text-red { color: #dc2626; }
.text-amber { color: #d97706; }

.notice-list { display: flex; flex-direction: column; gap: 0.75rem; }
.notice-item { display: flex; flex-direction: column; gap: 0.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-light); }
.notice-item:last-child { border-bottom: none; padding-bottom: 0; }
.notice-item strong { font-size: 0.88rem; font-weight: 800; color: var(--text-primary); }
.notice-date { font-size: 0.75rem; font-weight: 600; color: var(--text-dark); }
.priority-badge { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; align-self: flex-start; }
.priority-high, .priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-medium { background: #fef3c7; color: #d97706; }
.priority-normal, .priority-low { background: #ffedd5; color: #c2410c; }
</style>
