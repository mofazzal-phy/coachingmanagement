<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1>📝 Exam Fee Collection</h1>
        <span class="badge-count">Collect exam fees on behalf of students</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="loadStudents" :disabled="loading">🔄 Refresh</button>
      </div>
    </div>

    <!-- ====== STEP 1: SELECT STUDENT ====== -->
    <div class="card">
      <div class="card-header">
        <h3>Step 1: Select Student</h3>
      </div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Search Student <span class="required">*</span></label>
            <select v-model="selectedStudentId" class="form-control" @change="onStudentChange">
              <option value="">Select a student...</option>
              <option v-for="s in students" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.roll_no || 'N/A' }}) — {{ s.currentClass?.name || 'N/A' }}
              </option>
            </select>
          </div>
          <div class="form-group" v-if="selectedStudentId">
            <label>Filter by Status</label>
            <select v-model="filterStatus" class="form-control" @change="loadNotifications">
              <option value="">All Status</option>
              <option value="unread">Unread</option>
              <option value="read">Read</option>
              <option value="paid">Paid</option>
              <option value="expired">Expired</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- ====== STEP 2: VIEW NOTIFICATIONS ====== -->
    <div class="card" v-if="selectedStudentId">
      <div class="card-header">
        <h3>Step 2: Exam Fee Notifications</h3>
        <div class="header-right">
          <span class="badge-info" v-if="pendingNotifications.length > 0">
            {{ pendingNotifications.length }} pending
          </span>
          <span class="badge-info total" v-else-if="notifications.length > 0">
            {{ notifications.length }} total
          </span>
        </div>
      </div>
      <div class="card-body">
        <div v-if="notifLoading" class="loading-state"><div class="spinner"></div><p>Loading notifications...</p></div>
        <div v-else-if="notifError" class="error-state"><p>⚠️ {{ notifError }}</p></div>
        <div v-else-if="notifications.length === 0" class="empty-state-sm">
          <p>📭 No exam fee notifications found for this student.</p>
        </div>
        <div v-else>
          <!-- Select All / Deselect -->
          <div class="selection-bar" v-if="pendingNotifications.length > 0">
            <label class="checkbox-label">
              <input
                type="checkbox"
                :checked="selectedIds.length === pendingNotifications.length && pendingNotifications.length > 0"
                @change="toggleSelectAll"
              />
              <span>Select All Pending</span>
            </label>
            <span class="selected-count">{{ selectedIds.length }} selected</span>
          </div>

          <div class="notif-table">
            <div class="notif-table-header">
              <span class="col-check"></span>
              <span class="col-title">Title</span>
              <span class="col-amount">Amount</span>
              <span class="col-due">Due Date</span>
              <span class="col-enrollment">Enrollment</span>
              <span class="col-status">Status</span>
            </div>
            <div
              v-for="notif in notifications"
              :key="notif.id"
              class="notif-row"
              :class="{
                selected: selectedIds.includes(notif.id),
                'is-paid': notif.status === 'paid',
                'is-expired': notif.status === 'expired',
              }"
            >
              <span class="col-check">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(notif.id)"
                  :disabled="notif.status === 'paid' || notif.status === 'expired'"
                  @change="toggleSelect(notif.id)"
                />
              </span>
              <span class="col-title">
                <span class="notif-title">{{ notif.title || 'Exam Fee' }}</span>
                <span class="notif-type" v-if="notif.type">{{ notif.type }}</span>
              </span>
              <span class="col-amount"><strong>৳{{ formatNumber(notif.amount) }}</strong></span>
              <span class="col-due">{{ notif.due_date ? formatDate(notif.due_date) : '—' }}</span>
              <span class="col-enrollment">
                <span class="enr-label">{{ notif.enrollment?.batch?.course?.name || '—' }}</span>
                <span class="enr-batch">{{ notif.enrollment?.batch?.name || '' }}</span>
              </span>
              <span class="col-status">
                <span class="status-chip" :class="'chip-' + notif.status">
                  {{ notif.status }}
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ====== STEP 3: COLLECT PAYMENT ====== -->
    <div class="card" v-if="selectedIds.length > 0">
      <div class="card-header">
        <h3>Step 3: Collect Payment</h3>
        <span class="badge-info">Total: ৳{{ formatNumber(selectedTotal) }}</span>
      </div>
      <div class="card-body">
        <div v-if="collectError" class="alert alert-danger">{{ collectError }}</div>
        <div v-if="collectSuccess" class="alert alert-success">{{ collectSuccess }}</div>

        <div class="form-row">
          <div class="form-group">
            <label>Amount (৳) <span class="required">*</span></label>
            <input v-model.number="collectForm.amount" type="number" min="1" step="0.01" class="form-control" placeholder="0.00" />
          </div>
          <div class="form-group">
            <label>Payment Method <span class="required">*</span></label>
            <select v-model="collectForm.payment_method" class="form-control">
              <option value="">Select method...</option>
              <option value="cash">Cash</option>
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
              <option value="rocket">Rocket</option>
              <option value="bank">Bank Transfer</option>
              <option value="card">Card</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Remarks</label>
          <textarea v-model="collectForm.remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
        </div>
        <div class="form-actions">
          <button class="btn btn-success btn-lg" @click="submitCollection" :disabled="!collectForm.amount || collectForm.amount <= 0 || !collectForm.payment_method || collecting">
            <template v-if="collecting">
              <span class="spinner-sm"></span> Processing...
            </template>
            <template v-else>
              💰 Collect Exam Fee (৳{{ formatNumber(collectForm.amount) }})
            </template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import smartFeeService from '@/services/smart-fee.service'
import studentService from '@/services/student.service'

// ====== State ======
const students = ref([])
const selectedStudentId = ref('')
const notifications = ref([])
const selectedIds = ref([])
const filterStatus = ref('')
const notifLoading = ref(false)
const notifError = ref(null)
const collecting = ref(false)
const collectError = ref(null)
const collectSuccess = ref(null)
const collectForm = ref({
  amount: 0,
  payment_method: '',
  remarks: '',
})

// ====== Computed ======
const pendingNotifications = computed(() =>
  notifications.value.filter(n => n.status === 'unread' || n.status === 'read')
)

const selectedTotal = computed(() => {
  let total = 0
  for (const id of selectedIds.value) {
    const notif = notifications.value.find(n => n.id === id)
    if (notif) total += Number(notif.amount || 0)
  }
  return total
})

// ====== Methods ======
const loadStudents = async () => {
  try {
    const res = await studentService.list({ per_page: 500 })
    students.value = res.data?.data || []
  } catch (e) {
    console.warn('Failed to load students:', e)
  }
}

const onStudentChange = () => {
  selectedIds.value = []
  collectError.value = null
  collectSuccess.value = null
  collectForm.value.amount = 0
  collectForm.value.payment_method = ''
  collectForm.value.remarks = ''
  if (selectedStudentId.value) {
    loadNotifications()
  }
}

const loadNotifications = async () => {
  if (!selectedStudentId.value) return
  notifLoading.value = true
  notifError.value = null
  try {
    const params = { student_id: selectedStudentId.value, per_page: 100 }
    if (filterStatus.value) params.status = filterStatus.value
    const res = await smartFeeService.admin.examFeeNotifications(params)
    notifications.value = res.data?.data?.data || res.data?.data || []
  } catch (e) {
    notifError.value = e.response?.data?.message || 'Failed to load notifications'
    notifications.value = []
  } finally {
    notifLoading.value = false
  }
}

const toggleSelect = (id) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx === -1) {
    selectedIds.value.push(id)
  } else {
    selectedIds.value.splice(idx, 1)
  }
  recalcAmount()
}

const toggleSelectAll = () => {
  if (selectedIds.value.length === pendingNotifications.value.length && pendingNotifications.value.length > 0) {
    selectedIds.value = []
  } else {
    selectedIds.value = pendingNotifications.value.map(n => n.id)
  }
  recalcAmount()
}

const recalcAmount = () => {
  collectForm.value.amount = selectedTotal.value
}

const submitCollection = async () => {
  if (!collectForm.value.amount || collectForm.value.amount <= 0 || !collectForm.value.payment_method) return
  if (!selectedIds.value.length) return

  collecting.value = true
  collectError.value = null
  collectSuccess.value = null

  // Get enrollment_id from first selected notification
  const firstNotif = notifications.value.find(n => n.id === selectedIds.value[0])
  const enrollmentId = firstNotif?.enrollment_id || firstNotif?.enrollment?.id || ''

  try {
    const payload = {
      student_id: selectedStudentId.value,
      enrollment_id: enrollmentId,
      notification_ids: selectedIds.value,
      amount: collectForm.value.amount,
      payment_method: collectForm.value.payment_method,
      remarks: collectForm.value.remarks || 'Exam fee collection by admin',
    }
    const res = await smartFeeService.admin.collectExamFee(payload)
    collectSuccess.value = res.data?.message || '✅ Exam fee collected successfully!'
    collectForm.value.amount = 0
    collectForm.value.payment_method = ''
    collectForm.value.remarks = ''
    selectedIds.value = []
    // Reload notifications
    await loadNotifications()
  } catch (e) {
    collectError.value = e.response?.data?.message || 'Failed to collect exam fee'
  } finally {
    collecting.value = false
  }
}

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })
}

const formatDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(() => {
  loadStudents()
})
</script>

<style scoped>
.page-container { max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-left h1 { font-size: 1.5rem; color: var(--text-primary); margin: 0; }
.badge-count { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.header-actions { display: flex; gap: 0.5rem; }
.btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-outline { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-outline:hover { background: var(--bg-accent); border-color: var(--text-muted); }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-success:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-lg { padding: 0.75rem 1.5rem; font-size: 1rem; }
.form-actions { margin-top: 1rem; display: flex; justify-content: flex-end; }

/* Cards */
.card { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; overflow: hidden; }
.card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
.card-header h3 { margin: 0; font-size: 1rem; color: var(--text-primary); }
.card-body { padding: 1.25rem; }
.header-right { display: flex; align-items: center; gap: 0.5rem; }
.badge-info { background: #e8eaf6; color: #4f46e5; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge-info.total { background: #f3f4f6; color: var(--text-muted); }

/* Forms */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
.required { color: #ef4444; }
.form-control { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
textarea.form-control { resize: vertical; }

/* Selection Bar */
.selection-bar { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border-light); }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: #4f46e5; cursor: pointer; }
.selected-count { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

/* Notification Table */
.notif-table { border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
.notif-table-header { display: grid; grid-template-columns: 40px 1.5fr 1fr 1fr 1.5fr 1fr; gap: 0.5rem; padding: 0.6rem 1rem; background: var(--bg-accent); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.notif-row { display: grid; grid-template-columns: 40px 1.5fr 1fr 1fr 1.5fr 1fr; gap: 0.5rem; padding: 0.6rem 1rem; align-items: center; border-bottom: 1px solid var(--border-light); transition: background 0.15s; font-size: 0.85rem; }
.notif-row:last-child { border-bottom: none; }
.notif-row:hover { background: var(--bg-accent); }
.notif-row.selected { background: #eef2ff; }
.notif-row.is-paid { opacity: 0.6; }
.notif-row.is-expired { opacity: 0.5; }
.notif-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #4f46e5; cursor: pointer; }
.notif-row input[type="checkbox"]:disabled { opacity: 0.4; cursor: not-allowed; }
.col-check { display: flex; align-items: center; justify-content: center; }
.notif-title { font-weight: 600; color: var(--text-primary); display: block; }
.notif-type { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }
.enr-label { display: block; font-weight: 500; color: var(--text-secondary); }
.enr-batch { font-size: 0.75rem; color: var(--text-muted); }

/* Status Chips */
.status-chip { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
.chip-unread { background: #dbeafe; color: #2563eb; }
.chip-read { background: #f3f4f6; color: var(--text-muted); }
.chip-paid { background: #d1fae5; color: #059669; }
.chip-expired { background: #fee2e2; color: #dc2626; }

/* States */
.loading-state { text-align: center; padding: 2rem; color: var(--text-muted); }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.75rem; }
.spinner-sm { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 1.5rem; background: #fef2f2; border-radius: 8px; color: #dc2626; }
.empty-state-sm { text-align: center; padding: 2rem; color: var(--text-muted); }
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
</style>
