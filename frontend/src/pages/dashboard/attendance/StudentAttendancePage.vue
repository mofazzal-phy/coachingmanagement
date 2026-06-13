<template>
  <div class="attendance-erp">
    <!-- ===== TOP NAVBAR (embedded for this page) ===== -->
  

    <!-- ===== KPI STATISTICS ROW ===== -->
    <div v-if="loadingInitial" class="kpi-row kpi-skeleton" aria-busy="true" aria-label="Loading statistics">
      <div v-for="n in 5" :key="n" class="kpi-skel-card" />
    </div>
    <div v-else class="kpi-row">
      <AttendanceStatChart
        icon="👥"
        label="Total Students"
        :value="stats.total"
        :subtext="activeBatchName"
        :trend-data="trendTotal"
        chart-type="area"
        accent-color="#12b76a"
      />
      <AttendanceStatChart
        label="Present"
        :value="stats.present"
        :percentage="presentPercent"
        :trend-data="trendPresent"
        accent-color="#2563eb"
      />
      <AttendanceStatChart
        label="Absent"
        :value="stats.absent"
        :percentage="absentPercent"
        :trend-data="trendAbsent"
        accent-color="#f04438"
      />
      <AttendanceStatChart
        label="Late"
        :value="stats.late"
        :percentage="latePercent"
        :trend-data="trendLate"
        accent-color="#f79009"
      />
      <AttendanceStatChart
        label="Leave"
        :value="stats.leave"
        :percentage="leavePercent"
        :trend-data="trendLeave"
        accent-color="#8b5cf6"
      />
    </div>

            <!-- Tools & Session: horizontal strip above table (not right sidebar) -->
            <section class="tools-strip">
          <div class="tools-strip-header">
            <h3 class="tools-strip-heading">Tools & Session</h3>
            <div class="tools-strip-actions">
              <span v-if="refreshingStudents" class="refresh-dot" title="Refreshing data"></span>
              <button
                type="button"
                class="btn-tools-toggle"
                :class="{ active: toolsExpanded }"
                @click="toolsExpanded = !toolsExpanded"
              >
                {{ toolsExpanded ? 'Collapse' : 'Expand' }}
              </button>
            </div>
          </div>

          <div v-show="toolsExpanded" class="tools-grid">
            <div class="sidebar-card">
              <h4 class="sidebar-card-title">Attendance Methods</h4>
              <div class="methods-grid">
                <AttendanceMethodCard theme="manual" name="Manual Entry" :isActive="true" :statusText="`${stats.marked} marked today`" />
                <AttendanceMethodCard theme="fingerprint" name="Fingerprint" :isActive="biometricDevices.some(d => d.is_active)" :statusText="fingerprintStatusText" />
                <AttendanceMethodCard theme="qrcode" name="QR Code" :isActive="false" statusText="Coming soon" />
                <AttendanceMethodCard theme="card" name="Card/RFID" :isActive="false" statusText="Coming soon" />
              </div>
            </div>

            <div class="sidebar-card simulator-box">
              <div class="simulator-header-row">
                <div>
                  <h4 class="simulator-box-title">Quick Simulator <span class="device-tag">{{ simulatorDeviceLabel }}</span></h4>
                  <p class="simulator-box-subtitle">Uses current batch/subject filters when marking</p>
                </div>
                <span class="new-pill">New</span>
              </div>

              <div class="simulator-box-body">
                <div class="sim-field">
                  <label class="sim-label">Select User</label>
                  <select v-model="simUserId" class="sim-select">
                    <option value="">Choose a user...</option>
                    <option v-for="user in simulatorUsers" :key="user.id" :value="user.id">
                      {{ user.name }}<template v-if="user.roll"> ({{ user.roll }})</template>
                    </option>
                  </select>
                </div>

                <button class="btn-simulate-action" @click="triggerSimulation" :disabled="!simUserId">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a10 10 0 0 0-10 10c0 1.5.3 3 .9 4.3"></path>
                    <path d="M7 22c.6-1.5 2-2.5 3.5-2.5h3c1.5 0 2.9 1 3.5 2.5"></path>
                    <path d="M12 18a6 6 0 0 1-6-6c0-1.5.5-3 1.5-4a6 6 0 0 1 8.5 0c1 1 1.5 2.5 1.5 4"></path>
                    <path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>
                  </svg>
                  Simulate Fingerprint Scan
                </button>

                <router-link to="/dashboard/attendance/simulator" class="sim-logs-link">
                  View Simulation Logs
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                  </svg>
                </router-link>
              </div>
            </div>

            <div class="sidebar-card session-box">
              <h4 class="sidebar-card-title">Session Information</h4>
              <div class="session-info-list">
                <div class="session-info-item">
                  <div class="info-label-cell">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Teacher</span>
                  </div>
                  <span class="info-value-cell">{{ currentSession?.teacher_name || 'Rahim Sir' }}</span>
                </div>

                <div class="session-info-item">
                  <div class="info-label-cell">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                    <span>Room</span>
                  </div>
                  <span class="info-value-cell">{{ currentSession?.room_name || 'Room 101' }}</span>
                </div>

                <div class="session-info-item">
                  <div class="info-label-cell">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                      <circle cx="9" cy="7" r="4"></circle>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Total Students</span>
                  </div>
                  <span class="info-value-cell">{{ stats.total }}</span>
                </div>

                <div class="session-info-item">
                  <div class="info-label-cell">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Started At</span>
                  </div>
                  <span class="info-value-cell">{{ currentSession?.start_time ? currentSession.start_time.substring(0, 5) : '08:00 AM' }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

    <!-- ===== MAIN CONTENT (full-width table) ===== -->
    <div class="attendance-main">
        <!-- Attendance Controls -->
        <div class="control-panel">
          <!-- Attendance mode: Daily vs Session -->
          <div class="mode-tabs">
            <button
              type="button"
              class="mode-tab"
              :class="{ active: attendanceMode === 'daily' }"
              @click="setAttendanceMode('daily')"
            >Daily Attendance</button>
            <button
              type="button"
              class="mode-tab"
              :class="{ active: attendanceMode === 'session' }"
              @click="setAttendanceMode('session')"
            >Session Attendance</button>
          </div>

          <div v-if="attendanceMode === 'daily' && attendanceContext.scheduled_start_display" class="daily-schedule-hint">
            Batch first class start: <strong>{{ attendanceContext.scheduled_start_display }}</strong>
            · Present within {{ attendanceContext.present_grace_minutes }} min
            · Late within {{ attendanceContext.late_grace_minutes }} min
            · Check-in time auto-calculates status
          </div>

          <div v-if="attendanceMode === 'session'" class="session-mode-bar">
            <div class="control-field session-select-field">
              <label class="control-label">Class Session</label>
              <select v-model="activeClassSessionId" class="control-select" @change="onClassSessionChange">
                <option value="">{{ loadingClassSessions ? 'Loading...' : 'Select session...' }}</option>
                <option v-for="cs in classSessions" :key="cs.id" :value="cs.id">
                  {{ sessionOptionLabel(cs) }}
                </option>
              </select>
            </div>
            <button type="button" class="btn-session-sync" @click="syncAndLoadClassSessions" :disabled="loadingClassSessions">
              Sync from Routine
            </button>
            <p v-if="activeSessionId" class="session-linked-hint">Linked attendance session ready</p>
            <p v-if="activeClassSessionTimesLabel" class="session-time-hint">
              Scheduled: {{ activeClassSessionTimesLabel }} — check-in/out auto-filled for new marks
            </p>
          </div>

          <!-- Row 1: Filters -->
          <div class="filter-row">
            <div class="control-field">
              <label class="control-label">Batch</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <select :value="isAllBatchesSelected ? 'all' : (selectedBatchIds[0] || '')" @change="e => handleBatchSelect(e.target.value)" class="control-select">
                  <option value="all">All Batches</option>
                  <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
            </div>
            
            <div class="control-field">
              <label class="control-label">Subject</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                  <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <select v-model="filters.subject_id" class="control-select" @change="onSubjectChange">
                  <option value="">All Subjects</option>
                  <option v-for="s in batchSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
            </div>

            <div class="control-field">
              <label class="control-label">Class Slot</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <select v-model="filters.slot" class="control-select" @change="onSlotChange">
                  <option value="">All Slots</option>
                  <option v-for="slot in filteredSlots" :key="slot.key" :value="slot.key">{{ slot.label }}</option>
                </select>
              </div>
            </div>

            <div class="control-field">
              <label class="control-label">Date</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <input type="date" v-model="filters.date" class="control-date" @change="onFilterChange" />
              </div>
            </div>

            <div class="control-field">
              <label class="control-label">Search</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input
                  v-model="searchQuery"
                  type="search"
                  class="control-select control-search"
                  placeholder="Name or roll..."
                  @input="currentPage = 1"
                />
              </div>
            </div>

            <div class="control-field">
              <label class="control-label">Status</label>
              <div class="select-wrapper">
                <svg class="select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <select v-model="statusFilter" class="control-select" @change="currentPage = 1">
                  <option value="">All Status</option>
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="absent">Absent</option>
                  <option value="leave">Leave</option>
                </select>
              </div>
            </div>
          </div>
          
          <!-- Row 2: Action Row -->
          <div class="action-row">
            <button class="btn-detect" @click="detectSession">Auto Detect Slot</button>
            
            <div class="bulk-dropdown-container">
              <button class="btn-bulk" @click="toggleBulkDropdown">
                Bulk Actions
                <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                  <path d="M2.5 3.5l2.5 2.5 2.5-2.5"/>
                </svg>
              </button>
              <div class="bulk-dropdown-menu" v-if="showBulkDropdown">
                <a href="#" @click.prevent="bulkMarkStatus('present')">Mark Selected Present</a>
                <a href="#" @click.prevent="bulkMarkStatus('late')">Mark Selected Late</a>
                <a href="#" @click.prevent="bulkMarkStatus('absent')">Mark Selected Absent</a>
                <a href="#" @click.prevent="bulkMarkStatus('leave')">Mark Selected Leave</a>
                <div class="dropdown-divider"></div>
                <a href="#" @click.prevent="selectAllPresent">Mark All Present</a>
                <a v-if="attendanceMode === 'daily'" href="#" @click.prevent="applyClassStartToCheckIn">Set check-in to class start</a>
              </div>
            </div>

            <span v-if="hasUnsavedChanges" class="unsaved-hint">Unsaved changes</span>
            <button class="btn-save-attendance" @click="saveAttendance" :disabled="saving">
              <svg v-if="!saving" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <span v-else class="spinner-inline"></span>
              {{ saving ? 'Saving...' : 'Save Attendance' }}
            </button>
          </div>
        </div>



        <!-- Attendance Table -->
        <div class="table-card">
          <div class="table-scroll">
            <table class="attendance-table">
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" />
                  </th>
                  <th class="col-roll">Roll</th>
                  <th class="col-name">Name</th>
                  <th class="col-status">Status</th>
                  <th class="col-radio present-th">P<br><span class="full-status-label">Present</span></th>
                  <th class="col-radio late-th">L<br><span class="full-status-label">Late</span></th>
                  <th class="col-radio absent-th">A<br><span class="full-status-label">Absent</span></th>
                  <th class="col-radio leave-th">LV<br><span class="full-status-label">Leave</span></th>
                  <th class="col-time">Check In Time</th>
                  <th class="col-remarks">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingStudents" v-for="n in 5" :key="'sk-' + n" class="skeleton-row">
                  <td colspan="10">
                    <div class="table-skel-line" />
                  </td>
                </tr>
                <tr v-else v-for="student in paginatedStudents" :key="student.id" :class="{ selected: selectedIds.includes(student.id) }">
                  <td class="col-check">
                    <input type="checkbox" v-model="selectedIds" :value="student.id" />
                  </td>
                  <td class="col-roll">{{ student.roll_no || student.roll || '—' }}</td>
                  <td class="col-name">
                    <div class="student-profile-cell">
                      <img :src="student.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(student.name)}&background=2563eb&color=fff&size=32`" class="student-avatar" />
                      <div class="student-info">
                        <span class="student-name">{{ student.name }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="col-status">
                    <div class="status-cell">
                      <span class="status-pill" :class="getStatusDisplayClass(student.id)">
                        <span class="status-dot"></span>
                        {{ getStatusLabel(student.id) }}
                      </span>
                      <span
                        v-if="hasAttendance(student.id) && getAttendanceSource(student.id)"
                        class="source-chip"
                        :class="getAttendanceSource(student.id)"
                      >
                        {{ getSourceLabel(student.id) }}
                      </span>
                    </div>
                  </td>
                  <td class="col-radio">
                    <label class="radio-label present">
                      <input type="radio" :name="'status-' + student.id" value="present" @change="setStatus(student.id, 'present')" :checked="getStatus(student.id) === 'present'" />
                      <span class="radio-custom"></span>
                    </label>
                  </td>
                  <td class="col-radio">
                    <label class="radio-label late">
                      <input type="radio" :name="'status-' + student.id" value="late" @change="setStatus(student.id, 'late')" :checked="getStatus(student.id) === 'late'" />
                      <span class="radio-custom"></span>
                    </label>
                  </td>
                  <td class="col-radio">
                    <label class="radio-label absent">
                      <input type="radio" :name="'status-' + student.id" value="absent" @change="setStatus(student.id, 'absent')" :checked="getStatus(student.id) === 'absent'" />
                      <span class="radio-custom"></span>
                    </label>
                  </td>
                  <td class="col-radio">
                    <label class="radio-label leave">
                      <input type="radio" :name="'status-' + student.id" value="leave" @change="setStatus(student.id, 'leave')" :checked="getStatus(student.id) === 'leave'" />
                      <span class="radio-custom"></span>
                    </label>
                  </td>
                  <td class="col-time">
                    <div class="time-input-container">
                      <svg class="clock-icon" :class="getStatus(student.id)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                      </svg>
                      <input
                        type="time"
                        :key="`${student.id}-${getRecordValue(student.id, 'check_in')}`"
                        :value="getRecordValue(student.id, 'check_in')"
                        @input="setRecordValue(student.id, 'check_in', $event.target.value)"
                        class="time-input-borderless"
                        :disabled="getStatus(student.id) === 'absent' || getStatus(student.id) === 'leave'"
                      />
                    </div>
                    <div v-if="getCheckInDisplay(student.id)" class="check-in-display">{{ getCheckInDisplay(student.id) }}</div>
                    <div v-if="getStatus(student.id) === 'late' && getLateMinutes(student.id) > 0" class="late-minutes-badge">{{ getLateMinutesLabel(student.id) }}</div>
                  </td>
                  <td class="col-remarks">
                    <input type="text" :value="getRecordValue(student.id, 'remarks')" @input="setRecordValue(student.id, 'remarks', $event.target.value)" class="remarks-input-borderless" placeholder="—" maxlength="100" />
                  </td>
                </tr>
                <tr v-if="!loadingStudents && !paginatedStudents.length">
                  <td colspan="10" class="empty-row">
                    <div class="empty-state">
                      <span class="empty-icon">📭</span>
                      <p>No students found</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Bottom Pagination Row -->
          <div class="table-footer-row" v-if="totalStudents > 0">
            <span class="pagination-count-text">
              Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, totalStudents) }} of {{ totalStudents }} students
            </span>
            <div class="pagination-navigation">
              <button class="pag-nav-btn" :disabled="currentPage === 1" @click="currentPage--">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
              </button>
              
              <button
                v-for="p in Math.ceil(totalStudents / itemsPerPage)"
                :key="p"
                class="pag-nav-btn num"
                :class="{ active: currentPage === p }"
                @click="currentPage = p"
              >
                {{ p }}
              </button>
              
              <button class="pag-nav-btn" :disabled="currentPage === Math.ceil(totalStudents / itemsPerPage)" @click="currentPage++">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </div>
        </div>
    </div>

    <!-- ===== BOTTOM ANALYTICS SECTION ===== -->
    <div class="analytics-grid">
      <DoughnutChart :data="chartData" :date="currentDate" />
      <LowAttendanceAlert :students="lowAttendanceStudents" />
      <RecentActivity :activities="recentActivities" />
    </div>

    <div v-if="simulateMsg" class="toast toast-success">{{ simulateMsg }}</div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import attendanceService from '@/services/attendance.service'
import enrollmentService from '@/services/enrollment.service'
import academicService from '@/services/academic.service'
import { useStudentAttendanceStore } from '@/stores/student-attendance.store'
import AttendanceStatChart from '@/components/attendance/AttendanceStatChart.vue'
import AttendanceMethodCard from '@/components/attendance/AttendanceMethodCard.vue'
import DoughnutChart from '@/components/attendance/DoughnutChart.vue'
import LowAttendanceAlert from '@/components/attendance/LowAttendanceAlert.vue'
import RecentActivity from '@/components/attendance/RecentActivity.vue'
import { formatTime12, calcAttendanceStatus, nowTime12, toTimeInputValue, BD_LOCALE, BD_TIMEZONE } from '@/utils/datetime'

const attendanceStore = useStudentAttendanceStore()

const instance = getCurrentInstance()
const toast = (type, msg) => instance?.proxy?.$toast?.[type]?.(msg)

const normalizeId = (id) => (id == null || id === '' ? '' : String(id))

const POLL_INTERVAL_MS = 15000
let loadStudentsGeneration = 0
let pollTimer = null
const dirtyStudentIds = ref(new Set())

// ===== User / Auth =====
const userName = ref('Admin User')
const userAvatar = ref('https://ui-avatars.com/api/?name=Admin+User&background=2563eb&color=fff&size=40')
const showProfile = ref(false)

// ===== Date/Time =====
const currentDate = ref('')
const currentTime = ref('')
let timeInterval = null

const updateDateTime = () => {
  const now = new Date()
  currentDate.value = now.toLocaleDateString(BD_LOCALE, {
    timeZone: BD_TIMEZONE,
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
  currentTime.value = nowTime12(true)
}

const attendanceContext = reactive({
  scheduled_start: '',
  scheduled_start_display: '',
  present_grace_minutes: 10,
  late_grace_minutes: 20,
})

onMounted(() => {
  updateDateTime()
  timeInterval = setInterval(updateDateTime, 1000)
  restoreFromCache()
  loadInitialData()
  pollTimer = setInterval(() => {
    if (
      !loadingStudents.value
      && !saving.value
      && dirtyStudentIds.value.size === 0
      && students.value.length > 0
    ) {
      loadStudents({ background: true })
    }
  }, POLL_INTERVAL_MS)
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onUnmounted(() => {
  if (timeInterval) clearInterval(timeInterval)
  if (pollTimer) clearInterval(pollTimer)
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

// ===== Fullscreen =====
const toggleFullscreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen()
  } else {
    document.exitFullscreen()
  }
}

// ===== Filters =====
const filters = reactive({
  batch_id: '',
  subject_id: '',
  slot: '',
  date: new Date().toISOString().split('T')[0],
})

const attendanceMode = ref('daily')
const classSessions = ref([])
const activeClassSessionId = ref('')
const activeSessionId = ref(null)
const loadingClassSessions = ref(false)

// ===== Batch-aware Subjects & Routine Slots =====
const batchSubjects = ref([])
const routineSlots = ref([])

// Filter slots based on selected subject
const filteredSlots = computed(() => {
  if (!filters.subject_id) return routineSlots.value
  const subjectId = normalizeId(filters.subject_id)
  return routineSlots.value.filter(s => normalizeId(s.subject_id) === subjectId)
})

const onFilterChange = () => {
  currentPage.value = 1
  clearDirty()
  attendanceStore.invalidate()
  if (attendanceMode.value === 'session') {
    loadClassSessions().then(() => {
      syncFiltersFromClassSession()
      loadStudents()
    })
  } else {
    loadStudents()
  }
}

const setAttendanceMode = (mode) => {
  attendanceMode.value = mode
  clearDirty()
  attendanceStore.invalidate()
  if (mode === 'session') {
    loadClassSessions().then(() => {
      syncFiltersFromClassSession()
      loadStudents()
    })
  } else {
    activeClassSessionId.value = ''
    activeSessionId.value = null
    loadStudents()
  }
}

const sessionOptionLabel = (cs) => {
  const subject = cs.subject?.name || 'Subject'
  const start = cs.start_time ? formatTime12(cs.start_time) : '?'
  const end = cs.end_time ? formatTime12(cs.end_time) : '?'
  const batch = cs.batch?.name || ''
  return `${subject} (${start}-${end})${batch ? ` · ${batch}` : ''}`
}

const normalizeTimeValue = (time) => {
  if (!time) return ''
  const s = String(time)
  if (s.includes('T')) {
    // ISO datetime — convert to BD wall clock for time inputs
    return toTimeInputValue(s)
  }
  return s.substring(0, 5)
}

const getActiveClassSession = () => {
  if (!activeClassSessionId.value) return null
  return classSessions.value.find(cs => cs.id === activeClassSessionId.value) || null
}

const getDailyTimeDefaults = (student = null) => {
  const start = student?.scheduled_start || attendanceContext.scheduled_start || ''
  return {
    check_in: normalizeTimeValue(start),
    check_out: '',
  }
}

const getClassSessionTimeDefaults = () => {
  const cs = getActiveClassSession()
  if (!cs) return { check_in: '', check_out: '' }
  return {
    check_in: normalizeTimeValue(cs.start_time),
    check_out: normalizeTimeValue(cs.end_time),
  }
}

const activeClassSessionTimesLabel = computed(() => {
  const { check_in, check_out } = getClassSessionTimeDefaults()
  if (!check_in && !check_out) return ''
  const start = check_in ? formatTime12(check_in) : ''
  const end = check_out ? formatTime12(check_out) : ''
  if (start && end) return `${start} – ${end}`
  return start || end
})

/** Daily mode: pre-fill check-in from batch first class start (routine). */
const applyDailyScheduleTimes = ({ onlyUnmarked = true, force = false } = {}) => {
  if (attendanceMode.value !== 'daily') return

  students.value.forEach(s => {
    if (dirtyStudentIds.value.has(s.id)) return
    if (isBiometricRecord(s.id)) return

    const status = statusMap[s.id] || s.status || 'present'
    if (status === 'absent' || status === 'leave') return

    const hasSaved = Boolean(attendanceMeta[s.id]?.has_attendance)
    if (onlyUnmarked && hasSaved && !force) return

    const { check_in } = getDailyTimeDefaults(s)
    if (!check_in) return

    if (!records[s.id]) records[s.id] = { check_in: '', check_out: '', remarks: '', late_minutes: 0 }
    records[s.id].check_in = check_in
    const currentStatus = statusMap[s.id] || status
    if (currentStatus === 'present') {
      recalcStatusFromCheckIn(s.id)
    } else {
      updateLateMinutesOnly(s.id)
    }
  })
}

/** Apply routine start/end to check-in/out for session mode (skips saved + dirty rows). */
const applyClassSessionTimes = () => {
  if (attendanceMode.value !== 'session') return

  const { check_in, check_out } = getClassSessionTimeDefaults()
  if (!check_in && !check_out) return

  students.value.forEach(s => {
    if (dirtyStudentIds.value.has(s.id)) return

    const status = statusMap[s.id] || s.status || 'present'
    if (status === 'absent' || status === 'leave') return

    const hasSavedTimes = Boolean(
      attendanceMeta[s.id]?.has_attendance && records[s.id]?.check_in
    )
    if (hasSavedTimes) return

    if (!records[s.id]) records[s.id] = { check_in: '', check_out: '', remarks: '' }
    if (check_in) records[s.id].check_in = check_in
    if (check_out) records[s.id].check_out = check_out
  })
}

/** Align subject/slot filters with the selected class session. */
const syncFiltersFromClassSession = () => {
  const cs = getActiveClassSession()
  if (!cs) return

  const subjectId = normalizeId(cs.subject_id)
  if (subjectId) {
    filters.subject_id = subjectId
  }

  const start = normalizeTimeValue(cs.start_time)
  const end = normalizeTimeValue(cs.end_time)
  const matchingSlot = routineSlots.value.find(
    slot => normalizeTimeValue(slot.start_time) === start
      && normalizeTimeValue(slot.end_time) === end
      && (!subjectId || normalizeId(slot.subject_id) === subjectId)
  )
  filters.slot = matchingSlot?.key || ''
}

const syncAndLoadClassSessions = async () => {
  loadingClassSessions.value = true
  try {
    const batchIds = selectedBatchIds.value.filter(Boolean)
    await attendanceService.syncClassSessions({
      date: filters.date,
      batch_id: batchIds.length === 1 ? batchIds[0] : undefined,
    })
    await loadClassSessions()
    if (attendanceMode.value === 'session' && activeClassSessionId.value) {
      syncFiltersFromClassSession()
      await loadStudents()
    }
    toast('success', 'Class sessions synced from routine')
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to sync class sessions'
    if (String(msg).toLowerCase().includes('token expired')) {
      toast('error', 'Session expired. Please login again and retry sync.')
    } else {
      toast('error', msg)
    }
  } finally {
    loadingClassSessions.value = false
  }
}

const loadClassSessions = async () => {
  loadingClassSessions.value = true
  try {
    const params = { date: filters.date, per_page: 100 }
    const batchIds = selectedBatchIds.value.filter(Boolean)
    if (batchIds.length === 1) params.batch_id = batchIds[0]
    const res = await attendanceService.getClassSessions(params)
    classSessions.value = res.data?.data?.data || res.data?.data || []
    if (activeClassSessionId.value && !classSessions.value.some(cs => cs.id === activeClassSessionId.value)) {
      activeClassSessionId.value = ''
    }
    if (!activeClassSessionId.value && classSessions.value.length > 0) {
      activeClassSessionId.value = classSessions.value[0].id
    }
  } catch {
    classSessions.value = []
  } finally {
    loadingClassSessions.value = false
  }
}

const onClassSessionChange = () => {
  clearDirty()
  attendanceStore.invalidate()
  syncFiltersFromClassSession()
  loadStudents()
}

// When batch selection changes, reload subjects & slots from class routines
const onBatchChange = async () => {
  clearDirty()
  attendanceStore.invalidate()
  await loadBatchRoutines()
  syncSubjectWithBatch()
  await loadStudents()
  void loadSecondaryData()
}

// When subject changes, reset slot and reload students
const onSubjectChange = async () => {
  filters.subject_id = normalizeId(filters.subject_id)
  filters.slot = ''
  currentPage.value = 1
  clearDirty()
  attendanceStore.invalidate()
  await loadStudents()
}

// When slot changes, auto-fill check_in/check_out times (daily mode, or session without class session)
const onSlotChange = () => {
  if (attendanceMode.value === 'session' && activeClassSessionId.value) {
    applyClassSessionTimes()
    persistPageSnapshot()
    return
  }

  if (filters.slot) {
    const slot = routineSlots.value.find(s => s.key === filters.slot)
    if (slot) {
      students.value.forEach(s => {
        if (!records[s.id]) records[s.id] = { check_in: '', check_out: '', remarks: '' }
        if (slot.start_time) records[s.id].check_in = slot.start_time.substring(0, 5)
        if (slot.end_time) records[s.id].check_out = slot.end_time.substring(0, 5)
      })
    }
  } else {
    students.value.forEach(s => {
      if (records[s.id]) {
        records[s.id].check_in = ''
        records[s.id].check_out = ''
      }
    })
  }
  currentPage.value = 1
  persistPageSnapshot()
}

const syncSubjectWithBatch = () => {
  if (!filters.subject_id) return
  const subjectId = normalizeId(filters.subject_id)
  const stillValid = batchSubjects.value.some(s => normalizeId(s.id) === subjectId)
  if (!stillValid) {
    filters.subject_id = ''
    filters.slot = ''
  }
}

const extractRoutineSubject = (routine) => {
  const id = normalizeId(routine.subject?.id ?? routine.subject_id)
  if (!id) return null
  return {
    id,
    name: routine.subject?.name || routine.subject_name || 'Unknown',
  }
}

const fetchRoutinesForBatches = async (batchIds) => {
  const routineParams = { status: 'published' }
  const results = await Promise.all(
    batchIds.map((batchId) =>
      academicService.routines.byBatch(batchId, routineParams).catch(() => ({ data: { data: [] } }))
    )
  )
  const routineMap = new Map()
  results.flatMap((res) => res.data?.data || []).forEach((routine) => {
    const routineId = normalizeId(routine.id)
    if (routineId && !routineMap.has(routineId)) {
      routineMap.set(routineId, routine)
    }
  })
  return Array.from(routineMap.values())
}

// Load class routines for selected batches to extract subjects & slots
const loadBatchRoutines = async () => {
  try {
    const batchIds = selectedBatchIds.value.filter(Boolean)
    if (batchIds.length === 0) {
      batchSubjects.value = []
      routineSlots.value = []
      return
    }

    const allRoutines = await fetchRoutinesForBatches(batchIds)

    const subjectMap = new Map()
    allRoutines.forEach((routine) => {
      const subject = extractRoutineSubject(routine)
      if (subject && !subjectMap.has(subject.id)) {
        subjectMap.set(subject.id, subject)
      }
    })
    batchSubjects.value = Array.from(subjectMap.values()).sort((a, b) => a.name.localeCompare(b.name))

    const slotMap = new Map()
    allRoutines.forEach(r => {
      const subjectId = normalizeId(r.subject?.id || r.subject_id || '')
      const startTime = r.start_time || ''
      const endTime = r.end_time || ''
      const slotName = r.slot_name || ''
      const key = `${subjectId}:${slotName || `${startTime}-${endTime}`}`
      if ((slotName || startTime) && !slotMap.has(key)) {
        slotMap.set(key, {
          key,
          label: slotName ? `${slotName} (${startTime?.substring(0,5) || ''} - ${endTime?.substring(0,5) || ''})` : `${startTime?.substring(0,5) || ''} - ${endTime?.substring(0,5) || ''}`,
          subject_id: subjectId,
          start_time: startTime,
          end_time: endTime,
        })
      }
    })
    routineSlots.value = Array.from(slotMap.values())
  } catch (err) {
    console.error('Failed to load batch routines:', err)
    batchSubjects.value = []
    routineSlots.value = []
  }
}

const searchQuery = ref('')
const statusFilter = ref('')
const toolsExpanded = ref(true)
const refreshingStudents = ref(false)

// ===== Multi-Batch Selection =====
const selectedBatchIds = ref([])

const isAllBatchesSelected = computed(() => {
  if (batches.value.length === 0 || selectedBatchIds.value.length === 0) return false
  const allIds = new Set(batches.value.map((b) => normalizeId(b.id)))
  const selectedIds = new Set(selectedBatchIds.value.map(normalizeId))
  if (allIds.size !== selectedIds.size) return false
  for (const id of allIds) {
    if (!selectedIds.has(id)) return false
  }
  return true
})

const handleBatchSelect = (batchId) => {
  if (batchId === 'all') {
    selectedBatchIds.value = batches.value.map(b => normalizeId(b.id))
  } else if (batchId) {
    selectedBatchIds.value = [normalizeId(batchId)]
  } else {
    selectedBatchIds.value = []
  }
  currentPage.value = 1
  onBatchChange()
}

const activeBatchName = computed(() => {
  if (selectedBatchIds.value.length === 0) return 'No Batch'
  if (isAllBatchesSelected.value) return 'All Batches'
  const found = batches.value.find(b => normalizeId(b.id) === normalizeId(selectedBatchIds.value[0]))
  return found ? found.name : 'Morning A'
})

// ===== Loading =====
const loadingInitial = ref(true)
const loadingStudents = ref(false)

// ===== Data =====
const batches = ref([])
const students = ref([])
const saving = ref(false)
const selectAll = ref(false)
const selectedIds = ref([])
const records = reactive({})
const currentSession = ref(null)
const simulatorUsers = ref([])
const recentScans = ref([])
const lowAttendanceStudents = ref([])
const recentActivities = ref([])
const monthlyTrend = ref([])
const biometricDevices = ref([])
const simulatorDevices = ref([])
const simulateMsg = ref(null)

const stats = reactive({
  total: 0,
  present: 0,
  absent: 0,
  late: 0,
  leave: 0,
  marked: 0,
  unmarked: 0,
})

const chartData = reactive({
  present: 0,
  late: 0,
  absent: 0,
  leave: 0,
})

// ===== Pagination =====
const currentPage = ref(1)
const itemsPerPage = 7

const totalStudents = computed(() => filteredStudents.value.length)
const paginatedStudents = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredStudents.value.slice(start, end)
})

// ===== Computed =====
const filteredStudents = computed(() => {
  let list = students.value

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(s =>
      s.name.toLowerCase().includes(q) ||
      (s.roll_no && s.roll_no.toString().includes(q)) ||
      (s.roll && s.roll.toString().includes(q))
    )
  }

  if (statusFilter.value) {
    list = list.filter(s => getStatus(s.id) === statusFilter.value)
  }

  return list
})

// Dynamic percentage for KPI cards
const calcPercent = (count) => {
  if (stats.total === 0) return 0
  return Math.round((count / stats.total) * 100)
}
const presentPercent = computed(() => calcPercent(stats.present))
const absentPercent = computed(() => calcPercent(stats.absent))
const latePercent = computed(() => calcPercent(stats.late))
const leavePercent = computed(() => calcPercent(stats.leave))
const totalPercent = computed(() => {
  if (stats.total === 0) return 0
  return Math.round(((stats.present + stats.late) / stats.total) * 100)
})

// Trend data for KPI card sparklines
const trendTotal = computed(() =>
  monthlyTrend.value.map(m => {
    const t = m.total || 0
    return t > 0 ? Math.round(((m.present || 0) + (m.late || 0)) / t * 100) : 0
  })
)
const trendPresent = computed(() =>
  monthlyTrend.value.map(m => {
    const t = m.total || 0
    return t > 0 ? Math.round((m.present || 0) / t * 100) : 0
  })
)
const trendAbsent = computed(() =>
  monthlyTrend.value.map(m => {
    const t = m.total || 0
    return t > 0 ? Math.round((m.absent || 0) / t * 100) : 0
  })
)
const trendLate = computed(() =>
  monthlyTrend.value.map(m => {
    const t = m.total || 0
    return t > 0 ? Math.round((m.late || 0) / t * 100) : 0
  })
)
const trendLeave = computed(() =>
  monthlyTrend.value.map(m => {
    const t = m.total || 0
    return t > 0 ? Math.round((m.leave || 0) / t * 100) : 0
  })
)

// ===== Status Management =====
const statusMap = reactive({})
const attendanceMeta = reactive({})

const hasAttendance = (id) => Boolean(attendanceMeta[id]?.has_attendance)

const getAttendanceSource = (id) => attendanceMeta[id]?.attendance_source || null

const getSourceLabel = (id) => {
  const src = getAttendanceSource(id)
  if (src === 'biometric') return 'Fingerprint'
  if (src === 'manual') return 'Manual'
  return src ? src.charAt(0).toUpperCase() + src.slice(1) : ''
}

const markDirty = (id) => {
  const next = new Set(dirtyStudentIds.value)
  next.add(id)
  dirtyStudentIds.value = next
}

const clearDirty = () => {
  dirtyStudentIds.value = new Set()
}

const getStatus = (id) => statusMap[id] || 'present'

const getStatusLabel = (id) => (hasAttendance(id) ? getStatus(id) : 'Unmarked')

const getStatusDisplayClass = (id) => (hasAttendance(id) ? getStatus(id) : 'unmarked')

const fingerprintStatusText = computed(() => {
  const active = biometricDevices.value.filter(d => d.is_active)
  if (active.length === 0) return 'No active device'
  const online = active.filter(d => d.status === 'online').length
  return online > 0 ? `${online} device(s) online` : `${active.length} device(s) offline`
})

const simulatorDeviceLabel = computed(() => {
  if (simulatorDevices.value.length === 0) return 'No Device'
  return simulatorDevices.value[0].device_name
})

const hasUnsavedChanges = computed(() => dirtyStudentIds.value.size > 0)

const handleBeforeUnload = (e) => {
  if (!hasUnsavedChanges.value) return
  e.preventDefault()
  e.returnValue = ''
}

onBeforeRouteLeave((_to, _from, next) => {
  if (!hasUnsavedChanges.value) {
    next()
    return
  }
  next(window.confirm('You have unsaved attendance changes. Leave anyway?'))
})

const isBiometricRecord = (studentId) => {
  if (attendanceMeta[studentId]?.attendance_source === 'biometric') return true
  const student = students.value.find(s => s.id === studentId)
  return student?.attendance_source === 'biometric'
}

const ensureRecord = (studentId) => {
  if (!records[studentId]) {
    records[studentId] = {
      check_in: '',
      check_out: '',
      remarks: '',
      late_minutes: 0,
      biometric_late_minutes: 0,
      biometric_check_in: '',
    }
  }
  return records[studentId]
}

const applyManualCheckIn = (studentId, status) => {
  if (status !== 'present' && status !== 'late') return

  const rec = ensureRecord(studentId)
  const nowCheckIn = toTimeInputValue(new Date())

  records[studentId] = {
    ...rec,
    check_in: nowCheckIn,
    late_minutes: 0,
    biometric_late_minutes: 0,
    biometric_check_in: '',
  }

  // Only compute late minutes when admin explicitly marks Late
  if (attendanceMode.value === 'daily' && status === 'late') {
    updateLateMinutesOnly(studentId)
  }
}

const clearManualAttendanceTime = (studentId) => {
  const rec = ensureRecord(studentId)
  records[studentId] = {
    ...rec,
    check_in: '',
    check_out: '',
    late_minutes: 0,
    biometric_late_minutes: 0,
    biometric_check_in: '',
  }
}

const setStatus = (id, status) => {
  statusMap[id] = status
  markDirty(id)

  // Manual radio click always overrides fingerprint data
  attendanceMeta[id] = {
    has_attendance: true,
    attendance_source: 'manual',
  }

  if (status === 'absent' || status === 'leave') {
    clearManualAttendanceTime(id)
  } else {
    applyManualCheckIn(id, status)
  }
  updateStats()
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedIds.value = paginatedStudents.value.map(s => s.id)
  } else {
    selectedIds.value = []
  }
}

const selectAllPresent = () => {
  students.value.forEach(s => {
    statusMap[s.id] = 'present'
    markDirty(s.id)
    attendanceMeta[s.id] = {
      has_attendance: true,
      attendance_source: 'manual',
    }
    applyManualCheckIn(s.id, 'present')
    const defaults = attendanceMode.value === 'session' ? getClassSessionTimeDefaults() : null
    if (defaults?.check_out) ensureRecord(s.id).check_out = defaults.check_out
  })
  updateStats()
  showBulkDropdown.value = false
}

const applyClassStartToCheckIn = () => {
  applyDailyScheduleTimes({ onlyUnmarked: false, force: true })
  students.value.forEach(s => {
    markDirty(s.id)
    attendanceMeta[s.id] = {
      has_attendance: true,
      attendance_source: 'manual',
    }
  })
  updateStats()
  toast('info', 'Check-in set to class start time — click Save Attendance to store')
  showBulkDropdown.value = false
}

// ===== Bulk Actions Menu =====
const showBulkDropdown = ref(false)
const toggleBulkDropdown = () => {
  showBulkDropdown.value = !showBulkDropdown.value
}

const bulkMarkStatus = (status) => {
  if (selectedIds.value.length === 0) {
    toast('warning', 'Please select at least one student from the table.')
    return
  }
  selectedIds.value.forEach(id => {
    setStatus(id, status)
  })
  showBulkDropdown.value = false
}

// ===== Stats Calculation =====
const updateStats = () => {
  const counts = { present: 0, late: 0, absent: 0, leave: 0, unmarked: 0 }
  students.value.forEach(s => {
    if (!hasAttendance(s.id)) {
      counts.unmarked++
      return
    }
    const st = statusMap[s.id] || 'present'
    if (counts[st] !== undefined) counts[st]++
  })
  stats.total = students.value.length
  stats.present = counts.present
  stats.late = counts.late
  stats.absent = counts.absent
  stats.leave = counts.leave
  stats.unmarked = counts.unmarked
  stats.marked = stats.total - counts.unmarked
  chartData.present = counts.present
  chartData.late = counts.late
  chartData.absent = counts.absent
  chartData.leave = counts.leave
}

// ===== Safe Record Access =====
const getRecordValue = (studentId, field) => {
  return records[studentId]?.[field] ?? ''
}

const setRecordValue = (studentId, field, value) => {
  const rec = ensureRecord(studentId)
  records[studentId] = { ...rec, [field]: value }
  markDirty(studentId)
  if (field === 'check_in') {
    attendanceMeta[studentId] = {
      has_attendance: true,
      attendance_source: 'manual',
    }
    records[studentId].biometric_late_minutes = 0
    records[studentId].biometric_check_in = ''
    recalcStatusFromCheckIn(studentId)
    updateStats()
  }
}

const getLateMinutes = (studentId) => {
  if (getStatus(studentId) !== 'late') return 0
  return records[studentId]?.late_minutes || 0
}

const getLateMinutesLabel = (studentId) => {
  const mins = getLateMinutes(studentId)
  if (mins <= 0) return ''
  return `${mins} min late`
}

const getCheckInDisplay = (studentId) => {
  const checkIn = getRecordValue(studentId, 'check_in')
  if (!checkIn) return ''
  return formatTime12(checkIn)
}

const updateLateMinutesOnly = (studentId) => {
  if (attendanceMode.value !== 'daily') return

  const checkIn = records[studentId]?.check_in
  const student = students.value.find(s => s.id === studentId)
  const scheduledStart = student?.scheduled_start || attendanceContext.scheduled_start
  if (!checkIn || !scheduledStart || !records[studentId]) return

  const result = calcAttendanceStatus(
    checkIn,
    scheduledStart,
    attendanceContext.present_grace_minutes,
    attendanceContext.late_grace_minutes
  )
  records[studentId].late_minutes = result.late_minutes
}

const recalcStatusFromCheckIn = (studentId) => {
  if (attendanceMode.value !== 'daily') return

  const checkIn = records[studentId]?.check_in
  const student = students.value.find(s => s.id === studentId)
  const scheduledStart = student?.scheduled_start || attendanceContext.scheduled_start
  if (!checkIn || !scheduledStart) return

  const result = calcAttendanceStatus(
    checkIn,
    scheduledStart,
    attendanceContext.present_grace_minutes,
    attendanceContext.late_grace_minutes
  )

  statusMap[studentId] = result.status
  records[studentId].late_minutes = result.late_minutes
  attendanceMeta[studentId] = {
    has_attendance: true,
    attendance_source: attendanceMeta[studentId]?.attendance_source === 'biometric' ? 'biometric' : 'manual',
  }
}

const mapRealtimeActivities = (items) =>
  items.map((a) => ({
    name: a.user_name || a.student_name || 'Student',
    action: a.action || 'attendance marked',
    time: a.time || '08:00 AM',
    status: a.status || 'present',
    type: a.status || 'info',
  }))

const buildStudentsCacheKey = () =>
  attendanceStore.buildCacheKey({
    date: filters.date,
    subjectId: filters.subject_id,
    slot: filters.slot,
    batchIds: selectedBatchIds.value,
    searchQuery: searchQuery.value,
    statusFilter: statusFilter.value,
  })

const buildStudentQueryParams = () => {
  const params = { date: filters.date }
  const batchIds = selectedBatchIds.value.filter(Boolean)

  if (batchIds.length === 1) {
    params.batch_id = batchIds[0]
  } else if (batchIds.length > 1) {
    params.batch_ids = batchIds.join(',')
  }

  const subjectId = normalizeId(filters.subject_id)
  if (subjectId) params.subject_id = subjectId
  if (filters.slot) params.slot = filters.slot
  params.mode = attendanceMode.value
  if (attendanceMode.value === 'session' && activeClassSessionId.value) {
    params.class_session_id = activeClassSessionId.value
  }

  return params
}

const applyStudentsPayload = (data) => {
  students.value = data
  data.forEach(s => {
    if (dirtyStudentIds.value.has(s.id)) return

    const rec = ensureRecord(s.id)
    statusMap[s.id] = s.status || 'present'
    attendanceMeta[s.id] = {
      has_attendance: Boolean(s.has_attendance),
      attendance_source: s.attendance_source || null,
    }
    if (s.check_in) rec.check_in = s.check_in
    if (s.check_out) rec.check_out = s.check_out
    if (s.remarks) rec.remarks = s.remarks
    const status = s.status || 'present'
    rec.late_minutes = status === 'late' ? (s.late_minutes || 0) : 0
    if (s.attendance_source === 'biometric' && !dirtyStudentIds.value.has(s.id)) {
      rec.biometric_late_minutes = status === 'late' ? (s.late_minutes || 0) : 0
      rec.biometric_check_in = s.check_in || ''
    }
  })
  updateStats()
}

const persistPageSnapshot = () => {
  attendanceStore.persist(buildStudentsCacheKey(), {
    batches: batches.value,
    students: students.value,
    batchSubjects: batchSubjects.value,
    routineSlots: routineSlots.value,
    selectedBatchIds: selectedBatchIds.value,
    filters: { ...filters },
    records: { ...records },
    statusMap: { ...statusMap },
    attendanceMeta: { ...attendanceMeta },
    stats: { ...stats },
    chartData: { ...chartData },
    monthlyTrend: monthlyTrend.value,
    recentActivities: recentActivities.value,
    lowAttendanceStudents: lowAttendanceStudents.value,
    searchQuery: searchQuery.value,
    statusFilter: statusFilter.value,
    currentSession: currentSession.value,
  })
}

const restoreFromCache = () => {
  const cached = attendanceStore.hydrate()
  if (!cached) return false

  batches.value = cached.batches || []
  students.value = cached.students || []
  batchSubjects.value = cached.batchSubjects || []
  routineSlots.value = cached.routineSlots || []
  selectedBatchIds.value = (cached.selectedBatchIds || []).map(normalizeId)

  if (cached.filters) {
    Object.assign(filters, cached.filters)
    filters.subject_id = normalizeId(filters.subject_id)
  }

  Object.keys(records).forEach(key => delete records[key])
  Object.assign(records, cached.records || {})

  Object.keys(statusMap).forEach(key => delete statusMap[key])
  Object.assign(statusMap, cached.statusMap || {})

  Object.keys(attendanceMeta).forEach(key => delete attendanceMeta[key])
  Object.assign(attendanceMeta, cached.attendanceMeta || {})

  if (cached.stats) Object.assign(stats, cached.stats)
  if (cached.chartData) Object.assign(chartData, cached.chartData)

  monthlyTrend.value = cached.monthlyTrend || []
  recentActivities.value = cached.recentActivities || []
  lowAttendanceStudents.value = cached.lowAttendanceStudents || []
  searchQuery.value = cached.searchQuery || ''
  statusFilter.value = cached.statusFilter || ''
  currentSession.value = cached.currentSession || null

  loadingInitial.value = false
  loadingStudents.value = false
  return true
}

const loadSecondaryData = async () => {
  simulatorUsers.value = students.value.slice(0, 10).map((s) => ({
    id: s.id,
    name: s.name,
    roll: s.roll_no || s.roll || '',
    type: 'student',
  }))

  const alertParams = { threshold: 75 }
  if (selectedBatchIds.value.length > 0) {
    alertParams.batch_ids = selectedBatchIds.value.join(',')
  }

  const [alertRes, scansRes, devicesRes, simDevRes, realtimeRes] = await Promise.all([
    attendanceService.getLowAttendanceAlerts(alertParams).catch(() => ({ data: { data: [] } })),
    attendanceService.getRecentSimulatorScans({ limit: 10 }).catch(() => ({ data: { data: [] } })),
    attendanceService.getDevices().catch(() => ({ data: { data: [] } })),
    attendanceService.getSimulatorDevices().catch(() => ({ data: { data: [] } })),
    attendanceService.getRealtimeData({ limit: 5 }).catch(() => ({ data: { data: [] } })),
  ])

  biometricDevices.value = devicesRes.data?.data || []
  simulatorDevices.value = simDevRes.data?.data || []
  recentActivities.value = mapRealtimeActivities(realtimeRes.data?.data || [])

  const rawAlerts = alertRes.data?.data || []
  const studentBatchMap = {}
  students.value.forEach((s) => {
    studentBatchMap[s.id] = s.batch_name || ''
  })
  lowAttendanceStudents.value = rawAlerts.map((a) => ({
    id: a.student_id,
    name: a.name,
    batch: studentBatchMap[a.student_id] || a.batch || '',
    percentage: a.percentage,
    avatar: a.avatar || '',
    total: a.total,
    present: a.present,
  }))

  recentScans.value = (scansRes.data?.data || []).map((scan) => ({
    name: scan.name || scan.user_id || 'Unknown',
    status: scan.status || 'present',
    time: scan.scan_time
      ? formatTime12(scan.scan_time)
      : '08:00 AM',
  }))
}

// ===== API Calls =====
const loadInitialData = async () => {
  const hadCache = students.value.length > 0
  if (!hadCache) loadingInitial.value = true

  try {
    const [bRes, trendRes, actRes] = await Promise.all([
      enrollmentService.getBatches({ per_page: 100 }).catch((err) => {
        console.error('getBatches failed:', err)
        return { data: { data: [] } }
      }),
      attendanceService.getMonthlyTrend({ user_type: 'student', months: 6 }).catch(() => ({ data: { data: [] } })),
      attendanceService.getRealtimeData({ limit: 5 }).catch(() => ({ data: { data: [] } })),
    ])

    batches.value = bRes.data?.data || []
    if (selectedBatchIds.value.length === 0 && batches.value.length > 0) {
      selectedBatchIds.value = batches.value.map((b) => normalizeId(b.id))
    }
    monthlyTrend.value = trendRes.data?.data || []
    recentActivities.value = mapRealtimeActivities(actRes.data?.data || [])
  } catch (err) {
    console.error('Failed to load initial data:', err)
  } finally {
    loadingInitial.value = false
  }

  await loadBatchRoutines()
  syncSubjectWithBatch()
  await loadStudents({ background: hadCache })
  void loadSecondaryData()
}

const loadStudents = async ({ background = false } = {}) => {
  const generation = ++loadStudentsGeneration
  const cacheKeyAtStart = buildStudentsCacheKey()

  if (background) {
    refreshingStudents.value = true
  } else {
    loadingStudents.value = true
  }

  try {
    const params = buildStudentQueryParams()
    const res = await attendanceService.getStudents(params)
    if (generation !== loadStudentsGeneration) return

    const payload = res.data?.data
    const data = Array.isArray(payload) ? payload : (payload?.students || [])
    if (payload?.context) {
      attendanceContext.scheduled_start = payload.context.scheduled_start || ''
      attendanceContext.scheduled_start_display = payload.context.scheduled_start_display || ''
      attendanceContext.present_grace_minutes = payload.context.present_grace_minutes ?? 10
      attendanceContext.late_grace_minutes = payload.context.late_grace_minutes ?? 20
      if (payload.context.session_id) {
        activeSessionId.value = payload.context.session_id
      }
    }
    applyStudentsPayload(data)
    applyClassSessionTimes()
    applyDailyScheduleTimes()

    if (buildStudentsCacheKey() === cacheKeyAtStart) {
      persistPageSnapshot()
    }
  } catch (err) {
    console.error('Failed to load students:', err)
  } finally {
    if (generation === loadStudentsGeneration) {
      loadingStudents.value = false
      refreshingStudents.value = false
    }
  }
}

const saveAttendance = async () => {
  const changedIds = [...dirtyStudentIds.value]
  if (changedIds.length === 0) {
    toast('info', 'No changes to save')
    return
  }

  saving.value = true
  try {
    const batchIds = selectedBatchIds.value
    const changedSet = new Set(changedIds)

    const attendanceData = students.value
      .filter(s => changedSet.has(s.id))
      .map(s => ({
      student_id: s.id,
      status: statusMap[s.id] || 'present',
      check_in: records[s.id]?.check_in || null,
      check_out: records[s.id]?.check_out || null,
      remarks: records[s.id]?.remarks || null,
      date: filters.date,
      batch_id: s.batch_id || (batchIds.length === 1 ? batchIds[0] : null),
    }))

    const payload = {
      records: attendanceData,
      subject_id: filters.subject_id || null,
      date: filters.date,
    }
    
    if (batchIds.length > 1) {
      payload.batch_ids = batchIds
    } else if (batchIds.length === 1) {
      payload.batch_id = batchIds[0]
    }

    if (attendanceMode.value === 'session') {
      if (activeClassSessionId.value) {
        payload.class_session_id = activeClassSessionId.value
      } else if (activeSessionId.value) {
        payload.session_id = activeSessionId.value
      } else {
        toast('warning', 'Select a class session first')
        saving.value = false
        return
      }
    }

    await attendanceService.bulkMarkStudentAttendance(payload)

    clearDirty()
    attendanceStore.invalidate()
    await loadStudents({ background: true })

    recentActivities.value.unshift({
      name: 'You',
      action: `saved attendance for ${attendanceData.length} students`,
      time: nowTime12(),
      status: 'present',
      type: 'info',
    })

    toast('success', `Saved ${attendanceData.length} student record(s)`)
  } catch (err) {
    console.error('Failed to save attendance:', err)
    toast('error', err.response?.data?.message || 'Failed to save attendance. Please try again.')
  } finally {
    saving.value = false
  }
}

const detectSession = async () => {
  try {
    const params = {}
    const batchIds = selectedBatchIds.value
    if (batchIds.length > 0) {
      params.batch_ids = batchIds.join(',')
    }
    const res = await attendanceService.detectCurrentSession(params)
    currentSession.value = res.data?.data || null
    if (currentSession.value) {
      if (currentSession.value.batch_id) {
        selectedBatchIds.value = [normalizeId(currentSession.value.batch_id)]
      }
      filters.subject_id = normalizeId(currentSession.value.subject_id) || filters.subject_id
      attendanceStore.invalidate()
      await loadBatchRoutines()
      syncSubjectWithBatch()
      await loadStudents()
    }
  } catch (err) {
    console.error('Failed to detect session:', err)
  }
}

// ===== Quick Biometric Simulation =====
const simUserId = ref('')
const triggerSimulation = async () => {
  if (!simUserId.value) return
  await handleSimulate({ userId: simUserId.value, mode: 'fingerprint' })
}

const handleSimulate = async ({ userId, mode }) => {
  if (!userId) return
  try {
    const devRes = await attendanceService.getSimulatorDevices()
    const devs = devRes.data?.data || []
    const deviceId = devs.length > 0 ? devs[0].id : null

    if (!deviceId) {
      toast('warning', 'No biometric device found for simulation. Please register one first.')
      return
    }

    const batchIds = selectedBatchIds.value.filter(Boolean)
    const payload = {
      device_id: deviceId,
      user_type: 'student',
      user_id: userId,
      mode: mode || 'fingerprint',
    }

    const subjectId = normalizeId(filters.subject_id)
    if (subjectId) payload.subject_id = subjectId
    if (batchIds.length === 1) payload.batch_id = batchIds[0]

    attendanceStore.invalidate()
    const scanRes = await attendanceService.simulateScan(payload)
    simulateMsg.value = scanRes.data?.message || 'Biometric scan simulated successfully!'
    setTimeout(() => { simulateMsg.value = null }, 3000)

    await loadStudents({ background: true })
    void loadSecondaryData()

    const scanData = scanRes.data?.data || {}
    const simulatedStudent = students.value.find(s => s.id === userId)
    const scanStatus = scanData.attendance_status || scanData.status || 'present'

    recentScans.value.unshift({
      name: simulatedStudent?.name || `Student ID ${userId}`,
      status: scanStatus,
      time: nowTime12(),
    })

    recentActivities.value.unshift({
      name: simulatedStudent?.name || `Student ID ${userId}`,
      action: 'scanned fingerprint',
      time: nowTime12(),
      status: scanStatus,
      type: scanStatus,
    })
  } catch (err) {
    console.error('Failed to simulate scan:', err)
    toast('error', err.response?.data?.message || 'Failed to simulate scan')
  }
}
</script>

<style scoped>
.attendance-erp {
  padding: 0;
  background-color: transparent;
  min-height: 0;
  font-family: 'Inter', -apple-system, sans-serif;
  color: var(--text-primary);
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .attendance-erp {
    padding: 0.75rem;
  }
}

/* Breadcrumb & Top Bar */
.erp-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--bg-card);
  padding: 1rem 1.5rem;
  border-radius: 16px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.topbar-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
  color: var(--text-muted);
  margin-bottom: 0.25rem;
}

.crumb-link {
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.crumb-link:hover {
  color: #2563eb;
}

.crumb-sep {
  color: var(--border-strong);
}

.crumb-current {
  color: var(--text-primary);
  font-weight: 600;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.topbar-datetime {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--bg-accent);
  padding: 0.5rem 0.875rem;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.topbar-icon-btn {
  background: none;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  color: var(--text-muted);
  transition: all 0.2s;
}

.topbar-icon-btn:hover {
  background: var(--bg-subtle);
  color: var(--text-primary);
}

.notif-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 6px;
  height: 6px;
  background: #f04438;
  border-radius: 50%;
}

.topbar-avatar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 2px 8px 2px 2px;
  border-radius: 20px;
  border: 1px solid var(--border-color);
  transition: background 0.2s;
}

.topbar-avatar:hover {
  background: var(--bg-subtle);
}

.topbar-avatar img {
  width: 30px;
  height: 30px;
  border-radius: 50%;
}

.avatar-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-secondary);
}

/* KPI Row */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 1rem;
}

.kpi-skeleton .kpi-skel-card {
  height: 96px;
  border-radius: 14px;
  background: linear-gradient(90deg, var(--bg-accent) 25%, var(--bg-subtle) 50%, var(--bg-accent) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.table-skel-line {
  height: 14px;
  border-radius: 6px;
  background: linear-gradient(90deg, var(--bg-accent) 25%, var(--bg-subtle) 50%, var(--bg-accent) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Main Layout — full-width table, tools strip above table */
.attendance-main {
  width: 100%;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.tools-strip {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  padding: 1rem 1.25rem 1.25rem;
}

.tools-strip-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
}

.tools-strip-heading {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--text-primary);
}

.tools-strip-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tools-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  align-items: start;
}

@media (max-width: 1200px) {
  .tools-grid {
    grid-template-columns: 1fr;
  }
}

.btn-tools-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 0.85rem;
  border-radius: 8px;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-tools-toggle:hover,
.btn-tools-toggle.active {
  background: #dbeafe;
  border-color: #93c5fd;
}

.refresh-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #2563eb;
  animation: pulse-dot 1.2s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 0.35; transform: scale(0.85); }
  50% { opacity: 1; transform: scale(1); }
}

@media (max-width: 1024px) {
  .kpi-row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .kpi-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* Control Panel */
.control-panel {
  background: var(--bg-card);
  padding: 1.5rem;
  border-radius: 16px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 0;
}

.mode-tabs {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.mode-tab {
  padding: 0.45rem 1rem;
  border: 1.5px solid var(--border-color);
  border-radius: 999px;
  background: var(--bg-subtle);
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.mode-tab.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #fff;
}

.session-mode-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: var(--bg-subtle);
  border: 1px solid var(--border-color);
  border-radius: 12px;
}

.session-select-field {
  flex: 1;
  min-width: 220px;
}

.btn-session-sync {
  padding: 0.6rem 1rem;
  border: none;
  border-radius: 10px;
  background: #0f766e;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-session-sync:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.session-linked-hint {
  margin: 0;
  font-size: 0.72rem;
  color: #059669;
  font-weight: 600;
}

.session-time-hint {
  margin: 0;
  flex: 1 1 100%;
  font-size: 0.72rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.daily-schedule-hint {
  margin: 0 0 0.75rem;
  padding: 0.55rem 0.75rem;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: 0.78rem;
  color: #1e40af;
}

.check-in-display {
  font-size: 0.68rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
}

.late-minutes-badge {
  display: inline-block;
  margin-top: 0.2rem;
  font-size: 0.65rem;
  font-weight: 700;
  color: #b45309;
  background: #fef3c7;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

@media (max-width: 1024px) {
  .filter-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .filter-row {
    grid-template-columns: 1fr;
  }
}

.control-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.control-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.select-icon {
  position: absolute;
  left: 0.75rem;
  color: #94a3b8;
  pointer-events: none;
  font-size: 0.9rem;
}

.control-select, .control-date, .control-search {
  width: 100%;
  padding: 0.6rem 0.75rem 0.6rem 2.25rem;
  border: 1.5px solid var(--border-color);
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  background-color: var(--bg-card);
  outline: none;
  transition: all 0.2s;
  appearance: none;
}

.control-search {
  background-image: none;
}

.control-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 0.8rem;
}

.control-select:focus, .control-date:focus, .control-search:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Row 2: Action Row */
.action-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: flex-end;
  align-items: center;
  border-top: 1px solid var(--border-color);
  padding-top: 1rem;
}

@media (max-width: 768px) {
  .action-row {
    justify-content: stretch;
  }

  .btn-detect,
  .btn-bulk,
  .bulk-dropdown-container,
  .btn-save-attendance {
    width: 100%;
    flex: 1 1 100%;
  }

  .bulk-dropdown-container .btn-bulk {
    width: 100%;
  }
}

.btn-detect, .btn-bulk {
  width: 160px;
  padding: 0.65rem;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.btn-detect {
  border: 1.5px solid #dbeafe;
  background: #f0f7ff;
  color: #2563eb;
}

.btn-detect:hover {
  background: #dbeafe;
}

.btn-bulk {
  border: 1.5px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-secondary);
}

.btn-bulk:hover {
  background: var(--bg-subtle);
  border-color: var(--border-strong);
}

.bulk-dropdown-container {
  position: relative;
  display: flex;
}

.bulk-dropdown-menu {
  position: absolute;
  bottom: 100%;
  right: 0;
  margin-bottom: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.08);
  width: 180px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  padding: 4px;
}

.bulk-dropdown-menu a {
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-decoration: none;
  border-radius: 6px;
  transition: background 0.15s;
}

.bulk-dropdown-menu a:hover {
  background: var(--bg-accent);
  color: #2563eb;
}

.dropdown-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 4px 0;
}

.unsaved-hint {
  font-size: 0.75rem;
  font-weight: 600;
  color: #d97706;
  margin-right: 0.5rem;
}

.btn-save-attendance {
  width: 200px;
  padding: 0.65rem;
  background: #12b76a;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(18, 183, 106, 0.2);
}

.btn-save-attendance:hover:not(:disabled) {
  background: #0ea35d;
  box-shadow: 0 4px 12px rgba(18, 183, 106, 0.3);
}

.btn-save-attendance:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Table Card */
.table-card {
  background: var(--bg-card);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  overflow: hidden;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

.attendance-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

.attendance-table th {
  background: var(--bg-header-row);
  padding: 0.875rem 1rem;
  font-size: 0.75rem;
  font-weight: 800;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1.5px solid var(--border-color);
  text-align: left;
}

.attendance-table th.col-radio {
  text-align: center;
}

.present-th { color: #12b76a !important; }
.late-th { color: #f79009 !important; }
.absent-th { color: #f04438 !important; }
.leave-th { color: #2563eb !important; }

.full-status-label {
  font-size: 0.6rem;
  font-weight: 600;
  opacity: 0.8;
}

.attendance-table td {
  padding: 0.625rem 1rem;
  border-bottom: 1px solid var(--border-color);
  font-size: 0.82rem;
  color: var(--text-secondary);
  vertical-align: middle;
}

.attendance-table tr.selected td {
  background-color: var(--bg-card-hover);
}

.col-check {
  width: 40px;
}

.col-roll {
  width: 60px;
  font-weight: 700;
  color: var(--text-muted);
}

.student-profile-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.student-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid var(--border-color);
}

.student-info {
  display: flex;
  flex-direction: column;
}

.student-name {
  font-weight: 700;
  color: var(--text-primary);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: capitalize;
}

.status-pill.present {
  background: color-mix(in srgb, #12b76a 18%, var(--bg-card));
  color: #34d399;
}
.status-pill.present .status-dot { background: #12b76a; }

.status-pill.late {
  background: color-mix(in srgb, #f79009 18%, var(--bg-card));
  color: #fbbf24;
}
.status-pill.late .status-dot { background: #f79009; }

.status-pill.absent {
  background: color-mix(in srgb, #f04438 18%, var(--bg-card));
  color: #f87171;
}
.status-pill.absent .status-dot { background: #f04438; }

.status-pill.leave {
  background: color-mix(in srgb, #2563eb 18%, var(--bg-card));
  color: #60a5fa;
}
.status-pill.leave .status-dot { background: #2563eb; }

.status-pill.unmarked {
  background: var(--bg-accent);
  color: var(--text-muted);
}
.status-pill.unmarked .status-dot { background: #94a3b8; }

.status-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
}

.source-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.source-chip.biometric {
  background: color-mix(in srgb, #2563eb 20%, var(--bg-card));
  color: #60a5fa;
}

.source-chip.manual {
  background: color-mix(in srgb, #059669 20%, var(--bg-card));
  color: #34d399;
}

.toast {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  z-index: 2000;
  animation: slideIn 0.3s ease;
}

.toast-success {
  background: #059669;
  color: white;
}

@keyframes slideIn {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.status-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}

.col-radio {
  text-align: center;
}

.radio-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  width: 20px;
  height: 20px;
}

.radio-label input {
  display: none;
}

.radio-custom {
  width: 16px;
  height: 16px;
  border: 2px solid var(--border-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.radio-label.present input:checked + .radio-custom {
  border-color: #12b76a;
}
.radio-label.present input:checked + .radio-custom::after {
  content: '';
  width: 8px;
  height: 8px;
  background: #12b76a;
  border-radius: 50%;
}

.radio-label.late input:checked + .radio-custom {
  border-color: #f79009;
}
.radio-label.late input:checked + .radio-custom::after {
  content: '';
  width: 8px;
  height: 8px;
  background: #f79009;
  border-radius: 50%;
}

.radio-label.absent input:checked + .radio-custom {
  border-color: #f04438;
}
.radio-label.absent input:checked + .radio-custom::after {
  content: '';
  width: 8px;
  height: 8px;
  background: #f04438;
  border-radius: 50%;
}

.radio-label.leave input:checked + .radio-custom {
  border-color: #2563eb;
}
.radio-label.leave input:checked + .radio-custom::after {
  content: '';
  width: 8px;
  height: 8px;
  background: #2563eb;
  border-radius: 50%;
}

.col-name {
  min-width: 120px;
}

.col-time {
  min-width: 118px;
  width: 118px;
  white-space: nowrap;
}

.col-remarks {
  min-width: 100px;
}

.time-input-container {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  min-width: 0;
}

.clock-icon {
  color: #94a3b8;
  flex-shrink: 0;
}
.clock-icon.present { color: #12b76a; }
.clock-icon.late { color: #f79009; }

.time-input-borderless {
  border: none;
  background: transparent;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-secondary);
  width: 100%;
  min-width: 92px;
  flex: 1 1 auto;
  outline: none;
  padding: 2px 0;
  cursor: pointer;
  white-space: nowrap;
  overflow: visible;
  text-overflow: clip;
}
.time-input-borderless:focus {
  background: var(--bg-accent);
  border-radius: 4px;
}
.time-input-borderless:disabled {
  color: #94a3b8;
  cursor: not-allowed;
}

.remarks-input-borderless {
  border: none;
  background: transparent;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-secondary);
  width: 100%;
  outline: none;
  padding: 2px;
}
.remarks-input-borderless:focus {
  background: var(--bg-accent);
  border-radius: 4px;
}

/* Pagination Row */
.table-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: var(--bg-card);
  border-top: 1px solid var(--border-color);
}

.pagination-count-text {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-muted);
}

.pagination-navigation {
  display: flex;
  gap: 0.25rem;
}

.pag-nav-btn {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-secondary);
  transition: all 0.2s;
}

.pag-nav-btn:hover:not(:disabled) {
  background: var(--bg-subtle);
  border-color: var(--border-strong);
}

.pag-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pag-nav-btn.num {
  font-size: 0.78rem;
  font-weight: 700;
}

.pag-nav-btn.num.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

/* Tools panel cards (methods, simulator, session) */
.sidebar-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  padding: 1.25rem 1.5rem;
  margin-bottom: 0;
  overflow: visible;
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .sidebar-card {
    padding: 1rem;
  }
}

.sidebar-card-title {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
}

.methods-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

@media (max-width: 768px) {
  .methods-grid {
    gap: 0.5rem;
  }
}

/* Quick Simulator Card */
.simulator-box {
  background: var(--bg-subtle);
  border: 1px dashed var(--border-color);
}

.simulator-header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.simulator-box-title {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.simulator-box-subtitle {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0.15rem 0 0 0;
}

.new-pill {
  background: #2563eb;
  color: #ffffff;
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
}

.sim-field {
  margin-bottom: 0.875rem;
}

.sim-label {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}

.sim-select {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  outline: none;
  background-color: var(--bg-card);
}

.btn-simulate-action {
  width: 100%;
  padding: 0.65rem;
  background: #1e3a8a;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(30, 58, 138, 0.2);
}

.btn-simulate-action:hover:not(:disabled) {
  background: #172554;
  box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
}

.btn-simulate-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.sim-logs-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
  margin-top: 0.875rem;
  transition: color 0.15s;
}
.sim-logs-link:hover {
  color: #1d4ed8;
}

/* Session Info Card */
.session-info-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.session-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
}

.info-label-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  font-weight: 600;
}

.info-value-cell {
  font-weight: 750;
  color: var(--text-primary);
}

/* Bottom Analytics Row */
.analytics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  margin-top: 0.5rem;
}

@media (max-width: 1024px) {
  .analytics-grid {
    grid-template-columns: 1fr;
  }
}

/* Spinner & Utility styles */
.spinner-inline {
  width: 14px;
  height: 14px;
  border: 2px solid #ffffff;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .erp-topbar {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
  }

  .topbar-right {
    width: 100%;
    flex-wrap: wrap;
    justify-content: flex-start;
  }

  .topbar-datetime,
  .avatar-name {
    display: none;
  }

  .table-footer-row {
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
  }

  .pagination-navigation {
    flex-wrap: wrap;
  }
}

@media (max-width: 480px) {
  .attendance-erp {
    gap: 1rem;
  }

  .kpi-row {
    grid-template-columns: 1fr;
  }
}
</style>