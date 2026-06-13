<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left"><h1>Teacher Details</h1></div>
      <div class="header-actions">
        <router-link :to="`/dashboard/teachers/${teacher?.id}/edit`" class="btn btn-primary">✏️ Edit</router-link>
        <router-link to="/dashboard/teachers" class="btn btn-outline">← Back to List</router-link>
      </div>
    </div>

    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Loading teacher details...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p><button class="btn btn-outline" @click="loadData">Try Again</button></div>
    <div v-else>
      <!-- Profile Header -->
      <div class="profile-card">
        <div class="profile-avatar">{{ initials }}</div>
        <div class="profile-info">
          <h2>{{ teacher.first_name }} {{ teacher.last_name }}</h2>
          <p class="profile-meta">{{ teacher.teacher_id }} · {{ teacher.teacher_type }} · {{ teacher.status }}</p>
          <p class="profile-meta">{{ teacher.email }} · {{ teacher.phone || 'No phone' }}</p>
        </div>
        <div class="profile-stats">
          <div class="stat-item"><span class="stat-value">{{ teacher.experience_years || 0 }}</span><span class="stat-label">Years Exp</span></div>
          <div class="stat-item"><span class="stat-value">{{ assignedClassesCount }}</span><span class="stat-label">Classes</span></div>
          <div class="stat-item"><span class="stat-value">{{ assignedSubjectsCount }}</span><span class="stat-label">Subjects</span></div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs-container">
        <div class="tabs-header">
          <button v-for="tab in tabs" :key="tab.key" class="tab-btn" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
            {{ tab.label }}
          </button>
        </div>

        <!-- Tab: Personal Info -->
        <div v-if="activeTab === 'personal'" class="tab-content">
          <div class="info-grid">
            <div class="info-card">
              <h4>General Information</h4>
              <div class="info-row"><span>Teacher ID</span><strong>{{ teacher.teacher_id }}</strong></div>
              <div class="info-row"><span>Full Name</span><strong>{{ teacher.first_name }} {{ teacher.last_name }}</strong></div>
              <div class="info-row"><span>Email</span><strong>{{ teacher.email }}</strong></div>
              <div class="info-row"><span>Phone</span><strong>{{ teacher.phone || '—' }}</strong></div>
              <div class="info-row"><span>Gender</span><strong>{{ teacher.gender || '—' }}</strong></div>
              <div class="info-row"><span>Date of Birth</span><strong>{{ teacher.date_of_birth || '—' }}</strong></div>
              <div class="info-row"><span>Address</span><strong>{{ teacher.address || '—' }}</strong></div>
            </div>
            <div class="info-card">
              <h4>Employment Details</h4>
              <div class="info-row"><span>Teacher Type</span><strong>{{ teacher.teacher_type || '—' }}</strong></div>
              <div class="info-row"><span>Academic Group</span><strong>{{ teacher.academic_group?.name || teacher.group || '—' }}</strong></div>
              <div class="info-row"><span>Date of Joining</span><strong>{{ teacher.date_of_joining || '—' }}</strong></div>
              <div class="info-row"><span>Qualification</span><strong>{{ teacher.qualification || '—' }}</strong></div>
              <div class="info-row"><span>Specialization</span><strong>{{ teacher.specialization || '—' }}</strong></div>
              <div class="info-row"><span>Experience</span><strong>{{ teacher.experience_years || 0 }} years</strong></div>
              <div class="info-row"><span>Previous Institution</span><strong>{{ teacher.previous_institution || '—' }}</strong></div>
              <div class="info-row"><span>Status</span><strong><span class="status-badge" :class="teacher.status">{{ teacher.status }}</span></strong></div>
            </div>
            <div class="info-card">
              <h4>Salary Information</h4>
              <div class="info-row"><span>Salary Type</span><strong>{{ teacher.salary_type || '—' }}</strong></div>
              <div class="info-row"><span>Salary Amount</span><strong>{{ teacher.salary_amount ? '$' + teacher.salary_amount : '—' }}</strong></div>
            </div>
          </div>
        </div>

        <!-- Tab: Assigned Classes & Subjects -->
        <div v-if="activeTab === 'assignments'" class="tab-content">
          <div class="info-grid">
            <div class="info-card">
              <h4>Assigned Classes ({{ assignedClassesCount }})</h4>
              <div v-if="teacher.classes && teacher.classes.length" class="assignment-list">
                <div v-for="cls in teacher.classes" :key="cls.id" class="assignment-item">
                  <span class="assignment-icon">📚</span>
                  <div>
                    <strong>{{ cls.name }}</strong>
                    <small v-if="cls.pivot?.academic_session_id">Session: {{ cls.pivot.academic_session_id }}</small>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted">No classes assigned yet.</p>
            </div>
            <div class="info-card">
              <h4>Assigned Subjects ({{ assignedSubjectsCount }})</h4>
              <div v-if="teacher.subjects && teacher.subjects.length" class="assignment-list">
                <div v-for="sub in teacher.subjects" :key="sub.id" class="assignment-item">
                  <span class="assignment-icon">📖</span>
                  <div>
                    <strong>{{ sub.name }}</strong>
                    <small v-if="sub.code">Code: {{ sub.code }}</small>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted">No subjects assigned yet.</p>
            </div>
          </div>
        </div>

        <!-- Tab: Attendance History -->
        <div v-if="activeTab === 'attendance'" class="tab-content">
          <div class="info-card">
            <h4>Attendance History</h4>
            <div v-if="attendanceRecords.length" class="history-table-wrap">
              <table class="data-table">
                <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                  <tr v-for="rec in attendanceRecords" :key="rec.id">
                    <td>{{ rec.date }}</td>
                    <td><span class="status-badge" :class="rec.status">{{ rec.status }}</span></td>
                    <td>{{ rec.remarks || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-muted">No attendance records found.</p>
          </div>
        </div>

        <!-- Tab: Leave History -->
        <div v-if="activeTab === 'leaves'" class="tab-content">
          <div class="info-card">
            <h4>Leave History</h4>
            <div v-if="leaveRecords.length" class="history-table-wrap">
              <table class="data-table">
                <thead><tr><th>Type</th><th>From</th><th>To</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                  <tr v-for="rec in leaveRecords" :key="rec.id">
                    <td>{{ rec.leave_type?.name || rec.leave_type_id }}</td>
                    <td>{{ rec.start_date }}</td>
                    <td>{{ rec.end_date }}</td>
                    <td>{{ rec.reason || '—' }}</td>
                    <td><span class="status-badge" :class="rec.status">{{ rec.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-muted">No leave records found.</p>
          </div>
        </div>

        <!-- Tab: Payroll History -->
        <div v-if="activeTab === 'payroll'" class="tab-content">
          <div class="info-card">
            <h4>Payroll History</h4>
            <div v-if="payrollRecords.length" class="history-table-wrap">
              <table class="data-table">
                <thead><tr><th>Month</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead>
                <tbody>
                  <tr v-for="rec in payrollRecords" :key="rec.id">
                    <td>{{ rec.month }} {{ rec.year }}</td>
                    <td>{{ rec.basic_salary || '—' }}</td>
                    <td>{{ rec.allowances || 0 }}</td>
                    <td>{{ rec.deductions || 0 }}</td>
                    <td><strong>{{ rec.net_salary || rec.basic_salary || '—' }}</strong></td>
                    <td><span class="status-badge" :class="rec.status">{{ rec.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-muted">No payroll records found.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import teacherService from '@/services/teacher.service'
import hrService from '@/services/hr.service'
import attendanceService from '@/services/attendance.service'

const route = useRoute()
const teacher = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref('personal')
const attendanceRecords = ref([])
const leaveRecords = ref([])
const payrollRecords = ref([])

const tabs = [
  { key: 'personal', label: 'Personal Info' },
  { key: 'assignments', label: 'Classes & Subjects' },
  { key: 'attendance', label: 'Attendance' },
  { key: 'leaves', label: 'Leave History' },
  { key: 'payroll', label: 'Payroll' },
]

const initials = computed(() => {
  if (!teacher.value) return '?'
  return (teacher.value.first_name?.[0] || '') + (teacher.value.last_name?.[0] || '')
})

const assignedClassesCount = computed(() => teacher.value?.classes?.length || 0)
const assignedSubjectsCount = computed(() => teacher.value?.subjects?.length || 0)

const loadData = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await teacherService.getById(route.params.id)
    teacher.value = res.data || res

    // Load related data in parallel
    const [attendanceRes, leaveRes, payrollRes] = await Promise.all([
      attendanceService.list().catch(() => ({ data: [] })),
      hrService.leaveRequests.list({ teacher_id: route.params.id }).catch(() => ({ data: [] })),
      hrService.payroll.list({ teacher_id: route.params.id }).catch(() => ({ data: [] })),
    ])
    attendanceRecords.value = attendanceRes.data || []
    leaveRecords.value = leaveRes.data || []
    payrollRecords.value = payrollRes.data || []
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load teacher details'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.profile-card {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 24px;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  margin-bottom: 24px;
}
.profile-avatar {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--primary-color);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 700;
  flex-shrink: 0;
}
.profile-info {
  flex: 1;
}
.profile-info h2 {
  margin: 0 0 4px;
  font-size: 22px;
}
.profile-meta {
  color: var(--text-muted);
  font-size: 14px;
  margin: 2px 0;
}
.profile-stats {
  display: flex;
  gap: 20px;
}
.stat-item {
  text-align: center;
  padding: 8px 16px;
  background: var(--bg-light);
  border-radius: var(--radius-sm);
}
.stat-value {
  display: block;
  font-size: 22px;
  font-weight: 700;
  color: var(--primary-color);
}
.stat-label {
  font-size: 12px;
  color: var(--text-muted);
}

.tabs-container {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.tabs-header {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  overflow-x: auto;
}
.tab-btn {
  padding: 12px 20px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-muted);
  border-bottom: 2px solid transparent;
  transition: var(--transition);
  white-space: nowrap;
}
.tab-btn:hover { color: var(--primary-color); }
.tab-btn.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}
.tab-content {
  padding: 24px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
.info-card {
  padding: 20px;
  background: var(--bg-light);
  border-radius: var(--radius-sm);
}
.info-card h4 {
  margin: 0 0 16px;
  font-size: 15px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
}
.info-row:last-child { border-bottom: none; }
.info-row span { color: var(--text-muted); }
.info-row strong { text-align: right; }

.assignment-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.assignment-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
}
.assignment-icon { font-size: 20px; }
.assignment-item small {
  display: block;
  color: var(--text-muted);
  font-size: 12px;
}

.history-table-wrap {
  overflow-x: auto;
}
.text-muted {
  color: var(--text-muted);
  font-size: 14px;
  padding: 12px 0;
}
</style>
