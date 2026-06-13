<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>Attendance Reports</h1>
        <p class="header-subtitle">Generate and view attendance reports by various criteria</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="printReport" :disabled="!reportData">🖨️ Print</button>
        <button class="btn btn-outline" @click="exportExcel" :disabled="!reportData">📥 Excel</button>
        <button class="btn btn-outline" @click="exportPdf" :disabled="!reportData">📄 PDF</button>
      </div>
    </div>

    <!-- Report Type Selector -->
    <div class="report-tabs">
      <button v-for="tab in reportTabs" :key="tab.key" class="tab-btn" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="filter-group" v-if="activeTab === 'daily'">
        <label>Date</label>
        <input type="date" v-model="filters.start_date" class="form-control" />
      </div>

      <div class="filter-group" v-else-if="activeTab === 'monthly'">
        <label>Month</label>
        <input type="month" v-model="filters.month" class="form-control" />
      </div>

      <div class="filter-group" v-else>
        <label>Date Range</label>
        <div class="date-range">
          <input type="date" v-model="filters.start_date" class="form-control" />
          <span>to</span>
          <input type="date" v-model="filters.end_date" class="form-control" />
        </div>
      </div>

      <div class="filter-group" v-if="activeTab === 'daily' || activeTab === 'monthly'">
        <label>User Type</label>
        <select v-model="filters.user_type" class="form-control">
          <option value="">All Types</option>
          <option value="student">Students</option>
          <option value="teacher">Teachers</option>
          <option value="employee">Employees</option>
        </select>
      </div>

      <div class="filter-group" v-if="activeTab === 'batch'">
        <label>Batch</label>
        <select v-model="filters.batch_id" class="form-control">
          <option value="">Select Batch</option>
          <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name || b.batch_name }}</option>
        </select>
      </div>

      <div class="filter-group" v-if="activeTab === 'subject'">
        <label>Subject</label>
        <select v-model="filters.subject_id" class="form-control">
          <option value="">Select Subject</option>
          <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div class="filter-group" v-if="activeTab === 'teacher'">
        <label>Teacher</label>
        <select v-model="filters.teacher_id" class="form-control">
          <option value="">Select Teacher</option>
          <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name || t.full_name || (t.first_name + ' ' + (t.last_name || '')) }}</option>
        </select>
      </div>

      <div class="filter-group" v-if="activeTab === 'employee'">
        <label>Employee</label>
        <select v-model="filters.employee_id" class="form-control">
          <option value="">Select Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name || e.full_name }}</option>
        </select>
      </div>

      <div class="filter-actions">
        <button class="btn btn-primary" @click="generateReport" :disabled="loading">
          {{ loading ? 'Generating...' : '🔍 Generate' }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div><p>Generating report...</p></div>
    <div v-else-if="error" class="error-state"><p>⚠️ {{ error }}</p></div>

    <!-- Report Content -->
    <div v-else-if="reportData" class="report-container" ref="reportContent">
      <div class="report-header">
        <h2>{{ reportTitle }}</h2>
        <p class="report-period">{{ reportPeriod }}</p>
      </div>

      <!-- Summary Stats -->
      <div class="report-summary">
        <div class="summary-item">
          <span class="summary-label">Total Records</span>
          <span class="summary-value">{{ reportSummary.total }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Present</span>
          <span class="summary-value text-success">{{ reportSummary.present }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Absent</span>
          <span class="summary-value text-danger">{{ reportSummary.absent }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Late</span>
          <span class="summary-value text-warning">{{ reportSummary.late }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Leave</span>
          <span class="summary-value text-info">{{ reportSummary.leave }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Percentage</span>
          <span class="summary-value" :style="{ color: reportSummary.percentage >= 80 ? '#059669' : '#dc2626' }">
            {{ reportSummary.percentage }}%
          </span>
        </div>
      </div>

      <!-- Report Table -->
      <div class="report-table-wrapper">
        <table class="report-table">
          <thead>
            <tr>
              <th v-for="col in reportColumns" :key="col.key">{{ col.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in reportRows" :key="idx">
              <td v-for="col in reportColumns" :key="col.key">
                <span v-if="col.key === 'status'" class="status-badge" :class="'status-' + (row[col.key] || '').toLowerCase()">
                  {{ row[col.key] }}
                </span>
                <span v-else-if="col.key === 'percentage'" :style="{ color: (row[col.key] || 0) >= 80 ? '#059669' : '#dc2626', fontWeight: 600 }">
                  {{ row[col.key] }}%
                </span>
                <span v-else>{{ row[col.key] }}</span>
              </td>
            </tr>
            <tr v-if="reportRows.length === 0">
              <td :colspan="reportColumns.length" class="empty-cell">No records found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="!loading" class="empty-state">
      <div class="empty-icon">📊</div>
      <h3>Select Report Criteria</h3>
      <p>Choose a report type and filters above, then click Generate</p>
    </div>

    <div v-if="successMsg" class="toast toast-success">{{ successMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import attendanceService from '@/services/attendance.service'
import enrollmentService from '@/services/enrollment.service'
import academicService from '@/services/academic.service'
import teacherService from '@/services/teacher.service'
import hrService from '@/services/hr.service'

const loading = ref(false)
const error = ref(null)
const successMsg = ref(null)
const reportContent = ref(null)
const reportData = ref(null)

const batches = ref([])
const subjects = ref([])
const teachers = ref([])
const employees = ref([])

const activeTab = ref('daily')

const reportTabs = [
  { key: 'daily', icon: '📅', label: 'Daily Report' },
  { key: 'monthly', icon: '📆', label: 'Monthly Report' },
  { key: 'session', icon: '🎓', label: 'Session Report' },
  { key: 'batch', icon: '📦', label: 'Batch Report' },
  { key: 'subject', icon: '📚', label: 'Subject Report' },
  { key: 'teacher', icon: '👨‍🏫', label: 'Teacher Report' },
  { key: 'employee', icon: '👥', label: 'Employee Report' },
]

const today = new Date().toISOString().slice(0, 10)
const currentMonth = new Date().toISOString().slice(0, 7)

const filters = ref({
  start_date: today,
  end_date: today,
  month: currentMonth,
  user_type: '',
  batch_id: '',
  subject_id: '',
  teacher_id: '',
  employee_id: '',
})

const reportTitle = computed(() => {
  const tab = reportTabs.find(t => t.key === activeTab.value)
  return tab ? tab.label : 'Attendance Report'
})

const reportColumns = computed(() => {
  switch (activeTab.value) {
    case 'daily': return [
      { key: 'date', label: 'Date' },
      { key: 'name', label: 'Name' },
      { key: 'type', label: 'Type' },
      { key: 'status', label: 'Status' },
      { key: 'time', label: 'Time' },
    ]
    case 'monthly': return [
      { key: 'name', label: 'Name' },
      { key: 'present', label: 'Present' },
      { key: 'absent', label: 'Absent' },
      { key: 'late', label: 'Late' },
      { key: 'leave', label: 'Leave' },
      { key: 'total', label: 'Total' },
      { key: 'percentage', label: '%' },
    ]
    case 'session': return [
      { key: 'date', label: 'Date' },
      { key: 'name', label: 'Name' },
      { key: 'type', label: 'Type' },
      { key: 'subject', label: 'Subject' },
      { key: 'batch', label: 'Batch' },
      { key: 'status', label: 'Status' },
      { key: 'check_in', label: 'Check In' },
    ]
    case 'batch': return [
      { key: 'student_name', label: 'Student' },
      { key: 'roll_no', label: 'Roll' },
      { key: 'present', label: 'Present' },
      { key: 'absent', label: 'Absent' },
      { key: 'percentage', label: '%' },
    ]
    case 'subject': return [
      { key: 'student_name', label: 'Student' },
      { key: 'roll_no', label: 'Roll' },
      { key: 'present', label: 'Present' },
      { key: 'absent', label: 'Absent' },
      { key: 'late', label: 'Late' },
      { key: 'total', label: 'Total' },
      { key: 'percentage', label: '%' },
    ]
    case 'teacher': return [
      { key: 'date', label: 'Date' },
      { key: 'status', label: 'Status' },
      { key: 'check_in', label: 'Check In' },
      { key: 'check_out', label: 'Check Out' },
    ]
    case 'employee': return [
      { key: 'date', label: 'Date' },
      { key: 'status', label: 'Status' },
      { key: 'check_in', label: 'Check In' },
      { key: 'check_out', label: 'Check Out' },
    ]
    default: return [{ key: 'name', label: 'Name' }]
  }
})

const reportRows = computed(() => {
  if (!reportData.value) return []
  const data = reportData.value.records || reportData.value.detail_records || reportData.value.data
  return Array.isArray(data) ? data : []
})

const reportSummary = computed(() => {
  if (!reportData.value) {
    return { total: 0, present: 0, absent: 0, late: 0, leave: 0, percentage: 0 }
  }

  const source = reportData.value.summary || reportData.value
  const total = source.total ?? source.total_records ?? 0
  const present = source.present ?? 0

  return {
    total,
    present,
    absent: source.absent ?? 0,
    late: source.late ?? 0,
    leave: source.leave ?? 0,
    percentage: source.percentage ?? (total > 0 ? Math.round((present / total) * 100) : 0),
  }
})

const reportPeriod = computed(() => {
  if (!reportData.value) return ''

  if (activeTab.value === 'daily') {
    return reportData.value.date || filters.value.start_date
  }

  if (activeTab.value === 'monthly') {
    const monthName = reportData.value.month_name
    const year = reportData.value.year
    if (monthName && year) return `${monthName} ${year}`
    return filters.value.month
  }

  const start = reportData.value.start_date || filters.value.start_date
  const end = reportData.value.end_date || filters.value.end_date
  return `${start} to ${end}`
})

const validateFilters = () => {
  switch (activeTab.value) {
    case 'batch':
      if (!filters.value.batch_id) return 'Please select a batch'
      break
    case 'subject':
      if (!filters.value.subject_id) return 'Please select a subject'
      break
    case 'teacher':
      if (!filters.value.teacher_id) return 'Please select a teacher'
      break
    case 'employee':
      if (!filters.value.employee_id) return 'Please select an employee'
      break
  }
  return null
}

const generateReport = async () => {
  const validationError = validateFilters()
  if (validationError) {
    error.value = validationError
    reportData.value = null
    return
  }

  loading.value = true; error.value = null; reportData.value = null
  try {
    const params = { ...filters.value }
    let res
    switch (activeTab.value) {
      case 'daily': res = await attendanceService.getDailyReport(params); break
      case 'monthly': res = await attendanceService.getMonthlyReport(params); break
      case 'session': res = await attendanceService.getSessionReport(params); break
      case 'batch': res = await attendanceService.getBatchReport(params); break
      case 'subject': res = await attendanceService.getSubjectReport(params); break
      case 'teacher': res = await attendanceService.getTeacherReport(params); break
      case 'employee': res = await attendanceService.getEmployeeReport(params); break
    }
    reportData.value = res.data.data || res.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to generate report'
  } finally { loading.value = false }
}

const printReport = () => {
  window.print()
}

const exportExcel = () => {
  if (!reportData.value) return
  const rows = reportRows.value
  const cols = reportColumns.value
  if (rows.length === 0) {
    error.value = 'No data to export'
    return
  }

  // Build CSV content
  const headerRow = cols.map(c => `"${c.label}"`).join(',')
  const dataRows = rows.map(row => {
    return cols.map(col => {
      const val = row[col.key]
      return val !== null && val !== undefined ? `"${String(val).replace(/"/g, '""')}"` : '""'
    }).join(',')
  })
  const csv = [headerRow, ...dataRows].join('\n')

  // Download as CSV
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `${reportTitle.value.replace(/\s+/g, '_')}_${filters.value.start_date}_to_${filters.value.end_date}.csv`
  link.click()
  URL.revokeObjectURL(link.href)

  successMsg.value = 'Report exported as CSV'
  setTimeout(() => { successMsg.value = null }, 3000)
}

const exportPdf = () => {
  if (!reportData.value) return
  // Use window.print with print-specific CSS for PDF-like output
  printReport()
  successMsg.value = 'Use "Save as PDF" in the print dialog'
  setTimeout(() => { successMsg.value = null }, 3000)
}

// Reset filters when tab changes
watch(activeTab, () => {
  reportData.value = null
  error.value = null
})

// Load dropdown data on mount
onMounted(async () => {
  try {
    // Load batches
    const bRes = await enrollmentService.getBatches({ per_page: 200 })
    batches.value = bRes.data?.data?.data || bRes.data?.data || []
  } catch (e) { batches.value = [] }

  try {
    // Load subjects
    const sRes = await academicService.subjects.list({ per_page: 200 })
    subjects.value = sRes.data?.data?.data || sRes.data?.data || []
  } catch (e) { subjects.value = [] }

  try {
    // Load teachers
    const tRes = await teacherService.listAll({ per_page: 200 })
    teachers.value = tRes.data?.data?.data || tRes.data?.data || []
  } catch (e) { teachers.value = [] }

  try {
    // Load employees
    const eRes = await hrService.employees.list({ per_page: 200 })
    employees.value = eRes.data?.data?.data || eRes.data?.data || []
  } catch (e) { employees.value = [] }
})
</script>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.header-subtitle { font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-primary { background: #4f46e5; color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-outline:disabled { opacity: 0.5; cursor: not-allowed; }
.loading-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 2rem; background: #fef2f2; border-radius: 12px; color: #dc2626; }
.empty-state { text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { color: var(--text-primary); margin: 0 0 0.5rem; }
.empty-state p { color: var(--text-muted); margin: 0; }

/* Report Tabs */
.report-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.tab-btn { padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-secondary); }
.tab-btn:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.tab-btn.active { background: #4f46e5; color: white; border-color: #4f46e5; }

/* Filters */
.filters-bar { background: var(--bg-card); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
.filter-group { display: flex; flex-direction: column; gap: 0.3rem; }
.filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.date-range { display: flex; align-items: center; gap: 0.5rem; }
.date-range span { color: var(--text-muted); font-size: 0.85rem; }
.form-control { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.filter-actions { display: flex; align-items: flex-end; }

/* Report Container */
.report-container { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); overflow: hidden; }
.report-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
.report-header h2 { margin: 0; font-size: 1.2rem; color: var(--text-primary); }
.report-period { margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted); }

/* Summary */
.report-summary { display: flex; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light); flex-wrap: wrap; }
.summary-item { display: flex; flex-direction: column; align-items: center; min-width: 80px; }
.summary-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.summary-value { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); }
.text-success { color: #059669; }
.text-danger { color: #dc2626; }
.text-warning { color: #d97706; }
.text-info { color: #4f46e5; }

/* Table */
.report-table-wrapper { overflow-x: auto; }
.report-table { width: 100%; border-collapse: collapse; }
.report-table th { background: var(--bg-accent); padding: 0.65rem 1rem; text-align: left; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
.report-table td { padding: 0.55rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-light); }
.report-table tr:hover td { background: var(--bg-accent); }
.empty-cell { text-align: center; color: var(--text-muted); padding: 2rem !important; }

/* Status Badges */
.status-badge { padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.status-present { background: #d1fae5; color: #059669; }
.status-absent { background: #fee2e2; color: #dc2626; }
.status-late { background: #fef3c7; color: #d97706; }
.status-leave { background: #e0e7ff; color: #4f46e5; }
.status-holiday { background: #f3f4f6; color: var(--text-muted); }

.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; z-index: 2000; animation: slideIn 0.3s ease; }
.toast-success { background: #059669; color: white; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

@media print {
  .page-header, .report-tabs, .filters-bar, .header-actions, .toast { display: none; }
  .report-container { box-shadow: none; border: none; }
}
</style>
