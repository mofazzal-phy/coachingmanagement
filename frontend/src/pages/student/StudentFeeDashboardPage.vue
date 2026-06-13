<template>
  <div class="fee-dashboard">
    <!-- ====== PAGE HEADER ====== -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">
          <span class="title-icon">💰</span>
          My Fee Dashboard
        </h1>
        <p class="page-subtitle">Complete overview of your fees, payments, and dues</p>
      </div>
      <div class="header-actions">
        <button class="notif-btn" @click="goToNotifications" :title="'Fee Notifications'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="notif-icon">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span v-if="notifCount > 0" class="notif-badge">{{ notifCount > 99 ? '99+' : notifCount }}</span>
        </button>
        <button class="refresh-btn" @click="loadDashboard" :disabled="loading">
          <span class="refresh-icon" :class="{ spinning: loading }">⟳</span>
          {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </div>

    <!-- ====== LOADING STATE ====== -->
    <div v-if="loading" class="loading-card">
      <div class="loader-waves">
        <div class="wave"></div>
        <div class="wave"></div>
        <div class="wave"></div>
        <div class="wave"></div>
        <div class="wave"></div>
      </div>
      <p>Loading your fee information...</p>
    </div>

    <!-- ====== ERROR STATE ====== -->
    <div v-else-if="error" class="error-card">
      <div class="error-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <h3>Something went wrong</h3>
      <p>{{ error }}</p>
      <button class="retry-btn" @click="loadDashboard">Try Again</button>
    </div>

    <!-- ====== MAIN CONTENT ====== -->
    <template v-else-if="dashboard">
      <!-- Overall Stats -->
      <div class="stats-grid">
        <!-- Total Fees -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon-wrap icon-blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
              </svg>
            </div>
            <span class="stat-label">Total Fees</span>
          </div>
          <span class="stat-value">৳{{ formatNumber(dashboard.overall.total_fees) }}</span>
        </div>

        <!-- Total Paid -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon-wrap icon-green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>
            <span class="stat-label">Total Paid</span>
          </div>
          <span class="stat-value value-green">৳{{ formatNumber(dashboard.overall.total_paid) }}</span>
        </div>

        <!-- Total Discount -->
        <div class="stat-card" v-if="dashboard.overall.total_discount > 0">
          <div class="stat-top">
            <div class="stat-icon-wrap icon-purple">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
              </svg>
            </div>
            <span class="stat-label">Total Discount</span>
          </div>
          <span class="stat-value value-purple">-৳{{ formatNumber(dashboard.overall.total_discount) }}</span>
        </div>

        <!-- Total Due -->
        <div class="stat-card" :class="{ 'has-alert': dashboard.overall.total_due > 0 }">
          <div class="stat-top">
            <div class="stat-icon-wrap icon-orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <span class="stat-label">Total Due</span>
          </div>
          <span class="stat-value" :class="{ 'value-red': dashboard.overall.total_due > 0 }">৳{{ formatNumber(dashboard.overall.total_due) }}</span>
        </div>

        <!-- Overdue Items -->
        <div class="stat-card" :class="{ 'has-alert': dashboard.overall.overdue_count > 0 }">
          <div class="stat-top">
            <div class="stat-icon-wrap icon-red">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
            </div>
            <span class="stat-label">Overdue Items</span>
          </div>
          <span class="stat-value" :class="{ 'value-red': dashboard.overall.overdue_count > 0 }">{{ dashboard.overall.overdue_count }}</span>
        </div>
      </div>

      <!-- ====== EXAM FEE NOTIFICATION CARDS ====== -->
      <div v-if="pendingExamFees.length > 0" class="exam-fee-section">
        <div class="exam-fee-section-title">
          <span class="eft-icon">📝</span>
          <span>New Exam Fee{{ pendingExamFees.length > 1 ? 's' : '' }} Available to Pay Now</span>
          <span class="eft-badge">{{ pendingExamFees.length }}</span>
        </div>
        <div class="exam-fee-cards">
          <div
            v-for="fee in pendingExamFees"
            :key="fee.id"
            class="exam-fee-card"
            @click="goToPaymentWithFee(fee)"
          >
            <div class="efc-top">
              <span class="efc-badge efc-badge-exam">Exam Fee</span>
              <span v-if="fee.due_date" class="efc-due" :class="{ 'efc-overdue': isOverdue(fee.due_date) }">
                Due: {{ formatDate(fee.due_date) }}
              </span>
            </div>
            <div class="efc-body">
              <h4 class="efc-title">{{ fee.title || 'Exam Fee' }}</h4>
              <div class="efc-amount-row">
                <span class="efc-amount-label">Amount</span>
                <span class="efc-amount-value">৳{{ formatNumber(fee.amount) }}</span>
              </div>
              <p v-if="fee.description" class="efc-desc">{{ fee.description }}</p>
            </div>
            <div class="efc-footer">
              <button class="efc-pay-btn" @click.stop="goToPaymentWithFee(fee)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="efc-pay-icon">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
                Pay ৳{{ formatNumber(fee.amount) }} Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Enrollment Cards -->
      <div v-if="dashboard.enrollments.length === 0" class="empty-card">
        <div class="empty-icon">📋</div>
        <h3>No Enrollments Found</h3>
        <p>You don't have any active enrollments with fee records.</p>
      </div>

      <div v-else class="enrollment-grid">
        <div
          v-for="enr in dashboard.enrollments"
          :key="enr.enrollment_id"
          class="enrollment-card"
          :class="'status-' + enr.status"
        >
          <!-- Card Header -->
          <div class="enrollment-header">
            <div class="enrollment-info">
              <h3 class="course-name">{{ enr.course_name }}</h3>
              <span class="batch-name">{{ enr.batch_name }}</span>
            </div>
            <div class="header-right">
              <!-- Fee Category Badges -->
              <div v-if="enr.categories && enr.categories.length > 0" class="category-badges">
                <span
                  v-for="cat in enr.categories"
                  :key="cat"
                  class="cat-badge"
                  :class="'cat-' + cat"
                >{{ categoryLabel(cat) }}</span>
              </div>
              <span class="status-chip" :class="'chip-' + enr.status">
                <span class="chip-dot"></span>
                {{ enr.status === 'clear' ? 'Clear' : enr.status === 'overdue' ? 'Overdue' : 'Pending' }}
              </span>
            </div>
          </div>

          <!-- Stats Grid -->
          <div class="enrollment-stats">
            <div class="enrollment-stat">
              <span class="es-label">Total Fee</span>
              <span class="es-value">৳{{ formatNumber(enr.total_fees) }}</span>
            </div>
            <div class="enrollment-stat">
              <span class="es-label">Paid</span>
              <span class="es-value es-paid">৳{{ formatNumber(enr.total_paid) }}</span>
            </div>
            <div class="enrollment-stat" v-if="enr.total_discount > 0">
              <span class="es-label">Discount</span>
              <span class="es-value es-discount">-৳{{ formatNumber(enr.total_discount) }}</span>
              <span class="es-detail" v-if="enr.discount_percent > 0">
                ({{ enr.discount_percent }}% {{ enr.discount_reason || 'Discount' }})
              </span>
            </div>
            <div class="enrollment-stat">
              <span class="es-label">Due</span>
              <span class="es-value" :class="{ 'es-due': enr.total_due > 0 }">
                ৳{{ formatNumber(enr.total_due) }}
              </span>
            </div>
            <div class="enrollment-stat">
              <span class="es-label">Overdue</span>
              <span class="es-value" :class="{ 'es-due': enr.overdue_count > 0 }">
                {{ enr.overdue_count }}
              </span>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="progress-section">
            <div class="progress-header">
              <span>Payment Progress</span>
              <span>{{ enr.paid_percentage || calculatePercentage(enr) }}%</span>
            </div>
            <div class="progress-bar">
              <div
                class="progress-fill"
                :class="'fill-' + enr.status"
                :style="{ width: (enr.paid_percentage || calculatePercentage(enr)) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Actions -->
          <div class="enrollment-actions">
            <button class="btn btn-outline" @click="viewLedger(enr.enrollment_id)">
              📒 View Ledger
            </button>
            <button
              class="btn btn-primary"
              :disabled="enr.total_due <= 0"
              @click="goToPayment(enr.enrollment_id)"
            >
              💳 Pay Now
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ====== NO DATA ====== -->
    <div v-else class="empty-card">
      <div class="empty-icon">🪪</div>
      <h3>No Fee Data</h3>
      <p>Your fee information will appear here once it's set up.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import studentPortalService from '@/services/student-portal.service'
import smartFeeService from '@/services/smart-fee.service'

const router = useRouter()
const loading = ref(false)
const error = ref(null)
const dashboard = ref(null)
const notifCount = ref(0)
const pendingExamFees = ref([])

const pendingExamFeesTotal = computed(() => {
  return pendingExamFees.value.reduce((sum, f) => sum + Number(f.amount || 0), 0)
})

const calculatePercentage = (enr) => {
  // Use discounted total (total_fees - total_discount) as the denominator
  // so the percentage reflects actual payment progress against what's payable
  const payable = (enr.total_fees || 0) - (enr.total_discount || 0)
  if (payable <= 0) return 0
  return Math.round(((enr.total_paid || 0) / payable) * 100)
}

const loadDashboard = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await studentPortalService.feeDashboard()
    dashboard.value = response.data.data
    // Also load notification count and pending exam fees
    await Promise.all([loadNotifCount(), loadNotifiedFees()])
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load fee dashboard.'
  } finally {
    loading.value = false
  }
}

const loadNotifCount = async () => {
  try {
    // Get student ID from dashboard data or auth store
    const studentId = dashboard.value?.student?.id
    if (studentId) {
      const res = await smartFeeService.student.notificationCount({ student_id: studentId })
      const data = res.data?.data || {}
      notifCount.value = (data.unread_count || 0) + (data.pending_count || 0)
    }
  } catch (e) {
    console.warn('Could not load notification count:', e)
  }
}

const loadNotifiedFees = async () => {
  try {
    const studentId = dashboard.value?.student?.id
    if (!studentId) return
    const res = await smartFeeService.student.notifiedFees({ student_id: studentId })
    // Handle both response formats:
    // 1. Direct array: res.data?.data = [...] (after backend fix)
    // 2. Double-wrapped: res.data?.data = { success: true, data: [...] } (before backend fix)
    let raw = res.data?.data || []
    if (!Array.isArray(raw) && raw?.data && Array.isArray(raw.data)) {
      raw = raw.data
    }
    const flat = []
    if (Array.isArray(raw)) {
      raw.forEach(group => {
        const items = group.notifications || group.fees || []
        if (Array.isArray(items)) {
          items.forEach(fee => {
            if (fee.status !== 'paid' && fee.status !== 'expired') {
              flat.push(fee)
            }
          })
        }
      })
    }
    pendingExamFees.value = flat
  } catch (e) {
    console.warn('Could not load notified fees:', e)
    pendingExamFees.value = []
  }
}

const viewLedger = (enrollmentId) => {
  router.push({ name: 'StudentFeeLedgerDetail', params: { enrollmentId } })
}

const goToPayment = (enrollmentId) => {
  router.push({ name: 'StudentFeePaymentDetail', params: { enrollmentId } })
}

const goToPaymentWithFee = (fee) => {
  // Navigate to payment page with exam fee info as query params
  // The payment page will auto-include monthly fees in a combined modal
  const enrollmentId = fee.enrollment_id
  if (!enrollmentId) {
    // Fallback: go to notifications page
    router.push({ name: 'StudentFeeNotifications' })
    return
  }
  router.push({
    name: 'StudentFeePaymentDetail',
    params: { enrollmentId },
    query: {
      notify_fee_id: fee.fee_structure_id || fee.id,
      notify_amount: fee.amount,
      notify_title: fee.title || 'Exam Fee',
      include_monthly: 'true',  // Signal to include monthly fees in the modal
    },
  })
}

const goToNotifications = () => {
  router.push({ name: 'StudentFeeNotifications' })
}

const isOverdue = (dateStr) => {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })
}

const formatDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat
}

onMounted(() => loadDashboard())
</script>

<style scoped>
/* ========== CONTAINER ========== */
.fee-dashboard {
  padding: 1.5rem 2rem;
  max-width: 1100px;
  margin: 0 auto;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
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
  font-size: 1.65rem;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.title-icon {
  font-size: 1.5rem;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0.25rem 0 0;
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  background: var(--bg-card);
  border: 2px solid #e2e8f0;
  border-radius: 0.75rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s;
}

.refresh-btn:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.refresh-icon {
  display: inline-block;
  font-size: 1.1rem;
}

.spinning {
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ========== LOADING ========== */
.loading-card {
  text-align: center;
  padding: 4rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: var(--shadow-sm);
}

.loader-waves {
  display: flex;
  gap: 4px;
  justify-content: center;
  margin-bottom: 1rem;
}

.wave {
  width: 4px;
  height: 40px;
  background: #3b82f6;
  border-radius: 4px;
  animation: wave 1.2s ease-in-out infinite;
}

.wave:nth-child(1) { animation-delay: 0s; }
.wave:nth-child(2) { animation-delay: 0.1s; }
.wave:nth-child(3) { animation-delay: 0.2s; }
.wave:nth-child(4) { animation-delay: 0.3s; }
.wave:nth-child(5) { animation-delay: 0.4s; }

@keyframes wave {
  0%, 40%, 100% { transform: scaleY(0.4); }
  20% { transform: scaleY(1); }
}

.loading-card p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

/* ========== ERROR ========== */
.error-card {
  text-align: center;
  padding: 3rem 2rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 1rem;
}

.error-icon {
  width: 3rem;
  height: 3rem;
  margin: 0 auto 1rem;
  color: #ef4444;
}

.error-icon svg {
  width: 100%;
  height: 100%;
}

.error-card h3 {
  color: #991b1b;
  margin: 0 0 0.5rem;
}

.error-card p {
  color: #7f1d1d;
  margin: 0 0 1rem;
}

.retry-btn {
  padding: 0.6rem 1.5rem;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
}

.retry-btn:hover {
  background: #dc2626;
}

/* ========== STATS GRID ========== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.25rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card.has-alert {
  border-color: #fbbf24;
  background: #fffbeb;
}

.stat-top {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.stat-icon-wrap {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon-wrap svg {
  width: 1.25rem;
  height: 1.25rem;
}

.icon-blue { background: #eff6ff; color: #3b82f6; }
.icon-green { background: #ecfdf5; color: #10b981; }
.icon-purple { background: #f5f3ff; color: #8b5cf6; }
.icon-orange { background: #fff7ed; color: #f97316; }
.icon-red { background: #fef2f2; color: #ef4444; }

.stat-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
}

.value-green { color: #10b981; }
.value-purple { color: #8b5cf6; }
.value-red { color: #ef4444; }

/* ========== EXAM FEE SECTION (CARDS) ========== */
.exam-fee-section {
  margin-bottom: 1.5rem;
}

.exam-fee-section-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  background: linear-gradient(135deg, #fefce8 0%, #fffbeb 100%);
  border: 1px solid #fde68a;
  border-radius: 0.75rem;
  box-shadow: 0 2px 8px rgba(251, 191, 36, 0.12);
}

.eft-icon {
  font-size: 1.3rem;
}

.eft-badge {
  margin-left: auto;
  background: #f59e0b;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  min-width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 0.4rem;
}

.exam-fee-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.exam-fee-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
}

.exam-fee-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
  border-color: #fbbf24;
}

.efc-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: var(--bg-surface-muted);
  border-bottom: 1px solid var(--border-color);
}

.efc-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.efc-badge-exam {
  background: #fef3c7;
  color: #92400e;
}

.efc-due {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.efc-overdue {
  color: #ef4444;
  font-weight: 600;
}

.efc-body {
  padding: 1rem 1rem 0.75rem;
  flex: 1;
}

.efc-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.75rem;
}

.efc-amount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.75rem;
  background: var(--bg-accent);
  border-radius: 0.5rem;
}

.efc-amount-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
}

.efc-amount-value {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary);
}

.efc-desc {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0.5rem 0 0;
  line-height: 1.4;
}

.efc-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid #e2e8f0;
}

.efc-pay-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.65rem 1rem;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border: none;
  border-radius: 0.65rem;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.efc-pay-btn:hover {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  transform: scale(1.02);
}

.efc-pay-btn:active {
  transform: scale(0.98);
}

.efc-pay-icon {
  width: 1rem;
  height: 1rem;
}

/* ========== EMPTY STATE ========== */
.empty-card {
  text-align: center;
  padding: 3rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: var(--shadow-sm);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

.empty-card h3 {
  color: var(--text-primary);
  margin: 0.5rem 0;
}

.empty-card p {
  color: var(--text-muted);
  margin: 0;
}

/* ========== ENROLLMENT GRID ========== */
.enrollment-grid {
  display: grid;
  gap: 1rem;
}

.enrollment-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  border-left: 4px solid #e2e8f0;
  transition: all 0.2s;
}

.enrollment-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.enrollment-card.status-clear { border-left-color: #10b981; }
.enrollment-card.status-overdue { border-left-color: #ef4444; }
.enrollment-card.status-pending { border-left-color: #f59e0b; }

/* Enrollment Header */
.enrollment-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}

/* Category Badges */
.category-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.cat-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.cat-badge.cat-one_time {
  background: #dbeafe;
  color: #1e40af;
}

.cat-badge.cat-monthly {
  background: #d1fae5;
  color: #065f46;
}

.cat-badge.cat-event_based {
  background: #fef3c7;
  color: #92400e;
}

.course-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.15rem;
}

.batch-name {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.chip-clear { background: #d1fae5; color: #065f46; }
.chip-clear .chip-dot { background: #10b981; }

.chip-overdue { background: #fee2e2; color: #991b1b; }
.chip-overdue .chip-dot { background: #ef4444; }

.chip-pending { background: #fef3c7; color: #92400e; }
.chip-pending .chip-dot { background: #f59e0b; }

/* Stats Grid */
.enrollment-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
  gap: 0.75rem;
  padding: 1rem;
  background: var(--bg-surface-muted);
  border-radius: 0.75rem;
  margin-bottom: 1rem;
}

.enrollment-stat {
  text-align: center;
}

.es-label {
  display: block;
  font-size: 0.7rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.25rem;
  font-weight: 600;
}

.es-value {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}

.es-paid { color: #10b981; }
.es-discount { color: #8b5cf6; }
.es-due { color: #ef4444; }

.es-detail {
  display: block;
  font-size: 0.65rem;
  color: #8b5cf6;
  margin-top: 0.1rem;
}

/* Progress Bar */
.progress-section {
  margin-bottom: 1rem;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 0.35rem;
}

.progress-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}

.fill-clear { background: linear-gradient(90deg, #10b981, #34d399); }
.fill-pending { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.fill-overdue { background: linear-gradient(90deg, #ef4444, #f87171); }

/* Actions */
.enrollment-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn {
  padding: 0.6rem 1.25rem;
  border: none;
  border-radius: 0.75rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 2px solid #e2e8f0;
}

.btn-outline:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

/* ========== HEADER ACTIONS ========== */
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.notif-btn {
  position: relative;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.notif-btn:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.notif-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: var(--text-muted);
}

.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 1.15rem;
  height: 1.15rem;
  padding: 0 0.25rem;
  background: #ef4444;
  color: white;
  font-size: 0.6rem;
  font-weight: 700;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  line-height: 1;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .fee-dashboard {
    padding: 1rem;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-actions {
    width: 100%;
    justify-content: flex-end;
  }

  .enrollment-stats {
    grid-template-columns: 1fr 1fr 1fr;
  }

  .enrollment-actions {
    flex-direction: column;
  }

  .btn {
    justify-content: center;
  }
}
</style>