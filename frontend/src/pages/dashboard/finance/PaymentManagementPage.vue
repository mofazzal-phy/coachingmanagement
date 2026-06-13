<template>
  <div class="payment-management">
    <!-- ========== PAGE HEADER ========== -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">
          <span class="title-icon">💳</span>
          Payment Management
        </h1>
        <p class="page-subtitle">Approve, reject & manage all student payments</p>
      </div>
      <button class="btn btn-outline-primary" @click="showManualDialog = true">
        <span>+</span> Manual Payment
      </button>
    </div>

    <!-- ========== STATISTICS CARDS ========== -->
    <div class="stats-grid">
      <div class="stat-card stat-pending" :class="{ active: activeTab === 'pending' }" @click="switchTab('pending')">
        <div class="stat-card-top">
          <div class="stat-icon">⏳</div>
          <div class="stat-info">
            <span class="stat-value">{{ pendingTotal }}</span>
            <span class="stat-label">Pending</span>
          </div>
        </div>
        <div class="stat-bar-bg"><div class="stat-bar-fill pending-fill" :style="{ width: pendingPercent + '%' }"></div></div>
        <span class="stat-pct">{{ pendingPercent }}%</span>
      </div>
      
      <div class="stat-card stat-confirmed" :class="{ active: activeTab === 'confirmed' }" @click="switchTab('confirmed')">
        <div class="stat-card-top">
          <div class="stat-icon">✅</div>
          <div class="stat-info">
            <span class="stat-value">{{ confirmedTotal }}</span>
            <span class="stat-label">Confirmed</span>
          </div>
        </div>
        <div class="stat-bar-bg"><div class="stat-bar-fill confirmed-fill" :style="{ width: confirmedPercent + '%' }"></div></div>
        <span class="stat-pct">{{ confirmedPercent }}%</span>
      </div>
      
      <div class="stat-card stat-rejected" :class="{ active: activeTab === 'rejected' }" @click="switchTab('rejected')">
        <div class="stat-card-top">
          <div class="stat-icon">❌</div>
          <div class="stat-info">
            <span class="stat-value">{{ rejectedTotal }}</span>
            <span class="stat-label">Rejected</span>
          </div>
        </div>
        <div class="stat-bar-bg"><div class="stat-bar-fill rejected-fill" :style="{ width: rejectedPercent + '%' }"></div></div>
        <span class="stat-pct">{{ rejectedPercent }}%</span>
      </div>
    </div>

    <!-- ========== TABLE CARD ========== -->
    <div class="table-card">
      <!-- Tabs -->
      <div class="tab-nav">
        <button class="tab-btn" :class="{ active: activeTab === 'pending' }" @click="switchTab('pending')">
          ⏳ Pending
          <span class="tab-count count-pending">{{ pendingTotal }}</span>
        </button>
        <button class="tab-btn" :class="{ active: activeTab === 'confirmed' }" @click="switchTab('confirmed')">
          ✅ Confirmed
          <span class="tab-count count-confirmed">{{ confirmedTotal }}</span>
        </button>
        <button class="tab-btn" :class="{ active: activeTab === 'rejected' }" @click="switchTab('rejected')">
          ❌ Rejected
          <span class="tab-count count-rejected">{{ rejectedTotal }}</span>
        </button>
      </div>

      <!-- Search -->
      <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input v-model="searchQuery" type="text" class="search-input" placeholder="Search by transaction ID, student name..." @input="onSearchInput" />
        <button v-if="searchQuery" class="search-clear" @click="clearSearch">✕</button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading payments...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="currentPayments.length === 0" class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>No {{ activeTab }} payments</h3>
        <p>There are no {{ activeTab }} payments to display.</p>
      </div>

      <!-- Table -->
      <div v-else class="table-wrapper">
        <table class="payment-table">
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Student</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Date</th>
              <th v-if="activeTab === 'confirmed'">Confirmed By</th>
              <th v-if="activeTab === 'rejected'">Reason</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in currentPayments" :key="payment.id">
              <td>
                <span class="trx-id">{{ payment.transaction_no || payment.id }}</span>
              </td>
              <td>
                <div class="student-info">
                  <div class="student-avatar">
                    {{ getInitial(payment) }}
                  </div>
                  <span class="student-name">{{ getStudentName(payment) }}</span>
                </div>
              </td>
              <td>
                <span class="amount">৳{{ formatNumber(payment.amount) }}</span>
              </td>
              <td>
                <span class="method-badge" :class="'method-' + (payment.payment_method || 'cash')">
                  {{ formatMethod(payment.payment_method) }}
                </span>
              </td>
              <td>
                <span class="date-text">{{ formatDate(payment.created_at) }}</span>
              </td>
              <td v-if="activeTab === 'confirmed'">
                <span class="confirmer-text">{{ payment.confirmedBy?.name || 'System' }}</span>
              </td>
              <td v-if="activeTab === 'rejected'">
                <span class="reason-text" :title="payment.rejection_reason">
                  {{ payment.rejection_reason || '—' }}
                </span>
              </td>
              <td>
                <div class="action-btns">
                  <template v-if="activeTab === 'pending'">
                    <button class="action-btn btn-approve" @click="approvePayment(payment.id)" title="Approve">
                      ✓
                    </button>
                    <button class="action-btn btn-reject" @click="openRejectModal(payment)" title="Reject">
                      ✕
                    </button>
                  </template>
                  <button class="action-btn btn-view" @click="viewDetails(payment)" title="View Details">
                    👁
                  </button>
                  <button v-if="activeTab === 'confirmed'" class="action-btn btn-pdf" @click="downloadInvoice(payment)" title="Download Invoice">
                    📄
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalRecords > perPage" class="pagination">
        <button class="page-btn" :disabled="currentPage === 1" @click="changePage(currentPage - 1)">
          ← Previous
        </button>
        <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button class="page-btn" :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)">
          Next →
        </button>
      </div>
    </div>

    <!-- ========== MANUAL PAYMENT MODAL ========== -->
    <div v-if="showManualDialog" class="modal-overlay" @click.self="showManualDialog = false">
      <div class="modal">
        <div class="modal-header">
          <h2>Record Manual Payment</h2>
          <button class="modal-close" @click="showManualDialog = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Student <span class="required">*</span></label>
            <select v-model="manualForm.student_id" class="form-control" @change="onStudentSelect">
              <option value="">Select Student</option>
              <option v-for="s in students" :key="s.id" :value="s.id">
                {{ s.full_name || s.first_name + ' ' + s.last_name }}
              </option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Enrollment <span class="required">*</span></label>
            <select v-model="manualForm.enrollment_id" class="form-control" :disabled="!manualForm.student_id">
              <option value="">Select Enrollment</option>
              <option v-for="e in studentEnrollments" :key="e.id" :value="e.id">
                {{ e.label }}
              </option>
            </select>
          </div>
          
          <div class="form-row">
            <div class="form-group flex-1">
              <label>Amount (BDT) <span class="required">*</span></label>
              <input v-model="manualForm.amount" type="number" class="form-control" placeholder="Enter amount" min="1" />
            </div>
            <div class="form-group flex-1">
              <label>Payment Method <span class="required">*</span></label>
              <select v-model="manualForm.payment_method" class="form-control">
                <option value="">Select Method</option>
                <option value="cash">Cash</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
                <option value="bank">Bank Transfer</option>
                <option value="card">Card</option>
                <option value="check">Cheque</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label>Reference No.</label>
            <input v-model="manualForm.reference_no" type="text" class="form-control" placeholder="Transaction reference (optional)" />
          </div>
          
          <div class="form-group">
            <label>Remarks</label>
            <textarea v-model="manualForm.remarks" class="form-control" rows="2" placeholder="Optional notes"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showManualDialog = false">Cancel</button>
          <button class="btn btn-primary" @click="submitManualPayment" :disabled="submitting">
            {{ submitting ? 'Recording...' : 'Record Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========== REJECT MODAL ========== -->
    <div v-if="showRejectModal" class="modal-overlay" @click.self="showRejectModal = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h2>Reject Payment</h2>
          <button class="modal-close" @click="showRejectModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="reject-info">
            <p>Transaction: <strong>{{ rejectingPayment?.transaction_no }}</strong></p>
            <p>Amount: <strong>৳{{ formatNumber(rejectingPayment?.amount) }}</strong></p>
          </div>
          <div class="form-group">
            <label>Reason <span class="required">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Why is this being rejected? (min 10 characters)"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showRejectModal = false">Cancel</button>
          <button class="btn btn-danger" @click="confirmReject" :disabled="submitting">
            {{ submitting ? 'Rejecting...' : 'Reject Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========== DETAILS MODAL ========== -->
    <div v-if="showDetailsModal" class="modal-overlay" @click.self="showDetailsModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>Transaction Details</h2>
          <button class="modal-close" @click="showDetailsModal = false">✕</button>
        </div>
        <div v-if="viewingPayment" class="modal-body">
          <div class="detail-header">
            <span class="detail-trx">{{ viewingPayment.transaction_no }}</span>
            <span class="status-tag" :class="'status-' + viewingPayment.status">
              {{ viewingPayment.status }}
            </span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Student</span>
              <span class="detail-value">{{ getStudentName(viewingPayment) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Amount</span>
              <span class="detail-value detail-amount">৳{{ formatNumber(viewingPayment.amount) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Method</span>
              <span class="detail-value">{{ formatMethod(viewingPayment.payment_method) }}</span>
            </div>
            <div class="detail-item" v-if="viewingPayment.reference_no">
              <span class="detail-label">Reference</span>
              <span class="detail-value">{{ viewingPayment.reference_no }}</span>
            </div>
            <div class="detail-item" v-if="viewingPayment.confirmedBy">
              <span class="detail-label">Confirmed By</span>
              <span class="detail-value">{{ viewingPayment.confirmedBy.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Date</span>
              <span class="detail-value">{{ formatDate(viewingPayment.created_at) }}</span>
            </div>
            <div class="detail-item" v-if="viewingPayment.rejection_reason">
              <span class="detail-label">Reason</span>
              <span class="detail-value detail-reason">{{ viewingPayment.rejection_reason }}</span>
            </div>
          </div>

          <!-- Fee Allocation Breakdown -->
          <div v-if="viewingPayment.allocations && viewingPayment.allocations.length > 0" class="allocations-section">
            <h4 class="allocations-title">Fee Breakdown</h4>
            <div class="allocations-list">
              <div v-for="alloc in viewingPayment.allocations" :key="alloc.id" class="allocation-row">
                <div class="alloc-info">
                  <span class="cat-badge-sm" :class="'cat-' + (alloc.fee_assignment?.fee_structure?.fee_type?.category || 'monthly')">
                    {{ categoryLabel(alloc.fee_assignment?.fee_structure?.fee_type?.category) }}
                  </span>
                  <span class="alloc-desc">
                    {{ alloc.fee_assignment?.fee_structure?.fee_type?.name || 'Fee' }}
                    <span v-if="alloc.fee_assignment?.installment_number"> (Installment {{ alloc.fee_assignment.installment_number }})</span>
                    <span v-if="alloc.fee_assignment?.period_month"> - {{ formatPeriodMonth(alloc.fee_assignment.period_month) }}</span>
                  </span>
                </div>
                <span class="alloc-amount">৳{{ formatNumber(alloc.amount) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDetailsModal = false">Close</button>
          <button v-if="viewingPayment?.status === 'pending'" class="btn btn-success" @click="approvePayment(viewingPayment.id); showDetailsModal = false">
            ✓ Approve
          </button>
          <button v-if="viewingPayment?.status === 'confirmed'" class="btn btn-warning" @click="downloadInvoice(viewingPayment)">
            📄 Download Invoice
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat || 'Monthly'
}

const formatPeriodMonth = (periodMonth) => {
  if (!periodMonth) return ''
  const [year, month] = periodMonth.split('-')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${months[parseInt(month) - 1]} ${year}`
}
import smartFeeService from '@/services/smart-fee.service'
import studentService from '@/services/student.service'

// ========== State ==========
const activeTab = ref('pending')
const loading = ref(false)
const submitting = ref(false)
const currentPage = ref(1)
const perPage = 15
const searchQuery = ref('')
let searchTimer = null

const pendingPayments = ref([])
const pendingTotal = ref(0)
const confirmedPayments = ref([])
const confirmedTotal = ref(0)
const rejectedPayments = ref([])
const rejectedTotal = ref(0)

const showManualDialog = ref(false)
const showRejectModal = ref(false)
const showDetailsModal = ref(false)

const students = ref([])
const studentEnrollments = ref([])
const rejectingPayment = ref(null)
const rejectReason = ref('')
const viewingPayment = ref(null)

const manualForm = ref({
  student_id: '',
  enrollment_id: '',
  amount: '',
  payment_method: '',
  reference_no: '',
  remarks: ''
})

// ========== Computed ==========
const currentPayments = computed(() => {
  let list = []
  if (activeTab.value === 'pending') list = pendingPayments.value
  else if (activeTab.value === 'confirmed') list = confirmedPayments.value
  else list = rejectedPayments.value
  
  if (!searchQuery.value) return list
  const q = searchQuery.value.toLowerCase()
  return list.filter(p => {
    const student = p.enrollment?.student || p.student
    const studentName = student ? `${student.first_name || ''} ${student.last_name || ''}`.trim().toLowerCase() : ''
    return (p.transaction_no && String(p.transaction_no).toLowerCase().includes(q)) ||
           studentName.includes(q) ||
           (student?.student_id && String(student.student_id).toLowerCase().includes(q)) ||
           (p.gateway_trx_id && String(p.gateway_trx_id).toLowerCase().includes(q))
  })
})

const totalRecords = computed(() => {
  if (activeTab.value === 'pending') return pendingTotal.value
  if (activeTab.value === 'confirmed') return confirmedTotal.value
  return rejectedTotal.value
})

const totalPages = computed(() => Math.ceil(totalRecords.value / perPage))

// Overall totals for progress bars
const grandTotal = computed(() => pendingTotal.value + confirmedTotal.value + rejectedTotal.value)
const pendingPercent = computed(() => grandTotal.value > 0 ? Math.round((pendingTotal.value / grandTotal.value) * 100) : 0)
const confirmedPercent = computed(() => grandTotal.value > 0 ? Math.round((confirmedTotal.value / grandTotal.value) * 100) : 0)
const rejectedPercent = computed(() => grandTotal.value > 0 ? Math.round((rejectedTotal.value / grandTotal.value) * 100) : 0)

// ========== Methods ==========
const getInitial = (payment) => {
  const name = getStudentName(payment)
  return name ? name.charAt(0).toUpperCase() : '?'
}

const getStudentName = (payment) => {
  const student = payment.enrollment?.student || payment.student
  if (!student) return 'N/A'
  return student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'N/A'
}

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('en-BD')
}

const formatMethod = (method) => {
  const methods = {
    cash: 'Cash', bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket',
    bank: 'Bank', card: 'Card', check: 'Cheque'
  }
  return methods[method] || method || 'N/A'
}

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-BD', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadPayments()
  }, 300)
}

const clearSearch = () => {
  searchQuery.value = ''
  currentPage.value = 1
  loadPayments()
}

const switchTab = (tab) => {
  activeTab.value = tab
  currentPage.value = 1
  searchQuery.value = ''
  loadPayments()
}

const changePage = (page) => {
  currentPage.value = page
  loadPayments()
}

const loadDashboardCounts = async () => {
  // Load all 3 totals in parallel (per_page=1 just to get meta.total)
  try {
    const [pendingRes, confirmedRes, rejectedRes] = await Promise.all([
      smartFeeService.admin.pendingPayments({ per_page: 1 }),
      smartFeeService.admin.confirmedPayments({ per_page: 1 }),
      smartFeeService.admin.rejectedPayments({ per_page: 1 }),
    ])
    pendingTotal.value = pendingRes.data?.data?.meta?.total || pendingRes.data?.meta?.total || 0
    confirmedTotal.value = confirmedRes.data?.data?.meta?.total || confirmedRes.data?.meta?.total || 0
    rejectedTotal.value = rejectedRes.data?.data?.meta?.total || rejectedRes.data?.meta?.total || 0
  } catch (e) { /* silent */ }
}

const loadPayments = async () => {
  loading.value = true
  try {
    const params = { page: currentPage.value, per_page: perPage }
    let response
    
    if (activeTab.value === 'pending') {
      response = await smartFeeService.admin.pendingPayments(params)
      pendingPayments.value = response.data.data?.data || response.data.data || []
      pendingTotal.value = response.data.data?.meta?.total || response.data.meta?.total || pendingTotal.value
    } else if (activeTab.value === 'confirmed') {
      response = await smartFeeService.admin.confirmedPayments(params)
      confirmedPayments.value = response.data.data?.data || response.data.data || []
      confirmedTotal.value = response.data.data?.meta?.total || response.data.meta?.total || confirmedTotal.value
    } else {
      response = await smartFeeService.admin.rejectedPayments(params)
      rejectedPayments.value = response.data.data?.data || response.data.data || []
      rejectedTotal.value = response.data.data?.meta?.total || response.data.meta?.total || 0
    }
  } catch (error) {
    console.error('Failed to load payments:', error)
  } finally {
    loading.value = false
  }
}

const approvePayment = async (id) => {
  try {
    await smartFeeService.admin.confirmPayment(id)
    loadPayments()
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Failed to approve payment'
    alert(message)
  }
}

const openRejectModal = (payment) => {
  rejectingPayment.value = payment
  rejectReason.value = ''
  showRejectModal.value = true
}

const confirmReject = async () => {
  if (!rejectReason.value || rejectReason.value.length < 10) {
    alert('Please provide at least 10 characters for the reason')
    return
  }
  submitting.value = true
  try {
    await smartFeeService.admin.rejectPayment(rejectingPayment.value.id, { reason: rejectReason.value })
    showRejectModal.value = false
    loadPayments()
  } catch (error) {
    alert('Failed to reject payment')
  } finally {
    submitting.value = false
  }
}

const viewDetails = (payment) => {
  viewingPayment.value = payment
  showDetailsModal.value = true
}

const downloadInvoice = async (payment) => {
  try {
    const response = await smartFeeService.invoices.getByTransaction(payment.id)
    if (response.data.data) {
      const downloadResponse = await smartFeeService.invoices.download(response.data.data.id)
      const url = window.URL.createObjectURL(new Blob([downloadResponse.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `${response.data.data.invoice_no || 'invoice'}.pdf`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      window.URL.revokeObjectURL(url)
    }
  } catch (error) {
    alert('Failed to download invoice')
  }
}

const loadStudents = async () => {
  try {
    const response = await studentService.list({ per_page: 1000 })
    students.value = response.data.data?.data || response.data.data || []
  } catch (error) {
    console.error('Failed to load students:', error)
  }
}

const onStudentSelect = async () => {
  manualForm.value.enrollment_id = ''
  studentEnrollments.value = []
  if (!manualForm.value.student_id) return
  
  try {
    const response = await studentService.getEnrollments(manualForm.value.student_id)
    const enrollments = response.data.data || response.data || []
    studentEnrollments.value = enrollments.map(e => ({
      id: e.id,
      label: `${e.batch?.course?.name || 'Course'} - ${e.batch?.name || 'Batch'}`
    }))
  } catch (error) {
    console.error('Failed to load enrollments:', error)
  }
}

const submitManualPayment = async () => {
  if (!manualForm.value.enrollment_id || !manualForm.value.amount || !manualForm.value.payment_method) {
    alert('Please fill all required fields')
    return
  }
  submitting.value = true
  try {
    await smartFeeService.admin.manualPayment({
      enrollment_id: manualForm.value.enrollment_id,
      student_id: manualForm.value.student_id,
      amount: manualForm.value.amount,
      payment_method: manualForm.value.payment_method,
      reference_no: manualForm.value.reference_no,
      remarks: manualForm.value.remarks,
    })
    showManualDialog.value = false
    manualForm.value = {
      student_id: '', enrollment_id: '', amount: '',
      payment_method: '', reference_no: '', remarks: ''
    }
    studentEnrollments.value = []
    loadPayments()
  } catch (error) {
    alert('Failed to record payment')
  } finally {
    submitting.value = false
  }
}

// ========== Lifecycle ==========
onMounted(() => {
  loadDashboardCounts()
  loadPayments()
  loadStudents()
})
</script>

<style scoped>
.cat-badge-sm { display: inline-flex; align-items: center; padding: 0.1rem 0.4rem; border-radius: 999px; font-size: 0.6rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; width: fit-content; }
.cat-badge-sm.cat-one_time { background: #dbeafe; color: #1e40af; }
.cat-badge-sm.cat-monthly { background: #d1fae5; color: #065f46; }
.cat-badge-sm.cat-event_based { background: #fef3c7; color: #92400e; }
.allocations-section { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
.allocations-title { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin: 0 0 0.5rem; }
.allocations-list { display: flex; flex-direction: column; gap: 0.4rem; }
.allocation-row { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0.5rem; background: var(--bg-accent); border-radius: 6px; }
.alloc-info { display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0; }
.alloc-desc { font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.alloc-amount { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; margin-left: 0.5rem; }
/* ========== CONTAINER ========== */
.payment-management {
  padding: 1.5rem 2rem;
  max-width: 1400px;
  margin: 0 auto;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ========== PAGE HEADER ========== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.title-icon {
  font-size: 1.5rem;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0.25rem 0 0;
}

/* ========== BUTTONS ========== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.65rem 1.25rem;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #3b82f6;
  color: #fff;
}
.btn-primary:hover { background: #2563eb; }

.btn-secondary {
  background: var(--bg-accent);
  color: var(--text-secondary);
}
.btn-secondary:hover { background: #e2e8f0; }

.btn-success {
  background: #10b981;
  color: #fff;
}
.btn-success:hover { background: #059669; }

.btn-danger {
  background: #ef4444;
  color: #fff;
}
.btn-danger:hover { background: #dc2626; }

.btn-warning {
  background: #f59e0b;
  color: #fff;
}
.btn-warning:hover { background: #d97706; }

.btn-outline-primary {
  background: var(--bg-card);
  color: #3b82f6;
  border: 2px solid #3b82f6;
}
.btn-outline-primary:hover {
  background: #3b82f6;
  color: #fff;
}

/* ========== STATS CARDS ========== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card.active {
  border-color: #3b82f6;
  background: #eff6ff;
}

.stat-card-top {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.stat-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  flex-shrink: 0;
}

.stat-pending .stat-icon { background: #fef3c7; }
.stat-confirmed .stat-icon { background: #d1fae5; }
.stat-rejected .stat-icon { background: #fee2e2; }

.stat-info { flex: 1; }
.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  display: block;
  line-height: 1.2;
}

.stat-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
}

.stat-bar-bg {
  width: 100%;
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
  margin-top: 0.5rem;
  overflow: hidden;
}
.stat-bar-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease;
}
.stat-bar-fill.pending-fill { background: #f59e0b; }
.stat-bar-fill.confirmed-fill { background: #10b981; }
.stat-bar-fill.rejected-fill { background: #ef4444; }

.stat-pct {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 0.2rem;
  display: block;
  text-align: right;
}

/* ========== TABLE CARD ========== */
.table-card {
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

/* ========== SEARCH BAR ========== */
.search-bar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-card);
}
.search-bar .search-icon { font-size: 1rem; color: var(--text-muted); flex-shrink: 0; }
.search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 0.85rem;
  padding: 0.4rem 0;
  background: transparent;
  color: var(--text-primary);
}
.search-input::placeholder { color: var(--text-muted); }
.search-clear {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1rem;
  padding: 0.2rem 0.4rem;
  border-radius: 50%;
}
.search-clear:hover { color: #ef4444; background: #fef2f2; }

/* ========== TABS ========== */
.tab-nav {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-surface-muted);
}

.tab-btn {
  flex: 1;
  padding: 0.85rem 1rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--text-secondary);
  background: var(--bg-accent);
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
  background: var(--bg-card);
}

.tab-count {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}

.count-pending { background: #fef3c7; color: #d97706; }
.count-confirmed { background: #d1fae5; color: #059669; }
.count-rejected { background: #fee2e2; color: #dc2626; }

/* ========== TABLE ========== */
.table-wrapper {
  overflow-x: auto;
}

.payment-table {
  width: 100%;
  border-collapse: collapse;
}

.payment-table thead th {
  background: var(--bg-surface-muted);
  padding: 0.85rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}

.payment-table tbody td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.payment-table tbody tr:hover {
  background: var(--bg-surface-muted);
}

/* ========== CELL STYLES ========== */
.trx-id {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 0.8rem;
  color: #3b82f6;
  font-weight: 500;
  background: #eff6ff;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

.student-info {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.student-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: #3b82f6;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
  flex-shrink: 0;
}

.amount {
  font-weight: 600;
  color: var(--text-primary);
}

.method-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-cash { background: #dbeafe; color: #2563eb; }
.method-bkash { background: #fce7f3; color: #be185d; }
.method-nagad { background: #d1fae5; color: #059669; }
.method-rocket { background: #fef3c7; color: #d97706; }
.method-bank { background: #e0e7ff; color: #4338ca; }
.method-card { background: #f3e8ff; color: #7c3aed; }
.method-check { background: #e2e8f0; color: var(--text-secondary); }

.date-text {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.reason-text {
  max-width: 200px;
  display: inline-block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-muted);
  font-size: 0.8rem;
}

/* ========== ACTION BUTTONS ========== */
.action-btns {
  display: flex;
  gap: 0.35rem;
}

.action-btn {
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  background: transparent;
}

.btn-approve:hover { background: #d1fae5; color: #059669; }
.btn-reject:hover { background: #fee2e2; color: #dc2626; }
.btn-view:hover { background: #e0e7ff; color: #4338ca; }
.btn-pdf:hover { background: #fef3c7; color: #d97706; }

/* ========== PAGINATION ========== */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-top: 1px solid #e2e8f0;
}

.page-btn {
  padding: 0.5rem 1rem;
  background: var(--bg-accent);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  font-size: 0.85rem;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.85rem;
  color: var(--text-muted);
}

/* ========== STATES ========== */
.loading-state,
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
}

.spinner {
  width: 2.5rem;
  height: 2.5rem;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

/* ========== MODAL ========== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal {
  background: var(--bg-card);
  border-radius: 1rem;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-sm {
  max-width: 440px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: var(--text-muted);
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
}

/* ========== FORM ========== */
.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.35rem;
}

.required { color: #ef4444; }

.form-control {
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: var(--text-primary);
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.form-row {
  display: flex;
  gap: 1rem;
}

.flex-1 { flex: 1; }

/* ========== REJECT BOX ========== */
.reject-info {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 0.5rem;
  padding: 0.85rem;
  margin-bottom: 1rem;
}

.reject-info p {
  margin: 0;
  font-size: 0.85rem;
  color: #7f1d1d;
}

/* ========== DETAIL MODAL ========== */
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 1rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.detail-trx {
  font-family: 'SF Mono', monospace;
  font-weight: 600;
  color: #3b82f6;
}

.status-tag {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #d97706; }
.status-confirmed { background: #d1fae5; color: #059669; }
.status-rejected { background: #fee2e2; color: #dc2626; }

.detail-grid { display: flex; flex-direction: column; gap: 0.75rem; }

.detail-item { display: flex; justify-content: space-between; }

.detail-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
.detail-value { font-size: 0.875rem; color: var(--text-primary); font-weight: 500; }
.detail-amount { color: #059669; font-weight: 700; }
.detail-reason { color: #dc2626; }

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .payment-management { padding: 1rem; }
  .stats-grid { grid-template-columns: 1fr; }
  .form-row { flex-direction: column; gap: 0; }
  .page-header { flex-direction: column; align-items: flex-start; }
  .tab-btn { font-size: 0.8rem; padding: 0.7rem 0.5rem; }
}
</style>