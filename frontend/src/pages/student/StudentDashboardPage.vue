<template>
  <div class="student-dashboard">
    <!-- Hero -->
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
              <span v-if="student?.student_id" class="meta-chip">
                <span class="chip-label">ID</span>
                {{ student.student_id }}
              </span>
              <span v-if="classLabel" class="meta-chip">
                <span class="chip-label">Class</span>
                {{ classLabel }}
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

    <!-- Loading -->
    <div v-if="loading && !dashboardData" class="state-card loading-card">
      <ProgressSpinner strokeWidth="3" />
      <p>Loading your dashboard...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="state-card error-card">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Try Again" icon="pi pi-refresh" @click="loadDashboard" class="p-button-outlined mt-3" />
    </div>

    <template v-else-if="dashboardData">
      <!-- Key Metrics -->
      <section class="metrics-grid">
        <div v-if="canView('view attendance')" class="metric-card metric-indigo">
          <div class="metric-icon">✓</div>
          <div class="metric-body">
            <span class="metric-label">Attendance</span>
            <span class="metric-value">{{ dashboardData.attendance_percentage || 0 }}%</span>
            <span v-if="attendanceData?.today_status" class="metric-sub" :class="'status-' + attendanceData.today_status">
              Today: {{ capitalize(attendanceData.today_status) }}
            </span>
          </div>
        </div>

        <div v-if="canView('view class routines')" class="metric-card metric-teal">
          <div class="metric-icon">🏫</div>
          <div class="metric-body">
            <span class="metric-label">Classes Today</span>
            <span class="metric-value">{{ todayClasses.length }}</span>
            <span class="metric-sub">{{ liveClassCount }} live now</span>
          </div>
        </div>

        <div v-if="canView('view exams')" class="metric-card metric-amber">
          <div class="metric-icon">📝</div>
          <div class="metric-body">
            <span class="metric-label">Upcoming Exams</span>
            <span class="metric-value">{{ dashboardData.upcoming_exams?.length || 0 }}</span>
            <span v-if="nextExam" class="metric-sub">Next: {{ nextExam.date || formatDate(nextExam.exam_date) }}</span>
          </div>
        </div>

        <div class="metric-card metric-violet">
          <div class="metric-icon">📚</div>
          <div class="metric-body">
            <span class="metric-label">Active Enrollments</span>
            <span class="metric-value">{{ dashboardData.active_enrollments_count || 0 }}</span>
            <span class="metric-sub">{{ activeEnrollments.length }} course{{ activeEnrollments.length !== 1 ? 's' : '' }}</span>
          </div>
        </div>

        <div v-if="canView('view fee collections')" class="metric-card" :class="dashboardData.total_due > 0 ? 'metric-danger' : 'metric-green'">
          <div class="metric-icon">💰</div>
          <div class="metric-body">
            <span class="metric-label">Total Due</span>
            <span class="metric-value">৳{{ formatNumber(dashboardData.total_due) }}</span>
            <span v-if="dashboardData.overdue_count > 0" class="metric-sub danger-text">
              {{ dashboardData.overdue_count }} overdue
            </span>
            <span v-else class="metric-sub">All fees on track</span>
          </div>
        </div>

        <div v-if="canView('view fee collections') && feeNotifCount > 0" class="metric-card metric-orange">
          <div class="metric-icon">🔔</div>
          <div class="metric-body">
            <span class="metric-label">Fee Alerts</span>
            <span class="metric-value">{{ feeNotifCount }}</span>
            <router-link to="/student/fee-notifications" class="metric-link">View alerts</router-link>
          </div>
        </div>

        <div v-if="pendingLeaves > 0" class="metric-card metric-slate">
          <div class="metric-icon">📋</div>
          <div class="metric-body">
            <span class="metric-label">Pending Leaves</span>
            <span class="metric-value">{{ pendingLeaves }}</span>
            <router-link to="/student/leave-apply" class="metric-link">View status</router-link>
          </div>
        </div>
      </section>

      <PortalCmsSummary portal="student" />

      <!-- Quick Actions -->
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
            <span v-if="action.badge" class="quick-badge">{{ action.badge }}</span>
          </router-link>
        </div>
      </section>

      <!-- Main Grid -->
      <div class="main-grid">
        <div class="main-col">
          <!-- Enrollments -->
          <section class="panel">
            <div class="panel-header">
              <h3>My Enrollments</h3>
              <span class="panel-count">{{ activeEnrollments.length }} active</span>
            </div>
            <div v-if="activeEnrollments.length === 0" class="panel-empty">
              <p>No active enrollments yet.</p>
            </div>
            <div v-else class="enrollment-grid">
              <div v-for="enrollment in activeEnrollments" :key="enrollment.id" class="enrollment-card">
                <div class="enrollment-top">
                  <span class="enrollment-course">{{ enrollment.batch?.course?.name || 'Course' }}</span>
                  <span class="enrollment-status">{{ enrollment.status }}</span>
                </div>
                <p class="enrollment-batch">{{ enrollment.batch?.name || 'Batch' }}</p>
                <p v-if="enrollment.batch?.academic_session?.name" class="enrollment-session">
                  {{ enrollment.batch.academic_session.name }}
                </p>
              </div>
            </div>
          </section>

          <!-- Today's Schedule -->
          <section v-if="canView('view class routines')" class="panel">
            <div class="panel-header">
              <h3>Today's Schedule</h3>
              <router-link to="/student/class-routine" class="panel-link">Full Routine</router-link>
            </div>
            <div v-if="todayClasses.length === 0" class="panel-empty">
              <p>No classes scheduled for today.</p>
            </div>
            <div v-else class="schedule-list">
              <div
                v-for="slot in todayClasses.slice(0, 5)"
                :key="slot.id"
                class="schedule-item"
                :class="{ 'is-live': isCurrentSlot(slot) }"
              >
                <div class="schedule-time">
                  <strong>{{ formatTime(slot.start_time) }}</strong>
                  <span>{{ formatTime(slot.end_time) }}</span>
                </div>
                <div class="schedule-info">
                  <strong>{{ slot.subject?.name || slot.subject_name || 'Class' }}</strong>
                  <span>{{ slot.teacher?.name || slot.room?.name || '' }}</span>
                </div>
                <span v-if="isCurrentSlot(slot)" class="live-pill">Live</span>
              </div>
            </div>
          </section>

          <!-- Upcoming Exams -->
          <section v-if="canView('view exams')" class="panel">
            <div class="panel-header">
              <h3>Upcoming Exams</h3>
              <router-link to="/student/exams" class="panel-link">View All</router-link>
            </div>
            <div v-if="!dashboardData.upcoming_exams?.length" class="panel-empty">
              <p>No upcoming exams scheduled.</p>
            </div>
            <div v-else class="exam-list">
              <div v-for="exam in dashboardData.upcoming_exams" :key="exam.id" class="exam-item">
                <div class="exam-icon-wrap">📝</div>
                <div class="exam-info">
                  <strong>{{ exam.name }}</strong>
                  <span>{{ exam.exam_type?.name || 'Exam' }} · {{ exam.class?.name || exam.batch?.name || '' }}</span>
                </div>
                <div class="exam-date">
                  <strong>{{ formatDate(exam.start_date) }}</strong>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="side-col">
          <!-- Attendance Summary -->
          <section v-if="canView('view attendance') && attendanceData" class="panel">
            <div class="panel-header">
              <h3>Attendance Summary</h3>
              <router-link to="/student/attendance" class="panel-link">Details</router-link>
            </div>
            <div class="attendance-ring-wrap">
              <div class="attendance-ring">
                <svg viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="52" class="ring-bg"/>
                  <circle
                    cx="60" cy="60" r="52"
                    class="ring-fill"
                    :style="{ strokeDashoffset: ringOffset }"
                  />
                </svg>
                <div class="ring-center">
                  <strong>{{ attendanceData.percentage || dashboardData.attendance_percentage || 0 }}%</strong>
                  <span>Overall</span>
                </div>
              </div>
              <div class="attendance-breakdown">
                <div class="breakdown-row">
                  <span class="dot present"></span> Present <strong>{{ attendanceData.present || 0 }}</strong>
                </div>
                <div class="breakdown-row">
                  <span class="dot absent"></span> Absent <strong>{{ attendanceData.absent || 0 }}</strong>
                </div>
                <div class="breakdown-row">
                  <span class="dot late"></span> Late <strong>{{ attendanceData.late || 0 }}</strong>
                </div>
                <div class="breakdown-row">
                  <span class="dot leave"></span> Leave <strong>{{ attendanceData.leave || 0 }}</strong>
                </div>
              </div>
            </div>
          </section>

          <!-- Recent Notices -->
          <section v-if="canView('view notice board')" class="panel">
            <div class="panel-header">
              <h3>Recent Notices</h3>
              <router-link to="/student/notices" class="panel-link">View All</router-link>
            </div>
            <div v-if="!dashboardData.recent_notices?.length" class="panel-empty">
              <p>No notices at the moment.</p>
            </div>
            <div v-else class="notice-list">
              <div v-for="notice in dashboardData.recent_notices" :key="notice.id" class="notice-item">
                <span class="priority-badge" :class="'priority-' + (notice.priority || 'normal').toLowerCase()">
                  {{ notice.priority || 'Normal' }}
                </span>
                <strong>{{ notice.title }}</strong>
                <span class="notice-date">{{ formatDate(notice.publish_date) }}</span>
              </div>
            </div>
          </section>

          <!-- Fee Snapshot -->
          <section v-if="canView('view fee collections')" class="panel fee-panel">
            <div class="panel-header">
              <h3>Fee Snapshot</h3>
              <router-link to="/student/fee-dashboard" class="panel-link">Fee Dashboard</router-link>
            </div>
            <div class="fee-snapshot">
              <div class="fee-row">
                <span>Total Due</span>
                <strong :class="{ 'danger-text': dashboardData.total_due > 0 }">
                  ৳{{ formatNumber(dashboardData.total_due) }}
                </strong>
              </div>
              <div class="fee-row">
                <span>Overdue Items</span>
                <strong :class="{ 'danger-text': dashboardData.overdue_count > 0 }">
                  {{ dashboardData.overdue_count || 0 }}
                </strong>
              </div>
              <router-link v-if="dashboardData.total_due > 0" to="/student/fee-payment" class="pay-now-btn">
                Pay Now →
              </router-link>
            </div>
          </section>
        </div>
      </div>
    </template>

    <div v-else class="state-card empty-card">
      <i class="pi pi-info-circle empty-icon"></i>
      <h3>No Data Available</h3>
      <p>Your dashboard will appear here once your profile is set up.</p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import studentPortalService from '@/services/student-portal.service'
import classRoutineService from '@/services/class-routine.service'
import examService from '@/services/exam.service'
import smartFeeService from '@/services/smart-fee.service'
import PortalCmsSummary from '@/components/dashboard/PortalCmsSummary.vue'

const STUDENT_DEFAULT_PERMISSIONS = [
  'view attendance',
  'view exams', 'view exam results', 'view exam routines',
  'view classes', 'view subjects', 'view class routines',
  'view fee collections',
  'view notice board',
]

export default {
  name: 'StudentDashboardPage',
  components: { PortalCmsSummary },
  setup() {
    const authStore = useAuthStore()
    const loading = ref(false)
    const error = ref(null)
    const dashboardData = ref(null)
    const student = ref(null)
    const attendanceData = ref(null)
    const todayClasses = ref([])
    const nextExam = ref(null)
    const feeNotifCount = ref(0)
    const pendingLeaves = ref(0)
    const currentDate = ref('')

    const canView = (permission) => {
      if (!permission) return true
      if (authStore.hasPermission(permission)) return true
      return STUDENT_DEFAULT_PERMISSIONS.includes(permission)
    }

    const displayName = computed(() => {
      if (student.value?.first_name) {
        const last = student.value.last_name ? ` ${student.value.last_name}` : ''
        return `${student.value.first_name}${last}`
      }
      return authStore.user?.name || 'Student'
    })

    const avatarUrl = computed(() => {
      const name = encodeURIComponent(displayName.value)
      return authStore.user?.avatar || `https://ui-avatars.com/api/?name=${name}&background=4f46e5&color=fff&size=128`
    })

    const classLabel = computed(() => {
      const cls = student.value?.current_class?.name || student.value?.currentClass?.name
      const sec = student.value?.current_section?.name || student.value?.currentSection?.name
      if (cls && sec) return `${cls} · ${sec}`
      return cls || sec || ''
    })

    const greeting = computed(() => {
      const hour = new Date().getHours()
      if (hour < 12) return 'Good Morning'
      if (hour < 17) return 'Good Afternoon'
      return 'Good Evening'
    })

    const activeEnrollments = computed(() => {
      return (dashboardData.value?.enrollments || []).filter(e => e.status === 'active')
    })

    const liveClassCount = computed(() => {
      return todayClasses.value.filter(slot => isCurrentSlot(slot)).length
    })

    const ringOffset = computed(() => {
      const pct = attendanceData.value?.percentage ?? dashboardData.value?.attendance_percentage ?? 0
      const circumference = 2 * Math.PI * 52
      return circumference - (pct / 100) * circumference
    })

    const quickActions = [
      { to: '/student/attendance', icon: '✓', label: 'Attendance', permission: 'view attendance', color: '#4f46e5' },
      { to: '/student/class-routine', icon: '🏫', label: 'Class Routine', permission: 'view class routines', color: '#0d9488' },
      { to: '/student/exams', icon: '📝', label: 'Exams', permission: 'view exams', color: '#d97706' },
      { to: '/student/exam-results', icon: '📊', label: 'Results', permission: 'view exam results', color: '#7c3aed' },
      { to: '/student/fee-dashboard', icon: '💰', label: 'Fees', permission: 'view fee collections', color: '#059669' },
      { to: '/student/fee-payment', icon: '💳', label: 'Pay Fee', permission: 'view fee collections', color: '#dc2626' },
      { to: '/student/notices', icon: '📢', label: 'Notices', permission: 'view notice board', color: '#2563eb' },
      { to: '/student/leave-apply', icon: '📋', label: 'Apply Leave', permission: null, color: '#64748b' },
    ]

    const visibleQuickActions = computed(() => {
      return quickActions
        .filter(a => canView(a.permission))
        .map(a => ({
          ...a,
          badge: a.to === '/student/fee-dashboard' && feeNotifCount.value > 0 ? feeNotifCount.value : null,
        }))
    })

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
        const [profileRes, dashRes] = await Promise.all([
          studentPortalService.profile(),
          studentPortalService.dashboard(),
        ])

        student.value = profileRes.data?.data?.student || null
        dashboardData.value = dashRes.data?.data || null

        const [attRes, todayRes, nextExamRes, leavesRes, notifRes] = await Promise.all([
          loadOptional(canView('view attendance'), () => studentPortalService.attendance()),
          loadOptional(canView('view class routines'), () => classRoutineService.getStudentTodayClasses()),
          loadOptional(canView('view exams'), () => examService.student.nextExam()),
          loadOptional(true, () => studentPortalService.leaveApplications()),
          loadOptional(canView('view fee collections'), () => smartFeeService.student.notificationCount()),
        ])

        attendanceData.value = attRes?.data?.data || null
        todayClasses.value = todayRes?.data?.data?.classes || []

        const nextExamData = nextExamRes?.data?.data
        nextExam.value = nextExamData?.has_next ? nextExamData.exam : null

        const leaves = leavesRes?.data?.data || []
        pendingLeaves.value = Array.isArray(leaves)
          ? leaves.filter(l => l.status === 'pending').length
          : 0

        const notifData = notifRes?.data?.data || {}
        feeNotifCount.value = (notifData.unread_count || 0) + (notifData.pending_count || 0)
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load dashboard.'
      } finally {
        loading.value = false
      }
    }

    const formatNumber = (num) => Number(num || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })

    const formatDate = (date) => {
      if (!date) return '—'
      return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    }

    const formatTime = (time) => {
      if (!time) return ''
      const [h, m] = time.split(':')
      const hour = parseInt(h, 10)
      const ampm = hour >= 12 ? 'PM' : 'AM'
      const h12 = hour % 12 || 12
      return `${h12}:${m} ${ampm}`
    }

    const isCurrentSlot = (slot) => {
      if (!slot?.start_time || !slot?.end_time) return false
      const now = new Date()
      const [sh, sm] = slot.start_time.split(':').map(Number)
      const [eh, em] = slot.end_time.split(':').map(Number)
      const start = new Date(now); start.setHours(sh, sm, 0, 0)
      const end = new Date(now); end.setHours(eh, em, 0, 0)
      return now >= start && now <= end
    }

    const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ') : ''

    onMounted(() => {
      currentDate.value = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      })
      loadDashboard()
    })

    return {
      loading, error, dashboardData, student, attendanceData, todayClasses,
      nextExam, feeNotifCount, pendingLeaves, currentDate,
      displayName, avatarUrl, classLabel, greeting, activeEnrollments,
      liveClassCount, ringOffset, visibleQuickActions,
      canView, loadDashboard, formatNumber, formatDate, formatTime,
      isCurrentSlot, capitalize,
    }
  },
}
</script>

<style scoped>
.student-dashboard {
  max-width: 1280px;
  margin: 0 auto;
  padding-bottom: 2rem;
}

/* Hero */
.hero {
  position: relative;
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 1.5rem;
  border: 1px solid #e0e7ff;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 45%, #7c3aed 100%);
}

.hero-content {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 2rem;
  flex-wrap: wrap;
}

.hero-profile {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.avatar {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  object-fit: cover;
  border: 3px solid rgba(255, 255, 255, 0.35);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.status-dot {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 14px;
  height: 14px;
  background: #22c55e;
  border: 2px solid #fff;
  border-radius: 50%;
}

.hero-greeting {
  margin: 0 0 0.25rem;
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 500;
}

.hero-name {
  margin: 0 0 0.75rem;
  font-size: 1.75rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.2;
}

.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #ffffff;
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
}

.chip-label {
  opacity: 0.75;
  font-weight: 500;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.1rem;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  backdrop-filter: blur(4px);
}

.refresh-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.25);
}

.refresh-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.refresh-btn svg.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Metrics */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.metric-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1.1rem;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  box-shadow: var(--shadow-sm);
  transition: transform 0.2s, box-shadow 0.2s;
}

.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
}

.metric-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.metric-indigo .metric-icon { background: #eef2ff; }
.metric-teal .metric-icon { background: #ccfbf1; }
.metric-amber .metric-icon { background: #fef3c7; }
.metric-violet .metric-icon { background: #ede9fe; }
.metric-green .metric-icon { background: #d1fae5; }
.metric-danger .metric-icon { background: #fee2e2; }
.metric-orange .metric-icon { background: #ffedd5; }
.metric-slate .metric-icon { background: var(--bg-accent); }

.metric-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.metric-label {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1.2;
  margin: 0.15rem 0;
}

.metric-sub {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.metric-link {
  font-size: 0.75rem;
  color: #4f46e5;
  font-weight: 600;
  text-decoration: none;
  margin-top: 0.15rem;
}

.metric-link:hover { text-decoration: underline; }

.danger-text { color: #dc2626 !important; }

.status-present { color: #059669; }
.status-absent { color: #dc2626; }
.status-late { color: #d97706; }
.status-leave { color: #2563eb; }

/* Quick Actions */
.quick-section {
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.85rem;
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.75rem;
}

.quick-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 0.75rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  text-decoration: none;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}

.quick-card:hover {
  border-color: var(--accent);
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1);
}

.quick-icon {
  font-size: 1.5rem;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--accent) 12%, white);
  border-radius: 12px;
}

.quick-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-dark);
  text-align: center;
}

.quick-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #dc2626;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 999px;
}

/* Main Grid */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  align-items: start;
}

.main-col, .side-col {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.panel {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1.25rem;
  box-shadow: var(--shadow-sm);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.panel-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
}

.panel-count {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 600;
  background: var(--bg-accent);
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}

.panel-link {
  font-size: 0.8rem;
  color: #4f46e5;
  font-weight: 600;
  text-decoration: none;
}

.panel-link:hover { text-decoration: underline; }

.panel-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

/* Enrollments */
.enrollment-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.75rem;
}

.enrollment-card {
  border: 1px solid var(--border-light);
  border-radius: 12px;
  padding: 1rem;
  background: var(--bg-surface-muted);
}

.enrollment-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.enrollment-course {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}

.enrollment-status {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  background: #d1fae5;
  color: #059669;
  padding: 2px 8px;
  border-radius: 999px;
}

.enrollment-batch {
  margin: 0;
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 600;
}

.enrollment-session {
  margin: 0.25rem 0 0;
  font-size: 0.75rem;
  color: var(--text-muted);
}

/* Schedule */
.schedule-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.schedule-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--border-light);
  border-radius: 12px;
  background: var(--bg-surface-muted);
}

.schedule-item.is-live {
  border-color: #86efac;
  background: #f0fdf4;
}

.schedule-time {
  display: flex;
  flex-direction: column;
  min-width: 72px;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.schedule-time strong {
  font-size: 0.85rem;
  color: var(--text-primary);
}

.schedule-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.schedule-info strong {
  font-size: 0.9rem;
  color: var(--text-primary);
}

.schedule-info span {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.live-pill {
  font-size: 0.65rem;
  font-weight: 700;
  background: #22c55e;
  color: #fff;
  padding: 3px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Exams */
.exam-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.exam-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem;
  border: 1px solid var(--border-light);
  border-radius: 12px;
  background: var(--bg-surface-muted);
}

.exam-icon-wrap {
  width: 40px;
  height: 40px;
  background: #fef3c7;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.exam-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.exam-info strong {
  font-size: 0.9rem;
  color: var(--text-primary);
}

.exam-info span {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.exam-date strong {
  font-size: 0.8rem;
  color: #4f46e5;
  font-weight: 700;
  white-space: nowrap;
}

/* Attendance Ring */
.attendance-ring-wrap {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.attendance-ring {
  position: relative;
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.attendance-ring svg {
  transform: rotate(-90deg);
  width: 120px;
  height: 120px;
}

.ring-bg {
  fill: none;
  stroke: var(--border-color);
  stroke-width: 10;
}

.ring-fill {
  fill: none;
  stroke: #4f46e5;
  stroke-width: 10;
  stroke-linecap: round;
  stroke-dasharray: 326.73;
  transition: stroke-dashoffset 0.6s ease;
}

.ring-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.ring-center strong {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1;
}

.ring-center span {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 2px;
}

.attendance-breakdown {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.breakdown-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.breakdown-row strong {
  margin-left: auto;
  color: var(--text-primary);
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot.present { background: #22c55e; }
.dot.absent { background: #ef4444; }
.dot.late { background: #f59e0b; }
.dot.leave { background: #3b82f6; }

/* Notices */
.notice-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.notice-item {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 0.85rem;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  background: var(--bg-surface-muted);
}

.notice-item strong {
  font-size: 0.85rem;
  color: var(--text-primary);
  line-height: 1.4;
}

.notice-date {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.priority-badge {
  align-self: flex-start;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 999px;
}

.priority-high, .priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-medium { background: #fef3c7; color: #d97706; }
.priority-normal, .priority-low { background: #e0e7ff; color: #4f46e5; }

/* Fee Snapshot */
.fee-snapshot {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.fee-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: var(--text-secondary);
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-light);
}

.fee-row strong {
  font-size: 1rem;
  color: var(--text-primary);
}

.pay-now-btn {
  display: block;
  text-align: center;
  padding: 0.75rem;
  background: #4f46e5;
  color: #ffffff;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  margin-top: 0.25rem;
  transition: background 0.2s;
}

.pay-now-btn:hover {
  background: #4338ca;
}

/* States */
.state-card {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
}

.empty-icon {
  font-size: 3rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 1024px) {
  .main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .hero-content {
    padding: 1.25rem;
  }

  .hero-name {
    font-size: 1.35rem;
  }

  .metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .attendance-ring-wrap {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
