<template>
  <div class="teacher-dashboard">
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
              <span v-if="teacherId" class="meta-chip">
                <span class="chip-label">ID</span>
                {{ teacherId }}
              </span>
              <span v-if="designation" class="meta-chip">
                <span class="chip-label">Role</span>
                {{ designation }}
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

    <div v-if="loading && !loaded" class="state-card loading-card">
      <ProgressSpinner strokeWidth="3" />
      <p>Loading your dashboard...</p>
    </div>

    <div v-else-if="error" class="state-card error-card">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Try Again" icon="pi pi-refresh" @click="loadDashboard" class="p-button-outlined mt-3" />
    </div>

    <template v-else>
      <section class="metrics-grid">
        <div v-if="canView('view my schedule')" class="metric-card metric-violet">
          <div class="metric-icon">🏫</div>
          <div class="metric-body">
            <span class="metric-label">Classes Today</span>
            <span class="metric-value">{{ todayClasses.length }}</span>
            <span class="metric-sub">{{ liveClassCount }} live · {{ weeklyClassCount }} this week</span>
          </div>
        </div>

        <div v-if="canView('view exam routines')" class="metric-card metric-amber">
          <div class="metric-icon">📋</div>
          <div class="metric-body">
            <span class="metric-label">Exam Duties Today</span>
            <span class="metric-value">{{ todayDuties.length }}</span>
            <span class="metric-sub">{{ upcomingDuties.length }} upcoming</span>
          </div>
        </div>

        <div v-if="canView('view attendance')" class="metric-card metric-indigo">
          <div class="metric-icon">✓</div>
          <div class="metric-body">
            <span class="metric-label">My Attendance</span>
            <span class="metric-value">{{ attendanceData?.percentage || 0 }}%</span>
            <span v-if="attendanceData?.today_status" class="metric-sub" :class="'status-' + attendanceData.today_status">
              Today: {{ capitalize(attendanceData.today_status) }}
            </span>
          </div>
        </div>

        <div v-if="canView('view student attendance') && classAttendance" class="metric-card metric-teal">
          <div class="metric-icon">👨‍🎓</div>
          <div class="metric-body">
            <span class="metric-label">Students Present</span>
            <span class="metric-value">{{ classAttendance.percentage || 0 }}%</span>
            <span class="metric-sub">{{ classAttendance.present || 0 }} of {{ classAttendance.total || 0 }} today</span>
          </div>
        </div>

        <div v-if="canView('view questions')" class="metric-card metric-purple">
          <div class="metric-icon">❓</div>
          <div class="metric-body">
            <span class="metric-label">My Questions</span>
            <span class="metric-value">{{ questionCount }}</span>
            <router-link to="/dashboard/teacher/questions" class="metric-link">Manage questions</router-link>
          </div>
        </div>

        <div v-if="canView('view notifications') && unreadNotifs > 0" class="metric-card metric-slate">
          <div class="metric-icon">🔔</div>
          <div class="metric-body">
            <span class="metric-label">Notifications</span>
            <span class="metric-value">{{ unreadNotifs }}</span>
            <span class="metric-sub">Unread messages</span>
          </div>
        </div>
      </section>

      <PortalCmsSummary portal="teacher" />

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
          <section v-if="canView('view my schedule')" class="panel">
            <div class="panel-header">
              <h3>Today's Classes</h3>
              <router-link to="/dashboard/teacher/my-schedule" class="panel-link">Full Schedule</router-link>
            </div>
            <div v-if="todayClasses.length === 0" class="panel-empty">
              <p>No classes scheduled for today.</p>
            </div>
            <div v-else class="schedule-list">
              <div
                v-for="slot in todayClasses.slice(0, 6)"
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
                  <span>{{ slot.batch?.name || slot.class?.name || slot.room?.name || '' }}</span>
                </div>
                <span v-if="isCurrentSlot(slot)" class="live-pill">Live</span>
              </div>
            </div>
          </section>

          <section v-if="canView('view exam routines')" class="panel">
            <div class="panel-header">
              <h3>Today's Exam Duties</h3>
              <router-link to="/dashboard/teacher/exam-duties" class="panel-link">All Duties</router-link>
            </div>
            <div v-if="todayDuties.length === 0" class="panel-empty">
              <p>No exam duties scheduled for today.</p>
            </div>
            <div v-else class="duty-list">
              <div v-for="duty in todayDuties" :key="duty.id" class="duty-item">
                <div class="duty-icon-wrap">📝</div>
                <div class="duty-info">
                  <strong>{{ duty.exam }}</strong>
                  <span>{{ duty.subject }} · {{ duty.room }}</span>
                </div>
                <div class="duty-time">
                  <strong>{{ duty.start_time }}</strong>
                  <span>{{ duty.end_time }}</span>
                </div>
              </div>
            </div>
          </section>

          <section v-if="canView('view exam routines')" class="panel">
            <div class="panel-header">
              <h3>Upcoming Exam Duties</h3>
              <router-link to="/dashboard/teacher/my-exam-routines" class="panel-link">Exam Routines</router-link>
            </div>
            <div v-if="upcomingDuties.length === 0" class="panel-empty">
              <p>No upcoming exam duties.</p>
            </div>
            <div v-else class="duty-list">
              <div v-for="duty in upcomingDuties.slice(0, 5)" :key="duty.id" class="duty-item">
                <div class="duty-icon-wrap">📅</div>
                <div class="duty-info">
                  <strong>{{ duty.exam }}</strong>
                  <span>{{ duty.subject }} · {{ duty.room }}</span>
                </div>
                <div class="duty-time">
                  <strong>{{ duty.date }}</strong>
                  <span>{{ duty.start_time }}</span>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="side-col">
          <section v-if="canView('view attendance') && attendanceData" class="panel">
            <div class="panel-header">
              <h3>My Attendance</h3>
              <router-link to="/dashboard/my-attendance" class="panel-link">Details</router-link>
            </div>
            <div class="attendance-ring-wrap">
              <div class="attendance-ring">
                <svg viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="52" class="ring-bg"/>
                  <circle cx="60" cy="60" r="52" class="ring-fill" :style="{ strokeDashoffset: ringOffset }"/>
                </svg>
                <div class="ring-center">
                  <strong>{{ attendanceData.percentage || 0 }}%</strong>
                  <span>Overall</span>
                </div>
              </div>
              <div class="attendance-breakdown">
                <div class="breakdown-row"><span class="dot present"></span> Present <strong>{{ attendanceData.present || 0 }}</strong></div>
                <div class="breakdown-row"><span class="dot absent"></span> Absent <strong>{{ attendanceData.absent || 0 }}</strong></div>
                <div class="breakdown-row"><span class="dot late"></span> Late <strong>{{ attendanceData.late || 0 }}</strong></div>
                <div class="breakdown-row"><span class="dot leave"></span> Leave <strong>{{ attendanceData.leave || 0 }}</strong></div>
              </div>
            </div>
          </section>

          <section v-if="canView('view student attendance') && classAttendance" class="panel">
            <div class="panel-header">
              <h3>Class Attendance Today</h3>
              <router-link to="/dashboard/attendance/students" class="panel-link">Mark Attendance</router-link>
            </div>
            <div class="class-attendance-snapshot">
              <div class="snapshot-row">
                <span>Present</span>
                <strong class="success-text">{{ classAttendance.present || 0 }}</strong>
              </div>
              <div class="snapshot-row">
                <span>Absent</span>
                <strong class="danger-text">{{ classAttendance.absent || 0 }}</strong>
              </div>
              <div class="snapshot-row">
                <span>Attendance Rate</span>
                <strong>{{ classAttendance.percentage || 0 }}%</strong>
              </div>
            </div>
          </section>

          <section class="panel work-panel">
            <div class="panel-header">
              <h3>Work Summary</h3>
            </div>
            <div class="work-summary">
              <div class="work-row">
                <span>Weekly Classes</span>
                <strong>{{ weeklyClassCount }}</strong>
              </div>
              <div class="work-row">
                <span>Exam Duties Today</span>
                <strong>{{ todayDuties.length }}</strong>
              </div>
              <div class="work-row">
                <span>Upcoming Duties</span>
                <strong>{{ upcomingDuties.length }}</strong>
              </div>
              <div v-if="canView('view questions')" class="work-row">
                <span>Question Bank</span>
                <strong>{{ questionCount }}</strong>
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
import classRoutineService from '@/services/class-routine.service'
import examService from '@/services/exam.service'
import attendanceService from '@/services/attendance.service'
import communicationService from '@/services/communication.service'
import PortalCmsSummary from '@/components/dashboard/PortalCmsSummary.vue'

const TEACHER_DEFAULT_PERMISSIONS = [
  'view teachers',
  'view classes', 'view sections', 'view subjects',
  'view students',
  'view attendance',
  'view student attendance', 'view teacher attendance',
  'view attendance reports',
  'view exams', 'view exam types', 'view exam routines', 'view exam results',
  'create exam results', 'edit exam results',
  'view notice board', 'view notifications',
  'view my schedule',
  'view questions', 'create questions',
]

export default {
  name: 'TeacherDashboardPage',
  components: { PortalCmsSummary },
  setup() {
    const authStore = useAuthStore()
    const loading = ref(false)
    const loaded = ref(false)
    const error = ref(null)
    const currentDate = ref('')
    const todayClasses = ref([])
    const weeklyClassCount = ref(0)
    const todayDuties = ref([])
    const upcomingDuties = ref([])
    const attendanceData = ref(null)
    const classAttendance = ref(null)
    const questionCount = ref(0)
    const unreadNotifs = ref(0)

    const canView = (permission) => {
      if (!permission) return true
      if (authStore.hasPermission(permission)) return true
      return TEACHER_DEFAULT_PERMISSIONS.includes(permission)
    }

    const displayName = computed(() => authStore.user?.name || 'Teacher')
    const teacherId = computed(() => authStore.user?.teacher_id || authStore.user?.employee_id || '')
    const designation = computed(() => authStore.user?.designation || authStore.user?.role || 'Teacher')

    const avatarUrl = computed(() => {
      const name = encodeURIComponent(displayName.value)
      return authStore.user?.avatar || `https://ui-avatars.com/api/?name=${name}&background=7c3aed&color=fff&size=128`
    })

    const greeting = computed(() => {
      const hour = new Date().getHours()
      if (hour < 12) return 'Good Morning'
      if (hour < 17) return 'Good Afternoon'
      return 'Good Evening'
    })

    const liveClassCount = computed(() => todayClasses.value.filter(slot => isCurrentSlot(slot)).length)

    const ringOffset = computed(() => {
      const pct = attendanceData.value?.percentage || 0
      const circumference = 2 * Math.PI * 52
      return circumference - (pct / 100) * circumference
    })

    const quickActions = [
      { to: '/dashboard/teacher/my-schedule', icon: '📅', label: 'My Schedule', permission: 'view my schedule', color: '#7c3aed' },
      { to: '/dashboard/my-attendance', icon: '✓', label: 'My Attendance', permission: 'view attendance', color: '#4f46e5' },
      { to: '/dashboard/attendance/students', icon: '👨‍🎓', label: 'Mark Attendance', permission: 'view student attendance', color: '#0d9488' },
      { to: '/dashboard/teacher/exam-marks', icon: '✏️', label: 'Marks Entry', permission: 'create exam results', color: '#d97706' },
      { to: '/dashboard/teacher/exam-duties', icon: '📋', label: 'Exam Duties', permission: 'view exam routines', color: '#dc2626' },
      { to: '/dashboard/teacher/questions', icon: '❓', label: 'My Questions', permission: 'view questions', color: '#2563eb' },
      { to: '/dashboard/students', icon: '🎓', label: 'Students', permission: 'view students', color: '#059669' },
      { to: '/dashboard/communication/notice-board', icon: '📢', label: 'Notices', permission: 'view notice board', color: '#db2777' },
    ]

    const visibleQuickActions = computed(() => quickActions.filter(a => canView(a.permission)))

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
        const [
          todayRes, scheduleRes, todayDutyRes, upcomingDutyRes,
          attRes, overviewRes, questionsRes, notifRes,
        ] = await Promise.all([
          loadOptional(canView('view my schedule'), () => classRoutineService.getTeacherTodayClasses()),
          loadOptional(canView('view my schedule'), () => classRoutineService.getTeacherSchedule()),
          loadOptional(canView('view exam routines'), () => examService.teacher.todayDuties()),
          loadOptional(canView('view exam routines'), () => examService.teacher.upcomingDuties()),
          loadOptional(canView('view attendance'), () => attendanceService.getMyAttendance()),
          loadOptional(canView('view student attendance'), () => attendanceService.getTodayOverview()),
          loadOptional(canView('view questions'), () => examService.questions.list({ per_page: 1, my_only: 1 })),
          loadOptional(canView('view notifications'), () => communicationService.unreadCount()),
        ])

        todayClasses.value = todayRes?.data?.data?.classes || []
        weeklyClassCount.value = scheduleRes?.data?.data?.flat?.length || 0
        todayDuties.value = todayDutyRes?.data?.data?.routines || []
        upcomingDuties.value = upcomingDutyRes?.data?.data?.routines || []
        attendanceData.value = attRes?.data?.data || null

        const overview = overviewRes?.data?.data
        classAttendance.value = overview?.students || null

        questionCount.value = questionsRes?.data?.meta?.total ?? 0
        unreadNotifs.value = notifRes?.data?.data?.count ?? notifRes?.data?.count ?? 0

        loaded.value = true
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load dashboard.'
      } finally {
        loading.value = false
      }
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
      const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
      return currentTime >= slot.start_time && currentTime <= slot.end_time
    }

    const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ') : ''

    onMounted(() => {
      currentDate.value = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      })
      loadDashboard()
    })

    return {
      loading, loaded, error, currentDate, displayName, teacherId, designation,
      avatarUrl, greeting, todayClasses, weeklyClassCount, todayDuties, upcomingDuties,
      attendanceData, classAttendance, questionCount, unreadNotifs,
      liveClassCount, ringOffset, visibleQuickActions,
      canView, loadDashboard, formatTime, isCurrentSlot, capitalize,
    }
  },
}
</script>

<style scoped>
.teacher-dashboard {
  max-width: 1280px;
  margin: 0 auto;
  padding-bottom: 2rem;
}

.hero {
  position: relative;
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 1.5rem;
  border: 1px solid #ddd6fe;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 45%, #4f46e5 100%);
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

.avatar-wrap { position: relative; flex-shrink: 0; }

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
  color: #ffffff;
  font-weight: 600;
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
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}

.chip-label {
  font-weight: 600;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.1rem;
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}

.refresh-btn:hover:not(:disabled) { background: rgba(255, 255, 255, 0.28); }
.refresh-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.refresh-btn svg.spinning { animation: spin 1s linear infinite; }

@keyframes spin { to { transform: rotate(360deg); } }

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

.metric-violet .metric-icon { background: #ede9fe; }
.metric-amber .metric-icon { background: #fef3c7; }
.metric-indigo .metric-icon { background: #eef2ff; }
.metric-teal .metric-icon { background: #ccfbf1; }
.metric-purple .metric-icon { background: #f3e8ff; }
.metric-slate .metric-icon { background: var(--bg-accent); }

.metric-body { display: flex; flex-direction: column; min-width: 0; }

.metric-label {
  font-size: 0.75rem;
  color: var(--text-primary);
  font-weight: 700;
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
  color: var(--text-dark);
  font-weight: 600;
}

.metric-link {
  font-size: 0.75rem;
  color: #7c3aed;
  font-weight: 700;
  text-decoration: none;
  margin-top: 0.15rem;
}

.metric-link:hover { text-decoration: underline; }

.status-present { color: #059669; }
.status-absent { color: #dc2626; }
.status-late { color: #d97706; }
.status-leave { color: #2563eb; }

.quick-section { margin-bottom: 1.5rem; }

.section-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 0.85rem;
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.75rem;
}

.quick-card {
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
  font-weight: 800;
  color: var(--text-primary);
  text-align: center;
}

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
  font-weight: 800;
  color: var(--text-primary);
}

.panel-link {
  font-size: 0.8rem;
  color: #7c3aed;
  font-weight: 700;
  text-decoration: none;
}

.panel-link:hover { text-decoration: underline; }

.panel-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-dark);
  font-size: 0.85rem;
  font-weight: 600;
}

.schedule-list, .duty-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.schedule-item, .duty-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--border-light);
  border-radius: 12px;
  background: var(--bg-surface-muted);
}

.schedule-item.is-live {
  border-color: #c4b5fd;
  background: #f5f3ff;
}

.schedule-time, .duty-time {
  display: flex;
  flex-direction: column;
  min-width: 72px;
  font-size: 0.75rem;
  color: var(--text-dark);
  font-weight: 600;
}

.schedule-time strong, .duty-time strong {
  font-size: 0.85rem;
  color: var(--text-primary);
}

.schedule-info, .duty-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.schedule-info strong, .duty-info strong {
  font-size: 0.9rem;
  color: var(--text-primary);
  font-weight: 700;
}

.schedule-info span, .duty-info span {
  font-size: 0.75rem;
  color: var(--text-dark);
  font-weight: 600;
}

.duty-icon-wrap {
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

.live-pill {
  font-size: 0.65rem;
  font-weight: 800;
  background: #7c3aed;
  color: #fff;
  padding: 3px 8px;
  border-radius: 999px;
  text-transform: uppercase;
}

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

.ring-bg { fill: none; stroke: var(--border-color); stroke-width: 10; }

.ring-fill {
  fill: none;
  stroke: #7c3aed;
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
}

.ring-center span {
  font-size: 0.7rem;
  color: var(--text-dark);
  font-weight: 700;
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
  color: var(--text-dark);
  font-weight: 600;
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

.class-attendance-snapshot, .work-summary {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.snapshot-row, .work-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: var(--text-dark);
  font-weight: 600;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-light);
}

.snapshot-row strong, .work-row strong {
  font-size: 1rem;
  color: var(--text-primary);
  font-weight: 800;
}

.success-text { color: #059669 !important; }
.danger-text { color: #dc2626 !important; }

.state-card {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
}

@media (max-width: 1024px) {
  .main-grid { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .hero-content { padding: 1.25rem; }
  .hero-name { font-size: 1.35rem; }
  .metrics-grid { grid-template-columns: repeat(2, 1fr); }
  .attendance-ring-wrap { flex-direction: column; align-items: stretch; }
}
</style>
