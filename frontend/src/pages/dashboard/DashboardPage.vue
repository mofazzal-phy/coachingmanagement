<template>
  <div class="dashboard-page">
    <AdminDashboardView v-if="userRole === 'admin'" />

    <template v-else>
      <section class="role-hero" :class="`hero-${userRole}`">
        <div>
          <h1>Welcome, {{ user?.name }}! 👋</h1>
          <p>{{ currentDate }}</p>
        </div>
        <span class="role-badge">{{ roleLabel }}</span>
      </section>

      <div class="kpi-grid">
        <DashboardStatCard
          v-for="card in roleStatCards"
          :key="card.label"
          v-bind="card"
          :loading="loading"
        />
      </div>

      <PortalCmsSummary v-if="userRole" :portal="userRole" />

      <div v-if="upcomingExams.length" class="section-block">
        <h2>Upcoming Exams</h2>
        <div class="exam-list">
          <div v-for="exam in upcomingExams" :key="exam.id" class="exam-row">
            <strong>{{ exam.name || exam.title }}</strong>
            <span>{{ formatExamDate(exam.start_date || exam.date) }}</span>
          </div>
        </div>
      </div>

      <div v-if="attendanceBreakdown" class="section-block">
        <h2>Attendance Overview</h2>
        <div class="attendance-mini">
          <div class="att-stat">
            <span class="lbl">Present</span>
            <span class="val present">{{ attendanceBreakdown.present }}%</span>
          </div>
          <div class="att-stat">
            <span class="lbl">Rate</span>
            <span class="val">{{ attendanceBreakdown.rate }}%</span>
          </div>
        </div>
      </div>

      <section class="quick-section">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <router-link v-for="a in roleActions" :key="a.to" :to="a.to" class="action-tile">
            <span class="action-icon">{{ a.icon }}</span>
            <span class="action-label">{{ a.label }}</span>
          </router-link>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import dashboardService from '@/services/dashboard.service'
import AdminDashboardView from '@/components/dashboard/AdminDashboardView.vue'
import DashboardStatCard from '@/components/dashboard/DashboardStatCard.vue'
import PortalCmsSummary from '@/components/dashboard/PortalCmsSummary.vue'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const userRole = computed(() => authStore.userRole)
const can = (p) => authStore.hasPermission(p)

const loading = ref(false)
const currentDate = ref('')
const roleData = ref({})

const roleLabel = computed(() => {
  const labels = { teacher: 'Teacher', student: 'Student', employee: 'Employee', guardian: 'Guardian' }
  return labels[userRole.value] || userRole.value || 'User'
})

const upcomingExams = computed(() => {
  const portal = roleData.value.portal
  if (portal?.upcoming_exams) {
    return Array.isArray(portal.upcoming_exams) ? portal.upcoming_exams.slice(0, 5) : []
  }
  const exams = roleData.value.exams
  const list = exams?.data || exams || []
  return Array.isArray(list) ? list.slice(0, 5) : []
})

const attendanceBreakdown = computed(() => {
  const portal = roleData.value.portal
  if (portal?.attendance_percentage != null) {
    return { present: portal.attendance_percentage, rate: portal.attendance_percentage }
  }
  const today = roleData.value.attendanceToday
  if (userRole.value === 'teacher' && today?.students) {
    return { present: today.students.present, rate: today.students.percentage }
  }
  if (userRole.value === 'employee' && today?.employees) {
    return { present: today.employees.present, rate: today.employees.percentage }
  }
  return null
})

const roleStatCards = computed(() => {
  const d = roleData.value
  const p = d.portal

  if (userRole.value === 'student') {
    return [
      can('view attendance') && {
        label: 'My Attendance', value: p?.attendance_percentage ?? 0, icon: '✅', format: 'percent',
        accentColor: '#059669', iconBg: '#d1fae5',
      },
      can('view exams') && {
        label: 'Upcoming Exams', value: p?.upcoming_exams?.length ?? 0, icon: '📝',
        accentColor: '#2563eb', iconBg: '#dbeafe',
      },
      can('view fee collections') && {
        label: 'Fee Due', value: p?.total_due ?? 0, icon: '💰', format: 'currency',
        accentColor: '#d97706', iconBg: '#fef3c7',
        subtext: p?.overdue_count ? `${p.overdue_count} overdue` : 'Up to date',
      },
      {
        label: 'Active Courses', value: p?.active_enrollments_count ?? 0, icon: '📚',
        accentColor: '#7c3aed', iconBg: '#ede9fe',
      },
    ].filter(Boolean)
  }

  if (userRole.value === 'teacher') {
    const today = d.attendanceToday
    return [
      can('view attendance') && {
        label: "Today's Attendance", value: today?.students?.percentage ?? 0, icon: '✅', format: 'percent',
        accentColor: '#0891b2', iconBg: '#cffafe',
        subtext: `${today?.students?.present ?? 0} students present`,
      },
      can('view exams') && {
        label: 'Published Exams', value: d.exams?.meta?.total ?? upcomingExams.value.length, icon: '📝',
        accentColor: '#dc2626', iconBg: '#fee2e2',
      },
      can('view student attendance') && {
        label: 'Students Tracked', value: today?.students?.total ?? 0, icon: '🎓',
        accentColor: '#4f46e5', iconBg: '#eef2ff',
      },
      can('view notice board') && {
        label: 'Notices', value: 'View', icon: '📢', format: 'text',
        accentColor: '#db2777', iconBg: '#fce7f3',
      },
    ].filter(Boolean)
  }

  if (userRole.value === 'employee') {
    const today = d.attendanceToday
    return [
      can('view staff attendance') && {
        label: 'My Attendance', value: today?.employees?.percentage ?? 0, icon: '✅', format: 'percent',
        accentColor: '#d97706', iconBg: '#fef3c7',
      },
      can('view leave requests') && {
        label: 'Leave', value: 'Apply', icon: '📝', format: 'text',
        accentColor: '#2563eb', iconBg: '#dbeafe',
      },
      can('view payroll') && {
        label: 'Payroll', value: 'View', icon: '💵', format: 'text',
        accentColor: '#059669', iconBg: '#d1fae5',
      },
      can('view departments') && {
        label: 'Department', value: user.value?.department || '—', icon: '🏢', format: 'text',
        accentColor: '#7c3aed', iconBg: '#ede9fe',
      },
    ].filter(Boolean)
  }

  if (userRole.value === 'guardian') {
    return [
      can('view students') && {
        label: 'My Children', value: '—', icon: '👨‍🎓', format: 'text',
        accentColor: '#0891b2', iconBg: '#cffafe',
      },
      can('view attendance') && {
        label: 'Attendance', value: '—', icon: '✅', format: 'text',
        accentColor: '#059669', iconBg: '#d1fae5',
      },
      can('view exams') && {
        label: 'Upcoming Exams', value: upcomingExams.value.length, icon: '📝',
        accentColor: '#dc2626', iconBg: '#fee2e2',
      },
      can('view fee collections') && {
        label: 'Fee Status', value: 'View', icon: '💰', format: 'text',
        accentColor: '#d97706', iconBg: '#fef3c7',
      },
    ].filter(Boolean)
  }

  return []
})

const roleActions = computed(() => {
  const role = userRole.value
  const actions = []

  if (role === 'teacher') {
    if (can('view attendance')) actions.push({ label: 'My Attendance', icon: '📋', to: '/dashboard/my-attendance' })
    if (can('view student attendance')) actions.push({ label: 'Take Attendance', icon: '✓', to: '/dashboard/attendance/students' })
    if (can('view exams')) actions.push({ label: 'Exams', icon: '📝', to: '/dashboard/exams' })
    if (can('view exam results')) actions.push({ label: 'Results', icon: '📊', to: '/dashboard/exams/results' })
    if (can('view notice board')) actions.push({ label: 'Notice Board', icon: '📢', to: '/dashboard/communication/notice-board' })
  } else if (role === 'student') {
    if (can('view attendance')) actions.push({ label: 'My Attendance', icon: '✓', to: '/student/attendance' })
    if (can('view exams')) actions.push({ label: 'Exams', icon: '📝', to: '/student/exams' })
    if (can('view exam results')) actions.push({ label: 'Results', icon: '📊', to: '/student/exam-results' })
    if (can('view fee collections')) actions.push({ label: 'Fee Dashboard', icon: '💰', to: '/student/fee-dashboard' })
    if (can('view notice board')) actions.push({ label: 'Notices', icon: '📢', to: '/student/notices' })
  } else if (role === 'employee') {
    if (can('view attendance')) actions.push({ label: 'My Attendance', icon: '📋', to: '/dashboard/my-attendance' })
    if (can('view leave requests')) actions.push({ label: 'Leave', icon: '📝', to: '/dashboard/hr/leave-requests' })
    if (can('view payroll')) actions.push({ label: 'Payroll', icon: '💵', to: '/dashboard/hr/payroll' })
    if (can('view notice board')) actions.push({ label: 'Notice Board', icon: '📢', to: '/dashboard/communication/notice-board' })
  } else if (role === 'guardian') {
    if (can('view students')) actions.push({ label: 'My Children', icon: '👨‍🎓', to: '/dashboard/students' })
    if (can('view attendance')) actions.push({ label: 'Attendance', icon: '✓', to: '/dashboard/attendance' })
    if (can('view exams')) actions.push({ label: 'Exams', icon: '📝', to: '/dashboard/exams' })
    if (can('view fee collections')) actions.push({ label: 'Fees', icon: '💰', to: '/dashboard/finance/collections' })
    if (can('view notice board')) actions.push({ label: 'Notices', icon: '📢', to: '/dashboard/communication/notice-board' })
  } else {
    actions.push({ label: 'Notice Board', icon: '📢', to: '/dashboard/communication/notice-board' })
  }

  return actions
})

function formatExamDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function loadRoleData() {
  if (userRole.value === 'admin') return
  loading.value = true
  try {
    if (userRole.value === 'student') {
      roleData.value = await dashboardService.fetchStudentData()
    } else if (userRole.value === 'teacher') {
      roleData.value = await dashboardService.fetchTeacherData(can)
    } else if (userRole.value === 'employee') {
      roleData.value = await dashboardService.fetchEmployeeData(can)
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  currentDate.value = new Date().toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  })
  loadRoleData()
})
</script>

<style scoped>
.dashboard-page {
  max-width: 1440px;
  margin: 0 auto;
}

.role-hero {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1.35rem 1.5rem;
  border-radius: 16px;
  color: #fff;
  margin-bottom: 1.25rem;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.hero-teacher { background: linear-gradient(135deg, #1d4ed8, #2563eb); }
.hero-student { background: linear-gradient(135deg, #047857, #059669); }
.hero-employee { background: linear-gradient(135deg, #b45309, #d97706); }
.hero-guardian { background: linear-gradient(135deg, #0e7490, #0891b2); }

.role-hero h1 { margin: 0 0 0.35rem; font-size: clamp(1.2rem, 2.5vw, 1.5rem); }
.role-hero p { margin: 0; opacity: 0.9; font-size: 0.88rem; }

.role-badge {
  background: rgba(255,255,255,0.2);
  padding: 0.45rem 1rem;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 600;
  backdrop-filter: blur(4px);
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.section-block {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  padding: 1.15rem;
  margin-bottom: 1.25rem;
}

.section-block h2 {
  margin: 0 0 0.85rem;
  font-size: 0.95rem;
  color: var(--text-primary);
}

.exam-list { display: flex; flex-direction: column; gap: 0.5rem; }
.exam-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.85rem;
}
.exam-row:last-child { border-bottom: none; }
.exam-row strong { color: var(--text-primary); }
.exam-row span { color: var(--text-muted); font-size: 0.78rem; }

.attendance-mini { display: flex; gap: 1.5rem; }
.att-stat .lbl { display: block; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; }
.att-stat .val { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); }
.att-stat .val.present { color: #059669; }

.quick-section h2 { font-size: 1rem; margin: 0 0 0.75rem; color: var(--text-primary); }

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

@media (max-width: 600px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 400px) {
  .kpi-grid { grid-template-columns: 1fr; }
}
</style>
