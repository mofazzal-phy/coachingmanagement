<template>
  <div class="confirm-card">
    <h3 class="step-title">
      <span class="step-badge">5</span>
      Confirm & Complete
    </h3>

    <!-- Summary -->
    <div class="summary-box">
      <!-- Student -->
      <div class="summary-section">
        <h4>👨‍🎓 Student</h4>
        <div class="summary-grid">
          <div class="s-item">
            <span class="s-label">Name</span>
            <span class="s-value">{{ student?.first_name }} {{ student?.last_name }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">ID</span>
            <span class="s-value">{{ student?.student_id || 'New' }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Phone</span>
            <span class="s-value">{{ student?.phone || 'N/A' }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Class</span>
            <span class="s-value">{{ student?.current_class?.name || 'N/A' }}</span>
          </div>
        </div>
      </div>

      <!-- Course -->
      <div class="summary-section">
        <h4>📚 Course</h4>
        <div class="summary-grid">
          <div class="s-item">
            <span class="s-label">Course</span>
            <span class="s-value">{{ course?.name }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Code</span>
            <span class="s-value code">{{ course?.code }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Category</span>
            <span :class="['s-value', 'badge', course?.category === 'academic' ? 'badge-primary' : 'badge-warning']">
              {{ course?.category === 'academic' ? 'Academic' : 'Admission' }}
            </span>
          </div>
          <div class="s-item">
            <span class="s-label">Duration</span>
            <span class="s-value">{{ course?.duration_label || 'N/A' }}</span>
          </div>
        </div>
      </div>

      <!-- Batch -->
      <div class="summary-section">
        <h4>📦 Batch</h4>
        <div class="summary-grid">
          <div class="s-item">
            <span class="s-label">Batch</span>
            <span class="s-value">{{ batch?.name }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Mode</span>
            <span :class="['s-value', 'badge', modeClass]">{{ batch?.mode }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Schedule</span>
            <span class="s-value">{{ batch?.days?.join(', ') || 'N/A' }}</span>
          </div>
          <div class="s-item">
            <span class="s-label">Time</span>
            <span class="s-value">{{ batch?.start_time }} – {{ batch?.end_time }}</span>
          </div>
          <div v-if="batch?.teacher" class="s-item">
            <span class="s-label">Teacher</span>
            <span class="s-value">👨‍🏫 {{ batch?.teacher?.first_name }} {{ batch?.teacher?.last_name }}</span>
          </div>
        </div>
      </div>

      <!-- Fee -->
      <div class="summary-section fee-section">
        <h4>💰 Payment</h4>
        <div class="fee-final">
          <div class="ff-row">
            <span>Total Fee</span>
            <span>৳{{ Number(feeData?.total_fee || 0).toLocaleString() }}</span>
          </div>
          <div v-if="feeData?.discount_percent" class="ff-row discount">
            <span>Discount ({{ feeData?.discount_percent }}%)</span>
            <span>− ৳{{ Number(feeData?.discount_amount || 0).toLocaleString() }}</span>
          </div>
          <div class="ff-row payable">
            <span>Payable</span>
            <span>৳{{ Number(feeData?.payable_fee || 0).toLocaleString() }}</span>
          </div>
          <div class="ff-row paid">
            <span>Paying Now</span>
            <span :class="payment?.amount > 0 ? 'text-success' : 'text-danger'">
              ৳{{ Number(payment?.amount || 0).toLocaleString() }}
            </span>
          </div>
          <div class="ff-row due">
            <span>Remaining Due</span>
            <span :class="payment?.due_amount > 0 ? 'text-danger' : 'text-success'">
              ৳{{ Number(payment?.due_amount || feeData?.payable_fee || 0).toLocaleString() }}
            </span>
          </div>
          <div v-if="payment?.method" class="ff-row">
            <span>Method</span>
            <span class="text-capitalize">{{ payment?.method }}</span>
          </div>
          <div v-if="payment?.transaction_id" class="ff-row">
            <span>Trx ID</span>
            <span class="code">{{ payment?.transaction_id }}</span>
          </div>
        </div>
      </div>

      <!-- Status Warning -->
      <div :class="['status-alert', payment?.due_amount > 0 ? 'warning' : 'success']">
        <template v-if="payment?.due_amount > 0">
          ⚠️ This enrollment will be <strong>PENDING</strong> until full payment of ৳{{ Number(feeData?.payable_fee || 0).toLocaleString() }} is received.
        </template>
        <template v-else>
          ✅ Enrollment will be <strong>CONFIRMED</strong> immediately. Confirmation SMS & Email will be sent.
        </template>
      </div>
    </div>

    <!-- Confirm Button -->
    <div class="confirm-actions">
      <button
        class="btn btn-primary btn-lg btn-block"
        :disabled="submitting"
        @click="$emit('confirm')"
      >
        {{ submitting ? '⏳ Processing...' : '✅ Confirm Enrollment' }}
      </button>
      <p class="confirm-hint">
        Confirmation email & SMS will be sent to student & guardian after enrollment.
      </p>
    </div>

    <div v-if="errorMsg" class="alert alert-danger mt-2">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  student: { type: Object, default: null },
  course: { type: Object, default: null },
  batch: { type: Object, default: null },
  feeData: { type: Object, default: null },
  payment: { type: Object, default: () => ({ amount: 0, due_amount: 0 }) },
  submitting: { type: Boolean, default: false },
  errorMsg: { type: String, default: '' },
})

defineEmits(['confirm'])

const modeClass = computed(() => {
  switch (props.batch?.mode) {
    case 'online': return 'badge-info'
    case 'offline': return 'badge-success'
    case 'hybrid': return 'badge-warning'
    default: return ''
  }
})
</script>

<style scoped>
.confirm-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.step-title { font-size: 1.1rem; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
.step-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #4a90d9; color: white; font-size: 0.85rem; font-weight: 700; }

.summary-box { border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; }

.summary-section { padding: 1rem; border-bottom: 1px solid #f0f0f0; }
.summary-section:last-child { border-bottom: none; }
.summary-section h4 { margin: 0 0 0.5rem 0; font-size: 0.9rem; color: var(--text-dark); }

.summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.s-item { display: flex; flex-direction: column; }
.s-label { font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
.s-value { font-size: 0.85rem; color: var(--text-dark); font-weight: 500; }
.s-value.code { font-family: monospace; color: #4a90d9; }

.fee-section { background: var(--bg-accent); }
.fee-final { font-size: 0.85rem; }
.ff-row { display: flex; justify-content: space-between; padding: 0.3rem 0; }
.ff-row.payable { font-weight: 700; font-size: 1rem; border-top: 1px solid #ddd; margin-top: 0.3rem; padding-top: 0.5rem; }
.ff-row.paid { color: var(--text-dark); }
.ff-row.due { color: var(--text-dark); }
.ff-row.discount { color: #27ae60; }

.status-alert {
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  border-radius: 0;
}
.status-alert.warning { background: #fff8e1; color: #e67e22; border-top: 1px solid #fde68a; }
.status-alert.success { background: #eafaf1; color: #27ae60; border-top: 1px solid #a3e4b8; }

.confirm-actions { padding: 1.5rem 0 0 0; text-align: center; }
.btn-block { width: 100%; }
.btn-lg { padding: 0.85rem 1.5rem; font-size: 1.05rem; font-weight: 700; border-radius: 12px; }
.btn-primary { background: #4a90d9; color: white; border: none; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #3a7bc8; }
.btn-primary:disabled { background: #a0c4e8; cursor: not-allowed; }

.confirm-hint { font-size: 0.8rem; color: #888; margin-top: 0.5rem; }
.alert-danger { background: #fdeaea; color: #e74c3c; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.text-success { color: #27ae60; }
.text-danger { color: #e74c3c; }
.text-capitalize { text-transform: capitalize; }
.mt-2 { margin-top: 0.5rem; }
.badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-primary { background: #eef4ff; color: #4a90d9; }
.badge-warning { background: #fff3cd; color: #f39c12; }
.badge-info { background: #eef4ff; color: #4a90d9; }
.badge-success { background: #eafaf1; color: #27ae60; }
</style>
