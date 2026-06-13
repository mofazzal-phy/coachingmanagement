<template>
  <div class="attendance-page">
    <div class="page-header">
      <div>
        <h1>📋 My Attendance</h1>
        <p class="text-muted">Live view of your attendance records</p>
      </div>
      <div class="header-meta">
        <span v-if="todayStatus" class="today-badge" :class="'status-' + todayStatus">
          Today: {{ todayStatus }}
        </span>
        <span class="live-badge">LIVE</span>
        <span v-if="lastUpdated" class="last-updated">Updated: {{ lastUpdated }}</span>
        <button class="refresh-btn" @click="loadAttendance(true)" :disabled="loading">
          ⟳ {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </div>

    <div v-if="loading && !attendanceData" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>Loading attendance...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Try Again" icon="pi pi-refresh" @click="loadAttendance(true)" class="p-button-outlined mt-3" />
    </div>

    <template v-else-if="attendanceData">
      <div class="today-section">
        <h3>Today's Attendance</h3>
        <div class="today-split">
          <div class="today-block">
            <h4>Daily</h4>
            <div v-if="attendanceData.today_daily" class="today-cards">
              <div class="today-card">
                <div class="today-card-top">
                  <span class="status-badge" :class="attendanceData.today_daily.status">{{ attendanceData.today_daily.status }}</span>
                  <span v-if="attendanceData.today_daily.check_in_display || attendanceData.today_daily.check_in" class="time-meta">
                    In: {{ attendanceData.today_daily.check_in_display || attendanceData.today_daily.check_in }}
                  </span>
                  <span v-if="attendanceData.today_daily.late_minutes > 0" class="late-meta">{{ attendanceData.today_daily.late_minutes }} min late</span>
                  <span v-if="attendanceData.today_daily.check_out_display || attendanceData.today_daily.check_out" class="time-meta">
                    Out: {{ attendanceData.today_daily.check_out_display || attendanceData.today_daily.check_out }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="today-empty"><p>No daily attendance marked yet.</p></div>
          </div>
          <div class="today-block" v-if="attendanceData.today_sessions?.length">
            <h4>Sessions</h4>
            <div class="today-cards">
              <div
                v-for="record in attendanceData.today_sessions"
                :key="`${record.id}-${record.updated_at || record.status}`"
                class="today-card session-card"
              >
                <div class="today-card-top">
                  <span class="status-badge" :class="record.status">{{ record.status }}</span>
                  <span v-if="record.check_in_display || record.check_in" class="time-meta">
                    In: {{ record.check_in_display || record.check_in }}
                  </span>
                  <span v-if="record.late_minutes > 0" class="late-meta">{{ record.late_minutes }} min late</span>
                </div>
                <div class="today-card-detail">{{ recordDetail(record) }}</div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!attendanceData.today_daily && !attendanceData.today_sessions?.length" class="today-empty">
          <p>No attendance marked for today yet.</p>
        </div>
      </div>

      <div v-if="isTeacher && teacherClassLedger?.summary" class="teacher-class-section">
        <h3>My Classes <span class="period-hint">(last 30 days)</span></h3>
        <div class="class-stats-grid">
          <div class="class-stat completed">
            <div class="class-stat-value">{{ teacherClassLedger.summary.completed }}</div>
            <div class="class-stat-label">Completed</div>
          </div>
          <div class="class-stat pending">
            <div class="class-stat-value">{{ teacherClassLedger.summary.pending }}</div>
            <div class="class-stat-label">Pending</div>
          </div>
          <div class="class-stat no-show">
            <div class="class-stat-value">{{ teacherClassLedger.summary.no_show }}</div>
            <div class="class-stat-label">No Show</div>
          </div>
          <div class="class-stat cancelled">
            <div class="class-stat-value">{{ teacherClassLedger.summary.cancelled }}</div>
            <div class="class-stat-label">Cancelled</div>
          </div>
          <div class="class-stat rate">
            <div class="class-stat-value">{{ teacherClassLedger.summary.completion_rate }}%</div>
            <div class="class-stat-label">Completion Rate</div>
          </div>
        </div>

        <div v-if="teacherClassLedger.today_classes?.length" class="today-classes-block">
          <h4>Today's Classes</h4>
          <div class="class-records-table">
            <table>
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Subject</th>
                  <th>Batch</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in teacherClassLedger.today_classes" :key="item.class_session_id || item.ledger_id">
                  <td>{{ classTimeLabel(item) }}</td>
                  <td>{{ item.subject_name }}</td>
                  <td>{{ item.batch_name || '—' }}</td>
                  <td><span class="class-status-badge" :class="'cls-' + item.status">{{ item.status_label }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="teacherSubjectSummary?.length" class="subject-summary-section teacher-subject-section">
          <h4>Subject-wise Completion</h4>
          <div class="subject-summary-grid">
            <div
              v-for="item in teacherSubjectSummary"
              :key="item.subject_id || item.subject_name"
              class="subject-summary-card"
            >
              <strong>{{ item.subject_name }}</strong>
              <span class="subject-pct">{{ item.completion_rate }}%</span>
              <span class="subject-meta">
                {{ item.completed }} completed · {{ item.pending }} pending
                <template v-if="item.no_show"> · {{ item.no_show }} no show</template>
              </span>
            </div>
          </div>
        </div>

        <div v-if="teacherClassLedger.recent_classes?.length" class="recent-classes-block">
          <h4>Recent Class History</h4>
          <div class="class-records-table">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Subject</th>
                  <th>Batch</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in teacherClassLedger.recent_classes" :key="(item.class_session_id || item.ledger_id) + item.date">
                  <td>{{ formatDate(item.date) }}</td>
                  <td>{{ classTimeLabel(item) }}</td>
                  <td>{{ item.subject_name }}</td>
                  <td>{{ item.batch_name || '—' }}</td>
                  <td><span class="class-status-badge" :class="'cls-' + item.status">{{ item.status_label }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="!teacherClassLedger.today_classes?.length && !teacherClassLedger.recent_classes?.length" class="today-empty">
          <p>No class sessions found for the last 30 days. Classes appear after admin syncs from routine.</p>
        </div>
      </div>

      <div v-if="attendanceData.subject_summary?.length && !isTeacher" class="subject-summary-section">
        <h3>Subject Summary</h3>
        <div class="subject-summary-grid">
          <div v-for="item in attendanceData.subject_summary" :key="item.subject_id || item.subject_name" class="subject-summary-card">
            <strong>{{ item.subject_name }}</strong>
            <span class="subject-pct">{{ item.percentage }}%</span>
            <span class="subject-meta" v-if="item.expected_sessions != null">
              {{ item.attended_sessions ?? item.present ?? 0 }} / {{ item.expected_sessions ?? item.total ?? 0 }} sessions
            </span>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-value">{{ attendanceData.total || 0 }}</div>
          <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card present">
          <div class="stat-value">{{ attendanceData.present || 0 }}</div>
          <div class="stat-label">Present</div>
        </div>
        <div class="stat-card absent">
          <div class="stat-value">{{ attendanceData.absent || 0 }}</div>
          <div class="stat-label">Absent</div>
        </div>
        <div class="stat-card late">
          <div class="stat-value">{{ attendanceData.late || 0 }}</div>
          <div class="stat-label">Late</div>
        </div>
        <div class="stat-card leave">
          <div class="stat-value">{{ attendanceData.leave || 0 }}</div>
          <div class="stat-label">Leave</div>
        </div>
        <div class="stat-card excused">
          <div class="stat-value">{{ attendanceData.half_day || 0 }}</div>
          <div class="stat-label">Excused</div>
        </div>
        <div class="stat-card percentage">
          <div class="stat-value">{{ attendanceData.percentage || 0 }}%</div>
          <div class="stat-label">Attendance Rate</div>
        </div>
      </div>

      <div class="attendance-bar-section">
        <h3>Attendance Overview</h3>
        <div class="attendance-bar">
          <div class="bar-segment present" :style="{ width: presentPercent + '%' }"></div>
          <div class="bar-segment absent" :style="{ width: absentPercent + '%' }"></div>
          <div class="bar-segment late" :style="{ width: latePercent + '%' }"></div>
          <div class="bar-segment leave" :style="{ width: leavePercent + '%' }"></div>
          <div class="bar-segment excused" :style="{ width: halfDayPercent + '%' }"></div>
        </div>
        <div class="bar-legend">
          <span><span class="dot present"></span> Present ({{ attendanceData.present || 0 }})</span>
          <span><span class="dot absent"></span> Absent ({{ attendanceData.absent || 0 }})</span>
          <span><span class="dot late"></span> Late ({{ attendanceData.late || 0 }})</span>
          <span><span class="dot leave"></span> Leave ({{ attendanceData.leave || 0 }})</span>
          <span><span class="dot excused"></span> Excused ({{ attendanceData.half_day || 0 }})</span>
        </div>
      </div>

      <div class="recent-records">
        <h3>Recent Daily Records</h3>
        <div v-if="!attendanceData.recent_records?.length" class="empty-section">
          <p>No attendance records found yet.</p>
        </div>
        <div v-else class="records-table">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>{{ detailColumnLabel }}</th>
                <th>Status</th>
                <th>Check In</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="record in attendanceData.recent_records" :key="record.id">
                <td>{{ formatDate(record.date) }}</td>
                <td>{{ recordDetail(record) }}</td>
                <td>
                  <span class="status-badge" :class="record.status">{{ record.status }}</span>
                </td>
                <td>
                  {{ record.check_in_display || record.check_in || '—' }}
                  <span v-if="record.late_minutes > 0" class="late-meta-inline">({{ record.late_minutes }}m late)</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-else class="empty-state">
      <i class="pi pi-info-circle empty-icon"></i>
      <h3>No Attendance Data</h3>
      <p>Your attendance will appear here once marked by administration.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import attendanceService from '@/services/attendance.service'
import { nowTime12, formatTime12 } from '@/utils/datetime'

const POLL_INTERVAL_MS = 15000

const loading = ref(false)
const error = ref(null)
const attendanceData = ref(null)
const lastUpdated = ref(null)
let pollTimer = null

const todayStatus = computed(() => attendanceData.value?.today_status || null)

const isTeacher = computed(() => attendanceData.value?.user_type === 'teacher')

const teacherClassLedger = computed(() => attendanceData.value?.class_ledger || null)

const teacherSubjectSummary = computed(() => teacherClassLedger.value?.subject_summary || [])

const detailColumnLabel = computed(() => {
  const type = attendanceData.value?.user_type
  if (type === 'teacher' || type === 'student') return 'Subject'
  if (type === 'employee') return 'Department'
  return 'Details'
})

const presentPercent = computed(() => {
  const total = attendanceData.value?.total || 1
  return ((attendanceData.value?.present || 0) / total) * 100
})
const absentPercent = computed(() => {
  const total = attendanceData.value?.total || 1
  return ((attendanceData.value?.absent || 0) / total) * 100
})
const latePercent = computed(() => {
  const total = attendanceData.value?.total || 1
  return ((attendanceData.value?.late || 0) / total) * 100
})
const leavePercent = computed(() => {
  const total = attendanceData.value?.total || 1
  return ((attendanceData.value?.leave || 0) / total) * 100
})
const halfDayPercent = computed(() => {
  const total = attendanceData.value?.total || 1
  return ((attendanceData.value?.half_day || 0) / total) * 100
})

const loadAttendance = async (showSpinner = false) => {
  if (showSpinner) loading.value = true
  error.value = null
  try {
    const res = await attendanceService.getMyAttendance()
    attendanceData.value = res.data?.data || null
    lastUpdated.value = nowTime12()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load attendance.'
  } finally {
    loading.value = false
  }
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

const recordDetail = (record) => {
  return record.subject?.name || record.department?.name || record.batch?.name || 'N/A'
}

const classTimeLabel = (item) => {
  if (item.start_time && item.end_time) {
    return `${formatTime12(item.start_time)} – ${formatTime12(item.end_time)}`
  }
  if (item.start_time) return formatTime12(item.start_time)
  return '—'
}

const sourceLabel = (source) => {
  if (source === 'biometric') return 'Fingerprint'
  if (source === 'manual') return 'Manual'
  return source
}

const handleVisibility = () => {
  if (document.visibilityState === 'visible') {
    loadAttendance(false)
  }
}

onMounted(() => {
  loadAttendance(true)
  pollTimer = setInterval(() => loadAttendance(false), POLL_INTERVAL_MS)
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<style scoped>
.attendance-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 0.5rem;
  overflow-x: hidden;
  width: 100%;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  color: var(--text-primary);
}

.text-muted {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

.header-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.live-badge {
  background: #dc2626;
  color: white;
  font-size: 0.65rem;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-weight: 700;
  letter-spacing: 0.5px;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.last-updated {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.refresh-btn {
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  border-radius: 8px;
  padding: 0.35rem 0.75rem;
  font-size: 0.8rem;
  cursor: pointer;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.today-badge {
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.today-badge.status-present { background: #d1fae5; color: #059669; }
.today-badge.status-absent { background: #fee2e2; color: #dc2626; }
.today-badge.status-late { background: #fef3c7; color: #d97706; }
.today-badge.status-leave,
.today-badge.status-half_day { background: #ede9fe; color: #8b5cf6; }

.today-section {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.today-section h3 {
  margin: 0 0 1rem;
  font-size: 1rem;
  color: var(--text-secondary);
}

.today-split {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.today-block h4 {
  margin: 0 0 0.65rem;
  font-size: 0.85rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.subject-summary-section {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.subject-summary-section h3 {
  margin: 0 0 1rem;
  font-size: 1rem;
  color: var(--text-secondary);
}

.subject-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}

.subject-summary-card {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.subject-pct {
  font-size: 1.25rem;
  font-weight: 700;
  color: #2563eb;
}

.subject-meta {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.teacher-class-section {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.teacher-class-section h3 {
  margin: 0 0 1rem;
  font-size: 1rem;
  color: var(--text-secondary);
}

.teacher-class-section h4 {
  margin: 1.25rem 0 0.75rem;
  font-size: 0.88rem;
  color: var(--text-secondary);
}

.period-hint {
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--text-muted);
}

.class-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 0.65rem;
}

.class-stat {
  border-radius: 10px;
  padding: 0.75rem;
  text-align: center;
  border: 1px solid var(--border-color);
}

.class-stat-value {
  font-size: 1.35rem;
  font-weight: 700;
}

.class-stat-label {
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: var(--text-muted);
  margin-top: 0.15rem;
}

.class-stat.completed { background: #ecfdf5; }
.class-stat.completed .class-stat-value { color: #059669; }
.class-stat.pending { background: #eff6ff; }
.class-stat.pending .class-stat-value { color: #2563eb; }
.class-stat.no-show { background: #fef2f2; }
.class-stat.no-show .class-stat-value { color: #dc2626; }
.class-stat.cancelled { background: #f3f4f6; }
.class-stat.cancelled .class-stat-value { color: var(--text-muted); }
.class-stat.rate { background: #f5f3ff; }
.class-stat.rate .class-stat-value { color: #7c3aed; }

.class-records-table {
  overflow-x: auto;
}

.class-records-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.class-records-table th,
.class-records-table td {
  padding: 0.55rem 0.65rem;
  text-align: left;
  border-bottom: 1px solid var(--border-light);
}

.class-records-table th {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--text-muted);
  font-weight: 700;
}

.class-status-badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
}

.class-status-badge.cls-completed { background: #d1fae5; color: #047857; }
.class-status-badge.cls-no_show { background: #fee2e2; color: #b91c1c; }
.class-status-badge.cls-cancelled { background: #e5e7eb; color: var(--text-secondary); }
.class-status-badge.cls-scheduled { background: #dbeafe; color: #1d4ed8; }

.teacher-subject-section {
  margin-top: 0.5rem;
  margin-bottom: 0;
  padding: 0;
  box-shadow: none;
  background: transparent;
}

.today-cards {
  display: grid;
  gap: 0.75rem;
}

.today-card {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.85rem 1rem;
}

.today-card-top {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.35rem;
}

.time-meta {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.late-meta,
.late-meta-inline {
  font-size: 0.72rem;
  font-weight: 600;
  color: #b45309;
}

.today-card-detail {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.today-empty {
  text-align: center;
  padding: 1rem;
  color: var(--text-muted);
  font-size: 0.9rem;
  border: 1px dashed #e5e7eb;
  border-radius: 10px;
}

.source-tag {
  display: inline-block;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
}

.source-tag.biometric {
  background: #dbeafe;
  color: #2563eb;
}

.source-tag.manual {
  background: #d1fae5;
  color: #059669;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.stat-card.total .stat-value { color: #4f46e5; }
.stat-card.present .stat-value { color: #059669; }
.stat-card.absent .stat-value { color: #dc2626; }
.stat-card.late .stat-value { color: #d97706; }
.stat-card.leave .stat-value { color: #8b5cf6; }
.stat-card.excused .stat-value { color: #6366f1; }
.stat-card.percentage .stat-value { color: #2563eb; }

.attendance-bar-section {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.attendance-bar-section h3 {
  margin: 0 0 1rem;
  font-size: 1rem;
  color: var(--text-secondary);
}

.attendance-bar {
  display: flex;
  height: 24px;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 0.75rem;
}

.bar-segment.present { background: #059669; }
.bar-segment.absent { background: #dc2626; }
.bar-segment.late { background: #d97706; }
.bar-segment.leave { background: #8b5cf6; }
.bar-segment.excused { background: #6366f1; }

.bar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 0.25rem;
}

.dot.present { background: #059669; }
.dot.absent { background: #dc2626; }
.dot.late { background: #d97706; }
.dot.leave { background: #8b5cf6; }
.dot.excused { background: #6366f1; }

.recent-records {
  background: var(--bg-card);
  padding: 1.25rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.recent-records h3 {
  margin: 0 0 1rem;
  font-size: 1rem;
  color: var(--text-secondary);
}

.records-table {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-color);
  font-size: 0.9rem;
}

th {
  color: var(--text-muted);
  font-weight: 600;
  background: var(--bg-accent);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.present { background: #d1fae5; color: #059669; }
.status-badge.absent { background: #fee2e2; color: #dc2626; }
.status-badge.late { background: #fef3c7; color: #d97706; }
.status-badge.leave,
.status-badge.half_day,
.status-badge.excused { background: #ede9fe; color: #8b5cf6; }

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.empty-icon {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-section {
  text-align: center;
  padding: 1.5rem;
  color: var(--text-muted);
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
  }
  .stat-card {
    padding: 0.75rem;
  }
  .stat-value {
    font-size: 1.25rem;
  }
}
</style>
