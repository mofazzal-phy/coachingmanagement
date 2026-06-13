<template>
  <div class="fee-ledger-page">
    <!-- ========== PAGE HEADER ========== -->
    <div class="page-header">
      <button class="back-btn" @click="goBack">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <div class="header-info">
        <h1 class="page-title">
          <span class="title-icon">📒</span>
          Fee Ledger
        </h1>
        <p class="page-subtitle" v-if="ledgerData?.enrollment">
          {{ ledgerData.enrollment.batch?.course?.name || 'Course' }} — {{ ledgerData.enrollment.batch?.name || 'Batch' }}
        </p>
        <p class="page-subtitle" v-else-if="selectedEnrollment">
          {{ selectedEnrollment.batch?.course?.name || 'Course' }} — {{ selectedEnrollment.batch?.name || 'Batch' }}
        </p>
      </div>
      <button class="refresh-btn" @click="loadLedger" :disabled="loading">
        <span class="refresh-icon" :class="{ spinning: loading }">⟳</span>
      </button>
    </div>

    <!-- ========== ENROLLMENT SELECTOR ========== -->
    <div v-if="!hasEnrollmentId && enrollments.length > 1" class="selector-card">
      <label class="selector-label">Select Enrollment</label>
      <select v-model="selectedEnrollmentId" class="selector-input" @change="onEnrollmentChange">
        <option value="">Choose an enrollment...</option>
        <option v-for="enr in enrollments" :key="enr.id" :value="enr.id">
          {{ enr.label }}
        </option>
      </select>
    </div>

    <!-- ========== LOADING ========== -->
    <div v-if="loading" class="loading-card">
      <div class="loader-dots">
        <span></span><span></span><span></span><span></span>
      </div>
      <p>Loading ledger...</p>
    </div>

    <!-- ========== ERROR ========== -->
    <div v-else-if="error" class="error-card">
      <div class="error-icon">⚠️</div>
      <p>{{ error }}</p>
      <button class="retry-btn" @click="loadLedger">Retry</button>
    </div>

    <!-- ========== EMPTY ========== -->
    <div v-else-if="!hasEnrollmentId && !selectedEnrollmentId && enrollments.length > 0" class="empty-card">
      <div class="empty-icon">👆</div>
      <h3>Select an Enrollment</h3>
      <p>Please select an enrollment above to view its fee ledger.</p>
    </div>

    <div v-else-if="!hasEnrollmentId && enrollments.length === 0" class="empty-card">
      <div class="empty-icon">📭</div>
      <h3>No Enrollments Found</h3>
      <p>You don't have any active enrollments with fee records.</p>
    </div>

    <!-- ========== MAIN CONTENT ========== -->
    <template v-else-if="ledgerData">
      <!-- Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card">
          <span class="s-label">Total Fees</span>
          <span class="s-value">৳{{ formatNumber(ledgerData.summary.total_fees) }}</span>
        </div>
        <div class="summary-card paid">
          <span class="s-label">Total Paid</span>
          <span class="s-value">৳{{ formatNumber(ledgerData.summary.total_paid) }}</span>
        </div>
        <div class="summary-card discount" v-if="ledgerData.summary.total_discount > 0">
          <span class="s-label">Discount</span>
          <span class="s-value">-৳{{ formatNumber(ledgerData.summary.total_discount) }}</span>
          <span class="s-detail" v-if="(ledgerData.enrollment?.discount_percent || 0) > 0">
            ({{ ledgerData.enrollment.discount_percent }}% — {{ ledgerData.enrollment.discount_reason || 'Discount' }})
          </span>
        </div>
        <div class="summary-card" :class="{ due: ledgerData.summary.total_due > 0 }">
          <span class="s-label">Total Due</span>
          <span class="s-value">৳{{ formatNumber(ledgerData.summary.total_due) }}</span>
        </div>
        <div class="summary-card" :class="{ clear: ledgerData.summary.total_due <= 0, due: ledgerData.summary.total_due > 0 }">
          <span class="s-label">Status</span>
          <span class="status-chip" :class="ledgerData.summary.total_due <= 0 ? 'chip-clear' : 'chip-due'">
            <span class="chip-dot"></span>
            {{ ledgerData.summary.total_due <= 0 ? 'Clear' : 'Due' }}
          </span>
        </div>
      </div>

      <!-- ====== TABBED FEE RECORDS ====== -->
      <div class="section-card">
        <div class="section-header-row">
          <h2 class="section-title">
            <span>📋</span> Fee Records
          </h2>
          <!-- Category Tabs -->
          <div class="tab-bar">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              class="tab-btn"
              :class="{ active: activeTab === tab.key }"
              @click="activeTab = tab.key"
            >
              <span class="tab-dot" :class="'dot-' + tab.key"></span>
              {{ tab.label }}
              <span class="tab-count">{{ tab.count }}</span>
            </button>
          </div>
        </div>

        <!-- Monthly Fee Table -->
        <template v-if="activeTab === 'monthly'">
          <div v-if="monthlyRecords.length === 0" class="section-empty">
            <p>No monthly fee records found.</p>
          </div>
          <div v-else class="table-wrapper">
            <table class="ledger-table">
              <thead>
                <tr>
                  <th>Fee Type</th>
                  <th>Period</th>
                  <th>Fee Amount</th>
                  <th>Discount</th>
                  <th>Net Payable</th>
                  <th>Paid</th>
                  <th>Due</th>
                  <th>Due Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rec in monthlyRecords" :key="rec.id" class="table-row">
                  <td>
                    <span class="fee-type-name">{{ rec.fee_type_name || 'Monthly Fee' }}</span>
                  </td>
                  <td>
                    <strong class="month-text">{{ rec.period_month ? formatPeriod(rec.period_month) : (rec.month || '—') }}</strong>
                  </td>
                  <td>৳{{ formatNumber(rec.original_amount || rec.total_monthly_fee) }}</td>
                  <td>
                    <span v-if="(rec.original_amount || 0) > (rec.final_amount || rec.due_amount || 0)" class="text-discount">
                      -৳{{ formatNumber((rec.original_amount || 0) - (rec.final_amount || rec.due_amount || 0)) }}
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td>৳{{ formatNumber(rec.final_amount || rec.due_amount) }}</td>
                  <td>
                    <span class="text-paid">৳{{ formatNumber(rec.paid_amount) }}</span>
                  </td>
                  <td>
                    <span :class="getDueAmount(rec) > 0 ? 'text-due' : 'text-muted'">
                      ৳{{ formatNumber(getDueAmount(rec)) }}
                    </span>
                  </td>
                  <td>{{ formatDate(rec.due_date) }}</td>
                  <td>
                    <span class="status-badge" :class="'badge-' + (rec.status || rec.payment_status)">
                      {{ statusLabel(rec.status || rec.payment_status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Event/Exam Fee Table -->
        <template v-if="activeTab === 'event_based'">
          <div v-if="eventRecords.length === 0" class="section-empty">
            <p>No exam or event fee records found.</p>
          </div>
          <div v-else class="table-wrapper">
            <table class="ledger-table">
              <thead>
                <tr>
                  <th>Fee Type</th>
                  <th>Description</th>
                  <th>Amount</th>
                  <th>Paid</th>
                  <th>Due</th>
                  <th>Due Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rec in eventRecords" :key="rec.id" class="table-row">
                  <td>
                    <span class="fee-type-name">{{ rec.fee_type_name || 'Exam Fee' }}</span>
                  </td>
                  <td class="text-sm">{{ rec.description || rec.feeStructure?.description || '—' }}</td>
                  <td>৳{{ formatNumber(rec.original_amount || rec.total_monthly_fee || rec.amount) }}</td>
                  <td>
                    <span class="text-paid">৳{{ formatNumber(rec.paid_amount) }}</span>
                  </td>
                  <td>
                    <span :class="getDueAmount(rec) > 0 ? 'text-due' : 'text-muted'">
                      ৳{{ formatNumber(getDueAmount(rec)) }}
                    </span>
                  </td>
                  <td>{{ formatDate(rec.due_date) }}</td>
                  <td>
                    <span class="status-badge" :class="'badge-' + (rec.status || rec.payment_status)">
                      {{ statusLabel(rec.status || rec.payment_status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- One-Time Fee Table -->
        <template v-if="activeTab === 'one_time'">
          <div v-if="oneTimeRecords.length === 0" class="section-empty">
            <p>No one-time fee records found.</p>
          </div>
          <div v-else class="table-wrapper">
            <table class="ledger-table">
              <thead>
                <tr>
                  <th>Fee Type</th>
                  <th>Description</th>
                  <th>Amount</th>
                  <th>Paid</th>
                  <th>Due</th>
                  <th>Due Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rec in oneTimeRecords" :key="rec.id" class="table-row">
                  <td>
                    <span class="fee-type-name">{{ rec.fee_type_name || 'One-Time Fee' }}</span>
                  </td>
                  <td class="text-sm">{{ rec.description || rec.feeStructure?.description || '—' }}</td>
                  <td>৳{{ formatNumber(rec.original_amount || rec.total_monthly_fee || rec.amount) }}</td>
                  <td>
                    <span class="text-paid">৳{{ formatNumber(rec.paid_amount) }}</span>
                  </td>
                  <td>
                    <span :class="getDueAmount(rec) > 0 ? 'text-due' : 'text-muted'">
                      ৳{{ formatNumber(getDueAmount(rec)) }}
                    </span>
                  </td>
                  <td>{{ formatDate(rec.due_date) }}</td>
                  <td>
                    <span class="status-badge" :class="'badge-' + (rec.status || rec.payment_status)">
                      {{ statusLabel(rec.status || rec.payment_status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <!-- ====== COMPLETE LEDGER (compact timeline) ====== -->
      <div class="section-card">
        <div class="section-header-row">
          <h2 class="section-title">
            <span>📊</span> Complete Ledger
          </h2>
          <span class="ledger-count">{{ ledgerData.ledger?.length || 0 }} entries</span>
        </div>

        <div v-if="!ledgerData.ledger || ledgerData.ledger.length === 0" class="section-empty">
          <p>No ledger entries available.</p>
        </div>

        <div v-else class="ledger-timeline">
          <div
            v-for="(entry, idx) in ledgerData.ledger"
            :key="idx"
            class="ledger-entry"
            :class="'entry-' + entry.type"
          >
            <div class="entry-line">
              <div class="entry-dot" :class="'dot-' + entry.type"></div>
              <div v-if="idx < ledgerData.ledger.length - 1" class="entry-connector"></div>
            </div>
            <div class="entry-card">
              <div class="entry-top">
                <span class="entry-date">{{ entry.date }}</span>
                <span class="type-badge" :class="'type-' + entry.type">
                  {{ entry.type === 'debit' ? 'DEBIT' : 'CREDIT' }}
                </span>
              </div>
              <div class="entry-body">
                <div class="entry-desc">
                  <span v-if="entry.fee_type_name" class="cat-badge-sm" :class="'cat-' + (entry.fee_category || 'monthly')">
                    {{ categoryLabel(entry.fee_category) }}
                  </span>
                  {{ entry.description }}
                </div>
                <div class="entry-amounts">
                  <span v-if="entry.discount > 0" class="entry-discount">-৳{{ formatNumber(entry.discount) }} discount</span>
                  <span :class="entry.type === 'debit' ? 'text-debit' : 'text-credit'">
                    {{ entry.type === 'debit' ? '' : '+' }}৳{{ formatNumber(entry.amount) }}
                  </span>
                </div>
              </div>
              <div class="entry-footer">
                <span class="entry-balance">Balance: ৳{{ formatNumber(entry.balance) }}</span>
                <span class="status-dot-sm" :class="entry.status === 'paid' || entry.status === 'confirmed' ? 'dot-green' : 'dot-yellow'"></span>
                <span class="entry-status">{{ entry.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== PAYMENT HISTORY (collapsible) ====== -->
      <div class="section-card">
        <div class="section-header-row" @click="showPayments = !showPayments" style="cursor: pointer;">
          <h2 class="section-title">
            <span>💳</span> Payment History
          </h2>
          <div class="section-right">
            <span class="payment-count">{{ payments.length }} payment{{ payments.length !== 1 ? 's' : '' }}</span>
            <span class="collapse-icon" :class="{ collapsed: !showPayments }">▼</span>
          </div>
        </div>

        <template v-if="showPayments">
          <div v-if="payments.length === 0" class="section-empty">
            <p>No payments recorded yet.</p>
          </div>

          <div v-else class="payment-list">
            <div v-for="payment in payments" :key="payment.id" class="payment-item">
              <div class="payment-left">
                <div class="payment-icon" :class="'icon-' + (payment.status || payment.payment_status || 'pending')">
                  <svg v-if="payment.status === 'confirmed' || payment.payment_status === 'confirmed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                </div>
                <div class="payment-info">
                  <span class="payment-method">{{ formatMethod(payment.payment_method) }}</span>
                  <span class="payment-date">{{ formatDateTime(payment.created_at) }}</span>
                </div>
              </div>
              <div class="payment-center">
                <span v-if="payment.transaction_no || payment.transaction_id" class="trx-id">{{ payment.transaction_no || payment.transaction_id }}</span>
              </div>
              <div class="payment-right">
                <span class="payment-amount">৳{{ formatNumber(payment.amount) }}</span>
                <span class="payment-status" :class="'status-' + (payment.status || payment.payment_status)">
                  {{ statusLabel(payment.status || payment.payment_status) }}
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import studentPortalService from '@/services/student-portal.service'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const error = ref(null)
const ledgerData = ref(null)
const records = ref([])
const payments = ref([])
const enrollments = ref([])
const selectedEnrollmentId = ref(null)
const selectedEnrollment = ref(null)

// ===== NEW REACTIVE STATE =====
const activeTab = ref('monthly')
const showPayments = ref(true)

const hasEnrollmentId = computed(() => !!route.params.enrollmentId)

// ===== HELPER: extract fee_category from nested structure =====
const getFeeCategory = (rec) => {
  // Direct attribute (mapped format from feeRecords endpoint)
  if (rec.fee_category) return rec.fee_category
  // Nested via feeStructure->feeType (raw model from feeLedger endpoint)
  if (rec.fee_structure?.fee_type?.category) return rec.fee_structure.fee_type.category
  if (rec.feeStructure?.feeType?.category) return rec.feeStructure.feeType.category
  // Fallback: check if it has period_month → monthly, otherwise default
  return rec.period_month ? 'monthly' : 'monthly'
}

// ===== FILTERED RECORDS =====
const monthlyRecords = computed(() =>
  records.value.filter(r => getFeeCategory(r) === 'monthly')
)
const eventRecords = computed(() =>
  records.value.filter(r => getFeeCategory(r) === 'event_based')
)
const oneTimeRecords = computed(() =>
  records.value.filter(r => getFeeCategory(r) === 'one_time')
)

// ===== TABS =====
const tabs = computed(() => [
  { key: 'monthly', label: 'Monthly', count: monthlyRecords.value.length },
  { key: 'event_based', label: 'Exam', count: eventRecords.value.length },
  { key: 'one_time', label: 'Other', count: oneTimeRecords.value.length },
])

const loadEnrollments = async () => {
  try {
    const res = await studentPortalService.enrollments()
    const data = res.data?.data || []
    enrollments.value = data.map(e => ({
      id: e.id,
      label: `${e.batch?.course?.name || 'Course'} - ${e.batch?.name || 'Batch'}`,
      batch: e.batch,
    }))
    if (enrollments.value.length === 1) {
      selectedEnrollmentId.value = enrollments.value[0].id
      selectedEnrollment.value = enrollments.value[0]
      return true
    }
    return false
  } catch { return false }
}

const loadLedger = async () => {
  loading.value = true
  error.value = null
  try {
    let eid = route.params.enrollmentId
    if (!eid) {
      if (selectedEnrollmentId.value) {
        eid = selectedEnrollmentId.value
      } else {
        const hasAutoSelect = await loadEnrollments()
        if (hasAutoSelect && selectedEnrollmentId.value) eid = selectedEnrollmentId.value
        else { loading.value = false; return }
      }
    }
    const response = await studentPortalService.feeLedger(eid)
    ledgerData.value = response.data.data
    records.value = response.data.data?.records || []
    payments.value = response.data.data?.payments || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load ledger.'
  } finally { loading.value = false }
}

const onEnrollmentChange = () => {
  selectedEnrollment.value = enrollments.value.find(e => e.id === selectedEnrollmentId.value) || null
  loadLedger()
}

const goBack = () => router.push({ name: 'StudentFeeDashboard' })

// ===== HELPERS =====
const formatNumber = (num) => Number(num || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
const formatDateTime = (dt) => dt ? new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

const formatMethod = (method) => {
  const methods = { cash: 'Cash', bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket', bank: 'Bank', card: 'Card', check: 'Cheque' }
  return methods[method] || method || 'N/A'
}

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event', admission: 'Admission' }
  return labels[cat] || cat
}

const getDueAmount = (rec) => Math.max(0, (rec.final_amount || rec.due_amount || 0) - (rec.paid_amount || 0))

const statusLabel = (status) => {
  const labels = {
    paid: 'Paid',
    confirmed: 'Paid',
    pending: 'Pending',
    awaiting_confirmation: 'Pending',
    partial: 'Partial',
    unpaid: 'Unpaid',
    overdue: 'Overdue',
    cancelled: 'Cancelled',
  }
  return labels[status] || status || 'Unknown'
}

const formatPeriod = (period) => {
  if (!period) return '—'
  const [year, month] = period.split('-')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${months[parseInt(month, 10) - 1]} ${year}`
}

onMounted(() => loadLedger())
</script>

<style scoped>
/* ========== BASE ========== */
.fee-ledger-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: 'Inter', -apple-system, sans-serif;
}

/* ========== PAGE HEADER ========== */
.page-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.back-btn {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  border: 2px solid #e2e8f0;
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}

.back-btn svg {
  width: 1.2rem;
  height: 1.2rem;
  color: var(--text-muted);
}

.back-btn:hover {
  background: var(--bg-surface-muted);
  border-color: var(--text-muted);
}

.header-info { flex: 1; }

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin: 0.15rem 0 0;
}

.refresh-btn {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  border: 2px solid #e2e8f0;
  background: var(--bg-card);
  cursor: pointer;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
  color: var(--text-muted);
}

.refresh-btn:hover { background: var(--bg-surface-muted); border-color: var(--text-muted); }

.spinning { animation: spin 0.8s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ========== SELECTOR ========== */
.selector-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.25rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.selector-label {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-secondary);
  white-space: nowrap;
}

.selector-input {
  flex: 1;
  padding: 0.7rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.75rem;
  font-size: 0.9rem;
  color: var(--text-primary);
  background: var(--bg-card);
  cursor: pointer;
  max-width: 400px;
}

.selector-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

/* ========== LOADING / ERROR / EMPTY ========== */
.loading-card, .error-card, .empty-card {
  text-align: center;
  padding: 3rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: var(--shadow-sm);
}

.loader-dots {
  display: flex;
  justify-content: center;
  gap: 6px;
  margin-bottom: 1rem;
}

.loader-dots span {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  animation: bounce 1.4s infinite ease-in-out;
}

.loader-dots span:nth-child(1) { animation-delay: 0s; }
.loader-dots span:nth-child(2) { animation-delay: 0.16s; }
.loader-dots span:nth-child(3) { animation-delay: 0.32s; }
.loader-dots span:nth-child(4) { animation-delay: 0.48s; }

@keyframes bounce {
  0%, 80%, 100% { transform: scale(0.3); }
  40% { transform: scale(1); }
}

.loading-card p { color: var(--text-muted); margin: 0; }

.error-card p { color: #ef4444; margin-bottom: 1rem; }
.error-icon { font-size: 2rem; margin-bottom: 0.5rem; }

.retry-btn {
  padding: 0.6rem 1.5rem;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
}

.empty-icon { font-size: 3rem; margin-bottom: 0.5rem; }
.empty-card h3 { color: var(--text-primary); margin: 0.5rem 0; }
.empty-card p { color: var(--text-muted); margin: 0; }

/* ========== SUMMARY ========== */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  background: var(--bg-card);
  border-radius: 0.75rem;
  padding: 1rem 1.25rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.summary-card.due { border-color: #fbbf24; background: #fffbeb; }
.summary-card.paid { border-color: #a7f3d0; }
.summary-card.clear { border-color: #a7f3d0; background: #f0fdf4; }
.summary-card.discount { border-color: #ddd6fe; }

.s-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.s-value {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text-primary);
}

.s-detail {
  font-size: 0.65rem;
  color: #8b5cf6;
  font-weight: 500;
}

.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  width: fit-content;
}

.chip-clear { background: #d1fae5; color: #065f46; }
.chip-due { background: #fee2e2; color: #991b1b; }

.chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.chip-clear .chip-dot { background: #10b981; }
.chip-due .chip-dot { background: #ef4444; }

/* ========== SECTIONS ========== */
.section-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  margin-bottom: 1.25rem;
}

.section-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.section-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.section-empty {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

/* ========== TAB BAR ========== */
.tab-bar {
  display: flex;
  gap: 0.35rem;
  background: var(--bg-accent);
  border-radius: 0.75rem;
  padding: 0.25rem;
  flex-shrink: 0;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border: none;
  background: transparent;
  border-radius: 0.6rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--text-secondary);
  background: rgba(255,255,255,0.6);
}

.tab-btn.active {
  background: var(--bg-card);
  color: var(--text-primary);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.tab-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}

.dot-monthly { background: #10b981; }
.dot-event_based { background: #f59e0b; }
.dot-one_time { background: #3b82f6; }

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.3rem;
  height: 1.15rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: #e2e8f0;
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.tab-btn.active .tab-count {
  background: #3b82f6;
  color: white;
}

/* ========== LEDGER TIMELINE ========== */
.ledger-timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.ledger-entry {
  display: flex;
  gap: 0.75rem;
}

.entry-line {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 1.25rem;
  flex-shrink: 0;
  padding-top: 0.25rem;
}

.entry-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  z-index: 1;
}

.dot-debit { background: #ef4444; }
.dot-credit { background: #10b981; }

.entry-connector {
  width: 2px;
  flex: 1;
  background: #e2e8f0;
  min-height: 1rem;
}

.entry-card {
  flex: 1;
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 0.85rem 1rem;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
}

.entry-card:hover {
  background: var(--bg-card);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.entry-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.4rem;
}

.entry-date {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-weight: 500;
}

.entry-body {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.35rem;
}

.entry-desc {
  font-size: 0.82rem;
  color: var(--text-secondary);
  line-height: 1.4;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.entry-amounts {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  font-size: 0.85rem;
  font-weight: 600;
}

.entry-discount {
  font-size: 0.7rem;
  color: #8b5cf6;
  font-weight: 500;
}

.entry-footer {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.72rem;
}

.entry-balance {
  color: var(--text-muted);
  font-weight: 500;
}

.entry-status {
  color: var(--text-muted);
  text-transform: capitalize;
}

/* ========== COLLAPSIBLE ========== */
.collapse-icon {
  font-size: 0.7rem;
  color: var(--text-muted);
  transition: transform 0.25s ease;
  display: inline-block;
}

.collapse-icon.collapsed {
  transform: rotate(-90deg);
}

.payment-count,
.ledger-count {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* ========== TABLE ========== */
.table-wrapper {
  overflow-x: auto;
  border-radius: 0.5rem;
  border: 1px solid var(--border-color);
}

.ledger-table {
  width: 100%;
  border-collapse: collapse;
}

.ledger-table thead th {
  background: var(--bg-surface-muted);
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e2e8f0;
}

.ledger-table tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.table-row:hover { background: var(--bg-surface-muted); }
.month-text { color: var(--text-primary); font-weight: 600; }

/* Fee Type Cell */
.fee-type-cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.fee-type-name {
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Category Badge Small */
.cat-badge-sm {
  display: inline-flex;
  align-items: center;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  width: fit-content;
}

.cat-badge-sm.cat-one_time {
  background: #dbeafe;
  color: #1e40af;
}

.cat-badge-sm.cat-monthly {
  background: #d1fae5;
  color: #065f46;
}

.cat-badge-sm.cat-admission {
  background: #ede9fe;
  color: #5b21b6;
}

.cat-badge-sm.cat-event_based {
  background: #fef3c7;
  color: #92400e;
}

/* Description Cell */
.desc-cell {
  display: flex;
  align-items: center;
}

.desc-fee-type {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}


.text-discount { color: #8b5cf6; font-weight: 600; }
.text-paid { color: #10b981; font-weight: 600; }
.text-due { color: #ef4444; font-weight: 600; }
.text-debit { color: #ef4444; font-weight: 600; }
.text-credit { color: #10b981; font-weight: 600; }
.text-muted { color: var(--text-muted); }
.text-sm { font-size: 0.8rem; color: var(--text-muted); }

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: capitalize;
}

.badge-paid { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fee2e2; color: #991b1b; }
.badge-partial { background: #fef3c7; color: #92400e; }

/* Type Badge */
.type-badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
}

.type-debit { background: #fee2e2; color: #dc2626; }
.type-credit { background: #d1fae5; color: #059669; }

.status-dot-sm {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 0.35rem;
}

.dot-green { background: #10b981; }
.dot-yellow { background: #f59e0b; }

/* ========== PAYMENT LIST ========== */
.payment-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.payment-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1rem;
  background: var(--bg-surface-muted);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  transition: all 0.2s;
}

.payment-item:hover {
  background: var(--bg-card);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.payment-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.payment-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.6rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.payment-icon svg {
  width: 1.1rem;
  height: 1.1rem;
}

.icon-confirmed { background: #d1fae5; color: #10b981; }
.icon-awaiting_confirmation { background: #fef3c7; color: #f59e0b; }

.payment-info {
  display: flex;
  flex-direction: column;
}

.payment-method {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-primary);
}

.payment-date {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.trx-id {
  font-family: 'SF Mono', monospace;
  font-size: 0.75rem;
  color: var(--text-muted);
  background: var(--bg-accent);
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

.payment-right {
  text-align: right;
  display: flex;
  flex-direction: column;
}

.payment-amount {
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.payment-status {
  font-size: 0.7rem;
  font-weight: 600;
}

.status-confirmed { color: #10b981; }
.status-awaiting_confirmation { color: #f59e0b; }

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .fee-ledger-page { padding: 1rem; }
  .summary-grid { grid-template-columns: 1fr 1fr; }
  .payment-item { flex-direction: column; align-items: flex-start; }
  .payment-right { text-align: left; }
  .selector-card { flex-direction: column; align-items: stretch; }
  .selector-input { max-width: 100%; }
}
</style>