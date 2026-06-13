<template>
  <div class="notifications-page">
    <!-- ====== PAGE HEADER ====== -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">Fee Notifications</h1>
        <p class="page-subtitle">View and manage your exam fee notifications</p>
      </div>
      <div class="header-actions">
        <button v-if="hasUnread" class="btn btn-outline" @click="markAllRead">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-svg">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          Mark All Read
        </button>
        <button class="btn btn-outline" @click="loadNotifications" :disabled="loading">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-svg">
            <polyline points="23 4 23 10 17 10"/>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
          </svg>
          Refresh
        </button>
      </div>
    </div>

    <!-- ====== LOADING ====== -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading notifications...</p>
    </div>

    <!-- ====== ERROR ====== -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button class="btn btn-outline" @click="loadNotifications">Try Again</button>
    </div>

    <!-- ====== EMPTY ====== -->
    <div v-else-if="notifications.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </div>
      <h3>No Fee Notifications</h3>
      <p>You don't have any pending fee notifications at this time.</p>
      <button class="btn btn-primary" @click="$router.push({ name: 'StudentFeeDashboard' })">
        Go to Fee Dashboard
      </button>
    </div>

    <!-- ====== NOTIFICATIONS LIST ====== -->
    <div v-else class="notifications-list">
      <div
        v-for="notif in notifications"
        :key="notif.id"
        class="notification-card"
        :class="{ 'is-unread': notif.status === 'unread', 'is-paid': notif.status === 'paid', 'is-expired': notif.status === 'expired' }"
        @click="handleNotificationClick(notif)"
      >
        <div class="notif-icon" :class="'icon-' + notif.status">
          <template v-if="notif.status === 'paid'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </template>
          <template v-else-if="notif.status === 'expired'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
          </template>
          <template v-else>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
          </template>
        </div>

        <div class="notif-body">
          <div class="notif-header">
            <h3 class="notif-title">{{ notif.title }}</h3>
            <span class="notif-date">{{ formatDate(notif.created_at) }}</span>
          </div>
          <p class="notif-message">{{ notif.message }}</p>
          <div class="notif-meta">
            <span class="meta-item">
              <strong>Amount:</strong> ৳{{ formatNumber(notif.amount) }}
            </span>
            <span class="meta-item" v-if="notif.due_date">
              <strong>Due:</strong> {{ notif.due_date }}
            </span>
            <span class="meta-item" v-if="notif.enrollment?.batch?.course?.name">
              <strong>Course:</strong> {{ notif.enrollment.batch.course.name }}
            </span>
            <span class="meta-item" v-if="notif.enrollment?.batch?.name">
              <strong>Batch:</strong> {{ notif.enrollment.batch.name }}
            </span>
            <span class="meta-item" v-if="notif.meta?.exam_name">
              <strong>Exam:</strong> {{ notif.meta.exam_name }}
            </span>
          </div>
          <div class="notif-badges">
            <span class="badge" :class="'badge-' + notif.status">
              {{ statusLabel(notif.status) }}
            </span>
            <span class="badge badge-type">{{ notif.type?.replace('_', ' ') }}</span>
          </div>
        </div>

        <div class="notif-actions">
          <button
            v-if="notif.status === 'unread'"
            class="btn-action"
            title="Mark as read"
            @click.stop="markAsRead(notif)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </button>
          <button
            v-if="canPay(notif)"
            class="btn-pay-now"
            @click.stop="payNow(notif)"
          >
            Pay Now
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import smartFeeService from '@/services/smart-fee.service'
import studentPortalService from '@/services/student-portal.service'

const router = useRouter()

const loading = ref(false)
const error = ref(null)
const notifications = ref([])
const unreadCount = ref(0)

const hasUnread = computed(() => unreadCount.value > 0)

const statusLabel = (status) => {
  const map = { unread: 'Unread', read: 'Read', paid: 'Paid', expired: 'Expired' }
  return map[status] || status
}

const formatNumber = (num) => Number(num || 0).toLocaleString('en-BD')

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-BD', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const canPay = (notif) => {
  return notif.status === 'unread' || notif.status === 'read'
}

const resolveStudentId = async () => {
  try {
    const profileRes = await studentPortalService.profile()
    return profileRes.data?.data?.student?.id || null
  } catch {
    return null
  }
}

const loadNotifications = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await smartFeeService.student.notifications()
    const data = res.data?.data || {}
    const list = data.notifications || []
    notifications.value = Array.isArray(list) ? list : Object.values(list)
    unreadCount.value = data.unread_count || 0
  } catch (err) {
    const studentId = await resolveStudentId()
    if (!studentId) {
      error.value = 'Student profile not found. Please login again.'
      return
    }
    try {
      const res = await smartFeeService.student.notifications({ student_id: studentId })
      const data = res.data?.data || {}
      const list = data.notifications || []
      notifications.value = Array.isArray(list) ? list : Object.values(list)
      unreadCount.value = data.unread_count || 0
    } catch (innerErr) {
      error.value = innerErr.response?.data?.message || err.response?.data?.message || 'Failed to load notifications'
    }
  } finally {
    loading.value = false
  }
}

const markAsRead = async (notif) => {
  try {
    await smartFeeService.student.markNotificationRead(notif.id)
    notif.status = 'read'
    notif.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (err) {
    console.error('Failed to mark as read:', err)
  }
}

const markAllRead = async () => {
  try {
    const studentId = await resolveStudentId()
    if (!studentId) return
    await smartFeeService.student.markAllNotificationsRead({ student_id: studentId })
    notifications.value.forEach(n => {
      if (n.status === 'unread') {
        n.status = 'read'
        n.read_at = new Date().toISOString()
      }
    })
    unreadCount.value = 0
  } catch (err) {
    console.error('Failed to mark all as read:', err)
  }
}

const handleNotificationClick = (notif) => {
  if (notif.status === 'unread') {
    markAsRead(notif)
  }
}

const payNow = (notif) => {
  // Navigate to fee payment page with the enrollment and notification context
  // Pass notification fee_structure_id as query param so the payment page can pre-select it
  if (notif.enrollment_id) {
    router.push({
      name: 'StudentFeePaymentDetail',
      params: { enrollmentId: notif.enrollment_id },
      query: {
        notify_fee_id: `notif_${notif.id}`,
        notify_amount: notif.amount || 0,
        notify_title: notif.title || notif.fee_type_name || 'Exam Fee',
        exam_id: notif.exam_id || notif.meta?.exam_id || undefined,
        category: 'event_based',
        view: 'detail',
      },
    })
  } else {
    router.push({ name: 'StudentFeePayment' })
  }
}

onMounted(() => loadNotifications())
</script>

<style scoped>
.notifications-page {
  max-width: 860px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  color: var(--text-primary);
}

/* ====== HEADER ====== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 1rem;
  flex-wrap: wrap;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 0.875rem;
  margin: 0.25rem 0 0;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 1.1rem;
  border-radius: 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  white-space: nowrap;
}

.btn-svg {
  width: 1rem;
  height: 1rem;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-outline {
  background: var(--bg-card);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

/* ====== LOADING ====== */
.loading-state {
  text-align: center;
  padding: 4rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

/* ====== ERROR ====== */
.error-state {
  text-align: center;
  padding: 2rem;
  background: #fef2f2;
  border-radius: 1rem;
  color: #dc2626;
}

.error-state .btn {
  margin-top: 1rem;
}

/* ====== EMPTY ====== */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
}

.empty-icon {
  width: 4rem;
  height: 4rem;
  margin: 0 auto 1rem;
  background: var(--bg-surface-muted);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
}

.empty-icon svg {
  width: 2rem;
  height: 2rem;
}

.empty-state h3 {
  font-size: 1.1rem;
  color: var(--text-primary);
  margin: 0 0 0.35rem;
}

.empty-state p {
  color: var(--text-muted);
  font-size: 0.875rem;
  margin: 0 0 1.25rem;
}

/* ====== NOTIFICATIONS LIST ====== */
.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.notification-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  transition: all 0.2s;
  cursor: pointer;
}

.notification-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}

.notification-card.is-unread {
  background: var(--bg-surface-muted);
  border-left: 4px solid #3b82f6;
}

.notification-card.is-paid {
  opacity: 0.7;
  border-left: 4px solid #10b981;
}

.notification-card.is-expired {
  opacity: 0.6;
  border-left: 4px solid #ef4444;
}

/* ====== NOTIF ICON ====== */
.notif-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-icon svg {
  width: 1.35rem;
  height: 1.35rem;
}

.icon-unread {
  background: #eff6ff;
  color: #3b82f6;
}

.icon-read {
  background: var(--bg-accent);
  color: var(--text-muted);
}

.icon-paid {
  background: #ecfdf5;
  color: #10b981;
}

.icon-expired {
  background: #fef2f2;
  color: #ef4444;
}

/* ====== NOTIF BODY ====== */
.notif-body {
  flex: 1;
  min-width: 0;
}

.notif-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.notif-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.notif-date {
  font-size: 0.7rem;
  color: var(--text-muted);
  white-space: nowrap;
  flex-shrink: 0;
}

.notif-message {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0 0 0.75rem;
  line-height: 1.5;
}

.notif-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.meta-item {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.meta-item strong {
  color: var(--text-secondary);
}

/* ====== BADGES ====== */
.notif-badges {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.badge-unread {
  background: #dbeafe;
  color: #1e40af;
}

.badge-read {
  background: var(--bg-accent);
  color: var(--text-secondary);
}

.badge-paid {
  background: #d1fae5;
  color: #065f46;
}

.badge-expired {
  background: #fee2e2;
  color: #991b1b;
}

.badge-type {
  background: #fef3c7;
  color: #92400e;
}

/* ====== ACTIONS ====== */
.notif-actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  flex-shrink: 0;
}

.btn-action {
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-action svg {
  width: 1rem;
  height: 1rem;
  color: var(--text-muted);
}

.btn-action:hover {
  background: var(--bg-surface-muted);
  border-color: #3b82f6;
}

.btn-action:hover svg {
  color: #3b82f6;
}

.btn-pay-now {
  padding: 0.4rem 0.85rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-pay-now:hover {
  background: #2563eb;
}

/* ====== RESPONSIVE ====== */
@media (max-width: 768px) {
  .notifications-page {
    padding: 1rem;
  }

  .page-header {
    flex-direction: column;
  }

  .notification-card {
    flex-direction: column;
  }

  .notif-actions {
    flex-direction: row;
    width: 100%;
    justify-content: flex-end;
  }

  .notif-header {
    flex-direction: column;
  }
}
</style>
